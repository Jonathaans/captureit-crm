<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Schema;

require __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$controller = base_path(
    'packages/Webkul/Admin/src/Http/Controllers/InternalCommunication/InternalChatController.php'
);

$blade = base_path(
    'packages/Webkul/Admin/src/Resources/views/internal-communication/chat.blade.php'
);

$controllerSource = is_file($controller)
    ? file_get_contents($controller)
    : '';

$bladeSource = is_file($blade)
    ? file_get_contents($blade)
    : '';

echo "INTERNAL CHAT V3 COMPACT + READ RECEIPTS CHECK\n";
echo "=============================================\n\n";

$checks = [
    'Existing last_read_at schema' =>
        Schema::hasColumn('internal_conversation_members', 'last_read_at'),

    'Unread count backend' =>
        str_contains($controllerSource, "'unread_count'"),

    'Read receipt backend' =>
        str_contains($controllerSource, "'read_up_to_id'")
        && str_contains($controllerSource, 'readUpToMessageId('),

    'Compact recent conversations' =>
        str_contains($bladeSource, 'data-conversation-search')
        && str_contains($bladeSource, 'crm-wa-conversation-list'),

    'New Chat modal' =>
        str_contains($bladeSource, 'crm-new-chat-modal')
        && str_contains($bladeSource, 'crm-chat-user-modal-list'),

    'Unread badge UI' =>
        str_contains($bladeSource, '$unreadCount'),

    'Double-check receipt UI' =>
        str_contains($bladeSource, 'data-read-receipt')
        && str_contains($bladeSource, '✓✓'),

    'Blue read state' =>
        str_contains($bladeSource, 'text-blue-400'),

    'Attachment preserved' =>
        str_contains($bladeSource, 'crm-chat-attachments')
        && str_contains($bladeSource, 'admin.internal-chat.attachments.download'),

    'Send preserved' =>
        str_contains($bladeSource, 'crm-chat-send-form')
        && str_contains($bladeSource, 'admin.internal-chat.send'),

    'Polling preserved' =>
        str_contains($bladeSource, 'pollMessages')
        && str_contains($bladeSource, '5000'),
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
