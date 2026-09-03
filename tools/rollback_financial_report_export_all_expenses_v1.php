<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$targets = [
    $root . '/routes/web.php',
    $root . '/app/Http/Controllers/AllExpensesExportController.php',
];

function latestBackup(string $target): ?string
{
    $files = glob($target . '.bak-financial-expenses-export-v1-*') ?: [];
    if (!$files) return null;
    usort($files, static fn ($a, $b) => (filemtime($b) ?: 0) <=> (filemtime($a) ?: 0));
    return $files[0] ?? null;
}

foreach ($targets as $target) {
    $backup = latestBackup($target);
    if ($backup) {
        copy($backup, $target);
        echo "Restored: {$target}\n";
    } elseif (str_ends_with($target, 'AllExpensesExportController.php') && is_file($target)) {
        unlink($target);
        echo "Removed generated controller: {$target}\n";
    }
}

$searchRoots = [
    $root . '/packages/Webkul/Admin/src/Resources/views',
    $root . '/resources/views',
];
foreach ($searchRoots as $searchRoot) {
    if (!is_dir($searchRoot)) continue;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($searchRoot, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $file) {
        if (!$file->isFile() || !str_ends_with(strtolower($file->getFilename()), '.blade.php')) continue;
        $path = $file->getPathname();
        $content = @file_get_contents($path);
        if (!is_string($content) || !str_contains($content, 'FINANCIAL REPORT EXPORT ALL EXPENSES V1')) continue;

        $backup = latestBackup($path);
        if ($backup) {
            copy($backup, $path);
            echo "Restored: {$path}\n";
        }
    }
}

chdir($root);
passthru(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($root . '/artisan') . ' optimize:clear');
echo "\nROLLBACK SELESAI.\n";
