<?php

declare(strict_types=1);

/**
 * ROLLBACK INTERNAL CHAT V1.6 ERROR
 *
 * Mengembalikan:
 * - InternalChatController.php
 * - chat.blade.php
 *
 * ke backup otomatis TERBARU yang dibuat oleh V1.6:
 *   *.bak-sticky-latest50-v1_6-YYYYMMDD-HHMMSS
 *
 * Tujuan utama: membuat halaman Internal Chat normal kembali.
 */

$root = dirname(__DIR__);

$controller =
    $root . '/packages/Webkul/Admin/src/Http/Controllers/InternalCommunication/InternalChatController.php';

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

echo "ROLLBACK INTERNAL CHAT V1.6 ERROR\n";
echo "================================\n\n";

$controllerBackup =
    latestBackup(
        $controller . '.bak-sticky-latest50-v1_6-*'
    );

$chatBackup =
    latestBackup(
        $chat . '.bak-sticky-latest50-v1_6-*'
    );

if (!$controllerBackup) {
    fail(
        "Backup controller V1.6 tidak ditemukan.\n"
        . "Pattern:\n"
        . $controller
        . ".bak-sticky-latest50-v1_6-*"
    );
}

if (!$chatBackup) {
    fail(
        "Backup chat.blade.php V1.6 tidak ditemukan.\n"
        . "Pattern:\n"
        . $chat
        . ".bak-sticky-latest50-v1_6-*"
    );
}

echo "Backup yang dipakai:\n";
echo "- Controller : {$controllerBackup}\n";
echo "- Blade      : {$chatBackup}\n\n";

$safetyStamp = date('Ymd-His');

$currentControllerSafety =
    $controller . '.bak-before-v1_6-rollback-' . $safetyStamp;

$currentChatSafety =
    $chat . '.bak-before-v1_6-rollback-' . $safetyStamp;

if (
    !copy($controller, $currentControllerSafety)
    || !copy($chat, $currentChatSafety)
) {
    fail('Gagal membuat safety backup sebelum rollback.');
}

echo "Safety backup current state dibuat.\n\n";

if (!copy($controllerBackup, $controller)) {
    fail('Gagal restore controller.');
}

if (!copy($chatBackup, $chat)) {
    copy($currentControllerSafety, $controller);
    fail('Gagal restore chat.blade.php. Controller sudah dikembalikan ke current state.');
}

exec(
    escapeshellarg(PHP_BINARY)
    . ' -l '
    . escapeshellarg($controller)
    . ' 2>&1',
    $lintOutput,
    $lintCode
);

if ($lintCode !== 0) {
    copy($currentControllerSafety, $controller);
    copy($currentChatSafety, $chat);

    fail(
        "Controller hasil rollback gagal PHP lint:\n"
        . implode(PHP_EOL, $lintOutput)
        . "\nCurrent state dipulihkan."
    );
}

echo "Source restore PASS.\n";
echo "- V1.6 dibatalkan.\n";
echo "- Kondisi dikembalikan persis ke sebelum V1.6 dijalankan.\n\n";

chdir($root);

echo "Membersihkan Laravel compiled views/cache...\n";

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
echo "php tools/check_rollback_internal_chat_v1_6_error.php\n";
