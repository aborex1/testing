<?php

// Custom routes
use Botble\Theme\Facades\Theme;
use Illuminate\Support\Facades\Route;
use Theme\Nest\Http\Controllers\NestController;

Route::group(['controller' => NestController::class, 'middleware' => ['web', 'core']], function () {
    Route::group(apply_filters(BASE_FILTER_GROUP_PUBLIC_ROUTE, []), function () {
        Route::group(['prefix' => 'ajax', 'as' => 'public.ajax.'], function () {
            Route::get('cart', 'ajaxCart')
                ->name('cart');

            Route::get('product-reviews/{id}', 'ajaxGetProductReviews')
                ->name('product-reviews');

            Route::get('quick-view/{id}', 'getQuickView')
                ->name('quick-view')
                ->wherePrimaryKey();

            Route::get('top-products-group', 'ajaxTopProductsGroup')
                ->name('top-products-group');

            Route::get('search-products', 'ajaxSearchProducts')
                ->name('search-products');

            Route::get('ajax/products-by-collection/{id}', 'ajaxGetProductsByCollection')
                ->name('products-by-collection')
                ->wherePrimaryKey();

            Route::get('ajax/products-by-category/{id}', 'ajaxGetProductsByCategory')
                ->name('products-by-category')
                ->wherePrimaryKey();
        });
    });
});

Theme::routes();
