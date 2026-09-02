<?php

/*
|--------------------------------------------------------------------------
| Internal Chat V3.3.11 checker
|--------------------------------------------------------------------------
*/

$root = realpath(__DIR__.'/..');

if (! $root) {
    fwrite(STDERR, "Project root tidak ditemukan.\n");
    exit(1);
}

$badgePath =
    $root
    .'/packages/Webkul/Admin/src/Resources/views/internal-communication/chat-unread-badge.blade.php';

if (! is_file($badgePath)) {
    fwrite(STDERR, "chat-unread-badge.blade.php tidak ditemukan.\n");
    exit(2);
}

$source = file_get_contents($badgePath);

if ($source === false) {
    fwrite(STDERR, "Unread badge partial tidak dapat dibaca.\n");
    exit(3);
}

$checks = [
    'V3.3.11 marker' =>
        str_contains(
            $source,
            'INTERNAL CHAT V3.3.11 GLOBAL UNREAD TARGET ISOLATION'
        ),

    'Exact launcher selector' =>
        str_contains(
            $source,
            'isExactChatLauncher'
        ),

    'Conversation list explicitly excluded' =>
        str_contains(
            $source,
            "#crm-wa-conversation-list"
        ),

    'Legacy wrong row badges are cleaned' =>
        str_contains(
            $source,
            'removeWrongRowBadges'
        ),

    'Global badge owner marker' =>
        str_contains(
            $source,
            'globalChatUnreadOwner'
        ),

    'Old query-string fan-out removed' =>
        ! str_contains(
            $source,
            "href.startsWith(\n                                    chatUrl\n                                    + '?'"
        )
        && ! str_contains(
            $source,
            "href.startsWith(\r\n                                    chatUrl\r\n                                    + '?'"
        ),
];

$failed = false;

foreach ($checks as $label => $ok) {
    echo ($ok ? '[PASS] ' : '[FAIL] ').$label.PHP_EOL;

    if (! $ok) {
        $failed = true;
    }
}

if ($failed) {
    exit(10);
}

echo PHP_EOL;
echo "V3.3.11 global unread target isolation siap.\n";
echo "Global total unread hanya boleh menempel pada launcher Chat, bukan setiap conversation row.\n";
