<?php

namespace Botble\Faq\Tables;

use Botble\Base\Enums\BaseStatusEnum;
use Botble\Base\Facades\BaseHelper;
use Botble\Base\Facades\Html;
use Botble\Faq\Models\FaqCategory;
use Botble\Table\Abstracts\TableAbstract;
use Botble\Table\DataTables;
use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class FaqCategoryTable extends TableAbstract
{
    public function __construct(DataTables $table, UrlGenerator $urlGenerator, FaqCategory $faqCategory)
    {
        parent::__construct($table, $urlGenerator);

        $this->model = $faqCategory;

        $this->hasActions = true;
        $this->hasFilter = true;

        if (! Auth::user()->hasAnyPermission(['faq_category.edit', 'faq_category.destroy'])) {
            $this->hasOperations = false;
            $this->hasActions = false;
        }
    }

    public function ajax(): JsonResponse
    {
        $data = $this->table
            ->eloquent($this->query())
            ->editColumn('name', function (FaqCategory $item) {
                if (! Auth::user()->hasPermission('faq_category.edit')) {
                    return BaseHelper::clean($item->name);
                }

                return Html::link(route('faq_category.edit', $item->getKey()), BaseHelper::clean($item->name));
            })
            ->editColumn('checkbox', function (FaqCategory $item) {
                return $this->getCheckbox($item->getKey());
            })
            ->editColumn('created_at', function (FaqCategory $item) {
                return BaseHelper::formatDate($item->created_at);
            })
            ->editColumn('status', function (FaqCategory $item) {
                return $item->status->toHtml();
            })
            ->addColumn('operations', function (FaqCategory $item) {
                return $this->getOperations('faq_category.edit', 'faq_category.destroy', $item);
            });

        return $this->toJson($data);
    }

    public function query(): Relation|Builder|QueryBuilder
    {
        $query = $this
            ->getModel()
            ->query()
            ->select([
                'id',
                'name',
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
        return $this->addCreateButton(route('faq_category.create'), 'faq_category.create');
    }

    public function bulkActions(): array
    {
        return $this->addDeleteAction(route('faq_category.deletes'), 'faq_category.destroy', parent::bulkActions());
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
                'type' => 'customSelect',
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
