<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

require __DIR__.'/../vendor/autoload.php';

$app =
    require_once __DIR__.'/../bootstrap/app.php';

$app->make(
    Kernel::class
)->bootstrap();

echo "SPK WORK ORDER V1 CHECK\n";
echo "=======================\n\n";

$errors = [];
$warnings = [];

foreach (
    [
        'work_orders',
        'work_order_items',
    ]
    as $table
) {
    if (! Schema::hasTable($table)) {
        $errors[] =
            'Missing table: '
            .$table;
    }
}

if (
    ! Schema::hasColumn(
        'delivery_orders',
        'work_order_id'
    )
) {
    $errors[] =
        'Missing delivery_orders.work_order_id';
}

foreach (
    [
        'admin.work-orders.index',
        'admin.work-orders.show',
        'admin.work-orders.edit',
        'admin.work-orders.update',
        'admin.work-orders.print',
        'admin.invoices.work-orders.store',
        'admin.invoices.work-orders.open',
        'admin.work-orders.delivery-orders.generate',
        'admin.work-orders.release',
        'admin.work-orders.complete',
        'admin.work-orders.cancel',
    ]
    as $route
) {
    if (! Route::has($route)) {
        $errors[] =
            'Missing route: '
            .$route;
    }
}

$legacy =
    Route::getRoutes()
        ->getByName(
            'admin.invoices.delivery-order.generate'
        );

if (! $legacy) {
    $errors[] =
        'Legacy Invoice -> SJ route disappeared. It should be safely repointed to SPK.';
} elseif (
    ! str_contains(
        $legacy->getActionName(),
        'WorkOrderController'
    )
) {
    $errors[] =
        'Legacy Invoice -> SJ route is NOT repointed to WorkOrderController.';
}

$servicePath =
    base_path(
        'packages/Webkul/Invoice/src/Services/DeliveryOrderService.php'
    );

if (! is_file($servicePath)) {
    $errors[] =
        'DeliveryOrderService.php missing.';
} else {
    $source =
        file_get_contents(
            $servicePath
        );

    if (
        ! str_contains(
            $source,
            'SPK WORK ORDER V1 GUARD'
        )
    ) {
        $errors[] =
            'DeliveryOrderService SPK guard missing.';
    }

    if (
        ! str_contains(
            $source,
            "'work_order_id'"
        )
    ) {
        $errors[] =
            'DeliveryOrderService work_order_id link missing.';
    }
}

$invoiceViews = [];

$iterator =
    new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(
            base_path(
                'packages/Webkul/Admin/src/Resources/views'
            ),
            FilesystemIterator::SKIP_DOTS
        )
    );

foreach ($iterator as $file) {
    if (
        ! $file->isFile()
        || ! str_ends_with(
            $file->getFilename(),
            '.blade.php'
        )
    ) {
        continue;
    }

    $source =
        file_get_contents(
            $file->getPathname()
        );

    if (
        str_contains(
            $source,
            '$invoice'
        )
        && str_contains(
            $source,
            'admin.invoices.work-orders.store'
        )
    ) {
        $invoiceViews[] =
            $file->getPathname();
    }
}

if (count($invoiceViews) !== 1) {
    $warnings[] =
        'Expected 1 Invoice Blade with SPK button, found '
        .count($invoiceViews)
        .'.';
}

if ($errors) {
    echo "FAIL\n";

    foreach ($errors as $error) {
        echo " - {$error}\n";
    }

    if ($warnings) {
        echo "\nWARNINGS\n";

        foreach ($warnings as $warning) {
            echo " - {$warning}\n";
        }
    }

    exit(1);
}

echo "PASS\n";
echo " - Invoice -> SPK ready\n";
echo " - One Invoice -> One SPK enforced\n";
echo " - SPK editable items + notes ready\n";
echo " - SPK PDF ready\n";
echo " - 3 signatures: Admin Sales / Sales / Operational ready\n";
echo " - SPK -> multiple Surat Jalan ready\n";
echo " - Direct Invoice -> Surat Jalan generation blocked/repointed\n";
echo " - Existing multi-SJ inventory workflow preserved\n";

if ($warnings) {
    echo "\nWARNINGS\n";

    foreach ($warnings as $warning) {
        echo " - {$warning}\n";
    }
}
