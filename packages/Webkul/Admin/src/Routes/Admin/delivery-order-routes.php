<?php

use Illuminate\Support\Facades\Route;
use Webkul\Admin\Http\Controllers\DeliveryOrder\DeliveryOrderController;
use Webkul\Admin\Http\Controllers\DeliveryOrder\DeliveryOrderInventoryController;

Route::controller(DeliveryOrderController::class)
    ->prefix('delivery-orders')
    ->group(function () {
        /*
        |--------------------------------------------------------------------------
        | Listing / View
        |--------------------------------------------------------------------------
        */

        Route::get('/', 'index')
            ->name('admin.delivery-orders.index');

        /*
        |--------------------------------------------------------------------------
        | Print
        |--------------------------------------------------------------------------
        */

        Route::get('{id}/print', 'print')
            ->name('admin.delivery-orders.print');

        /*
        |--------------------------------------------------------------------------
        | Status Workflow
        |--------------------------------------------------------------------------
        |
        | IMPORTANT:
        | Setiap aksi mempunyai route name sendiri supaya ACL Roles
        | bisa membedakan Head Warehouse, Operations, Staff Warehouse, dll.
        |
        */

        Route::put('{id}/issue', 'issue')
            ->name('admin.delivery-orders.issue');

        Route::put('{id}/delivered', 'markDelivered')
            ->name('admin.delivery-orders.delivered');

        Route::put('{id}/returned', 'markReturned')
            ->name('admin.delivery-orders.returned');

        Route::put('{id}/cancel', 'cancel')
            ->name('admin.delivery-orders.cancel');

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


/*
|--------------------------------------------------------------------------
| Delivery Order > Inventory Allocation
|--------------------------------------------------------------------------
*/
Route::controller(DeliveryOrderInventoryController::class)
    ->prefix('delivery-orders')
    ->group(function () {
        Route::get('{id}/inventory-allocation', 'edit')
            ->name('admin.delivery-orders.inventory-allocation.edit');

        Route::put('{id}/inventory-allocation/{itemId}', 'update')
            ->name('admin.delivery-orders.inventory-allocation.update');

        Route::delete('{id}/inventory-allocation/{itemId}', 'release')
            ->name('admin.delivery-orders.inventory-allocation.release');
    });
