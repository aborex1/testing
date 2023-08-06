<?php

namespace Botble\Faq\Tables;

use Botble\Base\Facades\BaseHelper;
use Botble\Base\Facades\Html;
use Botble\Faq\Models\Faq;
use Botble\Table\Abstracts\TableAbstract;
use Botble\Table\DataTables;
use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class FaqTable extends TableAbstract
{
    public function __construct(DataTables $table, UrlGenerator $urlGenerator, Faq $faq)
    {
        parent::__construct($table, $urlGenerator);

        $this->model = $faq;

        $this->hasActions = true;
        $this->hasFilter = true;

        if (! Auth::user()->hasAnyPermission(['faq.edit', 'faq.destroy'])) {
            $this->hasOperations = false;
            $this->hasActions = false;
        }
    }

    public function ajax(): JsonResponse
    {
        $data = $this->table
            ->eloquent($this->query())
            ->editColumn('question', function (Faq $item) {
                if (! Auth::user()->hasPermission('faq.edit')) {
                    return $item->question;
                }

                return Html::link(route('faq.edit', $item->getKey()), $item->question);
            })
            ->editColumn('category_id', function (Faq $item) {
                return $item->category->name;
            })
            ->editColumn('checkbox', function (Faq $item) {
                return $this->getCheckbox($item->getKey());
            })
            ->editColumn('created_at', function (Faq $item) {
                return BaseHelper::formatDate($item->created_at);
            })
            ->editColumn('status', function (Faq $item) {
                return $item->status->toHtml();
            })
            ->addColumn('operations', function (Faq $item) {
                return $this->getOperations('faq.edit', 'faq.destroy', $item);
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
                'question',
                'created_at',
                'answer',
                'category_id',
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
            'question' => [
                'title' => trans('plugins/faq::faq.question'),
                'class' => 'text-start',
            ],
            'category_id' => [
                'title' => trans('plugins/faq::faq.category'),
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
        return $this->addCreateButton(route('faq.create'), 'faq.create');
    }

    public function bulkActions(): array
    {
        return $this->addDeleteAction(route('faq.deletes'), 'faq.destroy', parent::bulkActions());
    }

    public function getBulkChanges(): array
    {
        return [
            'question' => [
                'title' => trans('plugins/faq::faq.question'),
                'type' => 'text',
                'validate' => 'required|max:120',
            ],
            'created_at' => [
                'title' => trans('core/base::tables.created_at'),
                'type' => 'datePicker',
            ],
        ];
    }
}
