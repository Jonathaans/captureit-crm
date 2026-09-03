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
    'Initial query newest-first' =>
        str_contains(
            $index,
            '->orderByDesc('
        ),

    'Newest 300 disort ascending untuk UI' =>
        str_contains(
            $index,
            '->sortBy('
        )
        && str_contains(
            $index,
            '->values();'
        ),

    'V1.6 marker tepat satu kali' =>
        substr_count(
            $chat,
            'INTERNAL CHAT BOTTOM STICKY V1.6'
        ) === 1,

    'Standalone goBottom tersedia' =>
        str_contains(
            $chat,
            'const goBottom'
        ),

    'Scrollable element detection tersedia' =>
        str_contains(
            $chat,
            'const findScroller'
        ),

    'Initial bottom sentinel tersedia' =>
        str_contains(
            $chat,
            'crm-chat-scroll-end-v16'
        ),

    'Send capture menjaga bottom' =>
        str_contains(
            $chat,
            "form.addEventListener(\n                        'submit'"
        ),

    'MutationObserver menjaga incoming append' =>
        str_contains(
            $chat,
            'new MutationObserver'
        ),

    'User history scroll dihormati' =>
        str_contains(
            $chat,
            'stickToBottom'
        )
        && str_contains(
            $chat,
            'distanceFromBottom'
        ),

    'Message root tetap tersedia' =>
        str_contains(
            $chat,
            'id="crm-chat-messages"'
        ),

    'Send form tetap tersedia' =>
        str_contains(
            $chat,
            'id="crm-chat-send-form"'
        ),
];

echo "CHECK INTERNAL CHAT BOTTOM STICKY V1.6\n";
echo "======================================\n\n";

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
echo "1. Tutup tab Internal Chat lama.\n";
echo "2. Buka ulang Internal Chat dari sidebar.\n";
echo "3. Open room -> newest harus langsung terlihat paling bawah.\n";
echo "4. Kirim pesan -> bubble baru harus tetap muncul paling bawah, tidak lompat ke atas.\n";
echo "5. Tunggu incoming/polling -> jika sedang di bawah, viewport tetap di bawah.\n";
echo "6. Scroll manual ke atas -> incoming baru tidak boleh menarik Anda ke bawah.\n";

exit(0);
