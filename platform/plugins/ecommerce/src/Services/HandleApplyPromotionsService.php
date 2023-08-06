<?php

namespace Botble\Ecommerce\Services;

use Botble\Ecommerce\Facades\Cart;
use Botble\Ecommerce\Facades\Discount;
use Botble\Ecommerce\Facades\OrderHelper;
use Illuminate\Support\Arr;

class HandleApplyPromotionsService
{
    public function execute($token = null, array $data = [], ?string $prefix = ''): float|int
    {
        $promotionDiscountAmount = $this->getPromotionDiscountAmount($data);

        if (! $token) {
            $token = OrderHelper::getOrderSessionToken();
        }

        $sessionData = OrderHelper::getOrderSessionData($token);
        Arr::set($sessionData, $prefix . 'promotion_discount_amount', $promotionDiscountAmount);
        OrderHelper::setOrderSessionData($token, $sessionData);

        return $promotionDiscountAmount;
    }

    public function getPromotionDiscountAmount(array $data = [])
    {
        $promotionDiscountAmount = 0;

        $cartInstance = Cart::instance('cart');

        $rawTotal = Arr::get($data, 'rawTotal', $cartInstance->rawTotal());
        $cartItems = Arr::get($data, 'cartItems', $cartInstance->content());
        $countCart = Arr::get($data, 'countCart', $cartInstance->count());
        $productItems = Arr::get($data, 'productItems', $cartInstance->products());

        $availablePromotions = Discount::getAvailablePromotions();

        foreach ($productItems as $product) {
            if (! $product->is_variation) {
                $productCollections = $product->productCollections;
            } else {
                $productCollections = $product->original_product->productCollections;
            }

            $promotion = Discount::promotionForProduct([$product->id], $productCollections->pluck('id')->all());
            if ($promotion) {
                $availablePromotions = $availablePromotions->push($promotion);
            }
        }

        foreach ($availablePromotions as $promotion) {
            switch ($promotion->type_option) {
                case 'amount':
                    switch ($promotion->target) {
                        case 'amount-minimum-order':
                            if ($promotion->min_order_price <= $rawTotal) {
                                $promotionDiscountAmount += $promotion->value;
                            }

                            break;
                        case 'all-orders':
                            $promotionDiscountAmount += $promotion->value;

                            break;
                        default:
                            if ($countCart >= $promotion->product_quantity) {
                                $promotionDiscountAmount += $promotion->value;
                            }

                            break;
                    }

                    break;
                case 'percentage':
                    switch ($promotion->target) {
                        case 'amount-minimum-order':
                            if ($promotion->min_order_price <= $rawTotal) {
                                $promotionDiscountAmount += $rawTotal * $promotion->value / 100;
                            }

                            break;
                        case 'all-orders':
                            $promotionDiscountAmount += $rawTotal * $promotion->value / 100;

                            break;
                        default:
                            if ($countCart >= $promotion->product_quantity) {
                                $promotionDiscountAmount += $rawTotal * $promotion->value / 100;
                            }

                            break;
                    }

                    break;
                case 'same-price':
                    if ($promotion->product_quantity > 1 && $countCart >= $promotion->product_quantity) {
                        foreach ($cartItems as $item) {
                            if ($item->qty >= $promotion->product_quantity) {
                                if (in_array($promotion->target, ['specific-product', 'product-variant']) &&
                                    in_array($item->id, $promotion->products()->pluck('product_id')->all())
                                ) {
                                    $promotionDiscountAmount += ($item->price - $promotion->value) * $item->qty;
                                } elseif ($product = $productItems->firstWhere('id', $item->id)) {
                                    $productCollections = $product
                                        ->productCollections()
                                        ->pluck('ec_product_collections.id')->all();

                                    $discountProductCollections = $promotion
                                        ->productCollections()
                                        ->pluck('ec_product_collections.id')
                                        ->all();

                                    if (! empty(array_intersect(
                                        $productCollections,
                                        $discountProductCollections
                                    ))) {
                                        $promotionDiscountAmount += ($item->price - $promotion->value) * $item->qty;
                                    }
                                }
                            }
                        }
                    }

                    break;
            }
        }

        return $promotionDiscountAmount;
    }
}
