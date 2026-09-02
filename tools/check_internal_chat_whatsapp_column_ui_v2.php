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

echo "INTERNAL CHAT WHATSAPP COLUMN UI V2 CHECK\n";
echo "=========================================\n\n";

if (! is_file($path)) {
    echo "FAIL\n";
    echo " - Internal Chat Blade missing.\n";

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
    'Two-column responsive shell' =>
        str_contains(
            $source,
            'lg:flex-row'
        )
        && str_contains(
            $source,
            'lg:w-1/3'
        )
        && str_contains(
            $source,
            'lg:w-2/3'
        ),

    'Chat search' =>
        str_contains(
            $source,
            'crm-wa-chat-search'
        ),

    'Recent conversations list' =>
        str_contains(
            $source,
            'Recent Conversations'
        ),

    'Start new chat list' =>
        str_contains(
            $source,
            'Start New Chat'
        ),

    'Conversation header' =>
        str_contains(
            $source,
            '🔒 Private'
        ),

    'WhatsApp-style message alignment' =>
        str_contains(
            $source,
            'justify-end'
        )
        && str_contains(
            $source,
            'justify-start'
        ),

    'Composer preserved' =>
        str_contains(
            $source,
            'crm-chat-send-form'
        )
        && str_contains(
            $source,
            'Ketik pesan...'
        ),

    'Attachment UI preserved' =>
        str_contains(
            $source,
            'crm-chat-file-list'
        )
        && str_contains(
            $source,
            $routeAttachmentMarker
        ),

    'Send route preserved' =>
        str_contains(
            $source,
            $routeSendMarker
        ),

    'Polling preserved' =>
        str_contains(
            $source,
            'pollMessages'
        )
        && str_contains(
            $source,
            '5000'
        ),

    'No custom style block' =>
        ! str_contains(
            $source,
            '<style>'
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
