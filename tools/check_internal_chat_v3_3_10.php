<?php

use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';

$app =
    require_once __DIR__.'/../bootstrap/app.php';

$app->make(
    Kernel::class
)->bootstrap();

echo "INTERNAL CHAT V3.3.10 CHECK\n";
echo "===========================\n\n";

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

$checks = [
    'Authoritative unread renderer' =>
        str_contains(
            $source,
            'INTERNAL CHAT V3.3.10 AUTHORITATIVE UNREAD'
        ),

    'Unread badge owned by V3.3.10' =>
        str_contains(
            $source,
            'data-crm-v3310-unread'
        ),

    'Row marker' =>
        str_contains(
            $source,
            'crmV3310Row'
        ),

    'Legacy unread cleanup' =>
        str_contains(
            $source,
            'crmChatV3310CleanupLegacyUnread'
        ),

    'Legacy re-injection observer' =>
        str_contains(
            $source,
            'MutationObserver'
        ),

    'Bounded two-column row' =>
        str_contains(
            $source,
            'grid-template-columns:minmax(0,1fr) 82px'
        ),

    'Horizontal overflow blocked' =>
        str_contains(
            $source,
            "list.style.overflowX =\n                    'hidden'"
        ),

    'Compact preference controls' =>
        str_contains(
            $source,
            "'width:31px;'"
        )
        && str_contains(
            $source,
            "'width:23px;'"
        ),

    'V3.2.6 preview preserved' =>
        str_contains(
            $source,
            'INTERNAL CHAT V3.2.6 UNIVERSAL PREVIEW TOOLBAR'
        ),

    'Realtime sidebar preserved' =>
        str_contains(
            $source,
            'window.crmChatV33RefreshSidebar'
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
