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

echo "INTERNAL CHAT V3.2.2 UI CHECK\n";
echo "=============================\n\n";

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
    'V3.2.2 marker' =>
        str_contains(
            $source,
            'INTERNAL CHAT V3.2.2 ROBUST UI INTERACTIONS'
        ),

    'New Chat hard handler' =>
        str_contains(
            $source,
            'window.crmNewChatOpen'
        )
        && str_contains(
            $source,
            'window.crmNewChatClose'
        ),

    'Search hard handler' =>
        str_contains(
            $source,
            'window.crmChatSearchOpen'
        )
        && str_contains(
            $source,
            'window.crmChatSearchRun'
        ),

    'Pre-send image preview' =>
        str_contains(
            $source,
            'URL.createObjectURL'
        )
        && str_contains(
            $source,
            'renderSelectedFiles'
        ),

    'Sent image thumbnail' =>
        str_contains(
            $source,
            'decorateSentAttachment'
        )
        && str_contains(
            $source,
            'crmInlinePreviewReady'
        ),

    'Attachment preview handler' =>
        str_contains(
            $source,
            'window.crmChatPreviewAttachment'
        ),

    'Typing fallback' =>
        str_contains(
            $source,
            'crmV322TypingBound'
        ),

    'Reply/Edit/Delete preserved' =>
        str_contains(
            $source,
            'window.crmChatReplyAction'
        )
        && str_contains(
            $source,
            'window.crmChatEditAction'
        )
        && str_contains(
            $source,
            'window.crmChatDeleteAction'
        ),

    'Search route exists' =>
        Route::has(
            'admin.internal-chat.search'
        ),

    'Attachment preview route exists' =>
        Route::has(
            'admin.internal-chat.attachments.preview'
        ),

    'Typing routes exist' =>
        Route::has(
            'admin.internal-chat.typing'
        )
        && Route::has(
            'admin.internal-chat.typing-status'
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
