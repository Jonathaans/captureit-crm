<?php

declare(strict_types=1);

/**
 * Read-only checker for Financial Report Export All Expenses V4.
 *
 * Run: php tools/check_financial_report_export_all_expenses_v4.php
 */

use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

$root = dirname(__DIR__);
$controllerPath = $root.'/app/Http/Controllers/AllExpensesExportController.php';
$errors = [];

function financialExportV4Check(bool $condition, string $success, string $failure): void
{
    global $errors;

    if ($condition) {
        echo "[OK] {$success}\n";

        return;
    }

    echo "[FAIL] {$failure}\n";
    $errors[] = $failure;
}

echo "CHECK FINANCIAL REPORT EXPORT ALL EXPENSES V4\n";
echo "================================================\n\n";

foreach ([$root.'/vendor/autoload.php', $root.'/bootstrap/app.php'] as $path) {
    if (! is_file($path)) {
        fwrite(STDERR, "File tidak ditemukan: {$path}\n");
        exit(1);
    }
}

require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(ConsoleKernel::class)->bootstrap();

financialExportV4Check(
    is_file($controllerPath),
    'Controller export tersedia.',
    "Controller tidak ditemukan: {$controllerPath}"
);

$source = is_file($controllerPath)
    ? (string) file_get_contents($controllerPath)
    : '';

$anchors = [
    'FINANCIAL REPORT EXPORT ALL EXPENSES V4 DATA MAPPING FIX',
    'invoices.project_code as invoice_project_code',
    'invoices.subject as invoice_subject',
    "'invoice_items'",
    "'quote_items'",
    '$productsByInvoice->get($expense->invoice_id)',
    '$this->firstFilled([',
];

foreach ($anchors as $anchor) {
    financialExportV4Check(
        str_contains($source, $anchor),
        "Mapping ditemukan: {$anchor}",
        "Mapping V4 tidak lengkap: {$anchor}"
    );
}

$routeFound = false;

foreach (Route::getRoutes() as $route) {
    if (str_contains((string) $route->getActionName(), 'AllExpensesExportController')) {
        $routeFound = true;
        echo '[INFO] Route export: '.implode('|', $route->methods()).' '
            .'/'.ltrim($route->uri(), '/').' ('
            .($route->getName() ?: 'tanpa nama').")\n";
        break;
    }
}

financialExportV4Check(
    $routeFound,
    'Route Export All Expenses aktif.',
    'Route AllExpensesExportController tidak ditemukan.'
);

$requiredSchema = [
    'expenses' => ['id', 'invoice_id'],
    'invoices' => [
        'id',
        'quote_id',
        'invoice_number',
        'project_code',
        'subject',
        'event_date',
    ],
    'invoice_items' => ['id', 'invoice_id', 'product_id', 'name'],
    'quotes' => ['id', 'project_code', 'subject', 'event_date'],
    'quote_items' => ['id', 'quote_id', 'product_id', 'name'],
    'products' => ['id', 'name'],
];

foreach ($requiredSchema as $table => $columns) {
    $tableExists = Schema::hasTable($table);

    financialExportV4Check(
        $tableExists,
        "Tabel {$table} tersedia.",
        "Tabel {$table} tidak ditemukan."
    );

    if (! $tableExists) {
        continue;
    }

    $actual = Schema::getColumnListing($table);
    $missing = array_values(array_diff($columns, $actual));

    financialExportV4Check(
        $missing === [],
        "Kolom {$table} lengkap.",
        "Kolom {$table} kurang: ".implode(', ', $missing)
    );
}

if ($errors === []) {
    try {
        $expenseCount = (int) DB::table('expenses')->count();
        $orphanCount = (int) DB::table('expenses')
            ->leftJoin('invoices', 'expenses.invoice_id', '=', 'invoices.id')
            ->whereNull('invoices.id')
            ->count('expenses.id');
        $invoiceProjectCount = (int) DB::table('expenses')
            ->join('invoices', 'expenses.invoice_id', '=', 'invoices.id')
            ->whereNotNull('invoices.project_code')
            ->where('invoices.project_code', '<>', '')
            ->distinct()
            ->count('expenses.id');
        $quoteProjectCount = (int) DB::table('expenses')
            ->join('invoices', 'expenses.invoice_id', '=', 'invoices.id')
            ->join('quotes', 'invoices.quote_id', '=', 'quotes.id')
            ->whereNotNull('quotes.project_code')
            ->where('quotes.project_code', '<>', '')
            ->distinct()
            ->count('expenses.id');
        $invoiceProductCount = (int) DB::table('expenses')
            ->join('invoice_items', 'expenses.invoice_id', '=', 'invoice_items.invoice_id')
            ->distinct()
            ->count('expenses.id');

        echo "\nDATA DIAGNOSTIC (tidak mengubah data)\n";
        echo "[INFO] Total expenses: {$expenseCount}\n";
        echo "[INFO] Expenses tanpa invoice: {$orphanCount}\n";
        echo "[INFO] Expenses dengan project_code di invoice: {$invoiceProjectCount}\n";
        echo "[INFO] Expenses dengan fallback project_code di quote: {$quoteProjectCount}\n";
        echo "[INFO] Expenses dengan invoice items: {$invoiceProductCount}\n";
    } catch (Throwable $exception) {
        echo '[WARN] Diagnostic data tidak dapat dijalankan: '
            .$exception->getMessage()."\n";
    }
}

echo "\n";

if ($errors !== []) {
    echo '[FAIL] Checker menemukan '.count($errors)." masalah.\n";
    exit(1);
}

echo "[PASS] Export All Expenses V4 siap diuji.\n";
echo "Buka Financial Report, export CSV baru, lalu periksa Project Code, Product, dan Event / Project.\n";
