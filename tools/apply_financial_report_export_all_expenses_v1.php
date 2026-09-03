<?php

declare(strict_types=1);

/**
 * FINANCIAL REPORT - EXPORT ALL EXPENSES CSV V1
 *
 * Installer adaptif untuk project lokal CaptureIT.
 *
 * Yang dilakukan:
 * - bootstrap Laravel lokal;
 * - cari route Export Financial Report yang sudah ada;
 * - copy middleware route tersebut untuk route baru;
 * - deteksi tabel expense dari schema DB;
 * - buat controller CSV khusus expenses;
 * - tambah route baru ke routes/web.php;
 * - tambah tombol "Export All Expenses" di view Financial Report;
 * - backup semua file yang diubah.
 *
 * 12 kolom CSV tetap:
 * Invoice Number, Project Code, Product, Event / Project, Event Date,
 * Expense Date, Expense Name / Category, Amount, Note, Image / Receipt,
 * Created By, Created At
 */

use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

$root = dirname(__DIR__);
$webRoutes = $root . '/routes/web.php';
$controllerPath = $root . '/app/Http/Controllers/AllExpensesExportController.php';
$markerRouteStart = '// FINANCIAL REPORT EXPORT ALL EXPENSES V1 START';
$markerRouteEnd = '// FINANCIAL REPORT EXPORT ALL EXPENSES V1 END';
$markerView = 'FINANCIAL REPORT EXPORT ALL EXPENSES V1';

function fail(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

function atomicWrite(string $path, string $contents): void
{
    $dir = dirname($path);

    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new RuntimeException("Gagal membuat directory: {$dir}");
    }

    $tmp = $path . '.tmp-' . bin2hex(random_bytes(4));

    if (file_put_contents($tmp, $contents) === false) {
        @unlink($tmp);
        throw new RuntimeException("Gagal menulis temporary file: {$tmp}");
    }

    if (!rename($tmp, $path)) {
        @unlink($tmp);
        throw new RuntimeException("Gagal mengganti file: {$path}");
    }
}

function backupFile(string $path, string $suffix): ?string
{
    if (!is_file($path)) {
        return null;
    }

    $backup = $path . '.bak-' . $suffix . '-' . date('Ymd-His');

    if (!copy($path, $backup)) {
        throw new RuntimeException("Gagal membuat backup: {$path}");
    }

    return $backup;
}

function removeMarkedBlock(string $content, string $start, string $end): string
{
    $startPos = strpos($content, $start);

    if ($startPos === false) {
        return $content;
    }

    $endPos = strpos($content, $end, $startPos);

    if ($endPos === false) {
        throw new RuntimeException('Marker route lama tidak lengkap.');
    }

    $endPos += strlen($end);

    return rtrim(substr($content, 0, $startPos))
        . PHP_EOL . PHP_EOL
        . ltrim(substr($content, $endPos));
}

function phpArray(array $values): string
{
    return '[' . implode(', ', array_map(
        static fn ($value) => var_export((string) $value, true),
        $values
    )) . ']';
}

function allTables(): array
{
    $rows = DB::select('SHOW TABLES');
    $tables = [];

    foreach ($rows as $row) {
        $values = array_values((array) $row);
        if (isset($values[0])) {
            $tables[] = (string) $values[0];
        }
    }

    sort($tables);

    return $tables;
}

function expenseTableScore(string $table, array $columns): int
{
    $score = 0;
    $lower = strtolower($table);

    if (str_contains($lower, 'expense')) $score += 20;
    if (str_contains($lower, 'event')) $score += 3;
    if (str_contains($lower, 'project')) $score += 3;

    $c = array_map('strtolower', $columns);

    foreach (['amount', 'expense_amount', 'total', 'cost', 'value'] as $name) {
        if (in_array($name, $c, true)) { $score += 5; break; }
    }

    foreach (['note', 'notes', 'description', 'remark', 'remarks'] as $name) {
        if (in_array($name, $c, true)) { $score += 2; break; }
    }

    foreach (['event_id', 'project_id', 'invoice_id', 'quote_id', 'deal_id'] as $name) {
        if (in_array($name, $c, true)) { $score += 3; break; }
    }

    foreach ($c as $name) {
        if (preg_match('/(?:receipt|image|photo|attachment|file|path|url)/', $name)) {
            $score += 2;
            break;
        }
    }

    if (in_array('created_at', $c, true)) $score++;

    return $score;
}

