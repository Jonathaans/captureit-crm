<?php

declare(strict_types=1);

/**
 * ROLE MENU PERMISSION FIX V1
 *
 * Tujuan:
 * - Role default: ID 7 (sesuai URL /admin/settings/roles/edit/7)
 * - Tambahkan permission:
 *   * My Email
 *   * User Email Connections
 *   * Internal Chat Audit
 * - Tidak mengubah menu.php / acl.php / Webkul\Core\Acl.php
 * - Menemukan key ACL berdasarkan effective config('acl')
 * - Menyimpan permission ke role yang sudah ada
 * - Backup record role ke JSON sebelum update
 *
 * Usage:
 * php tools/apply_role_menu_permission_fix_v1.php
 * php tools/apply_role_menu_permission_fix_v1.php 7
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

if ($roleId < 1) {
    fwrite(STDERR, "Role ID tidak valid.\n");
    exit(1);
}

echo "ROLE MENU PERMISSION FIX V1\n";
echo "===========================\n";
echo "Role ID: {$roleId}\n\n";

/*
|--------------------------------------------------------------------------
| 1. Resolve target ACL keys from runtime config
|--------------------------------------------------------------------------
*/

$aclItems = config('acl');

if (!is_array($aclItems)) {
    fwrite(STDERR, "config('acl') tidak berupa array.\n");
    exit(1);
}

$targetsByLabel = [
    'My Email',
    'User Email Connections',
    'Internal Chat Audit',
];

$resolved = [];

foreach ($aclItems as $item) {
    if (!is_array($item) || empty($item['key'])) {
        continue;
    }

    $rawName =
        (string) ($item['name'] ?? '');

    $translated =
        $rawName !== ''
            ? (string) trans($rawName)
            : '';

    foreach ($targetsByLabel as $label) {
        if (
            strcasecmp(trim($rawName), $label) === 0
            || strcasecmp(trim($translated), $label) === 0
        ) {
            $resolved[$label] =
                (string) $item['key'];
        }
    }
}

/*
 * Exact-key fallbacks for the two keys already confirmed in this project.
 */
$knownFallbacks = [
    'My Email' =>
        'my-email',

    'Internal Chat Audit' =>
        'internal-chat-audit',
];

$allAclKeys =
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

foreach ($knownFallbacks as $label => $key) {
    if (
        !isset($resolved[$label])
        && in_array($key, $allAclKeys, true)
    ) {
        $resolved[$label] =
            $key;
    }
}

echo "Resolved ACL targets:\n";

foreach ($targetsByLabel as $label) {
    echo
        '- '
        . $label
        . ' => '
        . ($resolved[$label] ?? '[NOT FOUND]')
        . PHP_EOL;
}

echo PHP_EOL;

$requiredLabels = [
    'My Email',
    'Internal Chat Audit',
];

foreach ($requiredLabels as $label) {
    if (!isset($resolved[$label])) {
        fwrite(
            STDERR,
            "ACL wajib '{$label}' tidak ditemukan. Patch dibatalkan.\n"
        );

        exit(1);
    }
}

/*
|--------------------------------------------------------------------------
| 2. Detect role table
|--------------------------------------------------------------------------
*/

$tableCandidates = [
    'roles',
    'user_roles',
    'admin_roles',
];

$roleTable = null;

foreach ($tableCandidates as $table) {
    if (!Schema::hasTable($table)) {
        continue;
    }

    $columns =
        Schema::getColumnListing($table);

    if (
        in_array('id', $columns, true)
        && (
            in_array('permissions', $columns, true)
            || in_array('permission_type', $columns, true)
        )
    ) {
        $roleTable =
            $table;

        break;
    }
}

if (!$roleTable) {
    /*
     * Fallback via information_schema.
     */
    try {
        $dbName =
            DB::connection()
                ->getDatabaseName();

        $tables =
            DB::table('information_schema.tables')
                ->where('table_schema', $dbName)
                ->where('table_name', 'like', '%role%')
                ->pluck('table_name');

        foreach ($tables as $table) {
            $table =
                (string) $table;

            if (!Schema::hasTable($table)) {
                continue;
            }

            $columns =
                Schema::getColumnListing($table);

            if (
                in_array('id', $columns, true)
                && in_array('permissions', $columns, true)
            ) {
                $roleTable =
                    $table;

                break;
            }
        }
    } catch (Throwable) {
    }
}

if (!$roleTable) {
    fwrite(
        STDERR,
        "Table role dengan kolom permissions tidak ditemukan.\n"
    );

    exit(1);
}

$roleColumns =
    Schema::getColumnListing($roleTable);

echo "Role table: {$roleTable}\n";

/*
|--------------------------------------------------------------------------
| 3. Load role
|--------------------------------------------------------------------------
*/

$role =
    DB::table($roleTable)
        ->where('id', $roleId)
        ->first();

if (!$role) {
    fwrite(
        STDERR,
        "Role ID {$roleId} tidak ditemukan di table {$roleTable}.\n"
    );

    exit(1);
}

$roleArray =
    (array) $role;

echo
    'Role name: '
    . (
        $roleArray['name']
        ?? $roleArray['title']
        ?? '[unknown]'
    )
    . PHP_EOL;

echo
    'permission_type: '
    . (
        $roleArray['permission_type']
        ?? '[column not found]'
    )
    . PHP_EOL
    . PHP_EOL;

