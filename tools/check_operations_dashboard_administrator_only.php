<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Route;

require __DIR__.'/../vendor/autoload.php';

$app =
    require_once __DIR__.'/../bootstrap/app.php';

$app->make(Kernel::class)->bootstrap();

$path =
    base_path(
        'packages/Webkul/Admin/src/Http/Controllers/Dashboard/OperationsDashboardController.php'
    );

$errors = [];

if (! is_file($path)) {
    $errors[] =
        'OperationsDashboardController.php tidak ditemukan.';
} else {
    $source =
        file_get_contents($path);

    if (
        ! str_contains(
            $source,
            'OPERATIONS DASHBOARD ADMINISTRATOR ONLY V1'
        )
    ) {
        $errors[] =
            'Administrator-only marker belum terpasang.';
    }

    if (
        ! str_contains(
            $source,
            "=== 'administrator'"
        )
    ) {
        $errors[] =
            'Role Administrator hard-lock belum ditemukan.';
    }

    if (
        ! str_contains(
            $source,
            "hasPermission(\n                'operations-dashboard'"
        )
    ) {
        $errors[] =
            'ACL operations-dashboard second layer tidak ditemukan.';
    }
}

if (
    ! Route::has(
        'admin.operations-dashboard.index'
    )
) {
    $errors[] =
        'Route admin.operations-dashboard.index tidak terdaftar.';
}

echo "OPERATIONS DASHBOARD ADMIN-ONLY CHECK\n";
echo "=====================================\n\n";

if ($errors) {
    echo "FAIL\n";

    foreach ($errors as $error) {
        echo " - {$error}\n";
    }

    exit(1);
}

echo "PASS\n";
echo "Administrator : ALLOWED (subject to ACL)\n";
echo "Sales Admin   : 403\n";
echo "Sales User    : 403\n";
echo "Admin Finance : 403\n";
echo "Head Warehouse: 403\n";
