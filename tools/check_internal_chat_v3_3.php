<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

require __DIR__.'/../vendor/autoload.php';

$app =
    require_once __DIR__.'/../bootstrap/app.php';

$app->make(
    Kernel::class
)->bootstrap();

echo "INTERNAL CHAT V3.3 CHECK\n";
echo "========================\n\n";

$bladePath =
    base_path(
        'packages/Webkul/Admin/src/Resources/views/internal-communication/chat.blade.php'
    );

$controllerPath =
    base_path(
        'packages/Webkul/Admin/src/Http/Controllers/InternalCommunication/InternalChatController.php'
    );

$conversationController =
    base_path(
        'packages/Webkul/Admin/src/Http/Controllers/InternalCommunication/InternalChatConversationController.php'
    );

$blade =
    is_file($bladePath)
        ? file_get_contents($bladePath)
        : '';

$chatController =
    is_file($controllerPath)
        ? file_get_contents($controllerPath)
        : '';

$checks = [
    'Pinned column' =>
        Schema::hasColumn(
            'internal_conversation_members',
            'pinned_at'
        ),

    'Muted until column' =>
        Schema::hasColumn(
            'internal_conversation_members',
            'muted_until'
        ),

    'Mute forever column' =>
        Schema::hasColumn(
            'internal_conversation_members',
            'mute_forever'
        ),

    'Presence state table' =>
        Schema::hasTable(
            'internal_chat_user_states'
        ),

    'Conversation management controller' =>
        is_file(
            $conversationController
        ),

    'Sidebar summary route' =>
        Route::has(
            'admin.internal-chat.sidebar-summary'
        ),

    'Preference route' =>
        Route::has(
            'admin.internal-chat.preference'
        ),

    'Presence heartbeat route' =>
        Route::has(
            'admin.internal-chat.presence.heartbeat'
        ),

    'Mute notification guard' =>
        str_contains(
            $chatController,
            'INTERNAL CHAT V3.3 MUTE NOTIFICATION GUARD'
        ),

    'Pin UI' =>
        str_contains(
            $blade,
            'Pin Conversation UI'
        )
        || (
            str_contains(
                $blade,
                "'pin'"
            )
            && str_contains(
                $blade,
                "'unpin'"
            )
        ),

    'Mute UI' =>
        str_contains(
            $blade,
            'crm-chat-v33-mute-modal'
        ),

    'Presence UI' =>
        str_contains(
            $blade,
            'crm-chat-v33-presence'
        ),

    'Realtime sidebar' =>
        str_contains(
            $blade,
            'window.crmChatV33RefreshSidebar'
        )
        && str_contains(
            $blade,
            '4000'
        ),

    'V3.2.6 preview preserved' =>
        str_contains(
            $blade,
            'INTERNAL CHAT V3.2.6 UNIVERSAL PREVIEW TOOLBAR'
        ),

    'Reply/Edit/Delete preserved' =>
        str_contains(
            $blade,
            'window.crmChatReplyAction'
        )
        && str_contains(
            $blade,
            'window.crmChatEditAction'
        )
        && str_contains(
            $blade,
            'window.crmChatDeleteAction'
        ),
];

$failed = [];

foreach ($checks as $label => $ok) {
    if (! $ok) {
        $failed[] =
            $label;
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
