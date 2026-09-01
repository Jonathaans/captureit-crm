<?php

/*
|--------------------------------------------------------------------------
| Purchase Order Expense CSV Export V1
|--------------------------------------------------------------------------
|
| Adds:
| - Dedicated export controller (already copied by ZIP extraction)
| - GET purchase-orders/export-expense-csv
| - ACL purchase-orders.export
| - "Export Expense CSV" button on current PO index
|
| Existing customized PurchaseOrderController is NOT replaced.
| Existing PO calculations / release / expense posting are NOT changed.
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
        is_file(
            $path
        )
        && ! is_file(
            $backup
        )
    ) {
        copy(
            $path,
            $backup
        );
    }
}

function recursiveFiles(
    string $root,
    string $suffix
): array {
    $files = [];

    if (! is_dir($root)) {
        return $files;
    }

    $iterator =
        new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $root,
                FilesystemIterator::SKIP_DOTS
            )
        );

    foreach (
        $iterator
        as $file
    ) {
        if (
            $file->isFile()
            && str_ends_with(
                strtolower(
                    $file->getFilename()
                ),
                strtolower(
                    $suffix
                )
            )
        ) {
            $files[] =
                $file->getPathname();
        }
    }

    return $files;
}

function findExactlyOne(
    array $matches,
    string $label
): string {
    if (
        count(
            $matches
        ) !== 1
    ) {
        fwrite(
            STDERR,
            "{$label}: expected 1 file, found "
            .count(
                $matches
            )
            .".\n"
        );

        foreach (
            $matches
            as $match
        ) {
            fwrite(
                STDERR,
                " - {$match}\n"
            );
        }

        exit(10);
    }

    return $matches[0];
}

/*
|--------------------------------------------------------------------------
| 1. ROUTE
|--------------------------------------------------------------------------
*/

$routeMatches = [];

foreach (
    recursiveFiles(
        $projectRoot
        .'/packages/Webkul/Admin/src/Routes',
        '.php'
    )
    as $path
) {
    $source =
        file_get_contents(
            $path
        );

    if (
        $source !== false
        && str_contains(
            $source,
            'admin.purchase-orders.index'
        )
        && str_contains(
            $source,
            'PurchaseOrderController'
        )
    ) {
        $routeMatches[] =
            $path;
    }
}

$routePath =
    findExactlyOne(
        $routeMatches,
        'Purchase Order route file'
    );

$routeSource =
    file_get_contents(
        $routePath
    );

$routeMarker =
    'PO EXPENSE CSV EXPORT V1 ROUTE';

if (
    str_contains(
        $routeSource,
        $routeMarker
    )
    || str_contains(
        $routeSource,
        'admin.purchase-orders.export-expense'
    )
) {
    echo "[SKIP] PO Expense CSV route sudah ada.\n";
} else {
    $controllerNeedle =
        '\\Webkul\\Admin\\Http\\Controllers\\PurchaseOrder\\PurchaseOrderController::class';

    $controllerPos =
        strpos(
            $routeSource,
            $controllerNeedle
        );

    if (
        $controllerPos
        === false
    ) {
        /*
         * Support source that imports PurchaseOrderController and uses
         * PurchaseOrderController::class without the FQCN.
         */
        $controllerNeedle =
            'PurchaseOrderController::class';

        $controllerPos =
            strpos(
                $routeSource,
                $controllerNeedle
            );
    }

    if (
        $controllerPos
        === false
    ) {
        fwrite(
            STDERR,
            "PurchaseOrderController route anchor tidak ditemukan.\n"
        );

        exit(11);
    }

    $groupStart =
        strrpos(
            substr(
                $routeSource,
                0,
                $controllerPos
            ),
            'Route::controller'
        );

    if (
        $groupStart
        === false
    ) {
        fwrite(
            STDERR,
            "Purchase Order Route::controller anchor tidak ditemukan.\n"
        );

        exit(12);
    }

    /*
     * Export route MUST be before the PO {id} show route.
     */
    $routeBlock = <<<'PHP'

/*
|--------------------------------------------------------------------------
| PO EXPENSE CSV EXPORT V1 ROUTE
|--------------------------------------------------------------------------
*/

Route::get(
    'purchase-orders/export-expense-csv',
    [
        \Webkul\Admin\Http\Controllers\PurchaseOrder\PurchaseOrderExpenseExportController::class,
        'export',
    ]
)->name('admin.purchase-orders.export-expense');

PHP;

    backupOnce(
        $routePath,
        '.before-po-expense-csv-v1.bak'
    );

    $routeSource =
        substr_replace(
            $routeSource,
            $routeBlock,
            $groupStart,
            0
        );

    file_put_contents(
        $routePath,
        $routeSource
    );

    echo "[PASS] PO Expense CSV route ditambahkan sebelum dynamic {id} route.\n";
}

