<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Route;

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

echo "INTERNAL CHAT V3.1.4 HARD ACTION CHECK\n";
echo "======================================\n\n";

if (! is_file($path)) {
    echo "FAIL\n";
    echo " - chat.blade.php missing\n";
    exit(1);
}

$source =
    file_get_contents(
        $path
    );

$checks = [
    'Reply route exists' =>
        Route::has(
            'admin.internal-chat.send'
        ),

    'Edit route exists' =>
        Route::has(
            'admin.internal-chat.messages.update'
        ),

    'Delete route exists' =>
        Route::has(
            'admin.internal-chat.messages.delete'
        ),

    'Hard fallback marker' =>
        str_contains(
            $source,
            'INTERNAL CHAT V3.1.4 HARD ACTION FALLBACK'
        ),

    'Reply global handler' =>
        str_contains(
            $source,
            'window.crmChatReplyAction'
        ),

    'Edit global handler' =>
        str_contains(
            $source,
            'window.crmChatEditAction'
        ),

    'Delete global handler' =>
        str_contains(
            $source,
            'window.crmChatDeleteAction'
        ),

    'Server Reply onclick' =>
        str_contains(
            $source,
            'onclick="return window.crmChatReplyAction(this, event);"'
        ),

    'Server Edit onclick' =>
        str_contains(
            $source,
            'onclick="return window.crmChatEditAction(this, event);"'
        ),

    'Server Delete onclick' =>
        str_contains(
            $source,
            'onclick="return window.crmChatDeleteAction(this, event);"'
        ),

    'Stable action base' =>
        str_contains(
            $source,
            'data-action-base='
        ),

    'Dynamic action hooks' =>
        str_contains(
            $source,
            'replyButton.onclick'
        )
        && str_contains(
            $source,
            'editButton.onclick'
        )
        && str_contains(
            $source,
            'deleteButton.onclick'
        ),

    'Read receipt preserved' =>
        str_contains(
            $source,
            'data-read-receipt'
        ),

    'Attachment preserved' =>
        str_contains(
            $source,
            'crm-chat-attachments'
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
