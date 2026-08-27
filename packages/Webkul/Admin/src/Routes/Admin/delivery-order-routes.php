<?php

use Illuminate\Support\Facades\Route;
use Webkul\Admin\Http\Controllers\DeliveryOrder\DeliveryOrderController;

Route::controller(DeliveryOrderController::class)
    ->prefix('delivery-orders')
    ->group(function () {
        /*
        |--------------------------------------------------------------------------
        | Listing
        |--------------------------------------------------------------------------
        */

        Route::get('/', 'index')
            ->name('admin.delivery-orders.index');

        /*
        |--------------------------------------------------------------------------
        | Edit
        |--------------------------------------------------------------------------
        */

        Route::get('{id}/edit', 'edit')
            ->name('admin.delivery-orders.edit');

        Route::put('{id}', 'update')
            ->name('admin.delivery-orders.update');

        /*
        |--------------------------------------------------------------------------
        | Detail
        |--------------------------------------------------------------------------
        */

        Route::get('{id}', 'show')
            ->name('admin.delivery-orders.show');
    });