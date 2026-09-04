<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$formPath = $root.'/packages/Webkul/Admin/src/Resources/views/vendors/form.blade.php';
$providerPath = $root.'/packages/Webkul/Admin/src/Providers/CrmOperationsServiceProvider.php';
$syncServicePath = $root.'/packages/Webkul/Admin/src/Services/VendorSyncService.php';
$poControllerPath = $root.'/packages/Webkul/Admin/src/Http/Controllers/PurchaseOrder/PurchaseOrderController.php';

$failed = 0;

function checkHotfix(bool $ok, string $label): void
{
    global $failed;
    echo ($ok ? '[OK]   ' : '[FAIL] ').$label.PHP_EOL;

    if (! $ok) {
        $failed++;
    }
}

function readHotfix(string $path): string
{
    return is_file($path) ? (string) file_get_contents($path) : '';
}

function commandCheckHotfix(string $command): array
{
    exec($command.' 2>&1', $output, $code);

    return [$code, implode(PHP_EOL, $output)];
}

echo "CHECK VENDOR CREATE/EDIT + PO SYNC HOTFIX V1.1\n";
echo "================================================\n\n";

foreach ([$formPath, $providerPath, $syncServicePath, $poControllerPath] as $path) {
    checkHotfix(is_file($path), "File tersedia: {$path}");
}

$form = readHotfix($formPath);
$provider = readHotfix($providerPath);
$sync = readHotfix($syncServicePath);
$poController = readHotfix($poControllerPath);

checkHotfix(
    str_contains(
        $form,
        "action=\"{{ \$vendor->exists ? route('admin.vendors.update', \$vendor->id) : route('admin.vendors.store') }}\""
    ),
    'Action Create/Edit Vendor valid'
);

checkHotfix(
    ! preg_match(
        '~\\$vendor-\\s*enctype="multipart/form-data"\\s*>\\s*exists~',
        $form
    ),
    'Korupsi $vendor->exists sudah tidak ada'
);

checkHotfix(
    substr_count($form, 'enctype="multipart/form-data"') === 1
        && preg_match(
            '~^\\s*method="POST"\\s*\\r?\\n\\s*enctype="multipart/form-data"\\s*$~m',
            $form
        ) === 1,
    'Multipart terpasang tepat satu kali pada tag form'
);

checkHotfix(
    str_contains($form, 'name="npwp_image"'),
    'Input image NPWP tetap tersedia'
);

checkHotfix(
    str_contains($provider, 'PO VENDOR MASTER SYNC V1.1')
        && ! preg_match(
            '~empty\\s*\\(\\s*\\$purchaseOrder->vendor_id\\s*\\)\\s*&&~',
            $provider
        )
        && str_contains($provider, "! empty(\$purchaseOrder->vendor_name)"),
    'PO selalu menyinkronkan vendor_name tanpa tertahan vendor_id lama'
);

checkHotfix(
    str_contains($sync, 'findOrCreateFromPurchaseOrder')
        && str_contains($sync, "'normalized_name'")
        && str_contains($sync, 'Vendor::query()->create'),
    'VendorSyncService find-or-create anti-duplikat tersedia'
);

checkHotfix(
    substr_count(
        $poController,
        "'vendor_name' => trim(\$validated['vendor_name'])"
    ) >= 2,
    'PO Create dan Update menyimpan vendor_name'
);

if (is_file($root.'/artisan')) {
    chdir($root);
    commandCheckHotfix(
        escapeshellarg(PHP_BINARY).' '.escapeshellarg($root.'/artisan').' view:clear'
    );
    [$viewCode, $viewOutput] = commandCheckHotfix(
        escapeshellarg(PHP_BINARY).' '.escapeshellarg($root.'/artisan').' view:cache'
    );

    checkHotfix(
        $viewCode === 0,
        $viewCode === 0
            ? 'Semua Blade berhasil dikompilasi'
            : 'Blade compile gagal: '.$viewOutput
    );

    commandCheckHotfix(
        escapeshellarg(PHP_BINARY).' '.escapeshellarg($root.'/artisan').' view:clear'
    );
}

echo PHP_EOL;

if ($failed > 0) {
    echo "[FAIL] Checker menemukan {$failed} masalah.\n";
    exit(1);
}

echo "[PASS] Vendor Create/Edit dan PO -> Vendor Master sync sudah benar.\n";

