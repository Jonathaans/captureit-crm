<?php

declare(strict_types=1);

/**
 * VENDOR CREATE/EDIT + PO SYNC HOTFIX V1.1
 *
 * Fixes:
 * 1. Repairs form.blade.php corrupted by the previous multipart insertion.
 * 2. Places enctype on the actual <form> attribute line.
 * 3. Compiles all Blade views before declaring success.
 * 4. Always resolves vendor_name from a PO into Vendor Master, even when an
 *    old vendor_id is still present on the Purchase Order.
 *
 * Run:
 * php tools/apply_vendor_create_edit_po_sync_hotfix_v1_1.php
 */

$root = dirname(__DIR__);
$stamp = date('Ymd-His');
$suffix = '.bak-vendor-create-edit-po-sync-v1_1-'.$stamp;

$formPath = $root.'/packages/Webkul/Admin/src/Resources/views/vendors/form.blade.php';
$providerPath = $root.'/packages/Webkul/Admin/src/Providers/CrmOperationsServiceProvider.php';
$syncServicePath = $root.'/packages/Webkul/Admin/src/Services/VendorSyncService.php';
$poControllerPath = $root.'/packages/Webkul/Admin/src/Http/Controllers/PurchaseOrder/PurchaseOrderController.php';
$manifestPath = $root.'/storage/app/vendor_create_edit_po_sync_hotfix_v1_1_manifest.json';

function failHotfix(string $message, int $code = 1): never
{
    fwrite(STDERR, $message.PHP_EOL);
    exit($code);
}

function atomicWriteHotfix(string $path, string $contents): void
{
    $temporary = $path.'.tmp-'.bin2hex(random_bytes(4));

    if (file_put_contents($temporary, $contents) === false) {
        @unlink($temporary);
        throw new RuntimeException("Gagal menulis temporary file: {$temporary}");
    }

    if (! rename($temporary, $path)) {
        @unlink($temporary);
        throw new RuntimeException("Gagal mengganti file: {$path}");
    }
}

function commandHotfix(string $command): array
{
    exec($command.' 2>&1', $output, $code);

    return [$code, implode(PHP_EOL, $output)];
}

function repairVendorFormHotfix(string $source): string
{
    /*
     * Exact corruption produced by V1:
     *
     * $vendor-
     *     enctype="multipart/form-data">exists
     *
     * The old regex treated the > in PHP's -> operator as the end of the
     * HTML form tag.
     */
    $source = preg_replace(
        '~\\$vendor-\\s*enctype="multipart/form-data"\\s*>\\s*exists~',
        '$vendor->exists',
        $source,
        -1,
        $repairedCount
    );

    if (! is_string($source)) {
        throw new RuntimeException('Regex repair Vendor form gagal.');
    }

    if ($repairedCount > 1) {
        throw new RuntimeException(
            "Korupsi enctype ditemukan {$repairedCount} kali; hotfix dibatalkan agar tidak menebak."
        );
    }

    if (! str_contains(
        $source,
        "action=\"{{ \$vendor->exists ? route('admin.vendors.update', \$vendor->id) : route('admin.vendors.store') }}\""
    )) {
        throw new RuntimeException(
            'Action Create/Edit Vendor belum kembali ke ekspresi Blade yang valid.'
        );
    }

    /* Remove duplicate standalone enctype lines, then add exactly one. */
    $source = preg_replace(
        '~^[ \\t]*enctype="multipart/form-data"[ \\t]*\\r?\\n~m',
        '',
        $source,
        -1,
        $removedEnctypeLines
    );

    if (! is_string($source)) {
        throw new RuntimeException('Pembersihan enctype Vendor form gagal.');
    }

    $source = preg_replace(
        '~^(\\s*)method="POST"\\s*$~m',
        '$0'.PHP_EOL.'$1enctype="multipart/form-data"',
        $source,
        1,
        $multipartCount
    );

    if (! is_string($source) || $multipartCount !== 1) {
        throw new RuntimeException(
            "Baris method POST Vendor form tidak ditemukan tepat satu kali; count={$multipartCount}."
        );
    }

    if (preg_match(
        '~action="\\{\\{[\\s\\S]*?enctype="multipart/form-data"[\\s\\S]*?\\}\\}"~',
        $source
    )) {
        throw new RuntimeException('enctype masih berada di dalam action Blade.');
    }

    if (substr_count($source, 'enctype="multipart/form-data"') !== 1) {
        throw new RuntimeException('Vendor form harus memiliki tepat satu enctype multipart.');
    }

    return $source;
}

