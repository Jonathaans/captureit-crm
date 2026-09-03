<?php

declare(strict_types=1);

/**
 * CHECK INTERNAL CHAT AUDIT ACL PARENT V1
 *
 * Reproduces the effective ACL tree logic used by Webkul\Core\Acl.
 */

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Arr;

$root =
    dirname(__DIR__);

require
    $root
    . '/vendor/autoload.php';

$app =
    require_once
    $root
    . '/bootstrap/app.php';

$app->make(
    Kernel::class
)->bootstrap();

$oldKey =
    'operational-dashboard.internal-chat-audit';

$newKey =
    'internal-chat-audit';

$items =
    config('acl');

$checks = [];

$keys =
    [];

foreach ($items as $item) {
    if (is_array($item) && isset($item['key'])) {
        $keys[] =
            (string) $item['key'];
    }
}

$checks[
    'Old dotted audit ACL key hilang'
] =
    !in_array(
        $oldKey,
        $keys,
        true
    );

$checks[
    'Standalone internal-chat-audit ACL key tersedia'
] =
    count(
        array_filter(
            $keys,
            fn ($key) =>
                $key === $newKey
        )
    ) === 1;

/*
 * Reproduce Webkul\Core\Acl::prepareAclItems().
 */
$dot =
    [];

foreach ($items as $item) {
    if (
        is_array($item)
        && isset($item['key'])
    ) {
        $dot[
            $item['key']
        ] =
            $item;
    }
}

$tree =
    Arr::undot(
        Arr::dot(
            $dot
        )
    );

$missingNamePaths = [];

$walk =
    function (
        array $node,
        string $path
    ) use (
        &$walk,
        &$missingNamePaths
    ): void {
        if (
            !array_key_exists(
                'name',
                $node
            )
        ) {
            $missingNamePaths[] =
                $path;
        }

        foreach (
            $node
            as $key => $value
        ) {
            if (
                in_array(
                    $key,
                    [
                        'key',
                        'name',
                        'route',
                        'sort',
                    ],
                    true
                )
            ) {
                continue;
            }

            if (is_array($value)) {
                $walk(
                    $value,
                    $path
                    . '.'
                    . $key
                );
            }
        }
    };

foreach ($tree as $key => $node) {
    if (is_array($node)) {
        $walk(
            $node,
            (string) $key
        );
    }
}

$checks[
    'Tidak ada synthetic operational-dashboard node'
] =
    !array_key_exists(
        'operational-dashboard',
        $tree
    );

$checks[
    'Effective ACL tree tidak punya node missing name'
] =
    $missingNamePaths === [];

echo "CHECK INTERNAL CHAT AUDIT ACL PARENT V1\n";
echo "=======================================\n\n";

$failed = [];

foreach ($checks as $label => $ok) {
    echo
        ($ok ? '[OK]   ' : '[FAIL] ')
        . $label
        . PHP_EOL;

    if (!$ok) {
        $failed[] =
            $label;
    }
}

if ($missingNamePaths) {
    echo "\nMissing-name paths:\n";

    foreach ($missingNamePaths as $path) {
        echo "- {$path}\n";
    }
}

echo PHP_EOL;

if ($failed) {
    echo "HASIL: FAIL\n";
    exit(1);
}

echo "HASIL: PASS\n";
echo "\nTes browser:\n";
echo "1. Ctrl + Shift + R.\n";
echo "2. Buka Settings -> Roles.\n";
echo "3. Edit role yang sebelumnya error.\n";
echo "4. Halaman ACL/permission harus dapat dirender normal.\n";

exit(0);
