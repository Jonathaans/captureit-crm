<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Route;

require __DIR__.'/../vendor/autoload.php';

$app =
    require_once __DIR__.'/../bootstrap/app.php';

$app->make(
    Kernel::class
)->bootstrap();

echo "INTERNAL CHAT V3.3.5 MUTE DIALOG CHECK\n";
echo "======================================\n\n";

$path =
    base_path(
        'packages/Webkul/Admin/src/Resources/views/internal-communication/chat.blade.php'
    );

if (! is_file($path)) {
    echo "FAIL\n";
    echo " - chat.blade.php missing\n";
    exit(1);
}

$source =
    file_get_contents(
        $path
    );

$route =
    Route::getRoutes()
        ->getByName(
            'admin.internal-chat.preference'
        );

$checks = [
    'V3.3.5 renderer' =>
        str_contains(
            $source,
            'INTERNAL CHAT V3.3.5 MODERN MUTE DIALOG RENDERER'
        ),

    'Global dialog' =>
        str_contains(
            $source,
            'id="crm-chat-v335-mute-dialog"'
        ),

    'Mute trigger anchor' =>
        str_contains(
            $source,
            "document.createElement(\n                                'a'"
        ),

    'Native mute form' =>
        str_contains(
            $source,
            'id="crm-chat-v335-mute-form"'
        ),

    'Mute actions' =>
        str_contains(
            $source,
            'value="mute_1_hour"'
        )
        && str_contains(
            $source,
            'value="mute_today"'
        )
        && str_contains(
            $source,
            'value="mute_forever"'
        )
        && str_contains(
            $source,
            'value="unmute"'
        ),

    'Pin form preserved' =>
        str_contains(
            $source,
            'nativePreferenceForm'
        ),

    'Preference route POST' =>
        $route
        && in_array(
            'POST',
            $route->methods(),
            true
        ),

    'Realtime sidebar preserved' =>
        str_contains(
            $source,
            'window.crmChatV33RefreshSidebar'
        ),

    'Preview preserved' =>
        str_contains(
            $source,
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
