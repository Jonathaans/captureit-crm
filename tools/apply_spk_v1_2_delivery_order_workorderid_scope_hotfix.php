<?php

/*
|--------------------------------------------------------------------------
| SPK V1.2 - DeliveryOrder $workOrderId Scope Hotfix
|--------------------------------------------------------------------------
|
| Error:
| Undefined variable $workOrderId
|
| Cause:
| SPK V1 added $workOrderId to DeliveryOrderService::createFromInvoice(),
| then references it inside DB::transaction(), but the transaction closure
| still only imported:
|
| use ($invoice, $createdBy)
|
| Fix:
| use ($invoice, $createdBy, $workOrderId)
|
| Surgical patch only.
| No migration.
|
*/

$projectRoot = realpath(__DIR__.'/..');

if (! $projectRoot) {
    fwrite(STDERR, "Project root tidak ditemukan.\n");
    exit(1);
}

$path =
    $projectRoot
    .'/packages/Webkul/Invoice/src/Services/DeliveryOrderService.php';

if (! is_file($path)) {
    fwrite(
        STDERR,
        "DeliveryOrderService.php tidak ditemukan.\n"
    );
    exit(2);
}

$source = file_get_contents($path);

if ($source === false) {
    fwrite(
        STDERR,
        "DeliveryOrderService.php tidak dapat dibaca.\n"
    );
    exit(3);
}

/*
|--------------------------------------------------------------------------
| Guard exact SPK V1 installation
|--------------------------------------------------------------------------
*/

if (
    ! str_contains(
        $source,
        'SPK WORK ORDER V1 GUARD'
    )
    || ! str_contains(
        $source,
        'SPK WORK ORDER V1 LINK'
    )
    || ! str_contains(
        $source,
        '?int $workOrderId = null'
    )
) {
    fwrite(
        STDERR,
        "SPK V1 DeliveryOrderService marker tidak lengkap. "
        ."Patch dihentikan tanpa mengubah file.\n"
    );
    exit(4);
}

/*
|--------------------------------------------------------------------------
| Idempotent
|--------------------------------------------------------------------------
*/

$fixedMultiline = <<<'PHP'
            function () use (
                $invoice,
                $createdBy,
                $workOrderId
            ) {
PHP;

$fixedSingleLine =
    'function () use ($invoice, $createdBy, $workOrderId) {';

if (
    str_contains(
        $source,
        $fixedMultiline
    )
    || str_contains(
        $source,
        $fixedSingleLine
    )
) {
    echo "[SKIP] workOrderId sudah masuk transaction closure scope.\n";
    exit(0);
}

/*
|--------------------------------------------------------------------------
| Current customized multi-SJ format
|--------------------------------------------------------------------------
*/

$oldMultiline = <<<'PHP'
            function () use (
                $invoice,
                $createdBy
            ) {
PHP;

$count =
    substr_count(
        $source,
        $oldMultiline
    );

if ($count === 1) {
    $new =
        str_replace(
            $oldMultiline,
            $fixedMultiline,
            $source,
            $replaceCount
        );

    if ($replaceCount !== 1) {
        fwrite(
            STDERR,
            "Gagal patch transaction closure multiline.\n"
        );
        exit(5);
    }
} else {
    /*
    |--------------------------------------------------------------------------
    | Safe fallback for compact formatting
    |--------------------------------------------------------------------------
    */

    $oldSingleLine =
        'function () use ($invoice, $createdBy) {';

    $singleCount =
        substr_count(
            $source,
            $oldSingleLine
        );

    if ($singleCount !== 1) {
        fwrite(
            STDERR,
            "Transaction closure target tidak unik / format tidak dikenali. "
            ."Multiline target={$count}; compact target={$singleCount}. "
            ."Patch dihentikan agar tidak mengubah closure yang salah.\n"
        );
        exit(6);
    }

    $new =
        str_replace(
            $oldSingleLine,
            $fixedSingleLine,
            $source,
            $replaceCount
        );

    if ($replaceCount !== 1) {
        fwrite(
            STDERR,
            "Gagal patch transaction closure compact.\n"
        );
        exit(7);
    }
}

/*
|--------------------------------------------------------------------------
| Validate BEFORE write
|--------------------------------------------------------------------------
*/

$linkPos =
    strpos(
        $new,
        'SPK WORK ORDER V1 LINK'
    );

$scopePos =
    strpos(
        $new,
        '$workOrderId'
    );

if (
    $linkPos === false
    || $scopePos === false
    || ! (
        str_contains(
            $new,
            $fixedMultiline
        )
        || str_contains(
            $new,
            $fixedSingleLine
        )
    )
) {
    fwrite(
        STDERR,
        "Hasil patch gagal validasi. File tidak ditulis.\n"
    );
    exit(8);
}

$backup =
    $path
    .'.before-spk-v1-2-workorderid-scope.bak';

if (! is_file($backup)) {
    if (! copy($path, $backup)) {
        fwrite(
            STDERR,
            "Gagal membuat backup DeliveryOrderService.php.\n"
        );
        exit(9);
    }
}

if (
    file_put_contents(
        $path,
        $new
    ) === false
) {
    fwrite(
        STDERR,
        "Gagal menulis DeliveryOrderService.php.\n"
    );
    exit(10);
}

echo "[PASS] \$workOrderId ditambahkan ke DB::transaction closure scope.\n";
echo "[PASS] SPK -> Surat Jalan sekarang dapat menyimpan work_order_id.\n";
echo "[PASS] Existing multi-SJ logic tetap dipertahankan.\n";
echo "[PASS] Tidak ada migration.\n";