function strengthenPoVendorSyncHotfix(string $source): string
{
    if (str_contains($source, 'PO VENDOR MASTER SYNC V1.1')) {
        return $source;
    }

    $pattern = <<<'REGEX'
~if\s*\(\s*empty\s*\(\s*\$purchaseOrder->vendor_id\s*\)\s*&&\s*!\s*empty\s*\(\s*\$purchaseOrder->vendor_name\s*\)\s*\)\s*\{~s
REGEX;

    $replacement = <<<'PHP'
/* PO VENDOR MASTER SYNC V1.1
                 *
                 * vendor_name is the source of truth entered in the PO.
                 * Resolve it on every save so a stale vendor_id cannot prevent
                 * a newly typed/renamed vendor from entering Vendor Master.
                 */
                if (! empty($purchaseOrder->vendor_name)) {
PHP;

    $source = preg_replace($pattern, $replacement, $source, 1, $count);

    if (! is_string($source) || $count !== 1) {
        throw new RuntimeException(
            "Condition PO vendor sync lama tidak ditemukan tepat satu kali; count={$count}."
        );
    }

    return $source;
}

echo "VENDOR CREATE/EDIT + PO SYNC HOTFIX V1.1\n";
echo "=========================================\n\n";

foreach ([$formPath, $providerPath, $syncServicePath, $poControllerPath] as $path) {
    if (! is_file($path)) {
        failHotfix("File wajib tidak ditemukan: {$path}");
    }
}

$originalForm = file_get_contents($formPath);
$originalProvider = file_get_contents($providerPath);
$syncSource = (string) file_get_contents($syncServicePath);
$poControllerSource = (string) file_get_contents($poControllerPath);

if ($originalForm === false || $originalProvider === false) {
    failHotfix('Gagal membaca Vendor form atau CrmOperationsServiceProvider.');
}

if (! str_contains($syncSource, 'findOrCreateFromPurchaseOrder')
    || ! str_contains($syncSource, "'normalized_name'")) {
    failHotfix('Preflight gagal: VendorSyncService tidak memiliki normalized find-or-create.');
}

if (! str_contains($poControllerSource, "'vendor_name' => trim(\$validated['vendor_name'])")) {
    failHotfix('Preflight gagal: PurchaseOrderController tidak menyimpan vendor_name.');
}

$formBackup = $formPath.$suffix;
$providerBackup = $providerPath.$suffix;
$manifest = [
    'version' => 'vendor-create-edit-po-sync-hotfix-v1_1',
    'created_at' => date(DATE_ATOM),
    'files' => [
        ['path' => $formPath, 'backup' => $formBackup],
        ['path' => $providerPath, 'backup' => $providerBackup],
    ],
];

try {
    $updatedForm = repairVendorFormHotfix($originalForm);
    $updatedProvider = strengthenPoVendorSyncHotfix($originalProvider);

    if (! copy($formPath, $formBackup)) {
        throw new RuntimeException("Gagal membuat backup: {$formBackup}");
    }

    if (! copy($providerPath, $providerBackup)) {
        throw new RuntimeException("Gagal membuat backup: {$providerBackup}");
    }

    atomicWriteHotfix($formPath, $updatedForm);
    atomicWriteHotfix($providerPath, $updatedProvider);
    atomicWriteHotfix(
        $manifestPath,
        json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL
    );

    [$lintCode, $lintOutput] = commandHotfix(
        escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($providerPath)
    );

    if ($lintCode !== 0) {
        throw new RuntimeException("Provider PHP lint gagal:\n{$lintOutput}");
    }

    chdir($root);

    [$clearBeforeCode, $clearBeforeOutput] = commandHotfix(
        escapeshellarg(PHP_BINARY).' '.escapeshellarg($root.'/artisan').' view:clear'
    );

    if ($clearBeforeCode !== 0) {
        throw new RuntimeException("view:clear gagal:\n{$clearBeforeOutput}");
    }

    /* Compiles every Blade file and catches the exact regression from V1. */
    [$viewCode, $viewOutput] = commandHotfix(
        escapeshellarg(PHP_BINARY).' '.escapeshellarg($root.'/artisan').' view:cache'
    );

    if ($viewCode !== 0) {
        throw new RuntimeException("Blade compile gagal:\n{$viewOutput}");
    }

    [$optimizeCode, $optimizeOutput] = commandHotfix(
        escapeshellarg(PHP_BINARY).' '.escapeshellarg($root.'/artisan').' optimize:clear'
    );

    echo "[OK] Vendor Create/Edit form diperbaiki.\n";
    echo "[OK] Multipart berada pada tag form yang benar.\n";
    echo "[OK] Seluruh Blade berhasil dikompilasi.\n";
    echo "[OK] PO vendor_name selalu disinkronkan ke Vendor Master.\n";

    if ($optimizeCode !== 0) {
        echo "[WARN] optimize:clear gagal; jalankan manual bila perlu:\n{$optimizeOutput}\n";
    }

    echo "\nHOTFIX BERHASIL.\n";
    echo "Jalankan checker:\n";
    echo "php tools/check_vendor_create_edit_po_sync_hotfix_v1_1.php\n";
} catch (Throwable $e) {
    if (is_file($formBackup)) {
        @copy($formBackup, $formPath);
    }

    if (is_file($providerBackup)) {
        @copy($providerBackup, $providerPath);
    }

    if (is_file($root.'/artisan')) {
        chdir($root);
        commandHotfix(
            escapeshellarg(PHP_BINARY).' '.escapeshellarg($root.'/artisan').' view:clear'
        );
    }

    fwrite(STDERR, "\nHOTFIX GAGAL: {$e->getMessage()}\n");
    fwrite(STDERR, "Vendor form dan provider dipulihkan dari backup.\n");
    exit(1);
}

