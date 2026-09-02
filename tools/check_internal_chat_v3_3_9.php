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

echo "INTERNAL CHAT V3.3.9 CHECK\n";
echo "==========================\n\n";

$chatControllerPath =
    base_path(
        'packages/Webkul/Admin/src/Http/Controllers/InternalCommunication/InternalChatController.php'
    );

$summaryControllerPath =
    base_path(
        'packages/Webkul/Admin/src/Http/Controllers/InternalCommunication/InternalChatConversationController.php'
    );

$bladePath =
    base_path(
        'packages/Webkul/Admin/src/Resources/views/internal-communication/chat.blade.php'
    );

$chatController =
    is_file($chatControllerPath)
        ? file_get_contents($chatControllerPath)
        : '';

$summaryController =
    is_file($summaryControllerPath)
        ? file_get_contents($summaryControllerPath)
        : '';

$blade =
    is_file($bladePath)
        ? file_get_contents($bladePath)
        : '';

$checks = [
    'Read cursor column' =>
        Schema::hasColumn(
            'internal_conversation_members',
            'last_read_message_id'
        ),

    'Read cursor sync' =>
        str_contains(
            $chatController,
            'INTERNAL CHAT V3.3.9 READ CURSOR SYNC'
        )
        && str_contains(
            $chatController,
            'syncReadMessageCursor'
        ),

    'Unread uses message id cursor' =>
        str_contains(
            $summaryController,
            "'last_read_message_id'"
        )
        && str_contains(
            $summaryController,
            "'id',\n                                '>',\n                                \$readCursor"
        ),

    'Mute notification guard' =>
        str_contains(
            $chatController,
            'MUTE NOTIFICATION GUARD'
        ),

    'Fit sidebar renderer' =>
        str_contains(
            $blade,
            'INTERNAL CHAT V3.3.9 FIT SIDEBAR'
        ),

    'Horizontal overflow blocked' =>
        str_contains(
            $blade,
            "list.style.overflowX =\n                    'hidden'"
        )
        && str_contains(
            $blade,
            'grid-template-columns:minmax(0,1fr) auto'
        ),

    'Compact controls' =>
        str_contains(
            $blade,
            "'width:34px;'"
        )
        && str_contains(
            $blade,
            "apply.textContent =\n                        '✓'"
        ),

    'Preference route' =>
        Route::has(
            'admin.internal-chat.preference'
        ),

    'Sidebar summary route' =>
        Route::has(
            'admin.internal-chat.sidebar-summary'
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
