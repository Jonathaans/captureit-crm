<?php

use Illuminate\Support\Facades\Route;
use Webkul\Admin\Http\Controllers\Invoice\InvoiceController;

Route::controller(InvoiceController::class)
    ->prefix('invoices')
    ->group(function () {
        Route::get('', 'index')
            ->name('admin.invoices.index');

        /*
         * Harus sebelum /{id}.
         */
        Route::get('print/{id}', 'print')
            ->name('admin.invoices.print');

        Route::post('generate/{quoteId}', 'generate')
            ->name('admin.invoices.generate');

        Route::post('{id}/payments', 'addPayment')
            ->name('admin.invoices.payments.store');

        Route::get('{id}', 'show')
            ->name('admin.invoices.show');
    });