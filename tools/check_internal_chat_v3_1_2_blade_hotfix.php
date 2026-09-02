<?php

use Illuminate\Contracts\Console\Kernel;

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

echo "INTERNAL CHAT V3.1.2 BLADE HOTFIX CHECK\n";
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
    'Safe route template marker' =>
        str_contains(
            $source,
            'INTERNAL CHAT V3.1.2 SAFE ROUTE TEMPLATES'
        ),

    'Edit URL precomputed' =>
        str_contains(
            $source,
            '$crmEditMessageUrlTemplate'
        ),

    'Delete URL precomputed' =>
        str_contains(
            $source,
            '$crmDeleteMessageUrlTemplate'
        ),

    'Nested route array removed from JS @json' =>
        ! str_contains(
            $source,
            "const editUrlTemplate =\n"
            ."                    @json(\n"
            ."                        route("
        )
        && ! str_contains(
            $source,
            "const deleteUrlTemplate =\n"
            ."                    @json(\n"
            ."                        route("
        ),

    'Reply preserved' =>
        str_contains(
            $source,
            'data-reply-message'
        ),

    'Edit preserved' =>
        str_contains(
            $source,
            'data-edit-message'
        ),

    'Delete preserved' =>
        str_contains(
            $source,
            'data-delete-message'
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
