<?php

declare(strict_types=1);

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

$checks = [
    'V1.3 marker terpasang' =>
        str_contains(
            $chat,
            'INTERNAL CHAT WHATSAPP BOTTOM STACK V1.3'
        ),

    'Message stack tersedia' =>
        str_contains(
            $chat,
            'id="crm-chat-message-stack"'
        ),

    'Short history bottom alignment aktif' =>
        str_contains(
            $chat,
            'class="mt-auto flex w-full flex-col"'
        ),

    'Bottom sentinel tersedia' =>
        str_contains(
            $chat,
            'id="crm-chat-bottom"'
        ),

    'Dynamic append menggunakan message stack' =>
        str_contains(
            $chat,
            'messageStack.appendChild('
        ),

    'Initial bottom script tersedia' =>
        str_contains(
            $chat,
            'INTERNAL CHAT WHATSAPP INITIAL BOTTOM V1.3'
        ),

    'Browser scroll restoration dinonaktifkan untuk initial room' =>
        str_contains(
            $chat,
            'history.scrollRestoration'
        ),

    'Long history scroll-to-bottom tersedia' =>
        str_contains(
            $chat,
            'root.scrollTop'
        )
        && str_contains(
            $chat,
            'root.scrollHeight'
        ),

    'Native bottom fallback tersedia' =>
        str_contains(
            $chat,
            'bottom.scrollIntoView'
        ),

    'Message root tetap tersedia' =>
        str_contains(
            $chat,
            'id="crm-chat-messages"'
        ),
];

echo "CHECK INTERNAL CHAT WHATSAPP BOTTOM STACK V1.3\n";
echo "===============================================\n\n";

$failed = [];

foreach ($checks as $label => $ok) {
    echo
        ($ok ? '[OK]   ' : '[FAIL] ')
        . $label
        . PHP_EOL;

    if (!$ok) {
        $failed[] =
            $label;
    }
}

echo PHP_EOL;

if ($failed) {
    echo "HASIL: FAIL\n";
    exit(1);
}

echo "HASIL: PASS\n";
echo "\nQA browser:\n";
echo "1. Tutup tab Internal Chat yang lama.\n";
echo "2. Buka Internal Chat lagi.\n";
echo "3. Buka room dengan sedikit pesan: newest harus berada dekat composer/bawah.\n";
echo "4. Buka room dengan banyak pesan: viewport harus langsung di newest.\n";
echo "5. Scroll ke atas: histori lama tetap bisa dibaca normal.\n";
echo "6. Kirim pesan baru: pesan baru harus tetap muncul paling bawah.\n";

exit(0);
