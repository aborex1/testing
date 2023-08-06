<?php

namespace Botble\Ecommerce\Traits;

use Botble\Base\Enums\BaseStatusEnum;
use Botble\Base\Events\CreatedContentEvent;
use Botble\Base\Events\DeletedContentEvent;
use Botble\Base\Events\UpdatedContentEvent;
use Botble\Base\Http\Responses\BaseHttpResponse;
use Botble\Ecommerce\Events\ProductQuantityUpdatedEvent;
use Botble\Ecommerce\Facades\EcommerceHelper;
use Botble\Ecommerce\Http\Requests\AddAttributesToProductRequest;
use Botble\Ecommerce\Http\Requests\CreateProductWhenCreatingOrderRequest;
use Botble\Ecommerce\Http\Requests\ProductUpdateOrderByRequest;
use Botble\Ecommerce\Http\Requests\ProductVersionRequest;
use Botble\Ecommerce\Http\Requests\SearchProductAndVariationsRequest;
use Botble\Ecommerce\Http\Resources\AvailableProductResource;
use Botble\Ecommerce\Repositories\Interfaces\BrandInterface;
use Botble\Ecommerce\Repositories\Interfaces\ProductAttributeInterface;
use Botble\Ecommerce\Repositories\Interfaces\ProductAttributeSetInterface;
use Botble\Ecommerce\Repositories\Interfaces\ProductCategoryInterface;
use Botble\Ecommerce\Repositories\Interfaces\ProductCollectionInterface;
use Botble\Ecommerce\Repositories\Interfaces\ProductInterface;
use Botble\Ecommerce\Repositories\Interfaces\ProductVariationInterface;
use Botble\Ecommerce\Repositories\Interfaces\ProductVariationItemInterface;
use Botble\Ecommerce\Services\Products\CreateProductVariationsService;
use Botble\Ecommerce\Services\Products\StoreAttributesOfProductService;
use Botble\Ecommerce\Services\Products\StoreProductService;
use Botble\Media\Facades\RvMedia;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

trait ProductActionsTrait
{
    public function __construct(
        protected ProductInterface $productRepository,
        protected ProductCategoryInterface $productCategoryRepository,
        protected ProductCollectionInterface $productCollectionRepository,
        protected BrandInterface $brandRepository,
        protected ProductAttributeInterface $productAttributeRepository
    ) {
    }

