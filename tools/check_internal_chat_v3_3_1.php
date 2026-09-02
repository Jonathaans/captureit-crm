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

echo "INTERNAL CHAT V3.3.1 CHECK\n";
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
    'V3.3 schema still ready' =>
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

    'Presence table ready' =>
        Schema::hasTable(
            'internal_chat_user_states'
        ),

    'Global heartbeat marker' =>
        str_contains(
            $widget,
            'INTERNAL CHAT V3.3.1 GLOBAL PRESENCE'
        ),

    'Global heartbeat route' =>
        str_contains(
            $widget,
            "admin.internal-chat.presence.heartbeat"
        )
        && Route::has(
            'admin.internal-chat.presence.heartbeat'
        ),

    'Global heartbeat every 15 seconds' =>
        str_contains(
            $widget,
            '15000'
        ),

    'Last active fallback' =>
        str_contains(
            $controller,
            "return 'Last active belum tercatat';"
        )
        && ! str_contains(
            $controller,
            "return 'Offline';"
        ),

    'Stable CSRF for preferences' =>
        str_contains(
            $blade,
            'data-csrf="{{ csrf_token() }}"'
        )
        && str_contains(
            $blade,
            'config.dataset.csrf'
        ),

    'Compact sidebar renderer' =>
        str_contains(
            $blade,
            'INTERNAL CHAT V3.3.1 COMPACT SIDEBAR'
        )
        && str_contains(
            $blade,
            'min-height:62px'
        )
        && str_contains(
            $blade,
            'width:34px'
        ),

    'Pin route preserved' =>
        Route::has(
            'admin.internal-chat.preference'
        ),

    'Realtime sidebar preserved' =>
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
