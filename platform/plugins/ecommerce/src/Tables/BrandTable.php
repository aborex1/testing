<?php

namespace Botble\Ecommerce\Tables;

use Botble\Base\Enums\BaseStatusEnum;
use Botble\Base\Facades\BaseHelper;
use Botble\Base\Facades\Html;
use Botble\Ecommerce\Models\Brand;
use Botble\Table\Abstracts\TableAbstract;
use Botble\Table\DataTables;
use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class BrandTable extends TableAbstract
{
    public function __construct(DataTables $table, UrlGenerator $urlGenerator, Brand $model)
    {
        parent::__construct($table, $urlGenerator);

        $this->model = $model;
        $this->hasActions = true;
        $this->hasFilter = true;

        if (! Auth::user()->hasAnyPermission(['brands.edit', 'brands.destroy'])) {
            $this->hasOperations = false;
            $this->hasActions = false;
        }
    }

    public function ajax(): JsonResponse
    {
        $data = $this->table
            ->eloquent($this->query())
            ->editColumn('name', function (Brand $item) {
                if (! Auth::user()->hasPermission('brands.edit')) {
                    return BaseHelper::clean($item->name);
                }

                return Html::link(route('brands.edit', $item->id), BaseHelper::clean($item->name));
            })
            ->editColumn('checkbox', function (Brand $item) {
                return $this->getCheckbox($item->id);
            })
            ->editColumn('logo', function (Brand $item) {
                return $this->displayThumbnail($item->logo);
            })
            ->editColumn('created_at', function (Brand $item) {
                return BaseHelper::formatDate($item->created_at);
            })
            ->editColumn('is_featured', function (Brand $item) {
                return $item->is_featured ? trans('core/base::base.yes') : trans('core/base::base.no');
            })
            ->editColumn('status', function (Brand $item) {
                return BaseHelper::clean($item->status->toHtml());
            })
            ->addColumn('operations', function (Brand $item) {
                return $this->getOperations('brands.edit', 'brands.destroy', $item);
            });

        return $this->toJson($data);
    }

    public function query(): Relation|Builder|QueryBuilder
    {
        $query = $this->getModel()
            ->query()
            ->select([
                'id',
                'name',
                'created_at',
                'status',
                'is_featured',
                'logo',
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
            'name' => [
                'title' => trans('core/base::tables.name'),
                'class' => 'text-start',
            ],
            'logo' => [
                'title' => trans('plugins/ecommerce::brands.logo'),
                'class' => 'text-start',
            ],
            'is_featured' => [
                'title' => trans('core/base::tables.is_featured'),
                'class' => 'text-start',
            ],
            'created_at' => [
                'title' => trans('core/base::tables.created_at'),
                'width' => '100px',
                'class' => 'text-start',
            ],
            'status' => [
                'title' => trans('core/base::tables.status'),
                'width' => '100px',
                'class' => 'text-start',
            ],
        ];
    }

    public function buttons(): array
    {
        return $this->addCreateButton(route('brands.create'), 'brands.create');
    }

    public function bulkActions(): array
    {
        return $this->addDeleteAction(
            route('brands.deletes'),
            'brands.destroy',
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

    public function renderTable($data = [], $mergeData = []): View|Factory|Response
    {
        if ($this->query()->count() === 0 &&
            ! $this->request()->wantsJson() &&
            $this->request()->input('filter_table_id') !== $this->getOption('id') && ! $this->request()->ajax()
        ) {
            return view('plugins/ecommerce::brands.intro');
        }

        return parent::renderTable($data, $mergeData);
    }
}
