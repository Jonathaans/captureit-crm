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

echo "INTERNAL CHAT V3.1.1 CHECK\n";
echo "==========================\n\n";

$controllerPath =
    base_path(
        'packages/Webkul/Admin/src/Http/Controllers/InternalCommunication/InternalChatController.php'
    );

$bladePath =
    base_path(
        'packages/Webkul/Admin/src/Resources/views/internal-communication/chat.blade.php'
    );

$widgetPath =
    base_path(
        'packages/Webkul/Admin/src/Resources/views/internal-communication/widget.blade.php'
    );

$badgePath =
    base_path(
        'packages/Webkul/Admin/src/Resources/views/internal-communication/chat-unread-badge.blade.php'
    );

$controller =
    is_file($controllerPath)
        ? file_get_contents($controllerPath)
        : '';

$blade =
    is_file($bladePath)
        ? file_get_contents($bladePath)
        : '';

$widget =
    is_file($widgetPath)
        ? file_get_contents($widgetPath)
        : '';

$badge =
    is_file($badgePath)
        ? file_get_contents($badgePath)
        : '';

$checks = [
    'Existing reply_to_message_id schema' =>
        Schema::hasColumn(
            'internal_messages',
            'reply_to_message_id'
        ),

    'Existing edited_at schema' =>
        Schema::hasColumn(
            'internal_messages',
            'edited_at'
        ),

    'Existing deleted_at schema' =>
        Schema::hasColumn(
            'internal_messages',
            'deleted_at'
        ),

    'Existing last_read_at schema' =>
        Schema::hasColumn(
            'internal_conversation_members',
            'last_read_at'
        ),

    'Unread summary route' =>
        Route::has(
            'admin.internal-chat.unread-summary'
        ),

    'Edit route' =>
        Route::has(
            'admin.internal-chat.messages.update'
        ),

    'Delete route' =>
        Route::has(
            'admin.internal-chat.messages.delete'
        ),

    'Reply backend' =>
        str_contains(
            $controller,
            "'reply_to_message_id'"
        ),

    'Edit backend ownership guard' =>
        str_contains(
            $controller,
            'public function updateMessage('
        )
        && str_contains(
            $controller,
            "->where('user_id', \$user->id)"
        ),

    'Soft delete backend' =>
        str_contains(
            $controller,
            'public function deleteMessage('
        )
        && str_contains(
            $controller,
            '$message->deleted_at = now();'
        ),

    'Edit/delete polling synchronization' =>
        str_contains(
            $controller,
            "'changed_messages'"
        )
        && str_contains(
            $controller,
            "'deleted_message_ids'"
        )
        && str_contains(
            $blade,
            'sync_after'
        ),

    'Reply UI' =>
        str_contains(
            $blade,
            'crm-chat-reply-preview'
        )
        && str_contains(
            $blade,
            'data-reply-message'
        ),

    'Edit UI' =>
        str_contains(
            $blade,
            'data-edit-message'
        ),

    'Delete UI' =>
        str_contains(
            $blade,
            'data-delete-message'
        ),

    'Read receipts preserved' =>
        str_contains(
            $blade,
            'data-read-receipt'
        )
        && str_contains(
            $blade,
            'text-blue-400'
        ),

    'Attachments preserved' =>
        str_contains(
            $blade,
            'crm-chat-attachments'
        )
        && str_contains(
            $blade,
            'admin.internal-chat.attachments.download'
        ),

    /*
     * V3.1 checker sebelumnya salah mencari:
     * data.globalChatUnreadBadge
     *
     * Implementasi aktual memakai:
     * badge.dataset.globalChatUnreadBadge
     * dan selector:
     * [data-global-chat-unread-badge]
     */
    'Global badge partial installed' =>
        is_file(
            $badgePath
        )
        && (
            str_contains(
                $badge,
                'badge.dataset.globalChatUnreadBadge'
            )
            || str_contains(
                $badge,
                'data-global-chat-unread-badge'
            )
        )
        && str_contains(
            $badge,
            'pollUnread'
        )
        && str_contains(
            $badge,
            '5000'
        ),

    'Global widget includes unread badge' =>
        str_contains(
            $widget,
            "@include('admin::internal-communication.chat-unread-badge')"
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
