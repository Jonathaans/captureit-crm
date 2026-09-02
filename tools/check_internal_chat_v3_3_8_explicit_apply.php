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

echo "INTERNAL CHAT V3.3.8 EXPLICIT APPLY CHECK\n";
echo "=========================================\n\n";

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
    'Preference schema' =>
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

    'Preference POST route' =>
        $route
        && in_array(
            'POST',
            $route->methods(),
            true
        ),

    'Preference controller update' =>
        str_contains(
            $controller,
            "->update(\n                    \$values"
        )
        || str_contains(
            $controller,
            "->update(\n                \$values"
        ),

    'V3.3.8 renderer' =>
        str_contains(
            $blade,
            'INTERNAL CHAT V3.3.8 EXPLICIT APPLY PREFERENCES'
        ),

    'Pin submit carries action' =>
        str_contains(
            $blade,
            "button.name =\n                        'action'"
        )
        && str_contains(
            $blade,
            "'unpin'\n                            : 'pin'"
        ),

    'Mute select carries action' =>
        str_contains(
            $blade,
            "select.name =\n                        'action'"
        )
        && str_contains(
            $blade,
            "select.required =\n                        true"
        ),

    'Explicit Apply button' =>
        str_contains(
            $blade,
            "apply.type =\n                        'submit'"
        )
        && str_contains(
            $blade,
            "apply.textContent =\n                        'Apply'"
        ),

    'No JS form submit in V3.3.8 renderer' =>
        ! str_contains(
            substr(
                $blade,
                strpos(
                    $blade,
                    'INTERNAL CHAT V3.3.8 EXPLICIT APPLY PREFERENCES'
                ),
                (
                    strpos(
                        $blade,
                        'let refreshing =',
                        strpos(
                            $blade,
                            'INTERNAL CHAT V3.3.8 EXPLICIT APPLY PREFERENCES'
                        )
                    )
                    ?: strlen(
                        $blade
                    )
                )
                - strpos(
                    $blade,
                    'INTERNAL CHAT V3.3.8 EXPLICIT APPLY PREFERENCES'
                )
            ),
            'form.submit()'
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
