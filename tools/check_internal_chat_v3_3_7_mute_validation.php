<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Route;

require __DIR__.'/../vendor/autoload.php';

$app =
    require_once __DIR__.'/../bootstrap/app.php';

$app->make(
    Kernel::class
)->bootstrap();

echo "INTERNAL CHAT V3.3.7 MUTE VALIDATION CHECK\n";
echo "==========================================\n\n";

$bladePath =
    base_path(
        'packages/Webkul/Admin/src/Resources/views/internal-communication/chat.blade.php'
    );

$controllerPath =
    base_path(
        'packages/Webkul/Admin/src/Http/Controllers/InternalCommunication/InternalChatConversationController.php'
    );

$blade =
    is_file($bladePath)
        ? file_get_contents($bladePath)
        : '';

$controller =
    is_file($controllerPath)
        ? file_get_contents($controllerPath)
        : '';

$route =
    Route::getRoutes()
        ->getByName(
            'admin.internal-chat.preference'
        );

$checks = [
    'V3.3.7 renderer' =>
        str_contains(
            $blade,
            'INTERNAL CHAT V3.3.7 MUTE STATE VALIDATION'
        ),

    'Muted selector state' =>
        str_contains(
            $blade,
            '🔕 Muted'
        ),

    'Mute label badge' =>
        str_contains(
            $blade,
            'row.mute_label'
        )
        && str_contains(
            $blade,
            '#fffbeb'
        ),

    'Confirmation before submit' =>
        str_contains(
            $blade,
            'window.confirm'
        ),

    'Preference toast' =>
        str_contains(
            $blade,
            'crm-chat-v337-toast'
        )
        && str_contains(
            $blade,
            'internal_chat_preference_notice'
        ),

    'Controller notice' =>
        str_contains(
            $controller,
            'INTERNAL CHAT V3.3.7 PREFERENCE NOTICE'
        )
        && str_contains(
            $controller,
            'Conversation dimute selama 1 jam.'
        )
        && str_contains(
            $controller,
            'Mute conversation berhasil dinonaktifkan.'
        ),

    'Preference route POST' =>
        $route
        && in_array(
            'POST',
            $route->methods(),
            true
        ),

    'Pin preserved' =>
        str_contains(
            $blade,
            'nativePreferenceForm'
        ),

    'Realtime sidebar preserved' =>
        str_contains(
            $blade,
            'window.crmChatV33RefreshSidebar'
        ),

    'Preview preserved' =>
        str_contains(
            $blade,
            'INTERNAL CHAT V3.2.6 UNIVERSAL PREVIEW TOOLBAR'
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
