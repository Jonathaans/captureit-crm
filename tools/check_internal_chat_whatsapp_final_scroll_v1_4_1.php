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
    'Final V1.4.1 marker terpasang satu kali' =>
        substr_count(
            $chat,
            'INTERNAL CHAT WHATSAPP FINAL SCROLL V1.4.1'
        ) === 1,

    'Chronological bottom stack tetap ada' =>
        str_contains(
            $chat,
            'id="crm-chat-message-stack"'
        )
        && str_contains(
            $chat,
            'class="mt-auto flex w-full flex-col"'
        )
        && str_contains(
            $chat,
            'id="crm-chat-bottom"'
        ),

    'Dynamic messages tetap append ke bawah' =>
        str_contains(
            $chat,
            'messageStack.appendChild('
        ),

    'Final initial pin tersedia' =>
        str_contains(
            $chat,
            'const pinToNewest'
        )
        && str_contains(
            $chat,
            'root.scrollTop'
        )
        && str_contains(
            $chat,
            'root.scrollHeight'
        ),

    'Browser restoration conflict ditangani' =>
        str_contains(
            $chat,
            'history.scrollRestoration'
        ),

    'Initial settle retry tersedia' =>
        str_contains(
            $chat,
            '1500'
        )
        && str_contains(
            $chat,
            'setInterval'
        ),

    'User manual history scroll menghentikan initial pin' =>
        str_contains(
            $chat,
            'userTouchedHistory'
        )
        && str_contains(
            $chat,
            'stopInitialPin'
        ),

    'V1.2 conflicting script sudah hilang' =>
        !str_contains(
            $chat,
            'INTERNAL CHAT FORCE OPEN LATEST V1.2 START'
        ),

    'V1.3 old initial script sudah hilang' =>
        !str_contains(
            $chat,
            'INTERNAL CHAT WHATSAPP INITIAL BOTTOM V1.3'
        ),

    'V1.1 embedded block sudah hilang' =>
        !str_contains(
            $chat,
            'INTERNAL CHAT INITIAL LATEST V1.1'
        ),
];

echo "CHECK INTERNAL CHAT WHATSAPP FINAL SCROLL V1.4.1\n";
echo "=================================================\n\n";

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
echo "\nQA:\n";
echo "1. Tutup tab Internal Chat lama.\n";
echo "2. Buka ulang Internal Chat dari sidebar.\n";
echo "3. Masuk room: newest harus langsung terlihat di PALING BAWAH.\n";
echo "4. Histori lama tetap di atas.\n";
echo "5. Kirim pesan baru: bubble baru muncul di bawah newest sebelumnya.\n";
echo "6. Scroll ke atas manual: sistem tidak menarik balik setelah user mulai scroll.\n";

exit(0);