    public function postSaveAllVersions(
        array $versionInRequest,
        ProductVariationInterface $productVariation,
        int|string $id,
        BaseHttpResponse $response,
        bool $isUpdateProduct = true
    ) {
        $product = $this->productRepository->findOrFail($id);

        foreach ($versionInRequest as $variationId => $version) {
            $variation = $productVariation->findById($variationId);

            if (! $variation) {
                continue;
            }

            if (! $variation->product_id || $isUpdateProduct) {
                $isNew = false;
                $productRelatedToVariation = $this->productRepository->findById($variation->product_id);

                if (! $productRelatedToVariation) {
                    $productRelatedToVariation = $this->productRepository->getModel();
                    $isNew = true;
                }

                $version['images'] = array_values(array_filter((array)Arr::get($version, 'images', []) ?: []));

                $productRelatedToVariation->fill($version);

                $productRelatedToVariation->name = $product->name;
                $productRelatedToVariation->status = $product->status;
                $productRelatedToVariation->brand_id = $product->brand_id;
                $productRelatedToVariation->is_variation = 1;

                $productRelatedToVariation->sku = Arr::get($version, 'sku');
                if (! $productRelatedToVariation->sku && Arr::get($version, 'auto_generate_sku')) {
                    $productRelatedToVariation->sku = $product->sku;
                    if (isset($version['attribute_sets']) && is_array($version['attribute_sets'])) {
                        foreach ($version['attribute_sets'] as $attributeId) {
                            $attribute = $this->productAttributeRepository->findById($attributeId);
                            if ($attribute) {
                                $productRelatedToVariation->sku = ($productRelatedToVariation->sku ?: $product->id) . '-' . Str::upper($attribute->slug);
                            }
                        }
                    }
                }
                $productRelatedToVariation->price = Arr::get($version, 'price', $product->price);
                $productRelatedToVariation->sale_price = Arr::get($version, 'sale_price', $product->sale_price);
                $productRelatedToVariation->description = Arr::get($version, 'description');

                $productRelatedToVariation->length = Arr::get($version, 'length', $product->length);
                $productRelatedToVariation->wide = Arr::get($version, 'wide', $product->wide);
                $productRelatedToVariation->height = Arr::get($version, 'height', $product->height);
                $productRelatedToVariation->weight = Arr::get($version, 'weight', $product->weight);

                $productRelatedToVariation->sale_type = (int)Arr::get($version, 'sale_type', $product->sale_type);

                if ($productRelatedToVariation->sale_type == 0) {
                    $productRelatedToVariation->start_date = null;
                    $productRelatedToVariation->end_date = null;
                } else {
                    $productRelatedToVariation->start_date = Arr::get($version, 'start_date', $product->start_date);
                    $productRelatedToVariation->end_date = Arr::get($version, 'end_date', $product->end_date);
                }

                if ($isNew) {
                    $productRelatedToVariation->product_type = Arr::get($version, 'product_type', $product->product_type);
                    $productRelatedToVariation->images = json_encode($version['images']);
                }

                $productRelatedToVariation = $this->productRepository->createOrUpdate($productRelatedToVariation);

                if (EcommerceHelper::isEnabledSupportDigitalProducts()) {
                    if ($isNew && $product->productFiles->count()) {
                        foreach ($product->productFiles as $productFile) {
                            $productRelatedToVariation->productFiles()->create($productFile->toArray());
                        }
                    } else {
                        app(StoreProductService::class)->saveProductFiles(request(), $productRelatedToVariation);
                    }
                }

                if (! $productRelatedToVariation->is_variation) {
                    if ($isNew) {
                        event(new CreatedContentEvent(PRODUCT_MODULE_SCREEN_NAME, request(), $productRelatedToVariation));
                    } else {
                        event(new UpdatedContentEvent(PRODUCT_MODULE_SCREEN_NAME, request(), $productRelatedToVariation));
                    }
                }

                event(new ProductQuantityUpdatedEvent($variation->product));

                $variation->product_id = $productRelatedToVariation->id;
            }

            $variation->is_default = Arr::get($version, 'variation_default_id', 0) == $variation->id;

            $productVariation->createOrUpdate($variation);

            if (isset($version['attribute_sets']) && is_array($version['attribute_sets'])) {
                $variation->productAttributes()->sync($version['attribute_sets']);
            }
        }

        return $response->setMessage(trans('core/base::notices.update_success_message'));
    }

    public function postAddAttributeToProduct(
        int|string $id,
        AddAttributesToProductRequest $request,
        ProductVariationInterface $variationRepository,
        BaseHttpResponse $response,
        StoreAttributesOfProductService $storeAttributesOfProductService
    ) {
        $product = $this->productRepository->findOrFail($id);

        $addedAttributes = array_filter((array) $request->input('added_attributes', []));
        $addedAttributeSets =  array_filter((array) $request->input('added_attribute_sets', []));

        if ($addedAttributes && $addedAttributeSets) {
            $result = $variationRepository->getVariationByAttributesOrCreate($id, $addedAttributes);

            $storeAttributesOfProductService->execute($product, $addedAttributeSets, $addedAttributes);

            $variation = $result['variation']->toArray();
            $variation['variation_default_id'] = $variation['id'];
            $variation['auto_generate_sku'] = true;

            $this->postSaveAllVersions([$variation['id'] => $variation], $variationRepository, $id, $response);
        }

        return $response->setMessage(trans('core/base::notices.update_success_message'));
    }

    public function destroy(int|string $id, Request $request, BaseHttpResponse $response)
    {
        $product = $this->productRepository->findOrFail($id);

        try {
            $this->productRepository->deleteBy(['id' => $id]);
            event(new DeletedContentEvent(PRODUCT_MODULE_SCREEN_NAME, $request, $product));

            return $response->setMessage(trans('core/base::notices.delete_success_message'));
        } catch (Exception $exception) {
            return $response
                ->setError()
                ->setMessage($exception->getMessage());
        }
    }

    public function deletes(Request $request, BaseHttpResponse $response)
    {
        $ids = $request->input('ids');
        if (empty($ids)) {
            return $response
                ->setError()
                ->setMessage(trans('core/base::notices.no_select'));
        }

        foreach ($ids as $id) {
            $product = $this->productRepository->findOrFail($id);
            $this->productRepository->delete($product);
            event(new DeletedContentEvent(PRODUCT_MODULE_SCREEN_NAME, $request, $product));
        }

        return $response->setMessage(trans('core/base::notices.delete_success_message'));
    }

