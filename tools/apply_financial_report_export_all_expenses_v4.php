<?php

declare(strict_types=1);

/**
 * FINANCIAL REPORT - EXPORT ALL EXPENSES V4 DATA MAPPING FIX
 *
 * Fixes the controller installed by V1:
 * - Expense -> its own Invoice
 * - Project Code -> Invoice, then Quote, then Purchase Order
 * - Product -> Invoice Item snapshot/master, then Quote Item, then PO Item
 * - Event / Project -> Invoice subject, then Quote subject, then PO project name
 *
 * Run: php tools/apply_financial_report_export_all_expenses_v4.php
 */

use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

$root = dirname(__DIR__);
$controllerPath = $root.'/app/Http/Controllers/AllExpensesExportController.php';

function financialExportV4Fail(string $message): never
{
    fwrite(STDERR, $message.PHP_EOL);
    exit(1);
}

function financialExportV4AtomicWrite(string $path, string $contents): void
{
    $temporary = $path.'.tmp-'.bin2hex(random_bytes(4));

    if (file_put_contents($temporary, $contents) === false) {
        @unlink($temporary);
        throw new RuntimeException("Gagal menulis temporary file: {$temporary}");
    }

    if (! rename($temporary, $path)) {
        @unlink($temporary);
        throw new RuntimeException("Gagal mengganti file: {$path}");
    }
}

function financialExportV4PhpLint(string $path): array
{
    exec(
        escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($path).' 2>&1',
        $output,
        $code
    );

    return [$code, implode(PHP_EOL, $output)];
}

echo "FINANCIAL REPORT EXPORT ALL EXPENSES V4\n";
echo "=======================================\n\n";

foreach ([$root.'/vendor/autoload.php', $root.'/bootstrap/app.php', $controllerPath] as $path) {
    if (! is_file($path)) {
        financialExportV4Fail("File tidak ditemukan: {$path}");
    }
}

require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(ConsoleKernel::class)->bootstrap();

$requiredSchema = [
    'expenses' => [
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
    ],
    'invoices' => [
        'id',
        'quote_id',
        'invoice_number',
        'project_code',
        'subject',
        'event_date',
    ],
    'invoice_items' => [
        'id',
        'invoice_id',
        'product_id',
        'name',
    ],
    'quotes' => [
        'id',
        'project_code',
        'subject',
        'event_date',
    ],
    'quote_items' => [
        'id',
        'quote_id',
        'product_id',
        'name',
    ],
    'products' => [
        'id',
        'name',
    ],
    'users' => [
        'id',
        'name',
    ],
];

foreach ($requiredSchema as $table => $columns) {
    if (! Schema::hasTable($table)) {
        financialExportV4Fail("Preflight gagal: tabel {$table} tidak ditemukan.");
    }

    if (! Schema::hasColumns($table, $columns)) {
        $actual = Schema::getColumnListing($table);
        $missing = array_values(array_diff($columns, $actual));
        financialExportV4Fail("Preflight gagal: {$table} kurang kolom ".implode(', ', $missing));
    }
}

$exportRoute = null;

foreach (Route::getRoutes() as $route) {
    if (str_contains((string) $route->getActionName(), 'AllExpensesExportController')) {
        $exportRoute = $route;
        break;
    }
}

if ($exportRoute === null) {
    financialExportV4Fail(
        'Route AllExpensesExportController tidak ditemukan. '
        .'Pastikan Export All Expenses V1 sudah terpasang.'
    );
}

$controller = <<<'CONTROLLER'
<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * FINANCIAL REPORT EXPORT ALL EXPENSES V4 DATA MAPPING FIX
 *
 * One row is one expense. Every project and product value is resolved from
 * that expense's invoice_id; no value is shared globally between CSV rows.
 */
final class AllExpensesExportController extends Controller
{
    private const BATCH_SIZE = 500;

