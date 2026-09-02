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

echo "INTERNAL CHAT V3.2.5 IMAGE TOOLBAR CHECK\n";
echo "========================================\n\n";

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
    'V3.2.5 marker' =>
        str_contains(
            $source,
            'INTERNAL CHAT V3.2.5 COMPACT IMAGE PREVIEW TOOLBAR'
        ),

    'Compact modal height' =>
        str_contains(
            $source,
            'height:min(78vh,680px)'
        ),

    'Compact image size' =>
        str_contains(
            $source,
            'max-width:86%'
        )
        && str_contains(
            $source,
            'max-height:calc(78vh - 155px)'
        ),

    'Top download save' =>
        str_contains(
            $source,
            '⬇ Download / Save'
        ),

    'Back action visible' =>
        str_contains(
            $source,
            '← Back'
        ),

    'Bottom download action' =>
        str_contains(
            $source,
            'id="crm-chat-attachment-download-bottom"'
        ),

    'PDF preview route preserved' =>
        Route::has(
            'admin.internal-chat.attachments.preview'
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
