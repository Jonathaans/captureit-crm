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
        'packages/Webkul/Admin/src/Config/menu.php'
    );

echo "INTERNAL CHAT AUDIT V1.2 MENU ICON CHECK\n";
echo "=======================================\n\n";

if (! is_file($path)) {
    echo "FAIL\n";
    echo " - menu.php missing\n";
    exit(1);
}

$source =
    file_get_contents(
        $path
    );

$pattern =
    "/\[\s*"
    ."'key'\s*=>\s*'([^']*internal-chat-audit)'\s*,"
    ."(.*?)"
    ."'route'\s*=>\s*'admin\.operational-dashboard\.internal-chat-audit\.index'\s*,"
    ."(.*?)"
    ."\]/s";

$block =
    '';

$key =
    '';

if (
    preg_match(
        $pattern,
        $source,
        $match
    ) === 1
) {
    $block =
        $match[0];

    $key =
        $match[1];
}

$checks = [
    'Audit menu block found' =>
        $block !== '',

    'Audit menu name exists' =>
        str_contains(
            $block,
            "'name'"
        )
        && str_contains(
            $block,
            'Internal Chat Audit'
        ),

    'Audit route exists in menu' =>
        str_contains(
            $block,
            'admin.operational-dashboard.internal-chat-audit.index'
        ),

    'Audit sort exists' =>
        str_contains(
            $block,
            "'sort'"
        ),

    'Audit icon-class exists' =>
        str_contains(
            $block,
            "'icon-class'"
        ),

    'Legacy icon key removed from audit block' =>
        ! preg_match(
            "/'icon'\s*=>/",
            $block
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
echo " - Audit menu key: {$key}\n";

foreach (array_keys($checks) as $label) {
    echo " - {$label}\n";
}
