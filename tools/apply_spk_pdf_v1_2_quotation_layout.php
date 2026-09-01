<?php

/*
|--------------------------------------------------------------------------
| SPK PDF V1.2 - Quotation Layout Hotfix
|--------------------------------------------------------------------------
|
| Changes ONLY:
| packages/Webkul/Admin/src/Resources/views/work-orders/print.blade.php
|
| It intentionally follows the existing Quotation PDF visual language.
| No migration.
| No controller.
| No routes.
|
*/

$projectRoot =
    realpath(
        __DIR__.'/..'
    );

if (! $projectRoot) {
    fwrite(
        STDERR,
        "Project root tidak ditemukan.\n"
    );

    exit(1);
}

$target =
    $projectRoot
    .'/packages/Webkul/Admin/src/Resources/views/work-orders/print.blade.php';

$template =
    __DIR__
    .'/templates/work-order-print-v1-2.blade.php';

if (! is_file($target)) {
    fwrite(
        STDERR,
        "SPK print Blade tidak ditemukan: {$target}\n"
    );

    exit(2);
}

if (! is_file($template)) {
    fwrite(
        STDERR,
        "Template V1.2 tidak ditemukan: {$template}\n"
    );

    exit(3);
}

$current =
    file_get_contents(
        $target
    );

if (
    $current === false
    || ! str_contains(
        $current,
        'SURAT PERINTAH KERJA'
    )
    || ! str_contains(
        $current,
        '$workOrder'
    )
) {
    fwrite(
        STDERR,
        "Current SPK print Blade tidak dikenali. "
        ."Patch dihentikan agar tidak menimpa file yang salah.\n"
    );

    exit(4);
}

$new =
    file_get_contents(
        $template
    );

if (
    $new === false
    || ! str_contains(
        $new,
        'SPK PDF V1.2'
    )
    || ! str_contains(
        $new,
        'Member of Rental Indonesia.'
    )
) {
    fwrite(
        STDERR,
        "Template SPK V1.2 gagal validasi.\n"
    );

    exit(5);
}

if (
    str_contains(
        $current,
        'SPK PDF V1.2'
    )
) {
    echo "[SKIP] SPK PDF V1.2 already installed.\n";

    exit(0);
}

$backup =
    $target
    .'.before-spk-pdf-v1-2.bak';

if (! is_file($backup)) {
    if (! copy($target, $backup)) {
        fwrite(
            STDERR,
            "Gagal membuat backup SPK print Blade.\n"
        );

        exit(6);
    }
}

if (
    file_put_contents(
        $target,
        $new
    ) === false
) {
    fwrite(
        STDERR,
        "Gagal menulis SPK print Blade V1.2.\n"
    );

    exit(7);
}

echo "[PASS] SPK PDF V1.2 quotation-style KOP installed.\n";
echo "[PASS] Bill To + expanded project detail installed.\n";
echo "[PASS] Product/Service remains NAME ONLY.\n";
echo "[PASS] 3-signature spacing improved.\n";
echo "[PASS] Quotation-style fixed footer installed.\n";
echo "[PASS] No migration.\n";
