<?php

namespace Botble\SimpleSlider\Tables;

use Botble\Base\Facades\BaseHelper;
use Botble\Base\Facades\Html;
use Botble\SimpleSlider\Models\SimpleSliderItem;
use Botble\Table\Abstracts\TableAbstract;
use Botble\Table\DataTables;
use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class SimpleSliderItemTable extends TableAbstract
{
    protected string $type = self::TABLE_TYPE_SIMPLE;

    protected $view = 'plugins/simple-slider::items';

    public function __construct(
        DataTables $table,
        UrlGenerator $urlGenerator,
        SimpleSliderItem $simpleSliderItem
    ) {
        parent::__construct($table, $urlGenerator);
        $this->setOption('id', 'simple-slider-items-table');

        $this->model = $simpleSliderItem;

        if (! Auth::user()->hasAnyPermission(['simple-slider-item.edit', 'simple-slider-item.destroy'])) {
            $this->hasOperations = false;
            $this->hasActions = false;
        }
    }

    public function ajax(): JsonResponse
    {
        $data = $this->table
            ->eloquent($this->query())
            ->editColumn('image', function (SimpleSliderItem $item) {
                return view('plugins/simple-slider::partials.thumbnail', compact('item'))->render();
            })
            ->editColumn('title', function (SimpleSliderItem $item) {
                if (! Auth::user()->hasPermission('simple-slider-item.edit')) {
                    return BaseHelper::clean($item->title);
                }

                return Html::link('#', BaseHelper::clean($item->title), [
                    'data-fancybox' => true,
                    'data-type' => 'ajax',
                    'data-src' => route('simple-slider-item.edit', $item->getKey()),
                ]);
            })
            ->editColumn('checkbox', function (SimpleSliderItem $item) {
                return $this->getCheckbox($item->getKey());
            })
            ->editColumn('created_at', function (SimpleSliderItem $item) {
                return BaseHelper::formatDate($item->created_at);
            })
            ->addColumn('operations', function (SimpleSliderItem $item) {
                return view('plugins/simple-slider::partials.actions', compact('item'))->render();
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
                'title',
                'image',
                'order',
                'created_at',
            ])
            ->orderBy('order')
            ->where('simple_slider_id', request()->route()->parameter('id'));

        return $this->applyScopes($query);
    }

    public function columns(): array
    {
        return [
                'id' => [
                    'title' => trans('core/base::tables.id'),
                    'width' => '20px',
                ],
                'image' => [
                    'title' => trans('core/base::tables.image'),
                    'class' => 'text-center',
                ],
                'title' => [
                    'title' => trans('core/base::tables.title'),
                    'class' => 'text-start',
                ],
                'order' => [
                    'title' => trans('core/base::tables.order'),
                    'class' => 'text-start order-column',
                ],
                'created_at' => [
                    'title' => trans('core/base::tables.created_at'),
                    'width' => '100px',
                ],
            ] + $this->getOperationsHeading();
    }

    public function getOperationsHeading(): array
    {
        return array_merge(parent::getOperationsHeading(), ['operations' => ['width' => '170px']]);
    }
}
