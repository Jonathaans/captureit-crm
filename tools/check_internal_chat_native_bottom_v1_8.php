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
    file_get_contents($controllerPath)
    ?: '';

$chat =
    file_get_contents($chatPath)
    ?: '';

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
    'Backend newest-first' =>
        preg_match(
            '~->orderByDesc\s*\(\s*[\'"]id[\'"]\s*\)~',
            $index
        ) === 1,

    'Backend limit 50' =>
        preg_match(
            '~->limit\s*\(\s*50\s*\)~',
            $index
        ) === 1,

    'UI display remains ascending' =>
        preg_match(
            '~->sortBy\s*\(\s*[\'"]id[\'"]\s*\)~',
            $index
        ) === 1,

    'Outer viewport column-reverse' =>
        str_contains(
            $chat,
            'class="flex min-h-0 flex-1 flex-col-reverse overflow-y-auto bg-gray-100 p-5"'
        ),

    'Inner message stack remains normal column' =>
        str_contains(
            $chat,
            'class="flex w-full shrink-0 flex-col"'
        ),

    'Message append remains bottom of inner stack' =>
        str_contains(
            $chat,
            'messageStack.appendChild('
        ),

    'Bottom sentinel remains' =>
        str_contains(
            $chat,
            'id="crm-chat-bottom"'
        ),

    'V1.7 runtime guard removed' =>
        !str_contains(
            $chat,
            'INTERNAL CHAT SCROLL GUARD LATEST50 V1.7'
        ),
];

echo "CHECK INTERNAL CHAT NATIVE BOTTOM V1.8\n";
echo "======================================\n\n";

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
echo "2. Buka ulang room.\n";
echo "3. Scrollbar harus NATIF mulai dari bottom.\n";
echo "4. Newest tetap message terakhir secara chronology.\n";
echo "5. Scroll ke atas untuk melihat older history.\n";
echo "6. Kirim message baru -> append tetap di bawah.\n";

exit(0);
