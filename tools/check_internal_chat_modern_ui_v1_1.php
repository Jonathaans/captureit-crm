<?php

use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';

$app =
    require_once __DIR__.'/../bootstrap/app.php';

$app->make(
    Kernel::class
)->bootstrap();

$path =
    base_path(
        'packages/Webkul/Admin/src/Resources/views/internal-communication/chat.blade.php'
    );

echo "INTERNAL CHAT MODERN UI V1.1 CHECK\n";
echo "==================================\n\n";

if (! is_file($path)) {
    echo "FAIL\n";
    echo " - chat.blade.php tidak ditemukan.\n";

    exit(1);
}

$source =
    file_get_contents(
        $path
    );

$routeSendMarker = <<<'MARKER'
route('admin.internal-chat.send', $conversation->id)
MARKER;

$routeAttachmentMarker = <<<'MARKER'
route('admin.internal-chat.attachments.download', $attachment->id)
MARKER;

$checks = [
    'Modern shell layout' =>
        str_contains(
            $source,
            'crm-modern-chat-shell'
        ),

    'Modern hero header' =>
        str_contains(
            $source,
            'crm-modern-chat-hero'
        ),

    'Sidebar search' =>
        str_contains(
            $source,
            'crm-modern-chat-search'
        ),

    'Conversation card UI' =>
        str_contains(
            $source,
            'crm-modern-conversation-card'
        ),

    'Modern bubble UI' =>
        str_contains(
            $source,
            'crm-modern-bubble'
        ),

    'Modern composer UI' =>
        str_contains(
            $source,
            'crm-modern-composer'
        ),

    'Attachment chip area' =>
        str_contains(
            $source,
            'crm-chat-file-list'
        ),

    'Send route preserved' =>
        str_contains(
            $source,
            $routeSendMarker
        ),

    'Attachment download route preserved' =>
        str_contains(
            $source,
            $routeAttachmentMarker
        ),

    'Polling script preserved' =>
        str_contains(
            $source,
            'window.setInterval(pollMessages, 5000);'
        ),
];

$failed = [];

foreach ($checks as $label => $ok) {
    if (! $ok) {
        $failed[] = $label;
    }
}

if ($failed) {
    echo "FAIL\n";

    foreach ($failed as $label) {
        echo " - {$label}\n";
    }

    exit(1);
}

echo "PASS\n";

foreach (array_keys($checks) as $label) {
    echo " - {$label}\n";
}
