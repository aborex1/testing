<?php

namespace Botble\Ecommerce\Tables;

use Botble\Base\Facades\BaseHelper;
use Botble\Base\Facades\Html;
use Botble\Ecommerce\Enums\ShippingStatusEnum;
use Botble\Ecommerce\Models\Shipment;
use Botble\Table\Abstracts\TableAbstract;
use Botble\Table\DataTables;
use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class ShipmentTable extends TableAbstract
{
    public function __construct(DataTables $table, UrlGenerator $urlGenerator, Shipment $model)
    {
        parent::__construct($table, $urlGenerator);

        $this->model = $model;
        $this->hasActions = true;
        $this->hasFilter = true;

        if (! Auth::user()->hasAnyPermission(['ecommerce.shipments.edit', 'ecommerce.shipments.destroy'])) {
            $this->hasOperations = false;
            $this->hasActions = false;
        }
    }

    public function ajax(): JsonResponse
    {
        $data = $this->table
            ->eloquent($this->query())
            ->editColumn('checkbox', function (Shipment $item) {
                return $this->getCheckbox($item->id);
            })
            ->editColumn('order_id', function (Shipment $item) {
                if (! Auth::user()->hasPermission('orders.edit')) {
                    return $item->order->code;
                }

                return Html::link(route('orders.edit', $item->order->id), $item->order->code . ' <i class="fa fa-external-link-alt"></i>', ['target' => '_blank'], null, false);
            })
            ->editColumn('user_id', function (Shipment $item) {
                return BaseHelper::clean($item->order->user->name ?: $item->order->address->name);
            })
            ->editColumn('price', function (Shipment $item) {
                return format_price($item->price);
            })
            ->editColumn('created_at', function (Shipment $item) {
                return BaseHelper::formatDate($item->created_at);
            })
            ->editColumn('status', function (Shipment $item) {
                return BaseHelper::clean($item->status->toHtml());
            })
            ->editColumn('cod_status', function (Shipment $item) {
                if (! (float)$item->cod_amount) {
                    return Html::tag('span', trans('plugins/ecommerce::shipping.not_available'), ['class' => 'label-info status-label'])
                        ->toHtml();
                }

                return BaseHelper::clean($item->cod_status->toHtml());
            })
            ->addColumn('operations', function (Shipment $item) {
                return $this->getOperations('ecommerce.shipments.edit', 'ecommerce.shipments.destroy', $item);
            })
            ->filter(function ($query) {
                $keyword = $this->request->input('search.value');
                if ($keyword) {
                    return $query
                        ->whereHas('order.address', function ($subQuery) use ($keyword) {
                            return $subQuery->where('ec_order_addresses.name', 'LIKE', '%' . $keyword . '%');
                        });
                }

                return $query;
            });

        return $this->toJson($data);
    }

    public function query(): Relation|Builder|QueryBuilder
    {
        $query = $this->getModel()->query()->select([
            'id',
            'order_id',
            'user_id',
            'price',
            'status',
            'cod_status',
            'created_at',
        ]);

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
                'title' => trans('plugins/ecommerce::shipping.order_id'),
                'class' => 'text-center',
            ],
            'user_id' => [
                'title' => trans('plugins/ecommerce::order.customer_label'),
                'class' => 'text-start',
            ],
            'price' => [
                'title' => trans('plugins/ecommerce::shipping.shipping_amount'),
                'class' => 'text-center',
            ],
            'status' => [
                'title' => trans('core/base::tables.status'),
                'class' => 'text-center',
            ],
            'cod_status' => [
                'title' => trans('plugins/ecommerce::shipping.cod_status'),
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
            'status' => [
                'title' => trans('core/base::tables.status'),
                'type' => 'select',
                'choices' => ShippingStatusEnum::labels(),
                'validate' => 'required|in:' . implode(',', ShippingStatusEnum::values()),
            ],
            'created_at' => [
                'title' => trans('core/base::tables.created_at'),
                'type' => 'datePicker',
            ],
        ];
    }

    public function bulkActions(): array
    {
        return $this->addDeleteAction(route('ecommerce.shipments.deletes'), 'ecommerce.shipments.destroy', parent::bulkActions());
    }
}
