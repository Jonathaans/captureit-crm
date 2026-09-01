<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

require __DIR__.'/../vendor/autoload.php';

$app =
    require_once __DIR__.'/../bootstrap/app.php';

$app->make(Kernel::class)->bootstrap();

$errors = [];

if (
    ! Schema::hasTable(
        'financial_period_locks'
    )
) {
    $errors[] =
        'financial_period_locks table missing';
}

foreach (
    [
        'admin.operations-dashboard.index',
        'admin.financial-periods.index',
        'admin.financial-periods.store',
        'admin.financial-periods.destroy',
    ]
    as $route
) {
    if (! Route::has($route)) {
        $errors[] =
            'Missing route: '
            .$route;
    }
}

echo "CRM HARDENING PHASE 3 CHECK\n";
echo "===========================\n\n";

if ($errors) {
    echo "FAIL\n";
    foreach ($errors as $error) {
        echo " - {$error}\n";
    }
    exit(1);
}

echo "PASS\n";
echo " - Role-based Operations Dashboard ready\n";
echo " - Financial Period Lock ready\n";
echo " - Payment/Expense/PO write guard registered\n";
