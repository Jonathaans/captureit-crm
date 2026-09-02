<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Route;

require __DIR__.'/../vendor/autoload.php';

$app =
    require_once __DIR__.'/../bootstrap/app.php';

$app->make(
    Kernel::class
)->bootstrap();

echo "INTERNAL CHAT V3.3.6 NATIVE MUTE SELECT CHECK\n";
echo "=============================================\n\n";

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
    'V3.3.6 marker' =>
        str_contains(
            $source,
            'INTERNAL CHAT V3.3.6 NATIVE MUTE SELECT'
        ),

    'Native mute select' =>
        str_contains(
            $source,
            'nativeMuteSelect'
        )
        && str_contains(
            $source,
            "'select'"
        ),

    'All mute actions' =>
        str_contains(
            $source,
            "'mute_1_hour'"
        )
        && str_contains(
            $source,
            "'mute_today'"
        )
        && str_contains(
            $source,
            "'mute_forever'"
        )
        && str_contains(
            $source,
            "'unmute'"
        ),

    'Native form submit' =>
        str_contains(
            $source,
            'form.submit()'
        ),

    'Refresh pause guard' =>
        str_contains(
            $source,
            'window.__crmMuteSelectOpen'
        ),

    'Pin native form preserved' =>
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
