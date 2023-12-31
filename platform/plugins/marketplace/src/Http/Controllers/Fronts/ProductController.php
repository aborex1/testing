<?php

namespace Botble\Marketplace\Http\Controllers\Fronts;

use Botble\Base\Enums\BaseStatusEnum;
use Botble\Base\Facades\EmailHandler;
use Botble\Base\Facades\PageTitle;
use Botble\Base\Forms\FormBuilder;
use Botble\Base\Http\Controllers\BaseController;
use Botble\Base\Http\Responses\BaseHttpResponse;
use Botble\Ecommerce\Enums\ProductTypeEnum;
use Botble\Ecommerce\Facades\EcommerceHelper;
use Botble\Ecommerce\Http\Requests\ProductRequest;
use Botble\Ecommerce\Http\Requests\ProductVersionRequest;
use Botble\Ecommerce\Models\Customer;
use Botble\Ecommerce\Repositories\Interfaces\GlobalOptionInterface;
use Botble\Ecommerce\Repositories\Interfaces\GroupedProductInterface;
use Botble\Ecommerce\Repositories\Interfaces\ProductAttributeSetInterface;
use Botble\Ecommerce\Repositories\Interfaces\ProductVariationInterface;
use Botble\Ecommerce\Repositories\Interfaces\ProductVariationItemInterface;
use Botble\Ecommerce\Services\Products\StoreAttributesOfProductService;
use Botble\Ecommerce\Services\Products\StoreProductService;
use Botble\Ecommerce\Services\StoreProductTagService;
use Botble\Ecommerce\Traits\ProductActionsTrait;
use Botble\Marketplace\Facades\MarketplaceHelper;
use Botble\Marketplace\Forms\ProductForm;
use Botble\Marketplace\Tables\ProductTable;
use Botble\Marketplace\Tables\ProductVariationTable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class ProductController extends BaseController
{
    use ProductActionsTrait {
        ProductActionsTrait::postAddVersion as basePostAddVersion;
        ProductActionsTrait::postUpdateVersion as basePostUpdateVersion;
        ProductActionsTrait::deleteVersionItem as baseDeleteVersionItem;
    }

    public function index(ProductTable $table)
    {
        PageTitle::setTitle(__('Products'));

        return $table->render(MarketplaceHelper::viewPath('dashboard.table.base'));
    }

    public function create(FormBuilder $formBuilder, Request $request)
    {
        if (EcommerceHelper::isEnabledSupportDigitalProducts()) {
            if ($request->input('product_type') == ProductTypeEnum::DIGITAL) {
                PageTitle::setTitle(trans('plugins/ecommerce::products.create_product_type.digital'));
            } else {
                PageTitle::setTitle(trans('plugins/ecommerce::products.create_product_type.physical'));
            }
        } else {
            PageTitle::setTitle(trans('plugins/ecommerce::products.create'));
        }

        return $formBuilder->create(ProductForm::class)->renderForm();
    }

    public function store(
        ProductRequest $request,
        StoreProductService $service,
        BaseHttpResponse $response,
        ProductVariationInterface $variationRepository,
        ProductVariationItemInterface $productVariationItemRepository,
        GroupedProductInterface $groupedProductRepository,
        StoreAttributesOfProductService $storeAttributesOfProductService,
        StoreProductTagService $storeProductTagService
    ) {
        $request = $this->processRequestData($request);

        $product = $this->productRepository->getModel();

        $product->status = MarketplaceHelper::getSetting(
            'enable_product_approval',
            1
        ) ? BaseStatusEnum::PENDING : BaseStatusEnum::PUBLISHED;

        if (EcommerceHelper::isEnabledSupportDigitalProducts() && $request->input('product_type')) {
            $product->product_type = $request->input('product_type');
        }

        $product = $service->execute($request, $product);

        $product->store_id = auth('customer')->user()->store->id;
        $product->created_by_id = auth('customer')->id();
        $product->created_by_type = Customer::class;
        $product->save();

        $storeProductTagService->execute($request, $product);

        $addedAttributes = $request->input('added_attributes', []);

        if ($request->input('is_added_attributes') == 1 && $addedAttributes) {
            $storeAttributesOfProductService->execute($product, array_keys($addedAttributes), array_values($addedAttributes));

            $variation = $variationRepository->create([
                'configurable_product_id' => $product->id,
            ]);

            foreach ($addedAttributes as $attribute) {
                $productVariationItemRepository->createOrUpdate([
                    'attribute_id' => $attribute,
                    'variation_id' => $variation->id,
                ]);
            }

            $variation = $variation->toArray();

            $variation['variation_default_id'] = $variation['id'];

            $variation['sku'] = $product->sku ?? time();
            foreach ($addedAttributes as $attributeId) {
                $attribute = $this->productAttributeRepository->findById($attributeId);
                if ($attribute) {
                    $variation['sku'] .= '-' . $attribute->slug;
                }
            }

            $this->postSaveAllVersions([$variation['id'] => $variation], $variationRepository, $product->id, $response);
        }

        if ($request->has('grouped_products')) {
            $groupedProductRepository->createGroupedProducts($product->id, array_map(function ($item) {
                return [
                    'id' => $item,
                    'qty' => 1,
                ];
            }, array_filter(explode(',', $request->input('grouped_products', '')))));
        }

        if (MarketplaceHelper::getSetting('enable_product_approval', 1)) {
            EmailHandler::setModule(MARKETPLACE_MODULE_SCREEN_NAME)
                ->setVariableValues([
                    'product_name' => $product->name,
                    'product_url' => route('products.edit', $product->id),
                    'store_name' => auth('customer')->user()->store->name,
                ])
                ->sendUsingTemplate('pending-product-approval');
        }

        return $response
            ->setPreviousUrl(route('marketplace.vendor.products.index'))
            ->setNextUrl(route('marketplace.vendor.products.edit', $product->id))
            ->setMessage(trans('core/base::notices.create_success_message'));
    }

    public function edit(int|string $id, FormBuilder $formBuilder)
    {
        $product = $this->productRepository->findOrFail($id);

        if ($product->is_variation || $product->store_id != auth('customer')->user()->store->id) {
            abort(404);
        }

        PageTitle::setTitle(trans('plugins/ecommerce::products.edit', ['name' => $product->name]));

        return $formBuilder
            ->create(ProductForm::class, ['model' => $product])
            ->renderForm();
    }

    public function update(
        int|string $id,
        ProductRequest $request,
        StoreProductService $service,
        GroupedProductInterface $groupedProductRepository,
        BaseHttpResponse $response,
        ProductVariationInterface $variationRepository,
        ProductVariationItemInterface $productVariationItemRepository,
        StoreProductTagService $storeProductTagService
    ) {
        $product = $this->productRepository->findOrFail($id);

        if ($product->is_variation || $product->store_id != auth('customer')->user()->store->id) {
            abort(404);
        }

        $request = $this->processRequestData($request);

        $product->store_id = auth('customer')->user()->store->id;

        $product = $service->execute($request, $product);
        $storeProductTagService->execute($request, $product);

        if ($request->has('variation_default_id')) {
            $variationRepository
                ->getModel()
                ->where('configurable_product_id', $product->id)
                ->update(['is_default' => 0]);

            $defaultVariation = $variationRepository->findById($request->input('variation_default_id'));
            if ($defaultVariation) {
                $defaultVariation->is_default = true;
                $defaultVariation->save();
            }
        }

        $addedAttributes = $request->input('added_attributes', []);

        if ($request->input('is_added_attributes') == 1 && $addedAttributes) {
            $result = $variationRepository->getVariationByAttributesOrCreate($id, $addedAttributes);

            /**
             * @var Collection $variation
             */
            $variation = $result['variation'];

            foreach ($addedAttributes as $attribute) {
                $productVariationItemRepository->createOrUpdate([
                    'attribute_id' => $attribute,
                    'variation_id' => $variation->id,
                ]);
            }

            $variation = $variation->toArray();
            $variation['variation_default_id'] = $variation['id'];

            $product->productAttributeSets()->sync(array_keys($addedAttributes));

            $variation['sku'] = $product->sku ?? time();
            foreach (array_keys($addedAttributes) as $attributeId) {
                $attribute = $this->productAttributeRepository->findById($attributeId);
                if ($attribute) {
                    $variation['sku'] .= '-' . $attribute->slug;
                }
            }

            $this->postSaveAllVersions([$variation['id'] => $variation], $variationRepository, $product->id, $response);
        } elseif ($product->variations()->count() === 0) {
            $product->productAttributeSets()->detach();
        }

        if ($request->has('grouped_products')) {
            $groupedProductRepository->createGroupedProducts($product->id, array_map(function ($item) {
                return [
                    'id' => $item,
                    'qty' => 1,
                ];
            }, array_filter(explode(',', $request->input('grouped_products', '')))));
        }

        return $response
            ->setPreviousUrl(route('marketplace.vendor.products.index'))
            ->setMessage(trans('core/base::notices.update_success_message'));
    }

    protected function processRequestData(Request $request): Request
    {
        $shortcodeCompiler = shortcode()->getCompiler();

        $request->merge([
            'content' => $shortcodeCompiler->strip($request->input('content'), $shortcodeCompiler->whitelistShortcodes()),
            'images' => array_filter($request->input('images', [])),
        ]);

        $except = [
            'is_featured',
            'status',
        ];

        foreach ($except as $item) {
            $request->request->remove($item);
        }

        return $request;
    }

    public function getRelationBoxes($id, BaseHttpResponse $response)
    {
        $product = null;
        if ($id) {
            $product = $this->productRepository->findById($id);
        }

        $dataUrl = route(
            'marketplace.vendor.products.get-list-product-for-search',
            ['product_id' => $product ? $product->id : 0]
        );

        return $response->setData(view(
            'plugins/ecommerce::products.partials.extras',
            compact('product', 'dataUrl')
        )->render());
    }

    public function postAddVersion(
        ProductVersionRequest $request,
        ProductVariationInterface $productVariation,
        $id,
        BaseHttpResponse $response
    ) {
        $request->merge([
            'images' => array_filter($request->input('images', [])),
        ]);

        return $this->basePostAddVersion($request, $productVariation, $id, $response);
    }

    public function postUpdateVersion(
        ProductVersionRequest $request,
        ProductVariationInterface $productVariation,
        $id,
        BaseHttpResponse $response
    ) {
        $request->merge([
            'images' => array_filter($request->input('images', [])),
        ]);

        return $this->basePostUpdateVersion($request, $productVariation, $id, $response);
    }

    public function getVersionForm(
        $id,
        Request $request,
        ProductVariationInterface $productVariation,
        BaseHttpResponse $response,
        ProductAttributeSetInterface $productAttributeSetRepository,
        ProductVariationItemInterface $productVariationItemRepository
    ) {
        $product = null;
        $variation = null;
        $productVariationsInfo = [];

        if ($id) {
            $variation = $productVariation->findOrFail($id);
            $product = $this->productRepository->findOrFail($variation->product_id);
            $productVariationsInfo = $productVariationItemRepository->getVariationsInfo([$id]);
        }

        $productId = $variation ? $variation->configurable_product_id : $request->input('product_id');

        if ($productId) {
            $productAttributeSets = $productAttributeSetRepository->getByProductId($productId);
        } else {
            $productAttributeSets = $productAttributeSetRepository->getAllWithSelected($productId);
        }

        $originalProduct = $product;

        return $response
            ->setData(
                MarketplaceHelper::view('dashboard.products.product-variation-form', compact(
                    'productAttributeSets',
                    'product',
                    'productVariationsInfo',
                    'originalProduct'
                ))->render()
            );
    }

    protected function deleteVersionItem(
        ProductVariationInterface $productVariation,
        ProductVariationItemInterface $productVariationItem,
        $variationId
    ) {
        $variation = $productVariation->findOrFail($variationId);

        $product = $variation->product()->first();

        if (! $product || $product->original_product->store_id != auth('customer')->user()->store->id) {
            abort(404);
        }

        return $this->baseDeleteVersionItem($productVariation, $productVariationItem, $variationId);
    }

    public function getListProductForSearch(Request $request, BaseHttpResponse $response)
    {
        $availableProducts = $this->productRepository
            ->advancedGet([
                'condition' => [
                    'status' => BaseStatusEnum::PUBLISHED,
                    ['is_variation', '<>', 1],
                    ['id', '<>', $request->input('product_id', 0)],
                    ['name', 'LIKE', '%' . $request->input('keyword') . '%'],
                    'store_id' => auth('customer')->user()->store->id,
                ],
                'select' => [
                    'id',
                    'name',
                    'images',
                    'image',
                    'price',
                ],
                'paginate' => [
                    'per_page' => 5,
                    'type' => 'simplePaginate',
                    'current_paged' => $request->integer('page', 1),
                ],
            ]);

        $includeVariation = $request->input('include_variation', 0);

        return $response->setData(
            view('plugins/ecommerce::products.partials.panel-search-data', compact(
                'availableProducts',
                'includeVariation'
            ))->render()
        );
    }

    public function ajaxProductOptionInfo(
        Request $request,
        BaseHttpResponse $response,
        GlobalOptionInterface $globalOptionRepository
    ): BaseHttpResponse {
        $optionsValues = $globalOptionRepository->findOrFail($request->input('id'), ['values']);

        return $response->setData($optionsValues);
    }

    public function getProductVariations(int|string $id, ProductVariationTable $dataTable)
    {
        $product = $this->productRepository
            ->getModel()
            ->where([
                'is_variation' => 0,
                'store_id' => auth('customer')->user()->store->id,
            ])
            ->findOrFail($id);

        $dataTable->setProductId($id);

        if (EcommerceHelper::isEnabledSupportDigitalProducts() && $product->isTypeDigital()) {
            $dataTable->isDigitalProduct();
        }

        return $dataTable->renderTable();
    }

    public function setDefaultProductVariation(int|string $id, ProductVariationInterface $variationRepository, BaseHttpResponse $response)
    {
        $variation = $variationRepository->findOrFail($id);

        if ($variation->configurableProduct->store_id != auth('customer')->user()->store->id) {
            abort(404);
        }

        $variationRepository
            ->getModel()
            ->where('configurable_product_id', $variation->configurable_product_id)
            ->update(['is_default' => 0]);

        if ($variation) {
            $variation->is_default = true;
            $variation->save();
        }

        return $response->setMessage(trans('core/base::notices.update_success_message'));
    }
}
