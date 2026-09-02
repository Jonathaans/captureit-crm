<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Route;

require __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';

$app->make(Kernel::class)->bootstrap();

$path =
    base_path(
        'packages/Webkul/Admin/src/Resources/views/internal-communication/chat.blade.php'
    );

echo "INTERNAL CHAT V3.1.3 ACTION BUTTONS CHECK\n";
echo "=========================================\n\n";

if (! is_file($path)) {
    echo "FAIL\n - chat.blade.php missing\n";
    exit(1);
}

$source = file_get_contents($path);

$checks = [
    'Direct action binding marker' =>
        str_contains(
            $source,
            'INTERNAL CHAT V3.1.3 DIRECT ACTION BINDINGS'
        ),

    'Reply direct binding' =>
        str_contains(
            $source,
            'button.dataset.crmReplyBound'
        )
        && str_contains(
            $source,
            'setReply('
        ),

    'Edit direct binding' =>
        str_contains(
            $source,
            'button.dataset.crmEditBound'
        )
        && str_contains(
            $source,
            'setEdit('
        ),

    'Delete direct binding' =>
        str_contains(
            $source,
            'button.dataset.crmDeleteBound'
        )
        && str_contains(
            $source,
            'deleteMessageFromButton'
        ),

    'Dynamic message binding' =>
        str_contains(
            $source,
            'new MutationObserver'
        )
        && str_contains(
            $source,
            'messageActionObserver.observe'
        ),

    'Reply backend route' =>
        Route::has(
            'admin.internal-chat.send'
        ),

    'Edit backend route' =>
        Route::has(
            'admin.internal-chat.messages.update'
        ),

    'Delete backend route' =>
        Route::has(
            'admin.internal-chat.messages.delete'
        ),

    'Read receipts preserved' =>
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
