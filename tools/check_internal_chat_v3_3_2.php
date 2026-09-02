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

echo "INTERNAL CHAT V3.3.2 CHECK\n";
echo "==========================\n\n";

$bladePath =
    base_path(
        'packages/Webkul/Admin/src/Resources/views/internal-communication/chat.blade.php'
    );

$widgetPath =
    base_path(
        'packages/Webkul/Admin/src/Resources/views/internal-communication/widget.blade.php'
    );

$controllerPath =
    base_path(
        'packages/Webkul/Admin/src/Http/Controllers/InternalCommunication/InternalChatConversationController.php'
    );

$blade =
    is_file($bladePath)
        ? file_get_contents($bladePath)
        : '';

$widget =
    is_file($widgetPath)
        ? file_get_contents($widgetPath)
        : '';

$controller =
    is_file($controllerPath)
        ? file_get_contents($controllerPath)
        : '';

$checks = [
    'V3.3 schema' =>
        Schema::hasColumn(
            'internal_conversation_members',
            'pinned_at'
        )
        && Schema::hasColumn(
            'internal_conversation_members',
            'muted_until'
        )
        && Schema::hasColumn(
            'internal_conversation_members',
            'mute_forever'
        ),

    'Presence table' =>
        Schema::hasTable(
            'internal_chat_user_states'
        ),

    'Preference route' =>
        Route::has(
            'admin.internal-chat.preference'
        ),

    'Heartbeat route' =>
        Route::has(
            'admin.internal-chat.presence.heartbeat'
        ),

    'Activity presence widget' =>
        str_contains(
            $widget,
            'INTERNAL CHAT V3.3.2 ACTIVITY PRESENCE'
        )
        && str_contains(
            $widget,
            'idle_seconds'
        )
        && str_contains(
            $widget,
            'in_chat'
        ),

    'Online/Idle/Last active controller' =>
        str_contains(
            $controller,
            "'state' =>\n                        'online'"
        )
        && str_contains(
            $controller,
            "'state' =>\n                        'idle'"
        )
        && str_contains(
            $controller,
            'Last active '
        ),

    'Legacy heartbeat ignored' =>
        str_contains(
            $controller,
            'ignored_legacy_heartbeat'
        ),

    'Normal form preference support' =>
        str_contains(
            $controller,
            '$request->expectsJson()'
        )
        && str_contains(
            $controller,
            'redirect()'
        ),

    'Hard Pin/Mute fallback' =>
        str_contains(
            $blade,
            'INTERNAL CHAT V3.3.2 HARD PIN MUTE'
        )
        && str_contains(
            $blade,
            'submitPreference'
        )
        && str_contains(
            $blade,
            'stopImmediatePropagation'
        ),

    'Mute modal V3.3.2' =>
        str_contains(
            $blade,
            'crm-chat-v332-mute-modal'
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
