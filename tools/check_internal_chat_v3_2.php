<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Route;

require __DIR__.'/../vendor/autoload.php';

$app =
    require_once __DIR__.'/../bootstrap/app.php';

$app->make(
    Kernel::class
)->bootstrap();

echo "INTERNAL CHAT V3.2 CHECK\n";
echo "========================\n\n";

$controller =
    base_path(
        'packages/Webkul/Admin/src/Http/Controllers/InternalCommunication/InternalChatExperienceController.php'
    );

$blade =
    base_path(
        'packages/Webkul/Admin/src/Resources/views/internal-communication/chat.blade.php'
    );

$controllerSource =
    is_file($controller)
        ? file_get_contents($controller)
        : '';

$bladeSource =
    is_file($blade)
        ? file_get_contents($blade)
        : '';

$checks = [
    'Experience controller' =>
        is_file(
            $controller
        ),

    'Message Search route' =>
        Route::has(
            'admin.internal-chat.search'
        ),

    'Typing POST route' =>
        Route::has(
            'admin.internal-chat.typing'
        ),

    'Typing Status route' =>
        Route::has(
            'admin.internal-chat.typing-status'
        ),

    'Attachment Preview route' =>
        Route::has(
            'admin.internal-chat.attachments.preview'
        ),

    'Search authorization' =>
        str_contains(
            $controllerSource,
            'assertMember('
        )
        && str_contains(
            $controllerSource,
            "'like'"
        ),

    'Typing cache TTL' =>
        str_contains(
            $controllerSource,
            "now()->addSeconds(\n                    6"
        ),

    'Private attachment preview' =>
        str_contains(
            $controllerSource,
            "Storage::disk(\n                'local'"
        )
        && str_contains(
            $controllerSource,
            "'inline'"
        ),

    'Search UI' =>
        str_contains(
            $bladeSource,
            'crm-chat-search-modal'
        )
        && str_contains(
            $bladeSource,
            'window.crmChatSearchRun'
        ),

    'Attachment Preview UI' =>
        str_contains(
            $bladeSource,
            'crm-chat-attachment-preview-modal'
        )
        && str_contains(
            $bladeSource,
            'window.crmChatPreviewAttachment'
        ),

    'Typing Indicator UI' =>
        str_contains(
            $bladeSource,
            'crm-chat-typing-indicator'
        )
        && str_contains(
            $bladeSource,
            'pollTyping'
        ),

    'Reply preserved' =>
        str_contains(
            $bladeSource,
            'window.crmChatReplyAction'
        ),

    'Edit preserved' =>
        str_contains(
            $bladeSource,
            'window.crmChatEditAction'
        ),

    'Delete preserved' =>
        str_contains(
            $bladeSource,
            'window.crmChatDeleteAction'
        ),

    'Read receipts preserved' =>
        str_contains(
            $bladeSource,
            'data-read-receipt'
        ),

    'Message polling preserved' =>
        str_contains(
            $bladeSource,
            'pollMessages'
        )
        && str_contains(
            $bladeSource,
            '5000'
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
