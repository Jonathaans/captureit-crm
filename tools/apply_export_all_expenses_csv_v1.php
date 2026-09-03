<?php

declare(strict_types=1);

/**
 * Install Export All Expenses CSV V1.
 *
 * Run: php tools/apply_export_all_expenses_csv_v1.php
 */

$root = dirname(__DIR__);
$controllerPath = $root.'/packages/Webkul/Admin/src/Http/Controllers/Invoice/ExpenseExportController.php';
$routesPath = $root.'/packages/Webkul/Admin/src/Routes/Admin/invoice-routes.php';
$viewPath = $root.'/packages/Webkul/Admin/src/Resources/views/invoices/index.blade.php';
$aclPath = $root.'/packages/Webkul/Admin/src/Config/acl.php';

function fail(string $message): never
{
    fwrite(STDERR, $message.PHP_EOL);
    exit(1);
}

function readRequired(string $path): string
{
    $contents = file_get_contents($path);

    if ($contents === false) {
        throw new RuntimeException("Gagal membaca: {$path}");
    }

    return $contents;
}

function atomicWrite(string $path, string $contents): void
{
    $directory = dirname($path);

    if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
        throw new RuntimeException("Gagal membuat directory: {$directory}");
    }

    $temporary = $path.'.tmp-'.bin2hex(random_bytes(4));

    if (file_put_contents($temporary, $contents) === false || ! rename($temporary, $path)) {
        @unlink($temporary);
        throw new RuntimeException("Gagal menulis: {$path}");
    }
}

function phpLint(string $path): array
{
    exec(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($path).' 2>&1', $output, $code);

    return [$code, implode(PHP_EOL, $output)];
}

function hasConfigKey(string $contents, string $key): bool
{
    return (bool) preg_match(
        "~['\"]key['\"]\s*=>\s*['\"]".preg_quote($key, '~')."['\"]~",
        $contents
    );
}

function appendBeforeArrayEnd(string $contents, string $block): string
{
    $position = strrpos($contents, '];');

    if ($position === false) {
        throw new RuntimeException('Penutup array "];" tidak ditemukan pada acl.php.');
    }

    return substr($contents, 0, $position).rtrim($block).PHP_EOL.substr($contents, $position);
}

echo "EXPORT ALL EXPENSES CSV V1\n";
echo "==========================\n\n";

foreach ([$routesPath, $viewPath, $aclPath] as $required) {
    if (! is_file($required)) {
        fail("File tidak ditemukan: {$required}");
    }
}

