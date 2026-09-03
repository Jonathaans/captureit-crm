<?php

declare(strict_types=1);

/**
 * Check Export All Expenses CSV V1.
 *
 * Run: php tools/check_export_all_expenses_csv_v1.php
 */

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

$root = dirname(__DIR__);
$autoload = $root.'/vendor/autoload.php';
$bootstrap = $root.'/bootstrap/app.php';

if (! is_file($autoload) || ! is_file($bootstrap)) {
    fwrite(STDERR, "Project Laravel atau vendor/autoload.php tidak ditemukan.\n");
    exit(1);
}

require $autoload;

$app = require_once $bootstrap;
$app->make(Kernel::class)->bootstrap();

$controllerPath = base_path(
    'packages/Webkul/Admin/src/Http/Controllers/Invoice/ExpenseExportController.php'
);
$routesPath = base_path(
    'packages/Webkul/Admin/src/Routes/Admin/invoice-routes.php'
);
$viewPath = base_path(
    'packages/Webkul/Admin/src/Resources/views/invoices/index.blade.php'
);
$aclPath = base_path('packages/Webkul/Admin/src/Config/acl.php');

$controller = is_file($controllerPath) ? file_get_contents($controllerPath) : '';
$routes = is_file($routesPath) ? file_get_contents($routesPath) : '';
$view = is_file($viewPath) ? file_get_contents($viewPath) : '';
$acl = is_file($aclPath) ? file_get_contents($aclPath) : '';
$route = Route::getRoutes()->getByName('admin.invoices.expenses.export-all');

$schemaOk = false;
$schemaMessage = '';

try {
    $schemaOk = Schema::hasTable('expenses')
        && Schema::hasColumns('expenses', [
            'id',
            'invoice_id',
            'category',
            'description',
            'amount',
            'expense_date',
            'notes',
            'receipt_path',
            'created_by',
            'created_at',
        ])
        && Schema::hasColumns('invoices', [
            'id',
            'invoice_number',
            'project_code',
            'subject',
            'event_date',
        ])
        && Schema::hasColumns('invoice_items', ['invoice_id', 'name'])
        && Schema::hasColumns('users', ['id', 'name']);
} catch (Throwable $exception) {
    $schemaMessage = $exception->getMessage();
}

$headers = [
    'Invoice Number',
    'Project Code',
    'Product Event',
    'Project Event Date',
    'Expense Date',
    'Expense Name / Category',
    'Amount',
    'Note',
    'Image / Receipt',
    'Created By',
    'Created At',
];

$checks = [
    'Controller V1 tersedia' => is_file($controllerPath)
        && str_contains((string) $controller, 'EXPORT ALL EXPENSES CSV V1'),
    'Export memakai batch' => str_contains((string) $controller, 'BATCH_SIZE')
        && str_contains((string) $controller, '$lastExpenseId'),
    'Header CSV lengkap' => collect($headers)->every(
        static fn (string $header): bool => str_contains((string) $controller, "'{$header}'")
    ),
    'UTF-8 BOM tersedia' => str_contains((string) $controller, '\\xEF\\xBB\\xBF'),
    'Proteksi formula CSV tersedia' => str_contains((string) $controller, 'formulaCandidate'),
    'Route source terpasang' => str_contains(
        (string) $routes,
        'admin.invoices.expenses.export-all'
    ),
    'Route runtime terdaftar' => $route !== null,
    'Route memakai controller yang benar' => $route !== null
        && str_contains((string) $route->getActionName(), 'ExpenseExportController@export'),
    'Tombol Invoices terpasang' => str_contains(
        (string) $view,
        'EXPORT ALL EXPENSES CSV V1'
    ) && str_contains((string) $view, 'admin.invoices.expenses.export-all'),
    'ACL export-all terpasang' => (bool) preg_match(
        "~['\"]key['\"]\s*=>\s*['\"]invoices\\.expense\\.export-all['\"]~",
        (string) $acl
    ) && str_contains((string) $acl, 'admin.invoices.expenses.export-all'),
    'Schema database kompatibel' => $schemaOk,
];

echo "CHECK EXPORT ALL EXPENSES CSV V1\n";
echo "================================\n\n";

$failed = [];

foreach ($checks as $label => $ok) {
    echo ($ok ? '[OK]   ' : '[FAIL] ').$label.PHP_EOL;

    if (! $ok) {
        $failed[] = $label;
    }
}

if (! $schemaOk && $schemaMessage !== '') {
    echo "[INFO] Database: {$schemaMessage}\n";
}

echo PHP_EOL;

if ($failed) {
    echo "HASIL: FAIL\n";

    foreach ($failed as $label) {
        echo " - {$label}\n";
    }

    exit(1);
}

echo "HASIL: PASS\n\n";
echo "Buka Invoices lalu klik Export All Expenses.\n";
echo "Jika tombol tidak terlihat, aktifkan permission ";
echo "invoices.expense.export-all pada role user.\n";
