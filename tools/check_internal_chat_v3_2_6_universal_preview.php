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

echo "INTERNAL CHAT V3.2.6 UNIVERSAL PREVIEW CHECK\n";
echo "============================================\n\n";

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
    'V3.2.6 marker' =>
        str_contains(
            $source,
            'INTERNAL CHAT V3.2.6 UNIVERSAL PREVIEW TOOLBAR'
        ),

    'Image stage' =>
        str_contains(
            $source,
            'id="crm-chat-image-preview-stage"'
        ),

    'Image element' =>
        str_contains(
            $source,
            'id="crm-chat-attachment-preview-image"'
        ),

    'PDF frame' =>
        str_contains(
            $source,
            'id="crm-chat-attachment-preview-frame"'
        ),

    'Top download' =>
        str_contains(
            $source,
            '⬇ Download / Save'
        ),

    'Bottom download' =>
        str_contains(
            $source,
            'id="crm-chat-attachment-download-bottom"'
        ),

    'Back buttons' =>
        substr_count(
            $source,
            '← Back'
        ) >= 2,

    'Compact preview height' =>
        str_contains(
            $source,
            'height:min(78vh,680px)'
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
