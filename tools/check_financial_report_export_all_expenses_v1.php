<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

$root = dirname(__DIR__);
$webRoutes = $root . '/routes/web.php';
$controllerPath = $root . '/app/Http/Controllers/AllExpensesExportController.php';

require $root . '/vendor/autoload.php';
$app = require $root . '/bootstrap/app.php';
$app->make(ConsoleKernel::class)->bootstrap();

echo "CHECK FINANCIAL REPORT EXPORT ALL EXPENSES V1\n";
echo "==============================================\n\n";

$web = is_file($webRoutes) ? file_get_contents($webRoutes) : '';
$controller = is_file($controllerPath) ? file_get_contents($controllerPath) : '';

$route = null;
foreach (Route::getRoutes() as $candidate) {
    $name = (string) $candidate->getName();
    if (str_contains($name, 'expenses') && str_contains(strtolower($name), 'financial')) {
        $route = $candidate;
        break;
    }
}

$checks = [
    'Controller tersedia' => $controller !== '',
    'Route marker tersedia' => str_contains($web, 'FINANCIAL REPORT EXPORT ALL EXPENSES V1 START'),
    'Route expenses terdaftar' => $route !== null,
    'CSV header Invoice Number' => str_contains($controller, "'Invoice Number'"),
    'CSV header Project Code' => str_contains($controller, "'Project Code'"),
    'CSV header Product' => str_contains($controller, "'Product'"),
    'CSV header Event / Project' => str_contains($controller, "'Event / Project'"),
    'CSV header Event Date' => str_contains($controller, "'Event Date'"),
    'CSV header Expense Date' => str_contains($controller, "'Expense Date'"),
    'CSV header Expense Name / Category' => str_contains($controller, "'Expense Name / Category'"),
    'CSV header Amount' => str_contains($controller, "'Amount'"),
    'CSV header Note' => str_contains($controller, "'Note'"),
    'CSV header Image / Receipt' => str_contains($controller, "'Image / Receipt'"),
    'CSV header Created By' => str_contains($controller, "'Created By'"),
    'CSV header Created At' => str_contains($controller, "'Created At'"),
    'UTF-8 BOM tersedia' => str_contains($controller, '\\xEF\\xBB\\xBF'),
    'CSV injection guard tersedia' => str_contains($controller, 'safeCell'),
    'Receipt URL resolver tersedia' => str_contains($controller, 'receiptUrl'),
    'Graph FK resolver tersedia' => str_contains($controller, 'information_schema.KEY_COLUMN_USAGE'),
];

$viewFound = false;
$itRoots = [
    $root . '/packages/Webkul/Admin/src/Resources/views',
    $root . '/resources/views',
];
foreach ($itRoots as $searchRoot) {
    if (!is_dir($searchRoot)) continue;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($searchRoot, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $file) {
        if (!$file->isFile() || !str_ends_with(strtolower($file->getFilename()), '.blade.php')) continue;
        $source = @file_get_contents($file->getPathname());
        if (is_string($source) && str_contains($source, 'FINANCIAL REPORT EXPORT ALL EXPENSES V1')) {
            $viewFound = true;
            echo "View patched: " . $file->getPathname() . "\n";
            break 2;
        }
    }
}
$checks['Tombol Financial Report terpasang'] = $viewFound;

$failed = [];
foreach ($checks as $label => $ok) {
    echo ($ok ? '[OK]   ' : '[FAIL] ') . $label . PHP_EOL;
    if (!$ok) $failed[] = $label;
}

if ($route !== null) {
    echo "\nRoute:\n";
    echo 'name       : ' . $route->getName() . PHP_EOL;
    echo 'uri        : /' . $route->uri() . PHP_EOL;
    echo 'middleware : ' . implode(', ', $route->gatherMiddleware()) . PHP_EOL;
}

echo PHP_EOL;
if ($failed) {
    echo "HASIL: FAIL\n";
    exit(1);
}

echo "HASIL: PASS\n";
echo "\nQA browser:\n";
echo "1. Buka Financial Report.\n";
echo "2. Pastikan tombol Export All Expenses terlihat di sebelah export existing.\n";
echo "3. Klik tombol tersebut.\n";
echo "4. CSV harus terdownload dengan 12 kolom.\n";
echo "5. Cocokkan beberapa expense event: amount, note, receipt/image.\n";
