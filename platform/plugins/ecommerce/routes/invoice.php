<?php

use Botble\Base\Facades\BaseHelper;
use Illuminate\Support\Facades\Route;

Route::group(['namespace' => 'Botble\Ecommerce\Http\Controllers', 'middleware' => ['web', 'core']], function () {
    Route::group(['prefix' => BaseHelper::getAdminPrefix(), 'middleware' => 'auth'], function () {
        Route::group(['prefix' => 'ecommerce/invoices', 'as' => 'ecommerce.invoice.'], function () {
            Route::resource('', 'InvoiceController')
                ->parameters(['' => 'invoice'])
                ->except(['create', 'store', 'update']);

            Route::delete('items/destroy', [
                'as' => 'deletes',
                'uses' => 'InvoiceController@deletes',
                'permission' => 'ecommerce.invoice.destroy',
            ]);

            Route::get('generate-invoice/{id}', [
                'as' => 'generate-invoice',
                'uses' => 'InvoiceController@getGenerateInvoice',
                'permission' => 'ecommerce.invoice.edit',
            ])->wherePrimaryKey();

            Route::get('generate-invoices', [
                'as' => 'generate-invoices',
                'uses' => 'InvoiceController@generateInvoices',
                'permission' => 'ecommerce.invoice.edit',
            ]);
        });
    });
});
