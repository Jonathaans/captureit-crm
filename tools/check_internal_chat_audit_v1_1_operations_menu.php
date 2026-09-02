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

echo "INTERNAL CHAT AUDIT V1.1 MENU CHECK\n";
echo "==================================\n\n";

if (! is_file($path)) {
    echo "FAIL\n";
    echo " - menu.php missing\n";
    exit(1);
}

$source =
    file_get_contents($path);

$operationsParent =
    null;

if (
    preg_match_all(
        "/\[(.*?)\]/s",
        $source,
        $blocks
    )
) {
    foreach ($blocks[1] as $block) {
        if (
            ! str_contains(
                strtolower($block),
                'operations-dashboard'
            )
        ) {
            continue;
        }

        if (
            preg_match(
                "/'key'\s*=>\s*'([^']+)'/",
                $block,
                $keyMatch
            )
        ) {
            $operationsParent =
                $keyMatch[1];

            break;
        }
    }
}

$expectedKey =
    $operationsParent
        ? $operationsParent
            .'.internal-chat-audit'
        : null;

$checks = [
    'Operations Dashboard parent detected' =>
        ! empty(
            $operationsParent
        ),

    'Audit menu uses real parent' =>
        $expectedKey
        && (
            str_contains(
                $source,
                "'key'   => '".$expectedKey."'"
            )
            || str_contains(
                $source,
                "'key' => '".$expectedKey."'"
            )
        ),

    'Audit menu has name' =>
        str_contains(
            $source,
            "'name'  => 'Internal Chat Audit'"
        )
        || str_contains(
            $source,
            "'name' => 'Internal Chat Audit'"
        ),

    'Audit route preserved' =>
        str_contains(
            $source,
            "'route' => 'admin.operational-dashboard.internal-chat-audit.index'"
        ),

    'No orphan singular parent' =>
        ! (
            str_contains(
                $source,
                "'key'   => 'operational-dashboard.internal-chat-audit'"
            )
            || str_contains(
                $source,
                "'key' => 'operational-dashboard.internal-chat-audit'"
            )
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
echo " - Operations Dashboard parent: {$operationsParent}\n";
echo " - Audit menu key: {$expectedKey}\n";