function findFinancialView(string $root): ?array
{
    $searchRoots = [
        $root . '/packages/Webkul/Admin/src/Resources/views',
        $root . '/resources/views',
    ];

    $candidates = [];

    foreach ($searchRoots as $searchRoot) {
        if (!is_dir($searchRoot)) continue;

        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($searchRoot, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($it as $file) {
            if (!$file->isFile() || !str_ends_with(strtolower($file->getFilename()), '.blade.php')) {
                continue;
            }

            $path = $file->getPathname();
            $source = @file_get_contents($path);
            if (!is_string($source)) continue;

            $score = 0;
            $lower = strtolower($source);
            $pathLower = strtolower(str_replace('\\', '/', $path));

            if (str_contains($lower, 'export financial report')) $score += 30;
            if (str_contains($lower, 'financial report')) $score += 15;
            if (str_contains($lower, 'financial-report')) $score += 12;
            if (str_contains($lower, 'financial_report')) $score += 12;
            if (str_contains($lower, 'expense')) $score += 4;
            if (str_contains($pathLower, 'financial')) $score += 8;
            if (str_contains($lower, 'export')) $score += 4;

            if ($score > 0) {
                $candidates[] = compact('path', 'source', 'score');
            }
        }
    }

    usort($candidates, static fn ($a, $b) => $b['score'] <=> $a['score']);

    if (!$candidates) return null;

    if (count($candidates) > 1 && $candidates[0]['score'] === $candidates[1]['score']) {
        return null;
    }

    return $candidates[0];
}

function addButtonToView(string $source, string $routeName, string $marker): string
{
    if (str_contains($source, $marker)) {
        return $source;
    }

    $needlePos = stripos($source, "admin.invoices.financial-report.export");

if ($needlePos === false) {
    $needlePos = stripos($source, "Export CSV");
}

    if ($needlePos === false) {
        $needlePos = stripos($source, 'financial report');
    }

    if ($needlePos === false) {
        throw new RuntimeException('Anchor Financial Report tidak ditemukan di view terpilih.');
    }

    $aStart = strripos(substr($source, 0, $needlePos), '<a');
    $aEnd = stripos($source, '</a>', $needlePos);

    if ($aStart !== false && $aEnd !== false && ($aEnd - $aStart) < 4000) {
        $aEnd += strlen('</a>');
        $openingEnd = strpos($source, '>', $aStart);
        $openingTag = $openingEnd !== false
            ? substr($source, $aStart, $openingEnd - $aStart + 1)
            : '';

        $class = 'primary-button';
        if (preg_match('/\\bclass\\s*=\\s*["\']([^"\']+)["\']/i', $openingTag, $m)) {
            $class = $m[1];
        }

        $button = "\n\n            {{-- {$marker} --}}\n"
            . "            <a href=\"{{ route('{$routeName}') }}\" class=\"{$class}\">\n"
            . "                Export All Expenses\n"
            . "            </a>";

        return substr($source, 0, $aEnd) . $button . substr($source, $aEnd);
    }

    throw new RuntimeException('Tidak dapat menemukan anchor export untuk menyisipkan tombol baru.');
}

function routeScore($route): int
{
    $name = strtolower((string) $route->getName());
    $uri = strtolower((string) $route->uri());
    $score = 0;

    if (str_contains($name, 'financial') || str_contains($uri, 'financial')) $score += 20;
    if (str_contains($name, 'report') || str_contains($uri, 'report')) $score += 10;
    if (str_contains($name, 'export') || str_contains($uri, 'export')) $score += 20;
    if (in_array('GET', $route->methods(), true)) $score += 2;

    return $score;
}

echo "FINANCIAL REPORT EXPORT ALL EXPENSES V1\n";
echo "=======================================\n\n";

if (!is_file($webRoutes)) {
    fail("routes/web.php tidak ditemukan: {$webRoutes}");
}

require $root . '/vendor/autoload.php';
$app = require $root . '/bootstrap/app.php';
$app->make(ConsoleKernel::class)->bootstrap();

/* Detect existing financial export route. */
$routeCandidates = [];
foreach (Route::getRoutes() as $route) {
    $score = routeScore($route);
    if ($score >= 35) {
        $routeCandidates[] = ['route' => $route, 'score' => $score];
    }
}

usort($routeCandidates, static fn ($a, $b) => $b['score'] <=> $a['score']);

if (!$routeCandidates) {
    fail('Route Export Financial Report existing tidak ditemukan. Source tidak diubah.');
}

$existingRoute = $routeCandidates[0]['route'];
$existingName = (string) $existingRoute->getName();
$existingUri = (string) $existingRoute->uri();
$existingMiddleware = array_values(array_unique($existingRoute->gatherMiddleware()));

echo "Existing export route:\n";
echo "  name       : {$existingName}\n";
echo "  uri        : {$existingUri}\n";
echo "  middleware : " . implode(', ', $existingMiddleware) . "\n\n";

if ($existingName === '') {
    fail('Existing financial export route tidak punya route name. Source tidak diubah.');
}

$newRouteName = preg_match('/\\.export$/', $existingName)
    ? preg_replace('/\\.export$/', '.expenses.export', $existingName)
    : $existingName . '.expenses';

$newRouteName = (string) $newRouteName;

$uriBase = preg_replace('~/(?:export|download)(?:/.*)?$~i', '', $existingUri);
if (!is_string($uriBase) || $uriBase === $existingUri) {
    $uriBase = rtrim($existingUri, '/');
}
$newUri = rtrim($uriBase, '/') . '/export-expenses';

/* Detect expense table. */
$tableCandidates = [];
foreach (allTables() as $table) {
    try {
        $columns = Schema::getColumnListing($table);
    } catch (Throwable $e) {
        continue;
    }

    $score = expenseTableScore($table, $columns);
    if ($score >= 20) {
        $tableCandidates[] = compact('table', 'columns', 'score');
    }
}

usort($tableCandidates, static fn ($a, $b) => $b['score'] <=> $a['score']);

if (!$tableCandidates) {
    fail('Tabel expense tidak dapat dideteksi dengan confidence yang cukup. Source tidak diubah.');
}

if (count($tableCandidates) > 1 && ($tableCandidates[0]['score'] - $tableCandidates[1]['score']) < 3) {
    echo "Expense table candidates:\n";
    foreach ($tableCandidates as $c) {
        echo "  {$c['table']} score={$c['score']} columns=" . implode(',', $c['columns']) . "\n";
    }
    fail('Tabel expense ambigu. Source tidak diubah.');
}

$expenseTable = $tableCandidates[0]['table'];
$expenseColumns = $tableCandidates[0]['columns'];

echo "Expense table detected:\n";
echo "  table   : {$expenseTable}\n";
echo "  columns : " . implode(', ', $expenseColumns) . "\n\n";

/* Locate Financial Report Blade. */
$view = findFinancialView($root);
if (!$view) {
    fail('Financial Report Blade tidak dapat dipilih secara unik. Source tidak diubah.');
}

$viewPath = $view['path'];
$viewSource = $view['source'];

echo "Financial Report view:\n  {$viewPath}\n\n";

/* Backups. */
$backupSuffix = 'financial-expenses-export-v1';
$backups = [];
foreach ([$webRoutes, $controllerPath, $viewPath] as $path) {
    $backup = backupFile($path, $backupSuffix);
    if ($backup) $backups[$path] = $backup;
}

try {
    /* Generate controller with schema graph resolver. */
    $expenseTableLiteral = var_export($expenseTable, true);

    $controller = <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

final class AllExpensesExportController extends Controller
{
    private const EXPENSE_TABLE = __EXPENSE_TABLE__;

    private array $columnCache = [];
    private array $fkOutCache = [];
    private array $fkInCache = [];
    private array $pkCache = [];
    private array $rowCache = [];

    public function __invoke(): StreamedResponse
    {
        $filename = 'all-expenses-' . now()->format('Y-m-d-His') . '.csv';

        return response()->streamDownload(function (): void {
            $out = fopen('php://output', 'wb');
            if ($out === false) {
                return;
            }

            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, [
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

            $order = in_array('id', $this->columns(self::EXPENSE_TABLE), true)
                ? 'id'
                : (in_array('created_at', $this->columns(self::EXPENSE_TABLE), true) ? 'created_at' : null);

            $query = DB::table(self::EXPENSE_TABLE);
            if ($order !== null) {
                $query->orderBy($order);
            }

            foreach ($query->cursor() as $expense) {
                $row = (array) $expense;
                $nodes = $this->graph(self::EXPENSE_TABLE, $row, 4);

                $invoiceNumber = $this->directOrNode(
                    $row,
                    ['invoice_number', 'invoice_no', 'invoice_code'],
                    $nodes,
                    '/invoice/',
                    ['invoice_number', 'invoice_no', 'number', 'code']
                );

                $projectCode = $this->directOrNode(
                    $row,
                    ['project_code', 'event_code', 'code'],
                    $nodes,
                    '/(?:project|event)/',
                    ['project_code', 'event_code', 'code', 'project_number', 'event_number']
                );

                $product = $this->collectProducts($nodes);

                $eventProject = $this->directOrNode(
                    $row,
                    ['event_name', 'project_name'],
                    $nodes,
                    '/(?:event|project|deal|quote)/',
                    ['event_name', 'project_name', 'name', 'title', 'subject']
                );

                $eventDate = $this->directOrNode(
                    $row,
                    ['event_date', 'project_date'],
                    $nodes,
                    '/(?:event|project)/',
                    ['event_date', 'project_date', 'start_date', 'starts_at', 'date', 'event_start']
                );

                $expenseDate = $this->firstValue($row, [
                    'expense_date', 'transaction_date', 'spent_at', 'paid_at', 'date', 'created_at',
                ]);

                $expenseName = $this->expenseName($row, $nodes);
                $amount = $this->firstValue($row, ['amount', 'expense_amount', 'total', 'cost', 'value']);
                $note = $this->firstValue($row, ['note', 'notes', 'description', 'remark', 'remarks', 'memo']);
                $receipts = $this->collectReceipts($row, $nodes);
                $createdBy = $this->creator($row, $nodes);
                $createdAt = $this->firstValue($row, ['created_at']);

                fputcsv($out, [
                    $this->safeCell($invoiceNumber),
                    $this->safeCell($projectCode),
                    $this->safeCell($product),
                    $this->safeCell($eventProject),
                    $this->safeCell($eventDate),
                    $this->safeCell($expenseDate),
                    $this->safeCell($expenseName),
                    $amount,
                    $this->safeCell($note),
                    $this->safeCell($receipts),
                    $this->safeCell($createdBy),
                    $this->safeCell($createdAt),
                ]);
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }

    private function graph(string $startTable, array $startRow, int $maxDepth): array
    {
        $nodes = [];
        $queue = [[
            'table' => $startTable,
            'row' => $startRow,
            'depth' => 0,
        ]];
        $seen = [];

        while ($queue) {
            $current = array_shift($queue);
            $table = $current['table'];
            $row = $current['row'];
            $depth = $current['depth'];
            $pk = $this->primaryKey($table);
            $pkValue = $pk !== null ? ($row[$pk] ?? null) : null;
            $fingerprint = $table . ':' . ($pkValue ?? md5(json_encode($row)));

            if (isset($seen[$fingerprint])) {
                continue;
            }

            $seen[$fingerprint] = true;
            $nodes[] = ['table' => $table, 'row' => $row, 'depth' => $depth];

            if ($depth >= $maxDepth) {
                continue;
            }

            foreach ($this->foreignKeysOut($table) as $fk) {
                $value = $row[$fk['column']] ?? null;
                if ($value === null || $value === '') continue;

                $related = $this->fetchOne($fk['ref_table'], $fk['ref_column'], $value);
                if ($related !== null) {
                    $queue[] = [
                        'table' => $fk['ref_table'],
                        'row' => $related,
                        'depth' => $depth + 1,
                    ];
                }
            }

            if ($pk !== null && $pkValue !== null) {
                foreach ($this->foreignKeysIn($table) as $fk) {
                    if (!$this->reverseTableRelevant($fk['table'])) {
                        continue;
                    }

                    try {
                        $children = DB::table($fk['table'])
                            ->where($fk['column'], $pkValue)
                            ->limit(20)
                            ->get();
                    } catch (Throwable) {
                        continue;
                    }

                    foreach ($children as $child) {
                        $queue[] = [
                            'table' => $fk['table'],
                            'row' => (array) $child,
                            'depth' => $depth + 1,
                        ];
                    }
                }
            }
        }

        return $nodes;
    }

    private function columns(string $table): array
    {
        return $this->columnCache[$table] ??= Schema::getColumnListing($table);
    }

    private function primaryKey(string $table): ?string
    {
        if (array_key_exists($table, $this->pkCache)) {
            return $this->pkCache[$table];
        }

        try {
            $rows = DB::select(
                'SELECT COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE '
                . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? '
                . "AND CONSTRAINT_NAME = 'PRIMARY' ORDER BY ORDINAL_POSITION LIMIT 1",
                [$table]
            );
            $pk = isset($rows[0]) ? (string) $rows[0]->COLUMN_NAME : null;
        } catch (Throwable) {
            $pk = in_array('id', $this->columns($table), true) ? 'id' : null;
        }

        return $this->pkCache[$table] = $pk;
    }

    private function foreignKeysOut(string $table): array
    {
        if (isset($this->fkOutCache[$table])) {
            return $this->fkOutCache[$table];
        }

        try {
            $rows = DB::select(
                'SELECT COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME '
                . 'FROM information_schema.KEY_COLUMN_USAGE '
                . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? '
                . 'AND REFERENCED_TABLE_NAME IS NOT NULL',
                [$table]
            );
            $result = array_map(static fn ($r) => [
                'column' => (string) $r->COLUMN_NAME,
                'ref_table' => (string) $r->REFERENCED_TABLE_NAME,
                'ref_column' => (string) $r->REFERENCED_COLUMN_NAME,
            ], $rows);
        } catch (Throwable) {
            $result = [];
        }

        if (!$result) {
            foreach ($this->columns($table) as $column) {
                if (!str_ends_with($column, '_id')) continue;
                $stem = substr($column, 0, -3);
                foreach ([$stem . 's', $stem] as $candidate) {
                    if (Schema::hasTable($candidate)) {
                        $result[] = [
                            'column' => $column,
                            'ref_table' => $candidate,
                            'ref_column' => 'id',
                        ];
                        break;
                    }
                }
            }
        }

        return $this->fkOutCache[$table] = $result;
    }

    private function foreignKeysIn(string $table): array
    {
        if (isset($this->fkInCache[$table])) {
            return $this->fkInCache[$table];
        }

        try {
            $rows = DB::select(
                'SELECT TABLE_NAME, COLUMN_NAME, REFERENCED_COLUMN_NAME '
                . 'FROM information_schema.KEY_COLUMN_USAGE '
                . 'WHERE TABLE_SCHEMA = DATABASE() AND REFERENCED_TABLE_NAME = ?',
                [$table]
            );
            $result = array_map(static fn ($r) => [
                'table' => (string) $r->TABLE_NAME,
                'column' => (string) $r->COLUMN_NAME,
                'ref_column' => (string) $r->REFERENCED_COLUMN_NAME,
            ], $rows);
        } catch (Throwable) {
            $result = [];
        }

        return $this->fkInCache[$table] = $result;
    }

    private function fetchOne(string $table, string $column, mixed $value): ?array
    {
        $key = $table . '|' . $column . '|' . (string) $value;
        if (array_key_exists($key, $this->rowCache)) {
            return $this->rowCache[$key];
        }

        try {
            $row = DB::table($table)->where($column, $value)->first();
            return $this->rowCache[$key] = $row ? (array) $row : null;
        } catch (Throwable) {
            return $this->rowCache[$key] = null;
        }
    }

    private function reverseTableRelevant(string $table): bool
    {
        return preg_match('/(?:invoice|quote|event|project|deal|product|item|receipt|attachment|image|media|file|category|user)/i', $table) === 1;
    }

    private function firstValue(array $row, array $columns): string
    {
        foreach ($columns as $column) {
            if (array_key_exists($column, $row) && $row[$column] !== null && $row[$column] !== '') {
                return $this->scalar($row[$column]);
            }
        }
        return '';
    }

    private function directOrNode(array $row, array $directColumns, array $nodes, string $tablePattern, array $columns): string
    {
        $direct = $this->firstValue($row, $directColumns);
        if ($direct !== '') return $direct;

        foreach ($nodes as $node) {
            if (preg_match($tablePattern, $node['table']) !== 1) continue;
            $value = $this->firstValue($node['row'], $columns);
            if ($value !== '') return $value;
        }

        return '';
    }

    private function expenseName(array $row, array $nodes): string
    {
        $parts = [];
        foreach (['expense_name', 'name', 'title', 'category', 'type'] as $column) {
            $value = $this->firstValue($row, [$column]);
            if ($value !== '' && !in_array($value, $parts, true)) $parts[] = $value;
        }

        foreach ($nodes as $node) {
            if (preg_match('/category/i', $node['table']) !== 1) continue;
            $value = $this->firstValue($node['row'], ['name', 'title', 'category']);
            if ($value !== '' && !in_array($value, $parts, true)) $parts[] = $value;
        }

        if (!$parts) {
            $fallback = $this->firstValue($row, ['description', 'note']);
            if ($fallback !== '') $parts[] = $fallback;
        }

        return implode(' / ', $parts);
    }

    private function collectProducts(array $nodes): string
    {
        $products = [];
        foreach ($nodes as $node) {
            if (preg_match('/product/i', $node['table']) !== 1) continue;
            $value = $this->firstValue($node['row'], ['product_name', 'name', 'title', 'sku']);
            if ($value !== '' && !in_array($value, $products, true)) $products[] = $value;
        }
        return implode(' | ', $products);
    }

    private function collectReceipts(array $expense, array $nodes): string
    {
        $values = [];
        $collect = function (array $row) use (&$values): void {
            foreach ($row as $column => $value) {
                if ($value === null || $value === '') continue;
                if (preg_match('/(?:receipt|image|photo|attachment|file|path|url)/i', (string) $column) !== 1) continue;
                if (preg_match('/(?:id|size|mime|type)$/i', (string) $column)) continue;

                $text = $this->scalar($value);
                if ($text === '') continue;

                foreach (preg_split('/\\s*[|,]\\s*/', $text) ?: [$text] as $piece) {
                    $piece = trim($piece);
                    if ($piece === '') continue;
                    $normalized = $this->receiptUrl($piece);
                    if (!in_array($normalized, $values, true)) $values[] = $normalized;
                }
            }
        };

        $collect($expense);

        foreach ($nodes as $node) {
            if (preg_match('/(?:receipt|attachment|image|media|file)/i', $node['table']) === 1) {
                $collect($node['row']);
            }
        }

        return implode(' | ', $values);
    }

    private function receiptUrl(string $value): string
    {
        if (preg_match('~^(?:https?://|mailto:|tel:)~i', $value)) return $value;
        $clean = str_replace('\\\\', '/', $value);
        $clean = ltrim($clean, '/');

        if (str_starts_with($clean, 'public/')) {
            $clean = substr($clean, 7);
        }

        if (str_starts_with($clean, 'storage/')) {
            return url('/' . $clean);
        }

        return url('/storage/' . $clean);
    }

    private function creator(array $expense, array $nodes): string
    {
        foreach ($nodes as $node) {
            if (preg_match('/(?:users?|admins?)/i', $node['table']) !== 1) continue;
            $row = $node['row'];
            $name = $this->firstValue($row, ['name', 'full_name']);
            if ($name === '') {
                $first = $this->firstValue($row, ['first_name']);
                $last = $this->firstValue($row, ['last_name']);
                $name = trim($first . ' ' . $last);
            }
            if ($name === '') $name = $this->firstValue($row, ['email']);
            if ($name !== '') return $name;
        }

        return $this->firstValue($expense, ['created_by_name', 'created_by', 'user_id']);
    }

    private function scalar(mixed $value): string
    {
        if (is_bool($value)) return $value ? '1' : '0';
        if (is_scalar($value)) return trim((string) $value);
        return '';
    }

    private function safeCell(string $value): string
    {
        $value = trim($value);
        if ($value !== '' && preg_match('/^[=+@]/', $value)) {
            return "'" . $value;
        }
        return $value;
    }
}
PHP;

    $controller = str_replace('__EXPENSE_TABLE__', $expenseTableLiteral, $controller);
    atomicWrite($controllerPath, $controller);

    /* Patch route into routes/web.php using copied middleware. */
    $web = file_get_contents($webRoutes);
    if (!is_string($web)) throw new RuntimeException('Gagal membaca routes/web.php.');

    $web = removeMarkedBlock($web, $markerRouteStart, $markerRouteEnd);

    if (!str_contains($web, 'use Illuminate\\Support\\Facades\\Route;')) {
        $web = preg_replace('/^<\\?php\\s*/', "<?php\n\nuse Illuminate\\Support\\Facades\\Route;\n", $web, 1, $useCount);
        if (!is_string($web) || $useCount !== 1) {
            throw new RuntimeException('Gagal menambahkan Route facade ke routes/web.php.');
        }
    }

    $middlewareLiteral = phpArray($existingMiddleware);
    $routeBlock = "\n\n{$markerRouteStart}\n"
        . "Route::get(" . var_export($newUri, true) . ", \\App\\Http\\Controllers\\AllExpensesExportController::class)\n"
        . "    ->middleware({$middlewareLiteral})\n"
        . "    ->name(" . var_export($newRouteName, true) . ");\n"
        . "{$markerRouteEnd}\n";

    $web = rtrim($web) . $routeBlock;
    atomicWrite($webRoutes, $web);

    /* Patch Financial Report button. */
    $patchedView = addButtonToView($viewSource, $newRouteName, $markerView);
    atomicWrite($viewPath, $patchedView);

    /* Clear caches. */
    chdir($root);
    passthru(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($root . '/artisan') . ' optimize:clear');

    echo "\nPATCH PASS.\n";
    echo "- Route baru : {$newRouteName}\n";
    echo "- URI baru   : /{$newUri}\n";
    echo "- Expense DB : {$expenseTable}\n";
    echo "- View       : {$viewPath}\n";
    echo "- CSV        : 12 kolom sesuai requirement.\n";
    echo "- 1 row CSV  : 1 expense.\n";
    echo "- Multiple receipt/image digabung dengan |.\n";
    echo "- Existing Export Financial Report tidak diubah logic-nya.\n\n";
    echo "Jalankan checker:\n";
    echo "php tools/check_financial_report_export_all_expenses_v1.php\n";
} catch (Throwable $e) {
    foreach ($backups as $original => $backup) {
        @copy($backup, $original);
    }

    if (!isset($backups[$controllerPath]) && is_file($controllerPath)) {
        @unlink($controllerPath);
    }

    fwrite(STDERR, "\nPATCH GAGAL: {$e->getMessage()}\n");
    fwrite(STDERR, "File yang sempat berubah dipulihkan dari backup.\n");
    exit(1);
}
