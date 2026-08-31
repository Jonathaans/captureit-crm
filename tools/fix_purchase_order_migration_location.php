<?php

$projectRoot = realpath(__DIR__.'/..');

if (! $projectRoot) {
    fwrite(STDERR, "Project root tidak ditemukan.\n");
    exit(1);
}

$correct = $projectRoot.DIRECTORY_SEPARATOR.'database'
    .DIRECTORY_SEPARATOR.'migrations'
    .DIRECTORY_SEPARATOR.'2026_08_31_160000_create_purchase_orders_tables.php';

$wrong = $projectRoot.DIRECTORY_SEPARATOR.'packages'
    .DIRECTORY_SEPARATOR.'Webkul'
    .DIRECTORY_SEPARATOR.'Invoice'
    .DIRECTORY_SEPARATOR.'src'
    .DIRECTORY_SEPARATOR.'Database'
    .DIRECTORY_SEPARATOR.'Migrations'
    .DIRECTORY_SEPARATOR.'2026_08_31_160000_create_purchase_orders_tables.php';

if (! is_file($correct)) {
    fwrite(
        STDERR,
        "FAIL: migration yang benar belum ada di database/migrations.\n"
    );

    exit(2);
}

echo "[PASS] Migration Laravel ditemukan:\n";
echo "       {$correct}\n";

if (is_file($wrong)) {
    $backup = $wrong.'.misplaced.bak';

    if (! is_file($backup)) {
        copy($wrong, $backup);
    }

    unlink($wrong);

    echo "[PASS] Migration yang salah lokasi di package dihapus.\n";
    echo "       Backup: {$backup}\n";
} else {
    echo "[SKIP] Migration salah lokasi sudah tidak ada.\n";
}

echo "\n";
echo "Sekarang jalankan:\n";
echo "php artisan migrate:status\n";
echo "php artisan migrate\n";
