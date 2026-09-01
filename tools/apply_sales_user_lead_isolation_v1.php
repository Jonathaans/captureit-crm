<?php

/*
|--------------------------------------------------------------------------
| Sales User Lead Isolation V1
|--------------------------------------------------------------------------
|
| Result:
|
| Administrator:
|   sees all Leads
|
| Sales Admin:
|   sees all Leads
|
| Sales User:
|   sees only leads.user_id = logged-in user id
|
| Protection layers:
| 1. Eloquent global scope
| 2. LeadDataGrid raw query scope
| 3. Dashboard raw DB::table('leads...') scope, if such queries exist
| 4. Create/update owner forced back to logged-in Sales User
|
| Existing LeadController / DashboardController are NOT replaced.
|
*/

$projectRoot =
    realpath(
        __DIR__.'/..'
    );

if (! $projectRoot) {
    fwrite(
        STDERR,
        "Project root tidak ditemukan.\n"
    );
    exit(1);
}

function backupOnce(
    string $path,
    string $suffix
): void {
    $backup =
        $path
        .$suffix;

    if (
        is_file($path)
        && ! is_file($backup)
    ) {
        copy(
            $path,
            $backup
        );
    }
}

function phpFiles(
    string $root
): array {
    if (! is_dir($root)) {
        return [];
    }

    $result = [];

    $iterator =
        new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $root,
                FilesystemIterator::SKIP_DOTS
            )
        );

    foreach ($iterator as $file) {
        if (
            $file->isFile()
            && strtolower(
                $file->getExtension()
            ) === 'php'
        ) {
            $result[] =
                $file->getPathname();
        }
    }

    return $result;
}

function methodBounds(
    string $source,
    string $methodName
): ?array {
    if (
        ! preg_match(
            '/function\s+'
            .preg_quote(
                $methodName,
                '/'
            )
            .'\s*\([^)]*\)\s*(?::\s*[^{]+)?\{/m',
            $source,
            $match,
            PREG_OFFSET_CAPTURE
        )
    ) {
        return null;
    }

    $start =
        $match[0][1];

    $brace =
        strpos(
            $source,
            '{',
            $start
        );

    if ($brace === false) {
        return null;
    }

    $depth = 0;
    $length =
        strlen(
            $source
        );

    for (
        $index = $brace;
        $index < $length;
        $index++
    ) {
        $char =
            $source[$index];

        if ($char === '{') {
            $depth++;
        } elseif ($char === '}') {
            $depth--;

            if ($depth === 0) {
                return [
                    $start,
                    $index + 1,
                ];
            }
        }
    }

    return null;
}

/*
|--------------------------------------------------------------------------
| 1. REGISTER PROVIDER
|--------------------------------------------------------------------------
*/

$providerPath =
    $projectRoot
    .'/bootstrap/providers.php';

if (! is_file($providerPath)) {
    fwrite(
        STDERR,
        "bootstrap/providers.php tidak ditemukan.\n"
    );
    exit(2);
}

$providerSource =
    file_get_contents(
        $providerPath
    );

$provider =
    '\\Webkul\\Admin\\Providers\\SalesLeadIsolationServiceProvider::class';

if (
    str_contains(
        $providerSource,
        $provider
    )
) {
    echo "[SKIP] SalesLeadIsolationServiceProvider sudah terdaftar.\n";
} else {
    $end =
        strrpos(
            $providerSource,
            '];'
        );

    if ($end === false) {
        fwrite(
            STDERR,
            "Format bootstrap/providers.php tidak dikenali.\n"
        );
        exit(3);
    }

    backupOnce(
        $providerPath,
        '.before-sales-lead-isolation-v1.bak'
    );

    $providerSource =
        substr_replace(
            $providerSource,
            "    {$provider},\n",
            $end,
            0
        );

    file_put_contents(
        $providerPath,
        $providerSource
    );

    echo "[PASS] SalesLeadIsolationServiceProvider registered.\n";
}

/*
|--------------------------------------------------------------------------
| 2. PATCH LEAD DATAGRID
|--------------------------------------------------------------------------
|
| Krayin DataGrid commonly uses DB::table(), which bypasses Eloquent global
| scopes. Therefore LeadDataGrid gets an explicit raw-query owner scope.
|
*/

$dataGridMatches = [];

foreach (
    phpFiles(
        $projectRoot
        .'/packages'
    )
    as $path
) {
    if (
        basename(
            $path
        ) === 'LeadDataGrid.php'
    ) {
        $dataGridMatches[] =
            $path;
    }
}

if (
    count(
        $dataGridMatches
    ) !== 1
) {
    fwrite(
        STDERR,
        "LeadDataGrid.php expected 1 file, found "
        .count(
            $dataGridMatches
        )
        .". Patch dihentikan agar tidak mengubah file yang salah.\n"
    );

    foreach (
        $dataGridMatches
        as $match
    ) {
        fwrite(
            STDERR,
            " - {$match}\n"
        );
    }

    exit(4);
}

$dataGridPath =
    $dataGridMatches[0];

$dataGrid =
    file_get_contents(
        $dataGridPath
    );

