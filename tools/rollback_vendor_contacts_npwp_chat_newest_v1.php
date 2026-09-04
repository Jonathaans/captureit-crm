<?php

declare(strict_types=1);

/**
 * Conservative source rollback.
 * Database column/data and uploaded NPWP files are intentionally preserved.
 */

$root = dirname(__DIR__);
$manifestPath = $root.'/storage/app/vendor_contacts_npwp_chat_newest_v1_manifest.json';

echo "ROLLBACK VENDOR CONTACTS + NPWP IMAGE + CHAT NEWEST V1\n";
echo "========================================================\n\n";

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
    $created = (bool) ($file['created'] ?? false);

    if ($path === '') {
        continue;
    }

    if ($backup !== '' && is_file($backup)) {
        if (copy($backup, $path)) {
            echo "[RESTORE] {$path}\n";
        } else {
            echo "[FAIL] {$path}\n";
            $failed++;
        }
    } elseif ($created && is_file($path)) {
        if (unlink($path)) {
            echo "[REMOVE] {$path}\n";
        } else {
            echo "[FAIL] {$path}\n";
            $failed++;
        }
    }
}

if ($failed > 0) {
    fwrite(STDERR, "\nRollback selesai dengan {$failed} kegagalan.\n");
    exit(1);
}

chdir($root);
passthru(
    escapeshellarg(PHP_BINARY).' '.escapeshellarg($root.'/artisan').' optimize:clear',
    $clearCode
);

echo "\nROLLBACK SOURCE SELESAI.\n";
echo "Kolom/data NPWP serta file upload tidak dihapus untuk mencegah kehilangan data.\n";

