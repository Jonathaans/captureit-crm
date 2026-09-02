<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

require __DIR__.'/../vendor/autoload.php';

$app =
    require_once __DIR__.'/../bootstrap/app.php';

$app->make(
    Kernel::class
)->bootstrap();

echo "INTERNAL CHAT V3.3.3 NATIVE PIN/MUTE CHECK\n";
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

$methods =
    $route
        ? $route->methods()
        : [];

$checks = [
    'V3.3 schema present' =>
        Schema::hasColumn(
            'internal_conversation_members',
            'pinned_at'
        )
        && Schema::hasColumn(
            'internal_conversation_members',
            'muted_until'
        )
        && Schema::hasColumn(
            'internal_conversation_members',
            'mute_forever'
        ),

    'Preference route exists' =>
        $route !== null,

    'Preference route accepts POST' =>
        in_array(
            'POST',
            $methods,
            true
        ),

    'Controller accepts normal form POST' =>
        str_contains(
            $controller,
            '$request->expectsJson()'
        )
        && str_contains(
            $controller,
            'redirect()'
        ),

    'Native renderer marker' =>
        str_contains(
            $blade,
            'INTERNAL CHAT V3.3.3 NATIVE PIN MUTE RENDERER'
        ),

    'Exact route template' =>
        str_contains(
            $blade,
            "route('admin.internal-chat.preference'"
        )
        && str_contains(
            $blade,
            '__CID__'
        ),

    'Native Pin form' =>
        str_contains(
            $blade,
            'nativePreferenceForm'
        )
        && str_contains(
            $blade,
            "form.method =\n                        'POST'"
        ),

    'Native Mute form' =>
        str_contains(
            $blade,
            'id="crm-chat-v333-mute-form"'
        )
        && str_contains(
            $blade,
            'value="mute_1_hour"'
        )
        && str_contains(
            $blade,
            'value="unmute"'
        ),

    'V3.2.6 preview preserved' =>
        str_contains(
            $blade,
            'INTERNAL CHAT V3.2.6 UNIVERSAL PREVIEW TOOLBAR'
        ),

    'Reply/Edit/Delete preserved' =>
        str_contains(
            $blade,
            'window.crmChatReplyAction'
        )
        && str_contains(
            $blade,
            'window.crmChatEditAction'
        )
        && str_contains(
            $blade,
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
