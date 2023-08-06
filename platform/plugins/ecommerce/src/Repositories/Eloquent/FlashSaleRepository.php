<?php

namespace Botble\Ecommerce\Repositories\Eloquent;

use Botble\Base\Enums\BaseStatusEnum;
use Botble\Ecommerce\Models\FlashSale;
use Botble\Ecommerce\Repositories\Interfaces\FlashSaleInterface;
use Botble\Support\Repositories\Eloquent\RepositoriesAbstract;

class FlashSaleRepository extends RepositoriesAbstract implements FlashSaleInterface
{
    public function getAvailableFlashSales(array $with = [])
    {
        /**
         * @var FlashSale $model
         */
        $model = $this->model;
        $data = $model
            ->notExpired()
            ->where('status', BaseStatusEnum::PUBLISHED)
            ->latest();

        if ($with) {
            $data = $data->with($with);
        }

        return $this->applyBeforeExecuteQuery($data)->get();
    }
}
