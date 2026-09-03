<?php

declare(strict_types=1);

/**
 * CHECK ROLE MENU PERMISSION FIX V1
 *
 * Usage:
 * php tools/check_role_menu_permission_fix_v1.php
 * php tools/check_role_menu_permission_fix_v1.php 7
 */

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$root = dirname(__DIR__);

require $root . '/vendor/autoload.php';

$app = require_once $root . '/bootstrap/app.php';

$app->make(Kernel::class)->bootstrap();

$roleId =
    isset($argv[1])
        ? (int) $argv[1]
        : 7;

$aclItems =
    config('acl');

$expectedLabels = [
    'My Email',
    'Internal Chat Audit',
];

$optionalLabels = [
    'User Email Connections',
];

$resolved = [];

foreach ($aclItems as $item) {
    if (!is_array($item) || empty($item['key'])) {
        continue;
    }

    $raw =
        trim(
            (string) (
                $item['name']
                ?? ''
            )
        );

    $translated =
        $raw !== ''
            ? trim(
                (string) trans($raw)
            )
            : '';

    foreach (
        array_merge(
            $expectedLabels,
            $optionalLabels
        )
        as $label
    ) {
        if (
            strcasecmp($raw, $label) === 0
            || strcasecmp($translated, $label) === 0
        ) {
            $resolved[$label] =
                (string) $item['key'];
        }
    }
}

$allKeys =
    array_values(
        array_filter(
            array_map(
                static fn ($item) =>
                    is_array($item)
                        ? ($item['key'] ?? null)
                        : null,
                $aclItems
            )
        )
    );

if (
    !isset($resolved['My Email'])
    && in_array(
        'my-email',
        $allKeys,
        true
    )
) {
    $resolved['My Email'] =
        'my-email';
}

if (
    !isset($resolved['Internal Chat Audit'])
    && in_array(
        'internal-chat-audit',
        $allKeys,
        true
    )
) {
    $resolved['Internal Chat Audit'] =
        'internal-chat-audit';
}

$table = null;

foreach (['roles', 'user_roles', 'admin_roles'] as $candidate) {
    if (
        Schema::hasTable($candidate)
        && in_array(
            'permissions',
            Schema::getColumnListing($candidate),
            true
        )
    ) {
        $table =
            $candidate;

        break;
    }
}

echo "CHECK ROLE MENU PERMISSION FIX V1\n";
echo "=================================\n\n";

if (!$table) {
    echo "[FAIL] Role table tidak ditemukan.\n";
    exit(1);
}

$role =
    (array) DB::table($table)
        ->where('id', $roleId)
        ->first();

if (!$role) {
    echo "[FAIL] Role ID {$roleId} tidak ditemukan.\n";
    exit(1);
}

$raw =
    $role['permissions']
    ?? '';

$permissions =
    is_string($raw)
        ? (
            json_decode(
                $raw,
                true
            )
            ?: []
        )
        : (
            is_array($raw)
                ? $raw
                : []
        );

$failed = [];

echo
    '[INFO] Role: '
    . (
        $role['name']
        ?? $role['title']
        ?? $roleId
    )
    . PHP_EOL;

echo
    '[INFO] permission_type: '
    . (
        $role['permission_type']
        ?? '[n/a]'
    )
    . PHP_EOL
    . PHP_EOL;

foreach ($expectedLabels as $label) {
    $key =
        $resolved[$label]
        ?? null;

    $ok =
        $key !== null
        && in_array(
            $key,
            $permissions,
            true
        );

    echo
        ($ok ? '[OK]   ' : '[FAIL] ')
        . $label
        . ' => '
        . ($key ?? '[ACL key not found]')
        . PHP_EOL;

    if (!$ok) {
        $failed[] =
            $label;
    }
}

foreach ($optionalLabels as $label) {
    $key =
        $resolved[$label]
        ?? null;

    if (!$key) {
        echo
            '[WARN] '
            . $label
            . " ACL key tidak ditemukan di effective config.\n";

        continue;
    }

    $ok =
        in_array(
            $key,
            $permissions,
            true
        );

    echo
        ($ok ? '[OK]   ' : '[WARN] ')
        . $label
        . ' => '
        . $key
        . PHP_EOL;
}

echo PHP_EOL;

if ($failed) {
    echo "HASIL: FAIL\n";
    exit(1);
}

echo "HASIL: PASS\n";
echo "\nSetelah PASS:\n";
echo "1. Logout CRM.\n";
echo "2. Login kembali.\n";
echo "3. Ctrl + Shift + R.\n";
echo "4. Cek sidebar My Email dan Internal Chat Audit.\n";

exit(0);
