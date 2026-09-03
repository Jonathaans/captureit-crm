<?php

declare(strict_types=1);

/**
 * CHECK INTERNAL CHAT EMERGENCY ROLLBACK V1
 *
 * Read-only syntax sanity for Blade directives most relevant to the crash.
 */

use Illuminate\Contracts\Console\Kernel;

$root = dirname(__DIR__);

require $root . '/vendor/autoload.php';

$app = require_once $root . '/bootstrap/app.php';

$app->make(Kernel::class)->bootstrap();

$chatPath =
    base_path(
        'packages/Webkul/Admin/src/Resources/views/internal-communication/chat.blade.php'
    );

$chat =
    is_file($chatPath)
        ? file_get_contents($chatPath)
        : '';

echo "CHECK INTERNAL CHAT EMERGENCY ROLLBACK V1\n";
echo "=========================================\n\n";

if ($chat === '') {
    echo "[FAIL] chat.blade.php kosong/tidak ditemukan.\n";
    exit(1);
}

$ifCount =
    preg_match_all(
        '/@if\s*\(/',
        $chat
    );

$endifCount =
    substr_count(
        $chat,
        '@endif'
    );

$foreachCount =
    preg_match_all(
        '/@foreach\s*\(/',
        $chat
    );

$endforeachCount =
    substr_count(
        $chat,
        '@endforeach'
    );

echo "[INFO] @if count       : {$ifCount}\n";
echo "[INFO] @endif count    : {$endifCount}\n";
echo "[INFO] @foreach count  : {$foreachCount}\n";
echo "[INFO] @endforeach count: {$endforeachCount}\n\n";

$checks = [
    'Basic @if/@endif count seimbang' =>
        $ifCount === $endifCount,

    'Basic @foreach/@endforeach count seimbang' =>
        $foreachCount === $endforeachCount,

    'chat root masih ada' =>
        str_contains(
            $chat,
            'id="crm-chat-messages"'
        ),

    'send form masih ada' =>
        str_contains(
            $chat,
            'id="crm-chat-send-form"'
        ),

    'Broken V1.4.1 marker sudah tidak aktif' =>
        !str_contains(
            $chat,
            'INTERNAL CHAT WHATSAPP FINAL SCROLL V1.4.1'
        ),
];

$failed = [];

foreach ($checks as $label => $ok) {
    echo
        ($ok ? '[OK]   ' : '[FAIL] ')
        . $label
        . PHP_EOL;

    if (!$ok) {
        $failed[] = $label;
    }
}

echo PHP_EOL;

if ($failed) {
    echo "HASIL: FAIL\n";
    exit(1);
}

echo "HASIL: PASS\n";
echo "\nTes browser:\n";
echo "1. Ctrl + Shift + R.\n";
echo "2. Buka /admin/internal-chat.\n";
echo "3. Pastikan halaman chat kembali normal tanpa syntax error.\n";
echo "\nUntuk sekarang JANGAN jalankan patch V1.4/V1.4.1 lagi.\n";

exit(0);