    public function deleteVersion(
        ProductVariationInterface $productVariation,
        ProductVariationItemInterface $productVariationItem,
        int|string $variationId,
        BaseHttpResponse $response
    ) {
        $variation = $productVariation->findOrFail($variationId);

        $originProduct = $variation->configurableProduct;
        $result = $this->deleteVersionItem($productVariation, $productVariationItem, $variationId);

        if ($result) {
            return $response
                ->setData([
                    'total_product_variations' => $originProduct->variations()->count(),
                ])
                ->setMessage(trans('core/base::notices.delete_success_message'));
        }

        return $response
            ->setError()
            ->setMessage(trans('core/base::notices.delete_error_message'));
    }

    public function deleteVersions(
        Request $request,
        ProductVariationInterface $productVariation,
        ProductVariationItemInterface $productVariationItem,
        BaseHttpResponse $response
    ) {
        $ids = (array)$request->input('ids');

        if (empty($ids)) {
            return $response
                ->setError()
                ->setMessage(trans('core/base::notices.no_select'));
        }

        $variations = $productVariation->getModel()->whereIn('id', $ids)->get();

        if (! $variations->count() || $variations->count() != count($ids)) {
            return $response
                ->setError()
                ->setMessage(trans('core/base::notices.no_select'));
        }

        foreach ($ids as $id) {
            $this->deleteVersionItem($productVariation, $productVariationItem, $id);
        }

        $variation = $variations->first();
        $originProduct = $this->productRepository->findById($variation->configurable_product_id);

        return $response
            ->setData([
                'total_product_variations' => $originProduct->variations()->count(),
            ])
            ->setMessage(trans('core/base::notices.delete_success_message'));
    }

    protected function deleteVersionItem(
        ProductVariationInterface $productVariation,
        ProductVariationItemInterface $productVariationItem,
        int|string $variationId
    ) {
        $variation = $productVariation->findById($variationId);

        if (! $variation) {
            return true;
        }

        $productVariationItem->deleteBy(['variation_id' => $variationId]);
        $productRelatedToVariation = $this->productRepository->findById($variation->product_id);

        if ($productRelatedToVariation) {
            event(new DeletedContentEvent(PRODUCT_MODULE_SCREEN_NAME, request(), $productRelatedToVariation));
        }

        $this->productRepository->deleteBy(['id' => $variation->product_id]);
        $result = $productVariation->delete($variation);

        $originProduct = $this->productRepository->findById($variation->configurable_product_id);

        if ($variation->is_default) {
            $latestVariation = $productVariation->getFirstBy(['configurable_product_id' => $variation->configurable_product_id]);
            if ($latestVariation) {
                $latestVariation->is_default = 1;

                $productVariation->createOrUpdate($latestVariation);

                if ($originProduct && $latestVariation->product->id) {
                    $originProduct->sku = $latestVariation->product->sku;
                    $originProduct->price = $latestVariation->product->price;
                    $originProduct->length = $latestVariation->product->length;
                    $originProduct->wide = $latestVariation->product->wide;
                    $originProduct->height = $latestVariation->product->height;
                    $originProduct->weight = $latestVariation->product->weight;
                    $originProduct->sale_price = $latestVariation->product->sale_price;
                    $originProduct->sale_type = $latestVariation->product->sale_type;
                    $originProduct->start_date = $latestVariation->product->start_date;
                    $originProduct->end_date = $latestVariation->product->end_date;
                    $this->productRepository->createOrUpdate($originProduct);
                }
            } else {
                $originProduct->productAttributeSets()->detach();
            }
        }

        $originProduct && event(new ProductQuantityUpdatedEvent($originProduct));

        return $result;
    }

    public function postAddVersion(
        ProductVersionRequest $request,
        ProductVariationInterface $productVariation,
        int|string|null $id,
        BaseHttpResponse $response
    ) {
        $addedAttributes = $request->input('attribute_sets', []);

        if (! empty($addedAttributes) && is_array($addedAttributes)) {
            $result = $productVariation->getVariationByAttributesOrCreate($id, $addedAttributes);
            if (! $result['created']) {
                return $response
                    ->setError()
                    ->setMessage(trans('plugins/ecommerce::products.form.variation_existed') . ' #' . $result['variation']->id);
            }

            $this->postSaveAllVersions(
                [$result['variation']->id => $request->input()],
                $productVariation,
                $id,
                $response
            );

            return $response->setMessage(trans('plugins/ecommerce::products.form.added_variation_success'));
        }

        return $response
            ->setError()
            ->setMessage(trans('plugins/ecommerce::products.form.no_attributes_selected'));
    }

