<?php

declare(strict_types=1);

echo "CHECK CRM READ-ONLY ARCHIVE POLICY V1\n";
echo "=====================================\n\n";

$root = realpath(__DIR__.DIRECTORY_SEPARATOR.'..');

if ($root === false || ! is_file($root.DIRECTORY_SEPARATOR.'artisan')) {
    fwrite(STDERR, "CHECK GAGAL: File checker harus berada di folder tools dan dijalankan dari root project.\n");
    exit(1);
}

$service = $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, 'packages/Webkul/Admin/src/Services/CrmReadOnlyArchivePolicyService.php');
$provider = $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, 'packages/Webkul/Admin/src/Providers/CrmHardeningCoreServiceProvider.php');
$fails = 0;

function archiveResult(bool $ok, string $message): void
{
    global $fails;
    echo ($ok ? '[OK]   ' : '[FAIL] ').$message."\n";

    if (! $ok) {
        $fails++;
    }
}

archiveResult(is_file($service), 'Archive policy service tersedia');
archiveResult(is_file($provider), 'Hardening provider tersedia');

$serviceText = is_file($service) ? (string) file_get_contents($service) : '';
$providerText = is_file($provider) ? (string) file_get_contents($provider) : '';

archiveResult(str_contains($serviceText, 'CRM_READ_ONLY_ARCHIVE_POLICY_V1'), 'Marker archive policy terpasang');
archiveResult(str_contains($serviceText, "'quotes' =>"), 'Quotation final dilindungi');
archiveResult(str_contains($serviceText, "'invoices' =>"), 'Invoice final dilindungi');
archiveResult(str_contains($serviceText, "'work_orders' =>"), 'SPK final dilindungi');
archiveResult(str_contains($serviceText, "'delivery_orders' =>"), 'Surat Jalan final dilindungi');
archiveResult(str_contains($serviceText, "'inventory_stock_movements'"), 'Movement append-only dilindungi');
archiveResult(
    str_contains($providerText, "'eloquent.creating: *'")
        && str_contains($providerText, "'eloquent.updating: *'")
        && str_contains($providerText, "'eloquent.deleting: *'"),
    'Guard create detail, update, dan delete terdaftar'
);

if (function_exists('exec')) {
    foreach ([$service, $provider] as $path) {
        $output = [];
        $code = 0;
        exec(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($path).' 2>&1', $output, $code);
        archiveResult($code === 0, 'PHP lint: '.basename($path));
    }

    $output = [];
    $code = 0;
    $previous = getcwd();
    chdir($root);

    try {
        exec(escapeshellarg(PHP_BINARY).' artisan about 2>&1', $output, $code);
    } finally {
        if ($previous !== false) {
            chdir($previous);
        }
    }

    archiveResult($code === 0, 'Laravel bootstrap berhasil');
} else {
    echo "[WARN] PHP exec() tidak tersedia; lint runtime dilewati.\n";
}

echo "\n";

if ($fails > 0) {
    echo "[FAIL] Checker menemukan {$fails} masalah.\n";
    exit(1);
}

echo "[PASS] Kebijakan archive read-only terpasang.\n";
exit(0);
