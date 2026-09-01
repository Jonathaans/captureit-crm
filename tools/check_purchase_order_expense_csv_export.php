<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Webkul\Invoice\Models\Expense;
use Webkul\Invoice\Models\PurchaseOrder;

require __DIR__.'/../vendor/autoload.php';

$app =
    require_once __DIR__.'/../bootstrap/app.php';

$app->make(
    Kernel::class
)->bootstrap();

echo "PO EXPENSE CSV EXPORT CHECK\n";
echo "===========================\n\n";

$errors = [];

if (
    ! Schema::hasTable(
        'purchase_orders'
    )
) {
    $errors[] =
        'purchase_orders table tidak ditemukan.';
}

if (
    ! Route::has(
        'admin.purchase-orders.export-expense'
    )
) {
    $errors[] =
        'Route admin.purchase-orders.export-expense belum terdaftar.';
}

$controllerPath =
    base_path(
        'packages/Webkul/Admin/src/Http/Controllers/PurchaseOrder/PurchaseOrderExpenseExportController.php'
    );

if (
    ! is_file(
        $controllerPath
    )
) {
    $errors[] =
        'PurchaseOrderExpenseExportController.php tidak ditemukan.';
}

$indexPath =
    base_path(
        'packages/Webkul/Admin/src/Resources/views/purchase-orders/index.blade.php'
    );

if (
    ! is_file(
        $indexPath
    )
    || ! str_contains(
        file_get_contents(
            $indexPath
        ),
        'PO EXPENSE CSV EXPORT V1 BUTTON'
    )
) {
    $errors[] =
        'Export Expense CSV button belum terpasang.';
}

$aclPath =
    base_path(
        'packages/Webkul/Admin/src/Config/acl.php'
    );

if (
    ! is_file(
        $aclPath
    )
    || ! str_contains(
        file_get_contents(
            $aclPath
        ),
        'purchase-orders.export'
    )
) {
    $errors[] =
        'ACL purchase-orders.export belum terpasang.';
}

if ($errors) {
    echo "FAIL\n";

    foreach (
        $errors
        as $error
    ) {
        echo " - {$error}\n";
    }

    exit(1);
}

$purchaseOrders =
    PurchaseOrder::query()
        ->whereIn(
            'status',
            [
                'released',
                'completed',
            ]
        )
        ->get();

$expenseIds =
    $purchaseOrders
        ->pluck(
            'expense_id'
        )
        ->filter();

$postedExpenseTotal =
    $expenseIds->isEmpty()
        ? 0.0
        : (float) Expense::query()
            ->whereIn(
                'id',
                $expenseIds
            )
            ->sum(
                'amount'
            );

$poGrandTotal =
    (float) $purchaseOrders
        ->sum(
            'grand_total'
        );

echo "PASS\n";
echo "Route + Controller + ACL + Button ready.\n\n";

echo "Current default export scope:\n";
echo " - RELEASED PO : "
    .PurchaseOrder::query()
        ->where(
            'status',
            'released'
        )
        ->count()
    ."\n";

echo " - COMPLETED PO: "
    .PurchaseOrder::query()
        ->where(
            'status',
            'completed'
        )
        ->count()
    ."\n";

echo " - PO Grand Total: "
    .$poGrandTotal
    ."\n";

echo " - Posted Expense Total: "
    .$postedExpenseTotal
    ."\n";

echo " - Variance: "
    .(
        $poGrandTotal
        - $postedExpenseTotal
    )
    ."\n";