    public function getVersionForm(
        int|string|null $id,
        Request $request,
        ProductVariationInterface $productVariation,
        BaseHttpResponse $response,
        ProductAttributeSetInterface $productAttributeSetRepository,
        ProductVariationItemInterface $productVariationItemRepository
    ) {
        $product = null;
        $variation = null;
        $productVariationsInfo = collect();

        if ($id) {
            $variation = $productVariation->findOrFail($id);
            $product = $this->productRepository->findOrFail($variation->product_id);
            $productVariationsInfo = $productVariationItemRepository->getVariationsInfo([$id]);
            $originalProduct = $product;
        } else {
            $originalProduct = $this->productRepository->findOrFail($request->input('product_id'));
        }

        $productId = $variation ? $variation->configurable_product_id : $request->input('product_id');

        if ($productId) {
            $productAttributeSets = $productAttributeSetRepository->getByProductId($productId);
        } else {
            $productAttributeSets = $productAttributeSetRepository->getAllWithSelected($productId);
        }

        $html = view('plugins/ecommerce::products.partials.product-variation-form', compact(
            'productAttributeSets',
            'product',
            'productVariationsInfo',
            'originalProduct'
        ))->render();

        return $response->setData($html);
    }

    public function postUpdateVersion(
        ProductVersionRequest $request,
        ProductVariationInterface $productVariation,
        int|string $id,
        BaseHttpResponse $response
    ) {
        $variation = $productVariation->findOrFail($id);

        $addedAttributes = $request->input('attribute_sets', []);

        if (! empty($addedAttributes) && is_array($addedAttributes)) {
            $result = $productVariation->getVariationByAttributesOrCreate(
                $variation->configurable_product_id,
                $addedAttributes
            );

            if (! $result['created'] && $result['variation']->id !== $variation->id) {
                return $response
                    ->setError()
                    ->setData($result)
                    ->setMessage(trans('plugins/ecommerce::products.form.variation_existed') . ' #' . $result['variation']->id);
            }

            if ($variation->is_default) {
                $request->merge([
                    'variation_default_id' => $variation->id,
                ]);
            }

            $this->postSaveAllVersions(
                [$variation->id => $request->input()],
                $productVariation,
                $variation->configurable_product_id,
                $response
            );

            $productVariation->getModel()->where(['product_id' => null])->delete();

            return $response->setMessage(trans('plugins/ecommerce::products.form.updated_variation_success'));
        }

        return $response
            ->setError()
            ->setMessage(trans('plugins/ecommerce::products.form.no_attributes_selected'));
    }

    public function postGenerateAllVersions(
        CreateProductVariationsService $service,
        ProductVariationInterface $productVariation,
        int|string $id,
        BaseHttpResponse $response
    ) {
        $product = $this->productRepository->findOrFail($id);

        $variations = $service->execute($product);

        $variationInfo = [];

        foreach ($variations as $variation) {
            /**
             * @var Collection $variation
             */
            $data = $variation->toArray();
            if ((int)$variation->is_default === 1) {
                $data['variation_default_id'] = $variation->id;
            }

            $variationInfo[$variation->id] = $data;
        }

        $this->postSaveAllVersions($variationInfo, $productVariation, $id, $response, false);

        return $response->setMessage(trans('plugins/ecommerce::products.form.created_all_variation_success'));
    }