/*
|--------------------------------------------------------------------------
| 4. Backup DB role record
|--------------------------------------------------------------------------
*/

$backupDir =
    $root
    . '/storage/app/role-permission-backups';

if (!is_dir($backupDir)) {
    mkdir(
        $backupDir,
        0775,
        true
    );
}

$backupPath =
    $backupDir
    . '/role-'
    . $roleId
    . '-before-menu-permission-fix-v1-'
    . date('Ymd-His')
    . '.json';

file_put_contents(
    $backupPath,
    json_encode(
        [
            'table' =>
                $roleTable,

            'role' =>
                $roleArray,

            'resolved_acl' =>
                $resolved,
        ],
        JSON_PRETTY_PRINT
        | JSON_UNESCAPED_SLASHES
        | JSON_UNESCAPED_UNICODE
    )
);

echo "Backup DB record:\n{$backupPath}\n\n";

/*
|--------------------------------------------------------------------------
| 5. Decode existing permissions
|--------------------------------------------------------------------------
*/

$currentRaw =
    $roleArray['permissions']
    ?? [];

$currentPermissions = [];

if (is_array($currentRaw)) {
    $currentPermissions =
        $currentRaw;
} elseif (is_string($currentRaw)) {
    $trimmed =
        trim($currentRaw);

    if ($trimmed !== '') {
        $decoded =
            json_decode(
                $trimmed,
                true
            );

        if (is_array($decoded)) {
            $currentPermissions =
                $decoded;
        } else {
            /*
             * Conservative fallback for comma-separated legacy storage.
             */
            $currentPermissions =
                array_values(
                    array_filter(
                        array_map(
                            'trim',
                            explode(
                                ',',
                                $trimmed
                            )
                        )
                    )
                );
        }
    }
}

$currentPermissions =
    array_values(
        array_unique(
            array_map(
                static fn ($value) =>
                    (string) $value,
                $currentPermissions
            )
        )
    );

$keysToAdd =
    array_values(
        array_unique(
            array_values(
                $resolved
            )
        )
    );

$newPermissions =
    array_values(
        array_unique(
            array_merge(
                $currentPermissions,
                $keysToAdd
            )
        )
    );

sort(
    $newPermissions
);

echo
    'Existing permission count: '
    . count($currentPermissions)
    . PHP_EOL;

echo
    'New permission count     : '
    . count($newPermissions)
    . PHP_EOL;

echo "Adding:\n";

foreach ($keysToAdd as $key) {
    echo "- {$key}\n";
}

echo PHP_EOL;

/*
|--------------------------------------------------------------------------
| 6. Update role
|--------------------------------------------------------------------------
*/

$update = [];

if (in_array('permissions', $roleColumns, true)) {
    $update['permissions'] =
        json_encode(
            $newPermissions,
            JSON_UNESCAPED_SLASHES
            | JSON_UNESCAPED_UNICODE
        );
}

if (in_array('permission_type', $roleColumns, true)) {
    /*
     * Keep "all" if the role already has full access.
     * Otherwise ensure custom permissions are respected.
     */
    $currentType =
        strtolower(
            trim(
                (string) (
                    $roleArray['permission_type']
                    ?? ''
                )
            )
        );

    if ($currentType !== 'all') {
        $update['permission_type'] =
            'custom';
    }
}

if (in_array('updated_at', $roleColumns, true)) {
    $update['updated_at'] =
        now();
}

if (!$update) {
    fwrite(
        STDERR,
        "Tidak ada kolom role yang dapat di-update.\n"
    );

    exit(1);
}

DB::table($roleTable)
    ->where('id', $roleId)
    ->update($update);

/*
|--------------------------------------------------------------------------
| 7. Verify persisted value
|--------------------------------------------------------------------------
*/

$after =
    (array) DB::table($roleTable)
        ->where('id', $roleId)
        ->first();

$afterRaw =
    $after['permissions']
    ?? '';

$afterPermissions =
    is_string($afterRaw)
        ? (
            json_decode(
                $afterRaw,
                true
            )
            ?: []
        )
        : (
            is_array($afterRaw)
                ? $afterRaw
                : []
        );

$missing = [];

foreach ($keysToAdd as $key) {
    if (!in_array($key, $afterPermissions, true)) {
        $missing[] =
            $key;
    }
}

if ($missing) {
    fwrite(
        STDERR,
        "VERIFY FAIL. Permission belum tersimpan:\n- "
        . implode(
            "\n- ",
            $missing
        )
        . "\n"
    );

    exit(1);
}

echo "VERIFY PASS.\n";

echo
    'permission_type after: '
    . (
        $after['permission_type']
        ?? '[column not found]'
    )
    . PHP_EOL;

echo "\nClearing Laravel cache...\n";

chdir($root);

passthru(
    escapeshellarg(PHP_BINARY)
    . ' '
    . escapeshellarg($root . '/artisan')
    . ' optimize:clear',
    $clearCode
);

if ($clearCode !== 0) {
    echo
        "\nPERINGATAN: optimize:clear exit code {$clearCode}.\n";
}

echo "\nSELESAI.\n";
echo "Logout dari CRM lalu login kembali agar permission session/runtime dimuat ulang.\n";
echo "Setelah login tekan Ctrl+Shift+R.\n";
