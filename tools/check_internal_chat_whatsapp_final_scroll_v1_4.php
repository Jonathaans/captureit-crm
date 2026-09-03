<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;

$root =
    dirname(
        __DIR__
    );

require
    $root
    . '/vendor/autoload.php';

$app =
    require_once
    $root
    . '/bootstrap/app.php';

$app->make(
    Kernel::class
)->bootstrap();

$chatPath =
    base_path(
        'packages/Webkul/Admin/src/Resources/views/internal-communication/chat.blade.php'
    );

$chat =
    is_file($chatPath)
        ? file_get_contents($chatPath)
        : '';

$checks = [
    'Final V1.4 marker terpasang satu kali' =>
        substr_count(
            $chat,
            'INTERNAL CHAT WHATSAPP FINAL SCROLL V1.4'
        ) === 1,

    'Chronological bottom stack tetap ada' =>
        str_contains(
            $chat,
            'id="crm-chat-message-stack"'
        )
        && str_contains(
            $chat,
            'id="crm-chat-bottom"'
        ),

    'Dynamic messages append ke bottom stack' =>
        str_contains(
            $chat,
            'messageStack.appendChild('
        ),

    'Bounded viewport controller tersedia' =>
        str_contains(
            $chat,
            'const sizeViewport'
        )
        && str_contains(
            $chat,
            'window.innerHeight'
        ),

    'Initial force-bottom tersedia' =>
        str_contains(
            $chat,
            'const forceBottom'
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
            '2200'
        )
        && str_contains(
            $chat,
            'setInterval'
        ),

    'User interaction menghentikan forced pin' =>
        str_contains(
            $chat,
            'userInteracted'
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

    'V1.1 old initial script sudah hilang' =>
        !str_contains(
            $chat,
            'INTERNAL CHAT INITIAL LATEST V1.1'
        ),
];

echo "CHECK INTERNAL CHAT WHATSAPP FINAL SCROLL V1.4\n";
echo "===============================================\n\n";

$failed = [];

foreach (
    $checks
    as $label => $ok
) {
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
echo "1. Tutup tab Internal Chat lama.\n";
echo "2. Buka Internal Chat dari sidebar.\n";
echo "3. Masuk room: viewport harus langsung menunjukkan newest di paling bawah.\n";
echo "4. Pesan lama harus berada di atas newest, chronology tidak berubah.\n";
echo "5. Kirim pesan baru: bubble baru muncul di bawah newest sebelumnya.\n";
echo "6. Scroll ke atas manual: setelah interaction, sistem tidak boleh menarik kembali ke bawah.\n";

exit(0);
