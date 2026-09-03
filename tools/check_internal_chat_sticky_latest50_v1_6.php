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

$indexStart = strpos($controller, 'public function index(');
$indexEnd = strpos($controller, 'public function startDirect(');

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
    'V1.6 latest50 marker ada' =>
        str_contains(
            $index,
            'INTERNAL CHAT STICKY LATEST 50 V1.6'
        ),

    'Query mengambil newest first' =>
        str_contains(
            $index,
            '->orderByDesc('
        ),

    'Initial limit tepat 50' =>
        preg_match(
            '~->limit\s*\(\s*50\s*\)~',
            $index
        ) === 1,

    '50 newest disort ascending untuk UI' =>
        str_contains(
            $index,
            '->sortBy('
        )
        && str_contains(
            $index,
            '->values();'
        ),

    'V1.6 sticky bottom marker ada' =>
        str_contains(
            $chat,
            'INTERNAL CHAT STICKY BOTTOM V1.6'
        ),

    'Message stack tetap chronological' =>
        str_contains(
            $chat,
            'id="crm-chat-message-stack"'
        )
        && str_contains(
            $chat,
            'messageStack.appendChild('
        ),

    'Short history aligned bottom' =>
        str_contains(
            $chat,
            'stack.style.justifyContent'
        )
        && str_contains(
            $chat,
            "'flex-end'"
        ),

    'Initial pin bottom tersedia' =>
        str_contains(
            $chat,
            'const pinBottom'
        )
        && str_contains(
            $chat,
            'root.scrollHeight'
        ),

    'Mutation observer menjaga new/poll messages' =>
        str_contains(
            $chat,
            'MutationObserver'
        ),

    'Resize observer menangani late sizing' =>
        str_contains(
            $chat,
            'ResizeObserver'
        ),

    'Persistent low-frequency guard tersedia' =>
        str_contains(
            $chat,
            '750'
        )
        && str_contains(
            $chat,
            'setInterval'
        ),

    'User scroll-up dapat mematikan sticky' =>
        str_contains(
            $chat,
            'userIntent'
        )
        && str_contains(
            $chat,
            'sticky ='
        ),
];

echo "CHECK INTERNAL CHAT STICKY LATEST 50 V1.6\n";
echo "=========================================\n\n";

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
echo "2. Buka Internal Chat dari sidebar.\n";
echo "3. Masuk room -> harus langsung melihat newest paling bawah.\n";
echo "4. Tunggu 5-10 detik -> room TIDAK boleh meloncat kembali ke top.\n";
echo "5. Kirim pesan baru -> harus muncul di bawah dan tetap terlihat.\n";
echo "6. Scroll ke atas manual -> sistem harus membiarkan Anda membaca history.\n";
echo "7. Scroll kembali dekat bottom -> sticky mengikuti new messages lagi.\n";

exit(0);
