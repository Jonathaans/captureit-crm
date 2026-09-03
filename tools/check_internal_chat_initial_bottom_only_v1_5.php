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
    'V1.5 marker terpasang satu kali' =>
        substr_count(
            $chat,
            'INTERNAL CHAT INITIAL BOTTOM ONLY V1.5'
        ) === 1,

    'Chat message root tetap ada' =>
        str_contains(
            $chat,
            'id="crm-chat-messages"'
        ),

    'Simple goBottom tersedia' =>
        str_contains(
            $chat,
            'const goBottom'
        ),

    'Scroll menggunakan root.scrollTop' =>
        str_contains(
            $chat,
            'root.scrollTop'
        )
        && str_contains(
            $chat,
            'root.scrollHeight'
        ),

    'No new Blade conditional introduced by V1.5' =>
        !str_contains(
            $chat,
            '@if ($conversation)' . PHP_EOL
            . '        <script>' . PHP_EOL
            . '            (() => {' . PHP_EOL
            . '                const bootInternalChatBottomOnlyV15'
        ),
];

echo "CHECK INTERNAL CHAT INITIAL BOTTOM ONLY V1.5\n";
echo "============================================\n\n";

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
echo "2. Buka ulang Internal Chat dari sidebar.\n";
echo "3. Masuk room: scrollbar message harus langsung di posisi paling bawah.\n";
echo "4. Newest chat harus terlihat tepat di atas composer.\n";
echo "5. Kirim pesan baru: pesan berikutnya harus lanjut ke bawah.\n";
echo "6. Scroll ke atas manual: histori lama tetap dapat dibaca.\n";

exit(0);
