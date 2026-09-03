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
    'V1.2 marker terpasang' =>
        str_contains(
            $chat,
            'INTERNAL CHAT FORCE OPEN LATEST V1.2 START'
        ),

    'Runtime viewport sizing tersedia' =>
        str_contains(
            $chat,
            'const applyChatViewport'
        )
        && str_contains(
            $chat,
            'window.innerHeight'
        )
        && str_contains(
            $chat,
            'chatShell.style.height'
        ),

    'Flex min-height fix tersedia' =>
        str_contains(
            $chat,
            "chatSection.style.minHeight"
        )
        && str_contains(
            $chat,
            "messagesRoot.style.minHeight"
        ),

    'Messages overflow forced' =>
        str_contains(
            $chat,
            "messagesRoot.style.overflowY"
        ),

    'Forced jumpToLatest tersedia' =>
        str_contains(
            $chat,
            'const jumpToLatest'
        )
        && str_contains(
            $chat,
            'messagesRoot.scrollHeight'
        ),

    'Fallback scrollIntoView tersedia' =>
        str_contains(
            $chat,
            'last.scrollIntoView'
        ),

    'Late layout settling handled' =>
        str_contains(
            $chat,
            'ResizeObserver'
        )
        && str_contains(
            $chat,
            '1800'
        ),

    'Message root tetap ada' =>
        str_contains(
            $chat,
            'id="crm-chat-messages"'
        ),
];

echo "CHECK INTERNAL CHAT FORCE OPEN LATEST V1.2\n";
echo "==========================================\n\n";

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
echo "1. Tutup tab Internal Chat lama.\n";
echo "2. Buka Internal Chat dari menu/sidebar lagi.\n";
echo "3. Pilih room dengan histori panjang.\n";
echo "4. Room harus langsung terbuka di message paling baru.\n";
echo "5. Scroll ke atas secara manual setelah beberapa detik; posisi user tidak boleh ditarik kembali ke bawah.\n";

exit(0);
