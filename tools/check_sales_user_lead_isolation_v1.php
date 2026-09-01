<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

require __DIR__.'/../vendor/autoload.php';

$app =
    require_once __DIR__.'/../bootstrap/app.php';

$app->make(
    Kernel::class
)->bootstrap();

echo "SALES USER LEAD ISOLATION CHECK\n";
echo "===============================\n\n";

$errors = [];
$warnings = [];

if (
    ! Schema::hasTable(
        'leads'
    )
) {
    $errors[] =
        'Table leads tidak ditemukan.';
} elseif (
    ! Schema::hasColumn(
        'leads',
        'user_id'
    )
) {
    $errors[] =
        'Kolom leads.user_id tidak ditemukan.';
}

$providerPath =
    base_path(
        'bootstrap/providers.php'
    );

if (
    ! is_file($providerPath)
    || ! str_contains(
        file_get_contents(
            $providerPath
        ),
        'SalesLeadIsolationServiceProvider::class'
    )
) {
    $errors[] =
        'SalesLeadIsolationServiceProvider belum registered.';
}

$dataGridMatches = [];

$iterator =
    new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(
            base_path('packages'),
            FilesystemIterator::SKIP_DOTS
        )
    );

foreach ($iterator as $file) {
    if (
        $file->isFile()
        && $file->getFilename()
            === 'LeadDataGrid.php'
    ) {
        $dataGridMatches[] =
            $file->getPathname();
    }
}

if (
    count(
        $dataGridMatches
    ) !== 1
) {
    $errors[] =
        'LeadDataGrid count bukan 1: '
        .count(
            $dataGridMatches
        );
} else {
    $source =
        file_get_contents(
            $dataGridMatches[0]
        );

    if (
        ! str_contains(
            $source,
            'SALES USER LEAD DATAGRID ISOLATION V1'
        )
    ) {
        $errors[] =
            'LeadDataGrid raw-query scope belum terpasang.';
    }
}

/*
 * Detect unwrapped raw dashboard lead queries.
 */
$scanRoots = [
    base_path(
        'packages/Webkul/Admin/src/Http/Controllers/Dashboard'
    ),
    base_path(
        'packages/Webkul/Admin/src/Services'
    ),
    base_path(
        'packages/Webkul/Admin/src/Repositories'
    ),
];

foreach ($scanRoots as $root) {
    if (! is_dir($root)) {
        continue;
    }

    $iterator =
        new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $root,
                FilesystemIterator::SKIP_DOTS
            )
        );

    foreach ($iterator as $file) {
        if (
            ! $file->isFile()
            || strtolower(
                $file->getExtension()
            ) !== 'php'
        ) {
            continue;
        }

        if (
            $file->getFilename()
            === 'SalesLeadAccessService.php'
        ) {
            continue;
        }

        $source =
            file_get_contents(
                $file->getPathname()
            );

        if (
            preg_match(
                '/(?:\\\\?Illuminate\\\\Support\\\\Facades\\\\DB|\\\\?DB)::table\([\'"]leads(?:\s+(?:as\s+)?[A-Za-z_][A-Za-z0-9_]*)?[\'"]\)/i',
                $source
            )
            && ! str_contains(
                $source,
                'SalesLeadAccessService::class'
            )
        ) {
            $warnings[] =
                'Raw dashboard lead query masih ditemukan: '
                .$file->getPathname();
        }
    }
}

if ($errors) {
    echo "FAIL\n";

    foreach ($errors as $error) {
        echo " - {$error}\n";
    }

    exit(1);
}

echo "PASS\n";
echo "Backend + LeadDataGrid isolation ready.\n\n";

if ($warnings) {
    echo "WARNINGS\n";

    foreach ($warnings as $warning) {
        echo " - {$warning}\n";
    }

    echo "\n";
}

/*
 * Ownership overview.
 */
if (
    Schema::hasTable('users')
    && Schema::hasTable('roles')
    && Schema::hasColumn(
        'users',
        'role_id'
    )
) {
    $salesUsers =
        DB::table('users')
            ->join(
                'roles',
                'roles.id',
                '=',
                'users.role_id'
            )
            ->whereRaw(
                'LOWER(roles.name) = ?',
                [
                    'sales user',
                ]
            )
            ->select(
                'users.id',
                'users.name',
                'roles.name as role_name'
            )
            ->orderBy(
                'users.id'
            )
            ->get();

    echo "Sales User ownership preview\n";
    echo "----------------------------\n";

    if ($salesUsers->isEmpty()) {
        echo "No Sales User found.\n";
    }

    foreach ($salesUsers as $user) {
        $leadCount =
            DB::table('leads')
                ->where(
                    'user_id',
                    $user->id
                )
                ->count();

        echo
            '#'
            .$user->id
            .' '
            .$user->name
            .' -> '
            .$leadCount
            ." owned lead(s)\n";
    }

    $unowned =
        DB::table('leads')
            ->whereNull(
                'user_id'
            )
            ->count();

    echo "\nUnowned Leads: {$unowned}\n";
}

echo "\nExpected browser behavior:\n";
echo "Administrator -> all Leads\n";
echo "Sales Admin   -> all Leads\n";
echo "Sales User    -> own Leads only\n";
echo "Other user's Lead direct URL -> not accessible\n";