    public function __invoke(): StreamedResponse
    {
        $filename = 'all-expenses-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(
            function (): void {
                $out = fopen('php://output', 'wb');

                if ($out === false) {
                    return;
                }

                fwrite($out, "\xEF\xBB\xBF");

                $this->writeRow($out, [
                    'Invoice Number',
                    'Project Code',
                    'Product',
                    'Event / Project',
                    'Event Date',
                    'Expense Date',
                    'Expense Name / Category',
                    'Amount',
                    'Note',
                    'Image / Receipt',
                    'Created By',
                    'Created At',
                ]);

                $hasPurchaseOrderFallback = $this->hasPurchaseOrderFallback();
                $lastExpenseId = 0;

                do {
                    $expenses = DB::table('expenses')
                        ->leftJoin('invoices', 'expenses.invoice_id', '=', 'invoices.id')
                        ->leftJoin('quotes', 'invoices.quote_id', '=', 'quotes.id')
                        ->leftJoin('users', 'expenses.created_by', '=', 'users.id')
                        ->where('expenses.id', '>', $lastExpenseId)
                        ->orderBy('expenses.id')
                        ->limit(self::BATCH_SIZE)
                        ->get([
                            'expenses.id as expense_id',
                            'expenses.invoice_id',
                            'expenses.category',
                            'expenses.description',
                            'expenses.amount',
                            'expenses.expense_date',
                            'expenses.notes',
                            'expenses.receipt_path',
                            'expenses.created_at',
                            'invoices.quote_id',
                            'invoices.invoice_number',
                            'invoices.project_code as invoice_project_code',
                            'invoices.subject as invoice_subject',
                            'invoices.event_date as invoice_event_date',
                            'quotes.project_code as quote_project_code',
                            'quotes.subject as quote_subject',
                            'quotes.event_date as quote_event_date',
                            'users.name as created_by_name',
                        ]);

                    if ($expenses->isEmpty()) {
                        break;
                    }

                    $invoiceIds = $expenses
                        ->pluck('invoice_id')
                        ->map(static fn ($id): int => (int) $id)
                        ->unique()
                        ->values();

                    $quoteIds = $expenses
                        ->pluck('quote_id')
                        ->filter()
                        ->map(static fn ($id): int => (int) $id)
                        ->unique()
                        ->values();

                    $productsByInvoice = $this->productNames(
                        'invoice_items',
                        'invoice_id',
                        $invoiceIds
                    );

                    $productsByQuote = $this->productNames(
                        'quote_items',
                        'quote_id',
                        $quoteIds
                    );

                    $purchaseOrdersByExpense = collect();
                    $productsByPurchaseOrder = collect();

                    if ($hasPurchaseOrderFallback) {
                        $purchaseOrdersByExpense = DB::table('purchase_orders')
                            ->whereIn('expense_id', $expenses->pluck('expense_id'))
                            ->orderBy('id')
                            ->get([
                                'id',
                                'expense_id',
                                'project_code',
                                'project_name',
                            ])
                            ->keyBy('expense_id');

                        $productsByPurchaseOrder = $this->productNames(
                            'purchase_order_items',
                            'purchase_order_id',
                            $purchaseOrdersByExpense->pluck('id')
                        );
                    }

                    foreach ($expenses as $expense) {
                        $purchaseOrder = $purchaseOrdersByExpense->get($expense->expense_id);

                        $projectCode = $this->firstFilled([
                            $expense->invoice_project_code ?? null,
                            $expense->quote_project_code ?? null,
                            $purchaseOrder?->project_code,
                        ]);

                        $product = $this->firstFilled([
                            $productsByInvoice->get($expense->invoice_id),
                            $productsByQuote->get($expense->quote_id),
                            $productsByPurchaseOrder->get($purchaseOrder?->id),
                        ]);

                        $eventProject = $this->firstFilled([
                            $expense->invoice_subject ?? null,
                            $expense->quote_subject ?? null,
                            $purchaseOrder?->project_name,
                        ]);

                        $eventDate = $this->firstFilled([
                            $expense->invoice_event_date ?? null,
                            $expense->quote_event_date ?? null,
                        ]);

                        $expenseName = trim((string) ($expense->description ?? ''));
                        $category = ucwords(
                            str_replace('_', ' ', trim((string) ($expense->category ?? '')))
                        );

                        if ($expenseName !== '' && $category !== '') {
                            $expenseName .= ' / '.$category;
                        } elseif ($expenseName === '') {
                            $expenseName = $category;
                        }

                        $this->writeRow($out, [
                            $this->safeCell($expense->invoice_number ?? ''),
                            $this->safeCell($projectCode),
                            $this->safeCell($product),
                            $this->safeCell($eventProject),
                            $this->dateValue($eventDate),
                            $this->dateValue($expense->expense_date ?? null),
                            $this->safeCell($expenseName),
                            (string) ($expense->amount ?? '0'),
                            $this->safeCell($expense->notes ?? ''),
                            $this->safeCell($this->receiptUrl($expense->receipt_path ?? null)),
                            $this->safeCell($expense->created_by_name ?? ''),
                            $this->dateTimeValue($expense->created_at ?? null),
                        ]);

                        $lastExpenseId = (int) $expense->expense_id;
                    }

                    if (function_exists('flush')) {
                        flush();
                    }
                } while (true);

                fclose($out);
            },
            $filename,
            [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Cache-Control' => 'no-store, no-cache, must-revalidate',
                'Pragma' => 'no-cache',
                'X-Content-Type-Options' => 'nosniff',
            ]
        );
    }

    private function productNames(
        string $itemTable,
        string $ownerColumn,
        Collection $ownerIds
    ): Collection {
        if ($ownerIds->isEmpty()) {
            return collect();
        }

        return DB::table($itemTable)
            ->leftJoin('products', $itemTable.'.product_id', '=', 'products.id')
            ->whereIn($itemTable.'.'.$ownerColumn, $ownerIds)
            ->orderBy($itemTable.'.id')
            ->get([
                $itemTable.'.'.$ownerColumn.' as owner_id',
                $itemTable.'.name as snapshot_name',
                'products.name as master_name',
            ])
            ->groupBy('owner_id')
            ->map(
                fn (Collection $items): string => $items
                    ->map(
                        fn ($item): string => $this->firstFilled([
                            $item->snapshot_name ?? null,
                            $item->master_name ?? null,
                        ])
                    )
                    ->filter()
                    ->unique()
                    ->implode(' | ')
            );
    }

    private function hasPurchaseOrderFallback(): bool
    {
        return Schema::hasTable('purchase_orders')
            && Schema::hasColumns('purchase_orders', [
                'id',
                'expense_id',
                'project_code',
                'project_name',
            ])
            && Schema::hasTable('purchase_order_items')
            && Schema::hasColumns('purchase_order_items', [
                'id',
                'purchase_order_id',
                'product_id',
                'name',
            ]);
    }

    private function firstFilled(array $values): string
    {
        foreach ($values as $value) {
            $text = trim((string) ($value ?? ''));

            if ($text !== '') {
                return $text;
            }
        }

        return '';
    }

    private function writeRow($stream, array $row): void
    {
        fputcsv($stream, $row, ',', '"', '');
    }

    private function safeCell(mixed $value): string
    {
        $text = (string) ($value ?? '');
        $formulaCandidate = ltrim($text, " \t\r\n");

        if (
            $formulaCandidate !== ''
            && in_array($formulaCandidate[0], ['=', '+', '-', '@'], true)
        ) {
            return "'".$text;
        }

        return $text;
    }

    private function receiptUrl(mixed $value): string
    {
        $path = trim((string) ($value ?? ''));

        if ($path === '') {
            return '';
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        $path = ltrim(str_replace('\\', '/', $path), '/');

        if (str_starts_with($path, 'public/')) {
            $path = substr($path, 7);
        }

        if (str_starts_with($path, 'storage/')) {
            return url('/'.$path);
        }

        return url('/storage/'.$path);
    }

    private function dateValue(mixed $value): string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? '' : substr($value, 0, 10);
    }

    private function dateTimeValue(mixed $value): string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? '' : substr($value, 0, 19);
    }
}
CONTROLLER;