/*
|--------------------------------------------------------------------------
| 2. ACL
|--------------------------------------------------------------------------
*/

$aclPath =
    $projectRoot
    .'/packages/Webkul/Admin/src/Config/acl.php';

if (! is_file($aclPath)) {
    fwrite(
        STDERR,
        "ACL file tidak ditemukan.\n"
    );

    exit(13);
}

$aclSource =
    file_get_contents(
        $aclPath
    );

if (
    str_contains(
        $aclSource,
        "'key'   => 'purchase-orders.export'"
    )
    || str_contains(
        $aclSource,
        "'key' => 'purchase-orders.export'"
    )
) {
    echo "[SKIP] PO Expense CSV ACL sudah ada.\n";
} else {
    $end =
        strrpos(
            $aclSource,
            '];'
        );

    if (
        $end
        === false
    ) {
        fwrite(
            STDERR,
            "Akhir ACL array tidak ditemukan.\n"
        );

        exit(14);
    }

    $aclBlock = <<<'PHP'

    [
        'key'   => 'purchase-orders.export',
        'name'  => 'Export PO Expense CSV',
        'route' => 'admin.purchase-orders.export-expense',
        'sort'  => 8,
    ],
PHP;

    backupOnce(
        $aclPath,
        '.before-po-expense-csv-v1.bak'
    );

    $aclSource =
        substr_replace(
            $aclSource,
            $aclBlock,
            $end,
            0
        );

    file_put_contents(
        $aclPath,
        $aclSource
    );

    echo "[PASS] PO Expense CSV ACL ditambahkan.\n";
}

/*
|--------------------------------------------------------------------------
| 3. PO INDEX BUTTON
|--------------------------------------------------------------------------
*/

$indexPath =
    $projectRoot
    .'/packages/Webkul/Admin/src/Resources/views/purchase-orders/index.blade.php';

if (! is_file($indexPath)) {
    fwrite(
        STDERR,
        "PO index Blade tidak ditemukan.\n"
    );

    exit(15);
}

$indexSource =
    file_get_contents(
        $indexPath
    );

$buttonMarker =
    'PO EXPENSE CSV EXPORT V1 BUTTON';

if (
    str_contains(
        $indexSource,
        $buttonMarker
    )
) {
    echo "[SKIP] Export Expense CSV button sudah ada.\n";
} else {
    $createRouteNeedle =
        "route('admin.purchase-orders.create')";

    $createRoutePos =
        strpos(
            $indexSource,
            $createRouteNeedle
        );

    if (
        $createRoutePos
        === false
    ) {
        $createRouteNeedle =
            'route("admin.purchase-orders.create")';

        $createRoutePos =
            strpos(
                $indexSource,
                $createRouteNeedle
            );
    }

    if (
        $createRoutePos
        === false
    ) {
        fwrite(
            STDERR,
            "Create Purchase Order button anchor tidak ditemukan.\n"
        );

        exit(16);
    }

    /*
     * The existing Create button is wrapped by an @if permission block.
     * Insert AFTER its @endif so export has its own ACL gate.
     */
    $endifPos =
        strpos(
            $indexSource,
            '@endif',
            $createRoutePos
        );

    if (
        $endifPos
        === false
    ) {
        fwrite(
            STDERR,
            "Closing @endif Create PO tidak ditemukan.\n"
        );

        exit(17);
    }

    $insertAt =
        $endifPos
        + strlen(
            '@endif'
        );

    $button = <<<'BLADE'

                <!-- PO EXPENSE CSV EXPORT V1 BUTTON -->
                @if (bouncer()->hasPermission('purchase-orders.export'))
                    <a
                        href="{{ route(
                            'admin.purchase-orders.export-expense',
                            request()->only([
                                'q',
                                'status',
                                'invoice_id',
                            ])
                        ) }}"
                        class="secondary-button"
                    >
                        Export Expense CSV
                    </a>
                @endif
BLADE;

    backupOnce(
        $indexPath,
        '.before-po-expense-csv-v1.bak'
    );

    $indexSource =
        substr_replace(
            $indexSource,
            $button,
            $insertAt,
            0
        );

    file_put_contents(
        $indexPath,
        $indexSource
    );

    echo "[PASS] Export Expense CSV button ditambahkan ke Purchase Orders.\n";
}

echo "\n";
echo "PO Expense CSV Export V1 selesai.\n";
echo "Controller PO utama tidak diubah.\n";
echo "Financial Report tidak diubah.\n";
echo "PO calculation / release / expense posting tidak diubah.\n";
