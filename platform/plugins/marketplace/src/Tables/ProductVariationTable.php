<?php

namespace Botble\Marketplace\Tables;

use Botble\Base\Facades\Form;
use Botble\Base\Facades\Html;
use Botble\Ecommerce\Models\ProductVariation;
use Botble\Ecommerce\Tables\ProductVariationTable as EcommerceProductVariationTable;
use Botble\Marketplace\Exports\ProductExport;
use Botble\Table\DataTables;
use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\JsonResponse;

class ProductVariationTable extends EcommerceProductVariationTable
{
    public function __construct(DataTables $table, UrlGenerator $urlGenerator, ProductVariation $productVariation)
    {
        parent::__construct($table, $urlGenerator, $productVariation);

        $this->exportClass = ProductExport::class;
    }

    public function ajax(): JsonResponse
    {
        $data = $this->loadDataTable();
        $data
            ->editColumn('is_default', function (ProductVariation $item) {
                return Html::tag('label', Form::radio('variation_default_id', $item->id, $item->is_default, [
                    'data-url' => route('marketplace.vendor.products.set-default-product-variation', $item->id),
                ]));
            })
            ->editColumn('operations', function (ProductVariation $item) {
                $update = route('marketplace.vendor.products.update-version', $item->id);
                $loadForm = route('marketplace.vendor.products.get-version-form', $item->id);
                $delete = route('marketplace.vendor.products.delete-version', $item->id);

                return view('plugins/ecommerce::products.variations.actions', compact('update', 'loadForm', 'delete', 'item'));
            });

        return $this->toJson($data);
    }

    protected function baseQuery(): Relation|Builder|QueryBuilder
    {
        return $this
            ->getModel()
            ->query()
            ->whereHas('configurableProduct', function (Builder $query) {
                $query
                    ->where([
                        'configurable_product_id' => $this->productId,
                        'store_id' => auth('customer')->user()->store->id,
                    ]);
            });
    }

    public function setProductId(int|string $productId): self
    {
        parent::setProductId($productId);
        $this->setAjaxUrl(route('marketplace.vendor.products.product-variations', $this->productId));

        return $this;
    }
}
