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
    'chat.blade.php tersedia' =>
        $chat !== '',

    'crm-chat-messages masih ada' =>
        str_contains(
            $chat,
            'id="crm-chat-messages"'
        ),

    'send form masih ada' =>
        str_contains(
            $chat,
            'id="crm-chat-send-form"'
        ),

    'message stack masih ada' =>
        str_contains(
            $chat,
            'id="crm-chat-message-stack"'
        ),

    'V1.8 root class sudah hilang' =>
        !str_contains(
            $chat,
            'flex-col-reverse overflow-y-auto'
        ),
];

echo "CHECK ROLLBACK INTERNAL CHAT V1.8 ERROR\n";
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
