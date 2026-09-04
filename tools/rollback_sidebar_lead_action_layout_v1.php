<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$backupBase = $root.'/storage/app/crm-patch-backups/sidebar-lead-action-layout-v1';

echo "ROLLBACK SIDEBAR + LEAD FLOATING ACTION LAYOUT V1\n";
echo "=================================================\n\n";

$backupDirs = glob($backupBase.'/*', GLOB_ONLYDIR) ?: [];
rsort($backupDirs, SORT_STRING);
$backupRoot = $backupDirs[0] ?? null;

if (! $backupRoot) {
    fwrite(STDERR, "Backup tidak ditemukan: {$backupBase}\n");
    exit(1);
}

$relatives = [
    'packages/Webkul/Admin/src/Config/menu.php',
    'packages/Webkul/Admin/src/Resources/views/components/layouts/sidebar/desktop/index.blade.php',
    'packages/Webkul/Admin/src/Resources/views/leads/view.blade.php',
    'packages/Webkul/Admin/src/Resources/views/lead-commercial-workflow/action-widget.blade.php',
];

foreach ($relatives as $relative) {
    $source = $backupRoot.'/'.$relative;
    $target = $root.'/'.$relative;

    if (! is_file($source)) {
        fwrite(STDERR, "Backup tidak lengkap: {$source}\n");
        exit(1);
    }

    if (! copy($source, $target)) {
        fwrite(STDERR, "Gagal restore: {$target}\n");
        exit(1);
    }

    echo "[OK] {$relative}\n";
}

chdir($root);
passthru(escapeshellarg(PHP_BINARY).' artisan optimize:clear', $clearCode);

echo "\nRollback selesai dari backup: {$backupRoot}\n";
exit($clearCode === 0 ? 0 : 1);

