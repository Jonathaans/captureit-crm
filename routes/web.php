<?php

use Illuminate\Support\Facades\Route;

// FINANCIAL REPORT EXPORT ALL EXPENSES V1 START
Route::get('admin/invoices/financial-report/export-expenses', \App\Http\Controllers\AllExpensesExportController::class)
    ->middleware(['web', 'admin_locale', 'user'])
    ->name('admin.invoices.financial-report.expenses.export');
// FINANCIAL REPORT EXPORT ALL EXPENSES V1 END
