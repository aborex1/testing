<?php

namespace Botble\Ecommerce\Tables;

use Botble\Base\Enums\BaseStatusEnum;
use Botble\Base\Facades\BaseHelper;
use Botble\Base\Facades\Html;
use Botble\Ecommerce\Models\Invoice;
use Botble\Table\Abstracts\TableAbstract;
use Botble\Table\DataTables;
use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class InvoiceTable extends TableAbstract
{
    public function __construct(DataTables $table, UrlGenerator $urlGenerator, Invoice $model)
    {
        parent::__construct($table, $urlGenerator);

        $this->model = $model;
        $this->hasActions = true;
        $this->hasFilter = true;

        if (! Auth::user()->hasAnyPermission(['ecommerce.invoice.edit', 'ecommerce.invoice.destroy'])) {
            $this->hasOperations = false;
            $this->hasActions = false;
        }
    }

    public function ajax(): JsonResponse
    {
        $data = $this->table
            ->eloquent($this->query())
            ->editColumn('customer_name', function (Invoice $item) {
                return $item->customer_name;
            })
            ->editColumn('checkbox', function (Invoice $item) {
                return $this->getCheckbox($item->id);
            })
            ->editColumn('amount', function (Invoice $item) {
                return format_price($item->amount);
            })
            ->editColumn('code', function (Invoice $item) {
                if (! Auth::user()->hasPermission('ecommerce.invoice.edit')) {
                    return $item->code;
                }

                return Html::link(route('ecommerce.invoice.edit', $item->id), $item->code);
            })
            ->editColumn('created_at', function (Invoice $item) {
                return BaseHelper::formatDate($item->created_at);
            })
            ->editColumn('status', function (Invoice $item) {
                return $item->status->toHtml();
            })
            ->addColumn('operations', function (Invoice $item) {
                return $this->getOperations('ecommerce.invoice.edit', 'ecommerce.invoice.destroy', $item);
            });

        return $this->toJson($data);
    }

    public function query(): Relation|Builder|QueryBuilder
    {
        $query = $this->getModel()->query()
            ->select([
                'id',
                'customer_name',
                'code',
                'amount',
                'created_at',
                'updated_at',
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
            'customer_name' => [
                'title' => trans('core/base::tables.name'),
                'class' => 'text-start',
            ],
            'code' => [
                'title' => trans('plugins/ecommerce::invoice.table.code'),
                'class' => 'text-start',
            ],
            'amount' => [
                'title' => trans('plugins/ecommerce::invoice.table.amount'),
                'class' => 'text-start',
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
        $buttons = [];

        if (Auth::user()->hasPermission('ecommerce.invoice.edit')) {
            $buttons['generate-invoices'] = [
                'link' => route('ecommerce.invoice.generate-invoices'),
                'text' => '<i class="fas fa-file-export"></i> ' . trans('plugins/ecommerce::invoice.generate_invoices'),
                'class' => 'btn-info',
            ];
        }

        return $buttons;
    }

    public function bulkActions(): array
    {
        return $this->addDeleteAction(route('ecommerce.invoice.deletes'), 'ecommerce.invoice.destroy', parent::bulkActions());
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
