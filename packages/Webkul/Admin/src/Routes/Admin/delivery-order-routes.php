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
        | Detail
        |--------------------------------------------------------------------------
        */

        Route::get('{id}', 'show')
            ->name('admin.delivery-orders.show');
    });