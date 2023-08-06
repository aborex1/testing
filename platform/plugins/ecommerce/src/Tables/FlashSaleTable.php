<?php

namespace Botble\Ecommerce\Tables;

use Botble\Base\Enums\BaseStatusEnum;
use Botble\Base\Facades\BaseHelper;
use Botble\Base\Facades\Html;
use Botble\Ecommerce\Models\FlashSale;
use Botble\Table\Abstracts\TableAbstract;
use Botble\Table\DataTables;
use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class FlashSaleTable extends TableAbstract
{
    public function __construct(DataTables $table, UrlGenerator $urlGenerator, FlashSale $model)
    {
        parent::__construct($table, $urlGenerator);

        $this->model = $model;
        $this->hasActions = true;
        $this->hasFilter = true;

        if (! Auth::user()->hasAnyPermission(['flash-sale.edit', 'flash-sale.destroy'])) {
            $this->hasOperations = false;
            $this->hasActions = false;
        }
    }

    public function ajax(): JsonResponse
    {
        $data = $this->table
            ->eloquent($this->query())
            ->editColumn('name', function (FlashSale $item) {
                if (! Auth::user()->hasPermission('flash-sale.edit')) {
                    return BaseHelper::clean($item->name);
                }

                return Html::link(route('flash-sale.edit', $item->id), BaseHelper::clean($item->name));
            })
            ->editColumn('checkbox', function (FlashSale $item) {
                return $this->getCheckbox($item->id);
            })
            ->editColumn('end_date', function (FlashSale $item) {
                return Html::tag('span', BaseHelper::formatDate($item->end_date), ['class' => $item->expired ? 'text-danger' : 'text-success']);
            })
            ->editColumn('created_at', function (FlashSale $item) {
                return BaseHelper::formatDate($item->created_at);
            })
            ->editColumn('status', function (FlashSale $item) {
                return BaseHelper::clean($item->status->toHtml());
            })
            ->addColumn('operations', function (FlashSale $item) {
                return $this->getOperations('flash-sale.edit', 'flash-sale.destroy', $item);
            });

        return $this->toJson($data);
    }

    public function query(): Relation|Builder|QueryBuilder
    {
        $query = $this->getModel()->query()->select([
            'id',
            'name',
            'end_date',
            'created_at',
            'status',
        ]);

        return $this->applyScopes($query);
    }

    public function columns(): array
    {
        return [
            'id' => [
                'title' => trans('core/base::tables.id'),
                'width' => '20px',
            ],
            'name' => [
                'title' => trans('core/base::tables.name'),
                'class' => 'text-start',
            ],
            'end_date' => [
                'title' => __('End date'),
                'width' => '100px',
            ],
            'created_at' => [
                'title' => trans('core/base::tables.created_at'),
                'width' => '100px',
            ],
            'status' => [
                'title' => trans('core/base::tables.status'),
                'width' => '100px',
            ],
        ];
    }

    public function buttons(): array
    {
        return $this->addCreateButton(route('flash-sale.create'), 'flash-sale.create');
    }

    public function bulkActions(): array
    {
        return $this->addDeleteAction(route('flash-sale.deletes'), 'flash-sale.destroy', parent::bulkActions());
    }

    public function getBulkChanges(): array
    {
        return [
            'name' => [
                'title' => trans('core/base::tables.name'),
                'type' => 'text',
                'validate' => 'required|max:120',
            ],
            'status' => [
                'title' => trans('core/base::tables.status'),
                'type' => 'select',
                'choices' => BaseStatusEnum::labels(),
                'validate' => 'required|in:' . implode(',', BaseStatusEnum::values()),
            ],
            'created_at' => [
                'title' => trans('core/base::tables.created_at'),
                'type' => 'datePicker',
            ],
        ];
    }

    public function getFilters(): array
    {
        return $this->getBulkChanges();
    }
}
