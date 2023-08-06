<?php

namespace Botble\Ecommerce\Tables;

use Botble\Base\Enums\BaseStatusEnum;
use Botble\Base\Facades\BaseHelper;
use Botble\Base\Facades\Html;
use Botble\Ecommerce\Models\Tax;
use Botble\Table\Abstracts\TableAbstract;
use Botble\Table\DataTables;
use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class TaxTable extends TableAbstract
{
    public function __construct(DataTables $table, UrlGenerator $urlGenerator, Tax $model)
    {
        parent::__construct($table, $urlGenerator);

        $this->model = $model;
        $this->hasActions = true;
        $this->hasFilter = true;

        if (! Auth::user()->hasAnyPermission(['tax.edit', 'tax.destroy'])) {
            $this->hasOperations = false;
            $this->hasActions = false;
        }
    }

    public function ajax(): JsonResponse
    {
        $data = $this->table
            ->eloquent($this->query())
            ->editColumn('title', function (Tax $item) {
                if (! Auth::user()->hasPermission('tax.edit')) {
                    return BaseHelper::clean($item->title);
                }

                return Html::link(route('tax.edit', $item->id), BaseHelper::clean($item->title));
            })
            ->editColumn('percentage', function (Tax $item) {
                return $item->percentage . '%';
            })
            ->editColumn('checkbox', function (Tax $item) {
                return $this->getCheckbox($item->id);
            })
            ->editColumn('status', function (Tax $item) {
                return BaseHelper::clean($item->status->toHtml());
            })
            ->editColumn('created_at', function (Tax $item) {
                return BaseHelper::formatDate($item->created_at);
            })
            ->addColumn('operations', function (Tax $item) {
                return $this->getOperations('tax.edit', 'tax.destroy', $item);
            });

        return $this->toJson($data);
    }

    public function query(): Relation|Builder|QueryBuilder
    {
        $query = $this->getModel()->query()->select([
            'id',
            'title',
            'percentage',
            'priority',
            'status',
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
            'title' => [
                'title' => trans('core/base::tables.name'),
                'class' => 'text-start',
            ],
            'percentage' => [
                'title' => trans('plugins/ecommerce::tax.percentage'),
                'class' => 'text-center',
            ],
            'priority' => [
                'title' => trans('plugins/ecommerce::tax.priority'),
                'class' => 'text-center',
            ],
            'status' => [
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

    public function buttons(): array
    {
        return $this->addCreateButton(route('tax.create'), 'tax.create');
    }

    public function bulkActions(): array
    {
        return $this->addDeleteAction(route('tax.deletes'), 'tax.destroy', parent::bulkActions());
    }

    public function getBulkChanges(): array
    {
        return [
            'title' => [
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
}
