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

$indexMethod =
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
    'V1.1 controller marker' =>
        str_contains(
            $controller,
            'INTERNAL CHAT OPEN LATEST V1.1'
        ),

    'Initial query newest-first' =>
        str_contains(
            $indexMethod,
            '->orderByDesc('
        ),

    'Latest 300 disort ascending untuk display' =>
        str_contains(
            $indexMethod,
            '->sortBy('
        )
        && str_contains(
            $indexMethod,
            '->values();'
        ),

    'Old oldest-first 300 query hilang' =>
        !preg_match(
            '~->orderBy\s*\(\s*[\'"]id[\'"]\s*\)'
            . '\s*->limit\s*\(\s*300\s*\)~',
            $indexMethod
        ),

    'V1.1 initial latest marker' =>
        str_contains(
            $chat,
            'INTERNAL CHAT INITIAL LATEST V1.1'
        ),

    'jumpToLatest tersedia' =>
        str_contains(
            $chat,
            'const jumpToLatest'
        ),

    'Container scroll tersedia' =>
        str_contains(
            $chat,
            'messagesRoot.scrollHeight'
        ),

    'Page-scroll fallback tersedia' =>
        str_contains(
            $chat,
            'lastMessage.scrollIntoView'
        ),

    'Last message selector tersedia' =>
        str_contains(
            $chat,
            "'[data-message-id]'"
        ),
];

echo "CHECK INTERNAL CHAT OPEN LATEST V1.1\n";
echo "====================================\n\n";

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
echo "2. Kembali ke daftar Internal Chat.\n";
echo "3. Buka conversation yang panjang.\n";
echo "4. View harus langsung menuju message terbaru.\n";
echo "5. Pindah conversation lalu kembali; posisi awal harus tetap latest.\n";

exit(0);
