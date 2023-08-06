<?php

namespace Botble\Ecommerce\Tables;

use Botble\Base\Facades\BaseHelper;
use Botble\Ecommerce\Enums\OrderReturnStatusEnum;
use Botble\Ecommerce\Facades\OrderReturnHelper;
use Botble\Ecommerce\Models\OrderReturn;
use Botble\Table\Abstracts\TableAbstract;
use Botble\Table\DataTables;
use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class OrderReturnTable extends TableAbstract
{
    public function __construct(DataTables $table, UrlGenerator $urlGenerator, OrderReturn $model)
    {
        parent::__construct($table, $urlGenerator);

        $this->model = $model;
        $this->hasActions = true;
        $this->hasFilter = true;

        if (! Auth::user()->hasAnyPermission(['order_returns.edit', 'order_returns.destroy'])) {
            $this->hasOperations = false;
            $this->hasActions = false;
        }
    }

    public function ajax(): JsonResponse
    {
        $data = $this->table
            ->eloquent($this->query())
            ->editColumn('checkbox', function (OrderReturn $item) {
                return $this->getCheckbox($item->id);
            })
            ->editColumn('return_status', function (OrderReturn $item) {
                return BaseHelper::clean($item->return_status->toHtml());
            })
            ->editColumn('reason', function (OrderReturn $item) {
                return BaseHelper::clean($item->reason->toHtml());
            })
            ->editColumn('order_id', function (OrderReturn $item) {
                return BaseHelper::clean($item->order->code);
            })
            ->editColumn('user_id', function (OrderReturn $item) {
                if (! $item->customer->name) {
                    return '&mdash;';
                }

                return BaseHelper::clean($item->customer->name);
            })
            ->editColumn('created_at', function (OrderReturn $item) {
                return BaseHelper::formatDate($item->created_at);
            });

        $data = $data
            ->addColumn('operations', function (OrderReturn $item) {
                return $this->getOperations('order_returns.edit', 'order_returns.destroy', $item);
            })
            ->filter(function ($query) {
                $keyword = $this->request->input('search.value');
                if ($keyword) {
                    return $query
                        ->whereHas('items', function ($subQuery) use ($keyword) {
                            return $subQuery->where('product_name', 'LIKE', '%' . $keyword . '%');
                        })->orWhereHas('customer', function ($subQuery) use ($keyword) {
                            return $subQuery->where('name', 'LIKE', '%' . $keyword . '%');
                        });
                }

                return $query;
            });

        return $this->toJson($data);
    }

    public function query(): Relation|Builder|QueryBuilder
    {
        $query = $this->getModel()
            ->query()
            ->select([
                'id',
                'order_id',
                'user_id',
                'reason',
                'order_status',
                'return_status',
                'created_at',
            ])
            ->with(['customer', 'order', 'items'])
            ->withCount('items')
            ->orderBy('id', 'desc');

        return $this->applyScopes($query);
    }

    public function columns(): array
    {
        return [
            'id' => [
                'title' => trans('core/base::tables.id'),
                'width' => '20px',
                'class' => 'text-start',
            ],
            'order_id' => [
                'title' => trans('plugins/ecommerce::order.order_id'),
                'class' => 'text-start',
            ],
            'user_id' => [
                'title' => trans('plugins/ecommerce::order.customer_label'),
                'class' => 'text-start',
            ],
            'items_count' => [
                'title' => trans('plugins/ecommerce::order.order_return_items_count'),
                'class' => 'text-center',
            ],
            'return_status' => [
                'title' => trans('core/base::tables.status'),
                'class' => 'text-center',
            ],
            'created_at' => [
                'title' => trans('core/base::tables.created_at'),
                'width' => '100px',
                'class' => 'text-start',
            ],
        ];
    }

    public function getBulkChanges(): array
    {
        return [
            'return_status' => [
                'title' => trans('core/base::tables.status'),
                'type' => 'select',
                'choices' => OrderReturnStatusEnum::labels(),
                'validate' => 'required|in:' . implode(',', OrderReturnStatusEnum::values()),
            ],
            'created_at' => [
                'title' => trans('core/base::tables.created_at'),
                'type' => 'datePicker',
            ],
        ];
    }

    public function getDefaultButtons(): array
    {
        return [
            'export',
            'reload',
        ];
    }

    public function bulkActions(): array
    {
        return $this->addDeleteAction(route('order_returns.deletes'), 'order_returns.destroy', parent::bulkActions());
    }

    public function saveBulkChangeItem(Model|OrderReturn $item, string $inputKey, ?string $inputValue): Model|bool
    {
        if ($inputKey === 'status' && $inputValue == OrderReturnStatusEnum::CANCELED) {
            /**
             * @var OrderReturn $item
             */
            OrderReturnHelper::cancelReturnOrder($item);

            return $item;
        }

        return parent::saveBulkChangeItem($item, $inputKey, $inputValue);
    }
}
