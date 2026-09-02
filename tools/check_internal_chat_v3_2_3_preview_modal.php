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

echo "INTERNAL CHAT V3.2.3 PREVIEW + MODAL CHECK\n";
echo "==========================================\n\n";

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
    'V3.2.3 marker' =>
        str_contains(
            $source,
            'INTERNAL CHAT V3.2.3 PREVIEW + MODERN MODAL'
        ),

    'Server attachment name metadata' =>
        str_contains(
            $source,
            'data-attachment-name="{{ $attachment->original_name }}"'
        ),

    'Dynamic attachment name metadata' =>
        str_contains(
            $source,
            'link.dataset.attachmentName'
        ),

    'Dedicated pre-send preview container' =>
        str_contains(
            $source,
            'id="crm-chat-selected-preview"'
        ),

    'Direct file onchange' =>
        str_contains(
            $source,
            'onchange="window.crmChatRenderSelectedPreview(this);"'
        ),

    'Object URL preview' =>
        str_contains(
            $source,
            'URL.createObjectURL'
        ),

    'Sent image thumbnail decorator' =>
        str_contains(
            $source,
            'makeSentImagePreview'
        ),

    'Extension detection no longer relies on KB suffix' =>
        str_contains(
            $source,
            'fileNameFromLink'
        ),

    'Compact New Chat modal' =>
        str_contains(
            $source,
            'width:min(92vw,460px)'
        )
        && str_contains(
            $source,
            'max-height:min(72vh,620px)'
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

    'Preview route exists' =>
        Route::has(
            'admin.internal-chat.attachments.preview'
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
