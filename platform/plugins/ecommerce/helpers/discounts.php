<?php

use Botble\Base\Facades\Html;
use Botble\Ecommerce\Models\Discount;
use Botble\Ecommerce\Models\DiscountCustomer;
use Botble\Ecommerce\Models\DiscountProduct;
use Botble\Ecommerce\Models\DiscountProductCollection;
use Botble\Ecommerce\Models\Product;

if (! function_exists('get_discount_description')) {
    function get_discount_description(Discount $discount): string
    {
        $type = $discount->type_option;

        $target = $discount->target;

        $value = $discount->value;

        $description = [];

        switch ($type) {
            case 'shipping':
                if ($target) {
                    $description[] = __('Free shipping to <strong>:target</strong>', ['target' => $target]);
                } else {
                    $description[] = __('Free shipping for all orders');
                }

                $description[] = __('when shipping fee less than or equal :amount', ['amount' => format_price($value)]);

                break;
            case 'same-price':
                $description[] = __('Same fee :amount', ['amount' => format_price($value)]);
                switch ($target) {
                    case 'group-products':
                        $collections = DiscountProductCollection::query()->where('discount_id', $discount->getKey())
                            ->join(
                                'ec_product_collections',
                                'ec_product_collections.id',
                                '=',
                                'ec_discount_product_collections.product_collection_id'
                            )
                            ->pluck('ec_product_collections.name')
                            ->all();

                        $description[] = __('for all product in collection') . ' ' . implode(', ', $collections);

                        break;
                    default:
                        $description[] = __('for all products in order');

                        break;
                }

                break;
            default:
                if ($type === 'percentage') {
                    $description[] = __('Discount :percentage%', ['percentage' => $value]);
                } else {
                    $description[] = __('Discount :amount', ['amount' => format_price($value)]);
                }

                switch ($target) {
                    case 'amount-minimum-order':
                        $description[] = __('for order with amount from :price', ['price' => format_price($discount->min_order_price)]);

                        break;
                    case 'specific-product':
                        $products = DiscountProduct::query()->where('discount_id', $discount->getKey())
                            ->join('ec_products', 'ec_products.id', '=', 'ec_discount_products.product_id')
                            ->where('ec_products.is_variation', false)
                            ->pluck('ec_products.name', 'ec_products.id')
                            ->all();

                        $productLinks = [];
                        foreach (array_unique($products) as $productId => $productName) {
                            $productLinks[] = Html::link(route('products.edit', $productId), $productName, ['target' => '_blank'])->toHtml();
                        }

                        $description[] = __('for product(s) :products', ['products' => implode(', ', $productLinks)]);

                        break;
                    case 'customer':
                        $customers = DiscountCustomer::query()->where('discount_id', $discount->getKey())
                            ->join('ec_customers', 'ec_customers.id', '=', 'ec_discount_customers.customer_id')
                            ->pluck('ec_customers.name', 'ec_customers.id')
                            ->all();

                        $customerLinks = [];
                        foreach (array_unique($customers) as $customerId => $customerName) {
                            $customerLinks[] = Html::link(route('customers.edit', $customerId), $customerName, ['target' => '_blank'])->toHtml();
                        }

                        $description[] = __('for customer(s) :customers', ['customers' => implode(', ', $customerLinks)]);

                        break;
                    case 'group-products':
                        $collections = DiscountProductCollection::query()->where('discount_id', $discount->getKey())
                            ->join(
                                'ec_product_collections',
                                'ec_product_collections.id',
                                '=',
                                'ec_discount_product_collections.product_collection_id'
                            )
                            ->pluck('ec_product_collections.name', 'ec_product_collections.id')
                            ->all();

                        $collectionLinks = [];
                        foreach (array_unique($collections) as $collectionId => $collectionName) {
                            $collectionLinks[] = Html::link(route('product-collections.edit', $collectionId), $collectionName, ['target' => '_blank'])->toHtml();
                        }

                        $description[] = __('for all products in collection :collections', ['collections' => implode(', ', $collectionLinks)]);

                        break;
                    case 'product-variant':
                        $products = Product::query()
                            ->join('ec_discount_products', 'ec_discount_products.product_id', '=', 'ec_products.id')
                            ->where('discount_id', $discount->getKey())
                            ->where('is_variation', true)
                            ->with('variationInfo.configurableProduct')
                            ->get();

                        $productLinks = [];
                        foreach ($products as $variant) {
                            $productLinks[] = Html::link(route('products.edit', $variant->originalProduct->id), $variant->originalProduct->name . ' ' . $variant->variation_attributes, ['target' => '_blank'])->toHtml();
                        }

                        $description[] = __('for product variant(s) :variants', ['variants' => implode(', ', $productLinks)]);

                        break;
                    case 'once-per-customer':
                        $description[] = __('limited to use coupon code per customer. This coupon can only be used once per customer!');

                        break;
                    default:
                        $description[] = __('for all orders');

                        break;
                }
        }

        return trim(implode(' ', $description));
    }
}