try {
    $routesOriginal = readRequired($routesPath);
    $viewOriginal = readRequired($viewPath);
    $aclOriginal = readRequired($aclPath);
    $controllerOriginal = is_file($controllerPath) ? readRequired($controllerPath) : null;

    $marker = 'EXPORT ALL EXPENSES CSV V1';
    $routeName = 'admin.invoices.expenses.export-all';
    $aclKey = 'invoices.expense.export-all';

    foreach ([[$routesOriginal, 'route'], [$viewOriginal, 'view']] as [$contents, $label]) {
        if (str_contains($contents, $routeName) && ! str_contains($contents, $marker)) {
            throw new RuntimeException(
                ucfirst($label)." sudah memiliki {$routeName} dari implementasi lain."
            );
        }
    }

    $controller = <<<'CONTROLLER'
<?php

namespace Webkul\Admin\Http\Controllers\Invoice;

use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Webkul\Admin\Http\Controllers\Controller;

/**
 * EXPORT ALL EXPENSES CSV V1
 *
 * One CSV row is one expense. Keyset batches keep memory usage bounded.
 */
class ExpenseExportController extends Controller
{
    private const BATCH_SIZE = 500;

    public function export(): StreamedResponse
    {
        $fileName = 'All-Expenses-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(
            function (): void {
                $stream = fopen('php://output', 'w');

                if ($stream === false) {
                    return;
                }

                fwrite($stream, "\xEF\xBB\xBF");

                $this->writeRow($stream, [
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
                ]);

                $lastExpenseId = 0;

                do {
                    $expenses = DB::table('expenses')
                        ->join('invoices', 'expenses.invoice_id', '=', 'invoices.id')
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
                            'invoices.invoice_number',
                            'invoices.project_code',
                            'invoices.subject as project_event',
                            'invoices.event_date as project_event_date',
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

                    $productsByInvoice = DB::table('invoice_items')
                        ->whereIn('invoice_id', $invoiceIds)
                        ->whereNotNull('name')
                        ->where('name', '<>', '')
                        ->orderBy('name')
                        ->get(['invoice_id', 'name'])
                        ->groupBy('invoice_id')
                        ->map(
                            static fn ($items): string => $items
                                ->pluck('name')
                                ->map(static fn ($name): string => trim((string) $name))
                                ->filter()
                                ->unique()
                                ->implode(', ')
                        );

                    foreach ($expenses as $expense) {
                        $productEvent = trim((string) ($productsByInvoice->get($expense->invoice_id) ?? ''));

                        if ($productEvent === '') {
                            $productEvent = (string) ($expense->project_event ?? '');
                        }

                        $expenseNameCategory = trim((string) ($expense->description ?? ''));
                        $category = ucwords(str_replace('_', ' ', trim((string) ($expense->category ?? ''))));

                        if ($expenseNameCategory !== '' && $category !== '') {
                            $expenseNameCategory .= ' / '.$category;
                        } elseif ($expenseNameCategory === '') {
                            $expenseNameCategory = $category;
                        }

                        $this->writeRow($stream, [
                            $this->csvText($expense->invoice_number ?? ''),
                            $this->csvText($expense->project_code ?? ''),
                            $this->csvText($productEvent),
                            $this->dateValue($expense->project_event_date ?? null),
                            $this->dateValue($expense->expense_date ?? null),
                            $this->csvText($expenseNameCategory),
                            (string) ($expense->amount ?? '0'),
                            $this->csvText($expense->notes ?? ''),
                            $this->csvText($this->receiptUrl($expense->receipt_path ?? null)),
                            $this->csvText($expense->created_by_name ?? ''),
                            $this->dateTimeValue($expense->created_at ?? null),
                        ]);

                        $lastExpenseId = (int) $expense->expense_id;
                    }

                    if (function_exists('flush')) {
                        flush();
                    }
                } while (true);

                fclose($stream);
            },
            $fileName,
            [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Cache-Control' => 'no-store, no-cache, must-revalidate',
                'Pragma' => 'no-cache',
                'X-Content-Type-Options' => 'nosniff',
            ]
        );
    }

    private function writeRow($stream, array $row): void
    {
        fputcsv($stream, $row, ',', '"', '');
    }

    private function csvText(mixed $value): string
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

        return asset('storage/'.ltrim($path, '/'));
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

    $routeBlock = <<<'ROUTES'
/*
|--------------------------------------------------------------------------
| EXPORT ALL EXPENSES CSV V1
|--------------------------------------------------------------------------
| This static route must be declared before the dynamic invoices/{id} route.
*/

Route::controller(
    \Webkul\Admin\Http\Controllers\Invoice\ExpenseExportController::class
)
    ->prefix('invoices')
    ->group(function () {
        Route::get('expenses/export-all', 'export')
            ->name('admin.invoices.expenses.export-all');
    });

ROUTES;

    $buttonBlock = <<<'BLADE'
        {{-- EXPORT ALL EXPENSES CSV V1 --}}
        @if (bouncer()->hasPermission('invoices.expense.export-all'))
            <div class="flex items-center gap-2 max-sm:w-full">
                <a
                    href="{{ route('admin.invoices.expenses.export-all') }}"
                    class="primary-button max-sm:w-full max-sm:justify-center"
                >
                    Export All Expenses
                </a>
            </div>
        @endif

BLADE;

    $aclBlock = <<<'ACL'
[
    /**
     * Export All Expenses CSV.
     */
    'key'   => 'invoices.expense.export-all',
    'name'  => 'Export All Expenses',
    'route' => 'admin.invoices.expenses.export-all',
    'sort'  => 3,
],
ACL;

    $routes = $routesOriginal;

    if (! str_contains($routes, $marker)) {
        $needle = 'Route::controller(InvoiceController::class)';
        $position = strpos($routes, $needle);

        if ($position === false) {
            throw new RuntimeException("Preflight gagal: {$needle} tidak ditemukan.");
        }

        $routes = substr($routes, 0, $position)
            .rtrim($routeBlock).PHP_EOL.PHP_EOL
            .substr($routes, $position);
    }

    $view = $viewOriginal;

    if (! str_contains($view, $marker)) {
        $event = "{!! view_render_event('admin.invoices.index.header.after') !!}";
        $eventPosition = strpos($view, $event);

        if ($eventPosition === false) {
            throw new RuntimeException('Preflight gagal: event header invoice tidak ditemukan.');
        }

        $headerClose = strrpos(substr($view, 0, $eventPosition), '</div>');

        if ($headerClose === false) {
            throw new RuntimeException('Preflight gagal: penutup header invoice tidak ditemukan.');
        }

        $headerCloseLine = strrpos(substr($view, 0, $headerClose), "\n");
        $headerCloseLine = $headerCloseLine === false ? 0 : $headerCloseLine + 1;

        $view = substr($view, 0, $headerCloseLine)
            .rtrim($buttonBlock).PHP_EOL
            .substr($view, $headerCloseLine);
    }

    $acl = hasConfigKey($aclOriginal, $aclKey)
        ? $aclOriginal
        : appendBeforeArrayEnd($aclOriginal, $aclBlock);

    $stamp = date('Ymd-His');
    $backupDirectory = $root.'/storage/app/expense-export-all-v1-backup/'.$stamp;

    if (! mkdir($backupDirectory, 0775, true) && ! is_dir($backupDirectory)) {
        throw new RuntimeException("Gagal membuat backup: {$backupDirectory}");
    }

    foreach (
        [
            [$routesPath, 'invoice-routes.php'],
            [$viewPath, 'index.blade.php'],
            [$aclPath, 'acl.php'],
            [$controllerPath, 'ExpenseExportController.php'],
        ] as [$source, $name]
    ) {
        if (is_file($source) && ! copy($source, $backupDirectory.'/'.$name)) {
            throw new RuntimeException("Gagal membuat backup: {$name}");
        }
    }

    try {
        atomicWrite($controllerPath, rtrim($controller).PHP_EOL);
        atomicWrite($routesPath, rtrim($routes).PHP_EOL);
        atomicWrite($viewPath, rtrim($view).PHP_EOL);
        atomicWrite($aclPath, rtrim($acl).PHP_EOL);

        foreach ([$controllerPath, $routesPath, $aclPath] as $lintPath) {
            [$lintCode, $lintOutput] = phpLint($lintPath);

            if ($lintCode !== 0) {
                throw new RuntimeException("PHP lint gagal untuk {$lintPath}:\n{$lintOutput}");
            }
        }
    } catch (Throwable $exception) {
        copy($backupDirectory.'/invoice-routes.php', $routesPath);
        copy($backupDirectory.'/index.blade.php', $viewPath);
        copy($backupDirectory.'/acl.php', $aclPath);

        if ($controllerOriginal === null) {
            @unlink($controllerPath);
        } else {
            copy($backupDirectory.'/ExpenseExportController.php', $controllerPath);
        }

        throw $exception;
    }

    echo "[OK] Controller, route, tombol, ACL, dan PHP lint.\n";
    echo "[OK] Backup: {$backupDirectory}\n";

    $artisan = $root.'/artisan';

    if (is_file($artisan)) {
        passthru(escapeshellarg(PHP_BINARY).' '.escapeshellarg($artisan).' optimize:clear', $cacheCode);

        if ($cacheCode !== 0) {
            echo "[WARN] optimize:clear gagal; perubahan file tetap terpasang.\n";
        }
    }

    echo "\nINSTALL SELESAI\n";
    echo "Checker: php tools/check_export_all_expenses_csv_v1.php\n";
} catch (Throwable $exception) {
    fail('INSTALL GAGAL: '.$exception->getMessage());
}
