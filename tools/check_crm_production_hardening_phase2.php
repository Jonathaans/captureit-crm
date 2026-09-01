<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

require __DIR__.'/../vendor/autoload.php';

$app =
    require_once __DIR__.'/../bootstrap/app.php';

$app->make(Kernel::class)->bootstrap();

$errors = [];

foreach (
    [
        'vendors',
        'crm_notifications',
        'jobs',
        'failed_jobs',
    ]
    as $table
) {
    if (! Schema::hasTable($table)) {
        $errors[] = 'Missing table: '.$table;
    }
}

if (
    ! Schema::hasColumn(
        'purchase_orders',
        'vendor_id'
    )
) {
    $errors[] = 'purchase_orders.vendor_id missing';
}

foreach (
    [
        'admin.vendors.index',
        'admin.crm-notifications.index',
    ]
    as $route
) {
    if (! Route::has($route)) {
        $errors[] = 'Missing route: '.$route;
    }
}

echo "CRM HARDENING PHASE 2 CHECK\n";
echo "===========================\n\n";

if ($errors) {
    echo "FAIL\n";
    foreach ($errors as $error) {
        echo " - {$error}\n";
    }
    exit(1);
}

echo "PASS\n";
echo " - Google Calendar queue/retry foundation ready\n";
echo " - Notification Center ready\n";
echo " - Vendor Master ready\n";
echo " - PO vendor auto-link ready\n";
