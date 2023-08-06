<?php

namespace Botble\Ecommerce\Repositories\Eloquent;

use Botble\Base\Enums\BaseStatusEnum;
use Botble\Ecommerce\Repositories\Interfaces\ProductAttributeSetInterface;
use Botble\Support\Repositories\Eloquent\RepositoriesAbstract;
use Illuminate\Database\Eloquent\Collection;

class ProductAttributeSetRepository extends RepositoriesAbstract implements ProductAttributeSetInterface
{
    public function getByProductId(int|array|string|null $productId): Collection
    {
        if (! is_array($productId)) {
            $productId = [$productId];
        }

        $data = $this->model
            ->join(
                'ec_product_with_attribute_set',
                'ec_product_attribute_sets.id',
                'ec_product_with_attribute_set.attribute_set_id'
            )
            ->whereIn('ec_product_with_attribute_set.product_id', $productId)
            ->where('status', BaseStatusEnum::PUBLISHED)
            ->distinct()
            ->with(['attributes'])
            ->select(['ec_product_attribute_sets.*', 'ec_product_with_attribute_set.order'])
            ->orderBy('ec_product_with_attribute_set.order', 'ASC');

        return $this->applyBeforeExecuteQuery($data)->get();
    }

    public function getAllWithSelected(int|array|string|null $productId, array $with = []): Collection
    {
        if (! is_array($productId)) {
            $productId = $productId ? [$productId] : [];
        }

        if (func_num_args() == 1) {
            $with = ['attributes'];
        }

        $data = $this->model
            ->when($productId, function ($query) use ($productId) {
                $query
                    ->leftJoin('ec_product_with_attribute_set', function ($query) use ($productId) {
                        $query->on('ec_product_attribute_sets.id', 'ec_product_with_attribute_set.attribute_set_id')
                            ->whereIn('ec_product_with_attribute_set.product_id', $productId);
                    })
                    ->select([
                        'ec_product_attribute_sets.*',
                        'ec_product_with_attribute_set.product_id AS is_selected',
                    ]);
            }, function ($query) {
                $query
                    ->select([
                        'ec_product_attribute_sets.*',
                    ]);
            })
            ->with($with)
            ->orderBy('ec_product_attribute_sets.order', 'ASC')
            ->where('status', BaseStatusEnum::PUBLISHED);

        return $this->applyBeforeExecuteQuery($data)->get();
    }
}
