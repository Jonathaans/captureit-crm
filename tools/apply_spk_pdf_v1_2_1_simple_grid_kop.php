<?php

/*
|--------------------------------------------------------------------------
| SPK PDF V1.2.1 - Simple Grid + KOP
|--------------------------------------------------------------------------
|
| User requested:
| Keep the simple SPK detail grid exactly as before,
| only add the Quotation-style KOP.
|
| Changes ONLY:
| packages/Webkul/Admin/src/Resources/views/work-orders/print.blade.php
|
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
    .'/templates/work-order-print-v1-2-1.blade.php';

if (! is_file($target)) {
    fwrite(
        STDERR,
        "SPK print Blade tidak ditemukan.\n"
    );

    exit(2);
}

if (! is_file($template)) {
    fwrite(
        STDERR,
        "Template V1.2.1 tidak ditemukan.\n"
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
        '$workOrder'
    )
    || ! str_contains(
        $current,
        'SURAT PERINTAH KERJA'
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
        'SPK PDF V1.2.1'
    )
    || ! str_contains(
        $new,
        'Project / Event'
    )
) {
    fwrite(
        STDERR,
        "Template V1.2.1 gagal validasi.\n"
    );

    exit(5);
}

if (
    str_contains(
        $current,
        'SPK PDF V1.2.1'
    )
) {
    echo "[SKIP] SPK PDF V1.2.1 already installed.\n";
    exit(0);
}

$backup =
    $target
    .'.before-spk-pdf-v1-2-1.bak';

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
        "Gagal menulis SPK PDF V1.2.1.\n"
    );

    exit(7);
}

echo "[PASS] SPK PDF simple grid restored.\n";
echo "[PASS] Quotation-style KOP added.\n";
echo "[PASS] Product/Service remains NAME ONLY.\n";
echo "[PASS] Notes preserved.\n";
echo "[PASS] 3-signature layout preserved and spaced.\n";
echo "[PASS] No migration.\n";
