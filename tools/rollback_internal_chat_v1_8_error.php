<?php

declare(strict_types=1);

/**
 * ROLLBACK INTERNAL CHAT V1.8 ERROR
 *
 * Mengembalikan chat.blade.php ke backup otomatis terbaru yang dibuat
 * tepat sebelum V1.8 dijalankan:
 *
 *   chat.blade.php.bak-native-bottom-v1_8-YYYYMMDD-HHMMSS
 *
 * Tidak mengubah controller/database/route/menu/ACL.
 */

$root = dirname(__DIR__);

$chat =
    $root . '/packages/Webkul/Admin/src/Resources/views/internal-communication/chat.blade.php';

function fail(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

function latestBackup(string $pattern): ?string
{
    $files = glob($pattern) ?: [];

    if (!$files) {
        return null;
    }

    usort(
        $files,
        static fn (string $a, string $b): int =>
            filemtime($b) <=> filemtime($a)
    );

    return $files[0] ?? null;
}

echo "ROLLBACK INTERNAL CHAT V1.8 ERROR\n";
echo "================================\n\n";

if (!is_file($chat)) {
    fail("chat.blade.php tidak ditemukan:\n{$chat}");
}

$backup =
    latestBackup(
        $chat . '.bak-native-bottom-v1_8-*'
    );

if (!$backup) {
    fail(
        "Backup V1.8 tidak ditemukan.\nPattern:\n"
        . $chat
        . ".bak-native-bottom-v1_8-*"
    );
}

echo "Backup yang dipakai:\n{$backup}\n\n";

$safety =
    $chat
    . '.bak-before-v1_8-error-rollback-'
    . date('Ymd-His');

if (!copy($chat, $safety)) {
    fail('Gagal membuat safety backup current state.');
}

echo "Safety backup current state:\n{$safety}\n\n";

if (!copy($backup, $chat)) {
    fail('Gagal restore chat.blade.php dari backup V1.8.');
}

echo "Source restore PASS.\n";
echo "Membersihkan compiled Blade/cache...\n\n";

chdir($root);

passthru(
    escapeshellarg(PHP_BINARY)
    . ' '
    . escapeshellarg($root . '/artisan')
    . ' view:clear'
);

passthru(
    escapeshellarg(PHP_BINARY)
    . ' '
    . escapeshellarg($root . '/artisan')
    . ' optimize:clear'
);

echo "\nROLLBACK SELESAI.\n";
echo "Jalankan checker:\n";
echo "php tools/check_rollback_internal_chat_v1_8_error.php\n";
