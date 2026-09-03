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
    file_get_contents($controllerPath) ?: '';

$chat =
    file_get_contents($chatPath) ?: '';

$indexStart =
    strpos($controller, 'public function index(');

$indexEnd =
    strpos($controller, 'public function startDirect(');

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
    'Backend newest-first' =>
        preg_match(
            '~->orderByDesc\s*\(\s*[\'"]id[\'"]\s*\)~',
            $index
        ) === 1,

    'Backend initial limit 50' =>
        preg_match(
            '~->limit\s*\(\s*50\s*\)~',
            $index
        ) === 1,

    'Backend display ascending' =>
        preg_match(
            '~->sortBy\s*\(\s*[\'"]id[\'"]\s*\)~',
            $index
        ) === 1
        && str_contains(
            $index,
            '->values()'
        ),

    'V1.7 marker satu kali' =>
        substr_count(
            $chat,
            'INTERNAL CHAT SCROLL GUARD LATEST50 V1.7'
        ) === 1,

    'Chronological message stack tetap ada' =>
        str_contains(
            $chat,
            'id="crm-chat-message-stack"'
        )
        && str_contains(
            $chat,
            'messageStack.appendChild('
        ),

    'Bottom positioning aktif' =>
        str_contains(
            $chat,
            "stack.style.justifyContent"
        )
        && str_contains(
            $chat,
            "'flex-end'"
        ),

    'Initial goBottom tersedia' =>
        str_contains(
            $chat,
            'const goBottom'
        )
        && str_contains(
            $chat,
            'const maxScrollTop'
        ),

    'Poll/DOM mutation guard tersedia' =>
        str_contains(
            $chat,
            'MutationObserver'
        ),

    'Late layout resize guard tersedia' =>
        str_contains(
            $chat,
            'ResizeObserver'
        ),

    'Persistent scroll guard tersedia' =>
        str_contains(
            $chat,
            '600'
        )
        && str_contains(
            $chat,
            'followNewest'
        ),
];

echo "CHECK INTERNAL CHAT SCROLL GUARD + LATEST 50 V1.7\n";
echo "=================================================\n\n";

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
echo "\nQA:\n";
echo "1. Tutup tab Internal Chat lama.\n";
echo "2. Buka Internal Chat dari sidebar.\n";
echo "3. Masuk room -> newest harus langsung terlihat di bawah.\n";
echo "4. Tunggu minimal 6 detik (melewati poll 5 detik) -> tidak boleh kembali ke top.\n";
echo "5. Kirim pesan baru -> tetap muncul di bawah dan terlihat.\n";
echo "6. Scroll ke atas dengan mouse wheel -> guard harus berhenti menarik ke bawah.\n";
echo "7. Scroll kembali ke paling bawah -> followNewest aktif lagi.\n";

exit(0);
