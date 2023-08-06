<?php

namespace Botble\Ecommerce\Tables;

use Botble\Ecommerce\Models\Review;
use Botble\Table\DataTables;
use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Symfony\Component\HttpFoundation\Response;

class CustomerReviewTable extends ReviewTable
{
    protected int|string $customerId;

    public function __construct(DataTables $table, UrlGenerator $urlGenerator, Review $model)
    {
        parent::__construct($table, $urlGenerator, $model);

        $this->hasFilter = false;
        $this->hasOperations = false;
        $this->hasActions = false;
        $this->hasCheckbox = false;

        $this->view = 'core/table::simple-table';
    }

    public function query(): Relation|Builder|QueryBuilder
    {
        $query = $this->getModel()
            ->query()
            ->where('customer_id', $this->getCustomerId())
            ->select([
                'id',
                'star',
                'comment',
                'product_id',
                'status',
                'created_at',
                'images',
            ])
            ->with(['user', 'product']);

        return $this->applyScopes($query);
    }

    public function getCustomerId(): int|string
    {
        return $this->customerId;
    }

    public function customerId(int|string $customerId): self
    {
        $this->customerId = $customerId;

        return $this;
    }

    public function renderTable($data = [], $mergeData = []): View|Factory|Response
    {
        return $this->render($this->view, $data, $mergeData);
    }
}