$dataGridMarker =
    'SALES USER LEAD DATAGRID ISOLATION V1';

if (
    str_contains(
        $dataGrid,
        $dataGridMarker
    )
) {
    echo "[SKIP] LeadDataGrid owner scope sudah terpasang.\n";
} else {
    $bounds =
        methodBounds(
            $dataGrid,
            'prepareQueryBuilder'
        );

    if (! $bounds) {
        fwrite(
            STDERR,
            "prepareQueryBuilder() tidak ditemukan di LeadDataGrid.\n"
        );
        exit(5);
    }

    [
        $methodStart,
        $methodEnd,
    ] = $bounds;

    $method =
        substr(
            $dataGrid,
            $methodStart,
            $methodEnd
            - $methodStart
        );

    if (
        ! preg_match(
            '/return\s+\$([A-Za-z_][A-Za-z0-9_]*)\s*;/',
            $method,
            $returnMatch,
            PREG_OFFSET_CAPTURE
        )
    ) {
        fwrite(
            STDERR,
            "Return query builder tidak dikenali di LeadDataGrid.\n"
        );
        exit(6);
    }

    $queryVariable =
        $returnMatch[1][0];

    $returnOffsetInMethod =
        $returnMatch[0][1];

    $insertAt =
        $methodStart
        + $returnOffsetInMethod;

    $block =
        "        /* {$dataGridMarker} */\n"
        ."        app(\\Webkul\\Admin\\Services\\SalesLeadAccessService::class)\n"
        ."            ->scopeQuery(\${$queryVariable});\n\n";

    backupOnce(
        $dataGridPath,
        '.before-sales-lead-isolation-v1.bak'
    );

    $dataGrid =
        substr_replace(
            $dataGrid,
            $block,
            $insertAt,
            0
        );

    file_put_contents(
        $dataGridPath,
        $dataGrid
    );

    echo "[PASS] LeadDataGrid scoped by logged-in Sales User owner.\n";
}

/*
|--------------------------------------------------------------------------
| 3. PATCH RAW DASHBOARD LEAD QUERIES, IF ANY
|--------------------------------------------------------------------------
|
| Repository/Eloquent dashboard queries are already protected by the global
| scope. Only raw DB::table('leads...') calls need an explicit wrapper.
|
*/

$dashboardRoots = [
    $projectRoot
        .'/packages/Webkul/Admin/src/Http/Controllers/Dashboard',

    $projectRoot
        .'/packages/Webkul/Admin/src/Services',

    $projectRoot
        .'/packages/Webkul/Admin/src/Repositories',
];

$patchedDashboardFiles = 0;
$patchedRawQueries = 0;

foreach ($dashboardRoots as $dashboardRoot) {
    foreach (
        phpFiles(
            $dashboardRoot
        )
        as $path
    ) {
        /*
         * Do not patch our own access service.
         */
        if (
            basename(
                $path
            ) === 'SalesLeadAccessService.php'
        ) {
            continue;
        }

        $source =
            file_get_contents(
                $path
            );

        if ($source === false) {
            continue;
        }

        $original =
            $source;

        /*
         * Exact raw patterns:
         * DB::table('leads')
         * DB::table("leads")
         * DB::table('leads as l')
         * \DB::table(...)
         * Fully-qualified Illuminate\Support\Facades\DB::table(...)
         */
        $pattern =
            '/(?<!scopeQuery\()'
            .'((?:\\\\?Illuminate\\\\Support\\\\Facades\\\\DB|\\\\?DB)::table\('
            .'([\'"])'
            .'leads(?:\s+(?:as\s+)?[A-Za-z_][A-Za-z0-9_]*)?'
            .'\2'
            .'\))/i';

        $source =
            preg_replace_callback(
                $pattern,
                function ($matches) use (&$patchedRawQueries) {
                    $patchedRawQueries++;

                    return
                        'app(\\Webkul\\Admin\\Services\\SalesLeadAccessService::class)'
                        .'->scopeQuery('
                        .$matches[1]
                        .')';
                },
                $source
            );

        if (
            $source !== null
            && $source !== $original
        ) {
            backupOnce(
                $path,
                '.before-sales-lead-isolation-v1.bak'
            );

            file_put_contents(
                $path,
                $source
            );

            $patchedDashboardFiles++;

            echo
                "[PASS] Dashboard/raw lead query scoped: "
                .$path
                ."\n";
        }
    }
}

if ($patchedDashboardFiles === 0) {
    echo
        "[INFO] Tidak ada raw DB::table('leads') dashboard query yang perlu dipatch. "
        ."Dashboard Lead repository/Eloquent akan mengikuti global scope.\n";
} else {
    echo
        "[PASS] Raw dashboard lead queries patched: "
        .$patchedRawQueries
        ." occurrence(s) in "
        .$patchedDashboardFiles
        ." file(s).\n";
}

echo "\n";
echo "SALES USER LEAD ISOLATION V1 selesai.\n";
echo "Administrator : ALL LEADS\n";
echo "Sales Admin   : ALL LEADS\n";
echo "Sales User    : OWN LEADS ONLY\n";
