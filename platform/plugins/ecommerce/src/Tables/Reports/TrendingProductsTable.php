<?php

namespace Botble\Ecommerce\Tables\Reports;

use Botble\Base\Facades\Html;
use Botble\Ecommerce\Facades\EcommerceHelper as EcommerceHelper;
use Botble\Ecommerce\Models\Product;
use Botble\Ecommerce\Models\ProductView;
use Botble\Table\Abstracts\TableAbstract;
use Botble\Table\DataTables;
use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\JsonResponse;

class TrendingProductsTable extends TableAbstract
{
    protected string $type = self::TABLE_TYPE_SIMPLE;

    protected $view = 'core/table::simple-table';

    public function __construct(
        DataTables $table,
        UrlGenerator $urlGenerator,
        Product $model
    ) {
        parent::__construct($table, $urlGenerator);

        $this->model = $model;
    }

    public function ajax(): JsonResponse
    {
        $data = $this->table
            ->eloquent($this->query())
            ->editColumn('name', function (Product $product) {
                return Html::link($product->url, $product->name, ['target' => '_blank']);
            })
            ->editColumn('views', function (Product $product) {
                return Html::tag('i', '', ['class' => 'fa fa-eye'])->toHtml() . ' ' . number_format((float)$product->views_count);
            });

        return $data->escapeColumns([])->make();
    }

    public function query(): Relation|Builder|QueryBuilder
    {
        [$startDate, $endDate] = EcommerceHelper::getDateRangeInReport(request());

        $query = $this->getModel()
            ->query()
            ->select([
                'ec_products.id',
                'ec_products.name',
                'views_count' => ProductView::query()
                    ->selectRaw('SUM(views) as views_count')
                    ->whereColumn('product_id', 'ec_products.id')
                    ->whereDate('date', '>=', $startDate)
                    ->whereDate('date', '<=', $endDate)
                    ->groupBy('product_id'),
            ])
            ->orderByDesc('views_count')
            ->limit(5);

        return $this->applyScopes($query);
    }

    public function getColumns(): array
    {
        return $this->columns();
    }

    public function columns(): array
    {
        return [
            'id' => [
                'title' => trans('core/base::tables.id'),
                'width' => '20px',
                'class' => 'text-start no-sort',
            ],
            'name' => [
                'title' => trans('plugins/ecommerce::reports.product_name'),
                'class' => 'text-start no-sort',
            ],
            'views' => [
                'title' => trans('plugins/ecommerce::reports.views'),
                'class' => 'text-start no-sort',
            ],
        ];
    }
}