$stamp = date('Ymd-His');
$backup = $controllerPath.'.bak-financial-expenses-export-v4-'.$stamp;

if (! copy($controllerPath, $backup)) {
    financialExportV4Fail("Gagal membuat backup: {$backup}");
}

try {
    financialExportV4AtomicWrite($controllerPath, rtrim($controller).PHP_EOL);

    [$lintCode, $lintOutput] = financialExportV4PhpLint($controllerPath);

    if ($lintCode !== 0) {
        copy($backup, $controllerPath);
        throw new RuntimeException("PHP lint gagal:\n{$lintOutput}");
    }

    passthru(
        escapeshellarg(PHP_BINARY).' '.escapeshellarg($root.'/artisan').' optimize:clear',
        $cacheCode
    );

    echo "\n[OK] Mapping V4 terpasang.\n";
    echo "[OK] Route: ".($exportRoute->getName() ?: '(tanpa nama)')."\n";
    echo "[OK] Backup: {$backup}\n";
    echo "[OK] Project Code memakai invoice -> quote -> PO.\n";
    echo "[OK] Product memakai invoice_items -> quote_items -> PO items.\n";
    echo "[OK] Event / Project memakai subject milik invoice masing-masing.\n\n";
    echo "Jalankan checker:\n";
    echo "php tools/check_financial_report_export_all_expenses_v4.php\n";
} catch (Throwable $exception) {
    @copy($backup, $controllerPath);
    financialExportV4Fail('PATCH GAGAL: '.$exception->getMessage());
}