    public function postStoreRelatedAttributes(
        Request $request,
        StoreAttributesOfProductService $service,
        int|string $id,
        BaseHttpResponse $response
    ) {
        $product = $this->productRepository->findOrFail($id);

        $attributeSets = $request->input('attribute_sets', []);

        $service->execute($product, $attributeSets);

        return $response->setMessage(trans('plugins/ecommerce::products.form.updated_product_attributes_success'));
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

    public function getRelationBoxes(int|string|null $id, BaseHttpResponse $response)
    {
        $product = null;
        if ($id) {
            $product = $this->productRepository->findById($id);
        }

        $dataUrl = route('products.get-list-product-for-search', ['product_id' => $product ? $product->id : 0]);

        return $response->setData(view(
            'plugins/ecommerce::products.partials.extras',
            compact('product', 'dataUrl')
        )->render());
    }

    public function getListProductForSelect(Request $request, BaseHttpResponse $response)
    {
        $availableProducts = $this->productRepository
            ->getModel()
            ->where('status', BaseStatusEnum::PUBLISHED)
            ->where('is_variation', '<>', 1)
            ->where('name', 'LIKE', '%' . $request->input('keyword') . '%')
            ->select([
                'ec_products.*',
            ])
            ->distinct('ec_products.id');

        $includeVariation = $request->input('include_variation', 0);
        if ($includeVariation) {
            /**
             * @var Builder $availableProducts
             */
            $availableProducts = $availableProducts
                ->join('ec_product_variations', 'ec_product_variations.configurable_product_id', '=', 'ec_products.id')
                ->join(
                    'ec_product_variation_items',
                    'ec_product_variation_items.variation_id',
                    '=',
                    'ec_product_variations.id'
                );
        }

        $availableProducts = $availableProducts->simplePaginate(5);

        foreach ($availableProducts as &$availableProduct) {
            $image = Arr::first($availableProduct->images) ?? null;
            $availableProduct->image_url = RvMedia::getImageUrl($image, 'thumb', false, RvMedia::getDefaultImage());
            $availableProduct->price = $availableProduct->front_sale_price;
            if ($includeVariation) {
                foreach ($availableProduct->variations as &$variation) {
                    $variation->price = $variation->product->front_sale_price;
                    foreach ($variation->variationItems as &$variationItem) {
                        $variationItem->attribute_title = $variationItem->attribute->title;
                    }
                }
            }
        }

        return $response->setData($availableProducts);
    }

    public function postCreateProductWhenCreatingOrder(
        CreateProductWhenCreatingOrderRequest $request,
        BaseHttpResponse $response
    ) {
        $product = $this->productRepository->createOrUpdate($request->input());

        event(new CreatedContentEvent(PRODUCT_MODULE_SCREEN_NAME, $request, $product));

        return $response
            ->setData(new AvailableProductResource($product))
            ->setMessage(trans('core/base::notices.create_success_message'));
    }

    public function getAllProductAndVariations(SearchProductAndVariationsRequest $request, BaseHttpResponse $response)
    {
        $selectedProducts = collect();
        if ($productIds = $request->input('product_ids', [])) {
            $selectedProducts = $this->productRepository
                ->getModel()
                ->where('status', BaseStatusEnum::PUBLISHED)
                ->whereIn('id', $productIds)
                ->with(['variationInfo.configurableProduct'])
                ->get();
        }

        $availableProducts = $this->productRepository
            ->getModel()
            ->select(['ec_products.*'])
            ->where([
                ['status', '=', BaseStatusEnum::PUBLISHED],
                ['is_variation', '<>', 1],
            ])
            ->with(['variationInfo.configurableProduct']);

        if ($keyword = $request->input('keyword')) {
            $availableProducts = $availableProducts
                ->where(function ($query) use ($keyword) {
                    $query
                        ->where('name', 'LIKE', '%' . $keyword . '%')
                        ->orWhere('sku', 'LIKE', '%' . $keyword . '%');
                });
        }

        if (is_plugin_active('marketplace') && $selectedProducts->count()) {
            $selectedProducts = $selectedProducts->map(function ($item) {
                if ($item->is_variation) {
                    $item->store_id = $item->original_product->store_id;
                }

                if (! $item->store_id) {
                    $item->store_id = 0;
                }

                return $item;
            });
            $storeIds = array_unique($selectedProducts->pluck('store_id')->all());
            $availableProducts = $availableProducts->whereIn('store_id', $storeIds)->with(['store']);
        }

        $availableProducts = $availableProducts->simplePaginate(5);

        return $response->setData(AvailableProductResource::collection($availableProducts)->response()->getData());
    }

    public function postUpdateOrderBy(ProductUpdateOrderByRequest $request, BaseHttpResponse $response)
    {
        $product = $this->productRepository->findOrFail($request->input('pk'));
        $product->order = $request->input('value', 0);
        $this->productRepository->createOrUpdate($product);

        return $response->setMessage(trans('core/base::notices.update_success_message'));
    }

    public function getProductAttributeSets(
        BaseHttpResponse $response,
        ProductAttributeSetInterface $productAttributeSetRepository,
        int|string|null $id = null
    ) {
        $with = ['attributes' => function ($query) {
            $query->select(['id', 'slug', 'title', 'attribute_set_id']);
        }];

        $productAttributeSets = $productAttributeSetRepository->getAllWithSelected($id, $with);

        return $response->setData($productAttributeSets->transform(fn ($item) => $item->only(['attributes', 'id', 'title'])));
    }
}
