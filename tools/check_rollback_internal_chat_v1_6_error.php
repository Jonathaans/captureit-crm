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

$checks = [
    'Marker V1.6 controller sudah hilang' =>
        !str_contains(
            $controller,
            'INTERNAL CHAT STICKY LATEST 50 V1.6'
        ),

    'Marker V1.6 blade sudah hilang' =>
        !str_contains(
            $chat,
            'INTERNAL CHAT STICKY BOTTOM V1.6'
        ),

    'crm-chat-messages masih tersedia' =>
        str_contains(
            $chat,
            'id="crm-chat-messages"'
        ),

    'Form send masih tersedia' =>
        str_contains(
            $chat,
            'id="crm-chat-send-form"'
        ),

    'Conversation UI masih tersedia' =>
        str_contains(
            $chat,
            'Internal Chat'
        ),
];

echo "CHECK ROLLBACK INTERNAL CHAT V1.6 ERROR\n";
echo "=======================================\n\n";

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
echo "\nBrowser:\n";
echo "1. Ctrl + Shift + R.\n";
echo "2. Buka /admin/internal-chat.\n";
echo "3. Halaman harus kembali normal tanpa ViewException.\n";

exit(0);
