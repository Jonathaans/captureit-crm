<?php

declare(strict_types=1);

/**
 * CHECK INTERNAL CHAT OPEN LATEST V1
 */

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

$checks = [
    'Controller latest-message marker terpasang' =>
        str_contains(
            $controller,
            'INTERNAL CHAT OPEN LATEST V1'
        ),

    'Initial query mengambil newest first' =>
        str_contains(
            $controller,
            "->orderByDesc(\n                        'id'"
        ),

    'Newest 300 di-sort kembali ascending untuk UI' =>
        str_contains(
            $controller,
            "->sortBy(\n                        'id'"
        )
        && str_contains(
            $controller,
            '->values();'
        ),

    'Initial scroll marker terpasang' =>
        str_contains(
            $chat,
            'INTERNAL CHAT INITIAL LATEST V1'
        ),

    'Initial jumpToLatest tersedia' =>
        str_contains(
            $chat,
            'const jumpToLatest'
        ),

    'Container scroll-to-bottom tersedia' =>
        str_contains(
            $chat,
            'messagesRoot.scrollHeight'
        ),

    'Last message scrollIntoView tersedia' =>
        str_contains(
            $chat,
            'lastMessage.scrollIntoView'
        ),

    'Chat message root masih tersedia' =>
        str_contains(
            $chat,
            'id="crm-chat-messages"'
        ),
];

echo "CHECK INTERNAL CHAT OPEN LATEST V1\n";
echo "==================================\n\n";

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
echo "\nTes browser:\n";
echo "1. Ctrl + Shift + R.\n";
echo "2. Kembali ke daftar Internal Chat.\n";
echo "3. Buka conversation yang punya banyak message.\n";
echo "4. Viewport harus langsung berada di message terbaru.\n";
echo "5. Pindah conversation lain lalu kembali lagi; harus tetap membuka bagian terbaru.\n";

exit(0);
