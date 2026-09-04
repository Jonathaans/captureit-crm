<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$manifestPath = $root.'/storage/app/internal_chat_newest_hotfix_v1_1_manifest.json';

echo "ROLLBACK INTERNAL CHAT NEWEST HOTFIX V1.1\n";
echo "==========================================\n\n";

if (! is_file($manifestPath)) {
    fwrite(STDERR, "Manifest tidak ditemukan: {$manifestPath}\n");
    exit(1);
}

$manifest = json_decode((string) file_get_contents($manifestPath), true);

if (! is_array($manifest) || ! is_array($manifest['files'] ?? null)) {
    fwrite(STDERR, "Manifest tidak valid.\n");
    exit(1);
}

$failed = 0;

foreach (array_reverse($manifest['files']) as $file) {
    $path = (string) ($file['path'] ?? '');
    $backup = (string) ($file['backup'] ?? '');

    if ($path === '' || $backup === '' || ! is_file($backup)) {
        echo "[FAIL] Backup tidak ditemukan untuk {$path}\n";
        $failed++;
        continue;
    }

    if (copy($backup, $path)) {
        echo "[RESTORE] {$path}\n";
    } else {
        echo "[FAIL] {$path}\n";
        $failed++;
    }
}

if (is_file($root.'/artisan')) {
    chdir($root);
    passthru(
        escapeshellarg(PHP_BINARY).' '.escapeshellarg($root.'/artisan').' optimize:clear',
        $clearCode
    );
}

if ($failed > 0) {
    fwrite(STDERR, "\nRollback selesai dengan {$failed} kegagalan.\n");
    exit(1);
}

echo "\nROLLBACK SELESAI.\n";

