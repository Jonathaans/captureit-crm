<?php

use Illuminate\Support\Facades\Route;
use Webkul\Admin\Http\Controllers\Inventory\InventoryAssetController;
use Webkul\Admin\Http\Controllers\Inventory\InventoryDashboardController;
use Webkul\Admin\Http\Controllers\Inventory\InventoryItemController;
use Webkul\Admin\Http\Controllers\Inventory\InventoryMovementController;

Route::prefix('inventory')
    ->group(function () {
        Route::get('/', [InventoryDashboardController::class, 'index'])
            ->name('admin.inventory.dashboard');

        Route::controller(InventoryItemController::class)
            ->prefix('items')
            ->group(function () {
                Route::get('/', 'index')
                    ->name('admin.inventory.items.index');

                Route::get('create', 'create')
                    ->name('admin.inventory.items.create');

                Route::post('/', 'store')
                    ->name('admin.inventory.items.store');

                Route::get('{id}/edit', 'edit')
                    ->name('admin.inventory.items.edit');

                Route::put('{id}', 'update')
                    ->name('admin.inventory.items.update');
            });

        Route::controller(InventoryAssetController::class)
            ->prefix('assets')
            ->group(function () {
                Route::get('/', 'index')
                    ->name('admin.inventory.assets.index');

                /*
                 * Keep static barcode routes above {id} routes.
                 */
                Route::get('qr-labels', 'qrLabels')
                    ->name('admin.inventory.assets.qr-labels.index');

                Route::get('{id}/qr.svg', 'qrSvg')
                    ->name('admin.inventory.assets.qr-labels.svg');

                Route::get('create', 'create')
                    ->name('admin.inventory.assets.create');

                Route::post('/', 'store')
                    ->name('admin.inventory.assets.store');

                Route::get('{id}/edit', 'edit')
                    ->name('admin.inventory.assets.edit');

                Route::put('{id}', 'update')
                    ->name('admin.inventory.assets.update');
            });

        Route::controller(InventoryMovementController::class)
            ->prefix('movements')
            ->group(function () {
                Route::get('/', 'index')
                    ->name('admin.inventory.movements.index');

                Route::get('adjust-stock', 'createStockAdjustment')
                    ->name('admin.inventory.movements.adjust-stock.create');

                Route::post('adjust-stock', 'storeStockAdjustment')
                    ->name('admin.inventory.movements.adjust-stock.store');
            });
    });
