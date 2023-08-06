<?php

namespace Botble\Marketplace\Tables;

use Botble\Base\Enums\BaseStatusEnum;
use Botble\Base\Facades\BaseHelper;
use Botble\Base\Facades\Html;
use Botble\Marketplace\Models\Store;
use Botble\Media\Facades\RvMedia;
use Botble\Table\Abstracts\TableAbstract;
use Botble\Table\DataTables;
use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class StoreTable extends TableAbstract
{
    protected bool $canEditWalletBalance = false;

    public function __construct(DataTables $table, UrlGenerator $urlGenerator, Store $model)
    {
        parent::__construct($table, $urlGenerator);

        $this->model = $model;
        $this->hasActions = true;
        $this->hasFilter = true;

        if (! Auth::user()->hasAnyPermission(['marketplace.store.edit', 'marketplace.store.destroy'])) {
            $this->hasOperations = false;
            $this->hasActions = false;
        }

        if (Auth::user()->hasAnyPermission(['marketplace.store.view'])) {
            $this->canEditWalletBalance = true;
        }
    }

    public function ajax(): JsonResponse
    {
        $data = $this->table
            ->eloquent($this->query())
            ->editColumn('name', function ($item) {
                if (! Auth::user()->hasPermission('marketplace.store.edit')) {
                    return BaseHelper::clean($item->name);
                }

                return Html::link(route('marketplace.store.edit', $item->id), BaseHelper::clean($item->name));
            })
            ->editColumn('logo', function ($item) {
                return Html::image(
                    RvMedia::getImageUrl($item->logo, 'thumb', false, RvMedia::getDefaultImage()),
                    BaseHelper::clean($item->name),
                    ['width' => 50]
                );
            })
            ->editColumn('checkbox', function ($item) {
                return $this->getCheckbox($item->id);
            })
            ->editColumn('created_at', function ($item) {
                return BaseHelper::formatDate($item->created_at);
            })
            ->editColumn('earnings', function ($item) {
                return $item->customer->id ? format_price($item->customer->balance ?: 0) : '--';
            })
            ->editColumn('products_count', function ($item) {
                return $item->products_count;
            })
            ->addColumn('customer_name', function ($item) {
                if (! $item->customer->name) {
                    return '&mdash;';
                }

                return Html::link(route('customers.edit', $item->customer->id), $item->customer->name);
            })
            ->editColumn('status', function ($item) {
                return BaseHelper::clean($item->status->toHtml());
            })
            ->addColumn('operations', function ($item) {
                $viewBtn = '';
                if ($this->canEditWalletBalance && $item->customer->id) {
                    $viewBtn = Html::link(
                        route('marketplace.store.view', $item->id),
                        '<i class="fa fa-eye"></i>',
                        [
                            'class' => 'btn btn-info',
                            'data-bs-toggle' => 'tooltip',
                            'data-bs-original-title' => trans('plugins/marketplace::store.view'),
                        ],
                        null,
                        false
                    );
                }

                return $this->getOperations('marketplace.store.edit', 'marketplace.store.destroy', $item, $viewBtn);
            });

        return $this->toJson($data);
    }

    public function query(): Relation|Builder|QueryBuilder
    {
        $query = $this->getModel()->query()
            ->select([
                'id',
                'logo',
                'name',
                'created_at',
                'status',
                'customer_id',
            ])
            ->with(['customer', 'customer.vendorInfo'])
            ->withCount(['products']);

        return $this->applyScopes($query);
    }

    public function columns(): array
    {
        return [
            'id' => [
                'title' => trans('core/base::tables.id'),
                'width' => '20px',
            ],
            'logo' => [
                'title' => trans('plugins/marketplace::store.forms.logo'),
                'width' => '70px',
            ],
            'name' => [
                'title' => trans('core/base::tables.name'),
                'class' => 'text-start',
            ],
            'earnings' => [
                'title' => trans('plugins/marketplace::marketplace.tables.earnings'),
                'class' => 'text-start',
                'searchable' => false,
                'orderable' => false,
                'width' => '100px',
            ],
            'products_count' => [
                'title' => trans('plugins/marketplace::marketplace.tables.products_count'),
                'searchable' => false,
                'orderable' => false,
            ],
            'customer_name' => [
                'title' => trans('plugins/marketplace::marketplace.vendor'),
                'class' => 'text-start',
                'orderable' => false,
                'searchable' => false,
                'exportable' => false,
                'printable' => false,
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
        return $this->addCreateButton(route('marketplace.store.create'), 'marketplace.store.create');
    }

    public function bulkActions(): array
    {
        return $this->addDeleteAction(
            route('marketplace.store.deletes'),
            'marketplace.store.destroy',
            parent::bulkActions()
        );
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

    public function getOperationsHeading(): array
    {
        return [
            'operations' => [
                'title' => trans('core/base::tables.operations'),
                'width' => '180px',
                'class' => 'text-end',
                'orderable' => false,
                'searchable' => false,
                'exportable' => false,
                'printable' => false,
            ],
        ];
    }
}
