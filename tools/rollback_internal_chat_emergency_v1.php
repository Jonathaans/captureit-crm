<?php

declare(strict_types=1);

/**
 * INTERNAL CHAT EMERGENCY ROLLBACK V1
 *
 * Tujuan:
 * - Pulihkan chat.blade.php ke backup TERBARU sebelum patch V1.4.1.
 * - Tidak menebak isi Blade yang sedang rusak.
 * - Tidak menyentuh controller/database/menu/ACL.
 *
 * Mencari backup:
 * chat.blade.php.bak-internal-chat-whatsapp-final-scroll-v1_4_1-*
 *
 * Jika tidak ada, fallback ke backup V1.4:
 * chat.blade.php.bak-internal-chat-whatsapp-final-scroll-v1_4-*
 */

$root = dirname(__DIR__);

$chatPath =
    $root . '/packages/Webkul/Admin/src/Resources/views/internal-communication/chat.blade.php';

function fail(string $message, int $code = 1): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit($code);
}

echo "INTERNAL CHAT EMERGENCY ROLLBACK V1\n";
echo "===================================\n\n";

if (!is_file($chatPath)) {
    fail("chat.blade.php tidak ditemukan:\n{$chatPath}");
}

$patterns = [
    $chatPath . '.bak-internal-chat-whatsapp-final-scroll-v1_4_1-*',
    $chatPath . '.bak-internal-chat-whatsapp-final-scroll-v1_4-*',
];

$backups = [];

foreach ($patterns as $pattern) {
    foreach (glob($pattern) ?: [] as $file) {
        if (is_file($file)) {
            $backups[$file] = filemtime($file) ?: 0;
        }
    }
}

if (!$backups) {
    fail(
        "Backup V1.4.1/V1.4 tidak ditemukan.\n"
        . "Jangan edit Blade manual dulu. Kirim output ini."
    );
}

arsort($backups);

$backup =
    array_key_first($backups);

if (!$backup || !is_file($backup)) {
    fail('Backup valid tidak ditemukan.');
}

echo "Backup yang akan dipakai:\n{$backup}\n\n";

/*
 * Safety backup of the CURRENT broken file before restoring.
 */
$brokenBackup =
    $chatPath
    . '.broken-before-emergency-rollback-'
    . date('Ymd-His');

if (!copy($chatPath, $brokenBackup)) {
    fail(
        "Gagal membackup file rusak sebelum restore:\n{$brokenBackup}"
    );
}

echo "Current broken file disimpan sebagai:\n{$brokenBackup}\n\n";

if (!copy($backup, $chatPath)) {
    fail('Gagal restore backup ke chat.blade.php.');
}

echo "Restore file PASS.\n\n";

chdir($root);

echo "Membersihkan compiled view/cache...\n";

passthru(
    escapeshellarg(PHP_BINARY)
    . ' '
    . escapeshellarg($root . '/artisan')
    . ' optimize:clear',
    $clearCode
);

if ($clearCode !== 0) {
    echo
        "\nPERINGATAN: optimize:clear exit code {$clearCode}.\n";
}

echo "\nSELESAI.\n";
echo "Sekarang jalankan:\n";
echo "php tools/check_internal_chat_emergency_rollback_v1.php\n";

exit(0);
