<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;

$root = dirname(__DIR__);

require $root . '/vendor/autoload.php';

$app = require_once $root . '/bootstrap/app.php';

$app->make(Kernel::class)->bootstrap();

$controllerPath =
    base_path(
        'packages/Webkul/Admin/src/Http/Controllers/InternalCommunication/InternalChatController.php'
    );

$chatPath =
    base_path(
        'packages/Webkul/Admin/src/Resources/views/internal-communication/chat.blade.php'
    );

$controller =
    is_file($controllerPath)
        ? file_get_contents($controllerPath)
        : '';

$chat =
    is_file($chatPath)
        ? file_get_contents($chatPath)
        : '';

$indexStart =
    strpos(
        $controller,
        'public function index('
    );

$indexEnd =
    strpos(
        $controller,
        'public function startDirect('
    );

$index =
    (
        $indexStart !== false
        && $indexEnd !== false
        && $indexEnd > $indexStart
    )
        ? substr(
            $controller,
            $indexStart,
            $indexEnd - $indexStart
        )
        : '';

$checks = [
    'Latest 50 marker ada' =>
        str_contains(
            $index,
            'INTERNAL CHAT LATEST 50 V1.5'
        ),

    'Newest-first query' =>
        str_contains(
            $index,
            '->orderByDesc('
        ),

    'Limit tepat 50' =>
        preg_match(
            '~->limit\s*\(\s*50\s*\)~',
            $index
        ) === 1,

    'Display diurutkan ascending lagi' =>
        str_contains(
            $index,
            '->sortBy('
        )
        && str_contains(
            $index,
            '->values();'
        ),

    'Bottom V1.5 marker ada' =>
        str_contains(
            $chat,
            'INTERNAL CHAT LATEST50 BOTTOM V1.5'
        ),

    'Message stack tersedia' =>
        str_contains(
            $chat,
            'id="crm-chat-message-stack"'
        )
        && str_contains(
            $chat,
            'id="crm-chat-bottom"'
        ),

    'Short history bottom-aligned' =>
        str_contains(
            $chat,
            'stack.style.minHeight'
        )
        && str_contains(
            $chat,
            'stack.style.justifyContent'
        )
        && str_contains(
            $chat,
            "'flex-end'"
        ),

    'Initial pinBottom tersedia' =>
        str_contains(
            $chat,
            'const pinBottom'
        )
        && str_contains(
            $chat,
            'root.scrollHeight'
        ),

    'Pesan baru append ke bottom stack' =>
        str_contains(
            $chat,
            'messageStack.appendChild('
        ),
];

echo "CHECK INTERNAL CHAT LATEST 50 + BOTTOM V1.5\n";
echo "===========================================\n\n";

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
echo "2. Buka ulang Internal Chat.\n";
echo "3. Masuk room -> newest harus langsung terlihat di paling bawah.\n";
echo "4. Histori lama tetap di atas.\n";
echo "5. Pesan baru berikutnya harus muncul di bawah newest.\n";
echo "6. Initial room hanya memuat 50 message terbaru.\n";

exit(0);
