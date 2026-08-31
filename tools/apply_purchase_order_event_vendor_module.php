<?php

/*
|--------------------------------------------------------------------------
| Purchase Order Event Vendor Module Installer
|--------------------------------------------------------------------------
|
| Patches current customized routes/menu/ACL/InvoiceController in-place.
| It intentionally does NOT replace those existing customized files.
|
*/

$projectRoot = realpath(__DIR__.'/..');

if (! $projectRoot) {
    fwrite(STDERR, "Project root tidak ditemukan.\n");
    exit(1);
}

function backupFile(string $path, string $suffix): void
{
    $backup = $path.$suffix;

    if (! is_file($backup) && ! copy($path, $backup)) {
        throw new RuntimeException("Gagal backup {$path}");
    }
}

function recursiveFiles(string $root, string $suffix): array
{
    $result = [];

    if (! is_dir($root)) {
        return $result;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(
            $root,
            FilesystemIterator::SKIP_DOTS
        )
    );

    foreach ($iterator as $file) {
        if (
            $file->isFile()
            && str_ends_with(
                strtolower($file->getFilename()),
                strtolower($suffix)
            )
        ) {
            $result[] = $file->getPathname();
        }
    }

    return $result;
}

function filesContaining(string $root, string $needle, string $suffix): array
{
    $matches = [];

    foreach (recursiveFiles($root, $suffix) as $path) {
        $source = file_get_contents($path);

        if ($source !== false && str_contains($source, $needle)) {
            $matches[] = $path;
        }
    }

    return $matches;
}

/*
|--------------------------------------------------------------------------
| 1. ROUTES
|--------------------------------------------------------------------------
*/

$routeCandidates = filesContaining(
    $projectRoot.'/packages/Webkul/Admin/src/Routes',
    'admin.invoices.index',
    '.php'
);

if (count($routeCandidates) !== 1) {
    fwrite(
        STDERR,
        "Expected exactly 1 Invoice routes file. Found "
            .count($routeCandidates)
            .".\n"
    );

    foreach ($routeCandidates as $candidate) {
        fwrite(STDERR, " - {$candidate}\n");
    }

    exit(2);
}

$routePath = $routeCandidates[0];
$routeSource = file_get_contents($routePath);

if ($routeSource === false) {
    fwrite(STDERR, "Tidak dapat membaca Invoice routes.\n");
    exit(3);
}

backupFile($routePath, '.before-purchase-orders.bak');

if (str_contains($routeSource, 'admin.purchase-orders.index')) {
    echo "[SKIP] Purchase Order routes sudah ada.\n";
} else {
    $invoiceIndexPosition = strpos(
        $routeSource,
        'admin.invoices.index'
    );

    $groupStart = strrpos(
        substr($routeSource, 0, $invoiceIndexPosition),
        'Route::controller'
    );

    if ($groupStart === false) {
        fwrite(STDERR, "Invoice Route::controller group tidak ditemukan.\n");
        exit(4);
    }

    $poRoutes = <<<'PHP'

/*
|--------------------------------------------------------------------------
| Purchase Orders - Event Vendor / Outsource
|--------------------------------------------------------------------------
*/

Route::controller(
    \Webkul\Admin\Http\Controllers\PurchaseOrder\PurchaseOrderController::class
)
    ->prefix('purchase-orders')
    ->group(function () {
        Route::get('/', 'index')
            ->name('admin.purchase-orders.index');

        Route::get('create', 'create')
            ->name('admin.purchase-orders.create');

        Route::post('/', 'store')
            ->name('admin.purchase-orders.store');

        Route::get('{id}/edit', 'edit')
            ->name('admin.purchase-orders.edit');

        Route::put('{id}', 'update')
            ->name('admin.purchase-orders.update');

        Route::post('{id}/release', 'release')
            ->name('admin.purchase-orders.release');

        Route::post('{id}/complete', 'complete')
            ->name('admin.purchase-orders.complete');

        Route::post('{id}/cancel', 'cancel')
            ->name('admin.purchase-orders.cancel');

        Route::get('{id}/print', 'print')
            ->name('admin.purchase-orders.print');

        Route::get('{id}', 'show')
            ->name('admin.purchase-orders.show');
    });

PHP;

    $routeSource = substr_replace(
        $routeSource,
        $poRoutes,
        $groupStart,
        0
    );

    file_put_contents($routePath, $routeSource);

    echo "[PASS] Purchase Order routes ditambahkan.\n";
}

/*
|--------------------------------------------------------------------------
| 2. ACL
|--------------------------------------------------------------------------
*/

$aclPath = $projectRoot.'/packages/Webkul/Admin/src/Config/acl.php';

if (! is_file($aclPath)) {
    fwrite(STDERR, "ACL file tidak ditemukan.\n");
    exit(5);
}

$aclSource = file_get_contents($aclPath);
backupFile($aclPath, '.before-purchase-orders.bak');

if (
    str_contains($aclSource, "'key'   => 'purchase-orders'")
    || str_contains($aclSource, "'key' => 'purchase-orders'")
) {
    echo "[SKIP] Purchase Order ACL sudah ada.\n";
} else {
    $end = strrpos($aclSource, '];');

    if ($end === false) {
        fwrite(STDERR, "Akhir ACL array tidak ditemukan.\n");
        exit(6);
    }

    $aclEntries = <<<'PHP'

    [
        'key'   => 'purchase-orders',
        'name'  => 'Purchase Orders',
        'route' => 'admin.purchase-orders.index',
        'sort'  => 10,
    ], [
        'key'   => 'purchase-orders.create',
        'name'  => 'Create Purchase Order',
        'route' => [
            'admin.purchase-orders.create',
            'admin.purchase-orders.store',
        ],
        'sort'  => 1,
    ], [
        'key'   => 'purchase-orders.edit',
        'name'  => 'Edit Purchase Order',
        'route' => [
            'admin.purchase-orders.edit',
            'admin.purchase-orders.update',
        ],
        'sort'  => 2,
    ], [
        'key'   => 'purchase-orders.view',
        'name'  => 'View Purchase Order',
        'route' => 'admin.purchase-orders.show',
        'sort'  => 3,
    ], [
        'key'   => 'purchase-orders.release',
        'name'  => 'Release Purchase Order',
        'route' => 'admin.purchase-orders.release',
        'sort'  => 4,
    ], [
        'key'   => 'purchase-orders.complete',
        'name'  => 'Complete Purchase Order',
        'route' => 'admin.purchase-orders.complete',
        'sort'  => 5,
    ], [
        'key'   => 'purchase-orders.cancel',
        'name'  => 'Cancel Purchase Order',
        'route' => 'admin.purchase-orders.cancel',
        'sort'  => 6,
    ], [
        'key'   => 'purchase-orders.print',
        'name'  => 'Print Purchase Order',
        'route' => 'admin.purchase-orders.print',
        'sort'  => 7,
    ],
PHP;

    $aclSource = substr_replace(
        $aclSource,
        $aclEntries,
        $end,
        0
    );

    file_put_contents($aclPath, $aclSource);

    echo "[PASS] Purchase Order ACL ditambahkan.\n";
}

/*
|--------------------------------------------------------------------------
| 3. MENU
|--------------------------------------------------------------------------
*/

$menuPath = $projectRoot.'/packages/Webkul/Admin/src/Config/menu.php';

if (! is_file($menuPath)) {
    fwrite(STDERR, "menu.php tidak ditemukan.\n");
    exit(7);
}

$menuSource = file_get_contents($menuPath);
backupFile($menuPath, '.before-purchase-orders.bak');

if (
    str_contains($menuSource, "'key'        => 'purchase-orders'")
    || str_contains($menuSource, "'key' => 'purchase-orders'")
    || str_contains($menuSource, "'key'   => 'purchase-orders'")
) {
    echo "[SKIP] Purchase Orders menu sudah ada.\n";
} else {
    $menuEntry = <<<'PHP'

    [
        'key'        => 'purchase-orders',
        'name'       => 'Purchase Orders',
        'route'      => 'admin.purchase-orders.index',
        'sort'       => 8,
        'icon-class' => 'icon-cart',
    ],
PHP;

    $inventoryMatch = preg_match(
        "/\\[\\s*'key'\\s*=>\\s*'inventory'\\s*,/s",
        $menuSource,
        $match,
        PREG_OFFSET_CAPTURE
    );

    if ($inventoryMatch === 1) {
        $insertAt = $match[0][1];

        $menuSource = substr_replace(
            $menuSource,
            $menuEntry,
            $insertAt,
            0
        );
    } else {
        $end = strrpos($menuSource, '];');

        if ($end === false) {
            fwrite(STDERR, "Akhir menu array tidak ditemukan.\n");
            exit(8);
        }

        $menuSource = substr_replace(
            $menuSource,
            $menuEntry,
            $end,
            0
        );
    }

    file_put_contents($menuPath, $menuSource);

    echo "[PASS] Purchase Orders menu ditambahkan.\n";
}

/*
|--------------------------------------------------------------------------
| 4. PROTECT PO-GENERATED EXPENSE
|--------------------------------------------------------------------------
|
| A RELEASED/COMPLETED PO is the source of truth.
| Its Expense must not be manually edited/deleted from Invoice.
|
*/

$invoiceControllerCandidates = filesContaining(
    $projectRoot.'/packages/Webkul/Admin/src/Http/Controllers',
    'function updateExpense',
    '.php'
);

$invoiceControllerCandidates = array_values(
    array_filter(
        $invoiceControllerCandidates,
        function ($path) {
            $source = file_get_contents($path);

            return $source !== false
                && str_contains($source, 'function deleteExpense')
                && str_contains($source, 'Invoice');
        }
    )
);

if (count($invoiceControllerCandidates) !== 1) {
    fwrite(
        STDERR,
        "Expected exactly 1 InvoiceController with updateExpense/deleteExpense. Found "
            .count($invoiceControllerCandidates)
            .".\n"
    );

    foreach ($invoiceControllerCandidates as $candidate) {
        fwrite(STDERR, " - {$candidate}\n");
    }

    exit(9);
}

$invoiceControllerPath = $invoiceControllerCandidates[0];
$invoiceControllerSource = file_get_contents($invoiceControllerPath);

backupFile(
    $invoiceControllerPath,
    '.before-purchase-orders.bak'
);

$guardMarker = 'PO-GENERATED EXPENSE GUARD';

if (str_contains($invoiceControllerSource, $guardMarker)) {
    echo "[SKIP] PO Expense guard sudah ada.\n";
} else {
    $guard = <<<'PHP'


        /*
         * PO-GENERATED EXPENSE GUARD
         *
         * Expense dari RELEASED/COMPLETED PO dikunci agar PO dan
         * Financial Report tidak berbeda nominal.
         */
        $linkedPurchaseOrder =
            \Webkul\Invoice\Models\PurchaseOrder::query()
                ->where('expense_id', $expenseId)
                ->whereIn(
                    'status',
                    [
                        'released',
                        'completed',
                    ]
                )
                ->first();

        if ($linkedPurchaseOrder) {
            session()->flash(
                'warning',
                'Expense ini berasal dari '
                    .$linkedPurchaseOrder->po_number
                    .' dan dikunci. Kelola koreksi dari Purchase Order.'
            );

            return back();
        }
PHP;

    foreach (['updateExpense', 'deleteExpense'] as $method) {
        $pattern = '/public\\s+function\\s+'
            .preg_quote($method, '/')
            .'\\s*\\([^\\)]*\\)[^{]*\\{/s';

        if (
            preg_match(
                $pattern,
                $invoiceControllerSource,
                $methodMatch,
                PREG_OFFSET_CAPTURE
            ) !== 1
        ) {
            fwrite(STDERR, "Method {$method} tidak dapat dipatch.\n");
            exit(10);
        }

        $methodText = $methodMatch[0][0];

        if (! str_contains($methodText, '$expenseId')) {
            fwrite(
                STDERR,
                "Method {$method} tidak memakai parameter $expenseId. Controller tidak diubah agar aman.\n"
            );
            exit(11);
        }

        $methodStart = $methodMatch[0][1];
        $bracePosition = $methodStart + strrpos($methodText, '{') + 1;

        $invoiceControllerSource = substr_replace(
            $invoiceControllerSource,
            $guard,
            $bracePosition,
            0
        );
    }

    file_put_contents(
        $invoiceControllerPath,
        $invoiceControllerSource
    );

    echo "[PASS] PO-generated Expense edit/delete guard ditambahkan.\n";
}

echo "\n";
echo "Purchase Order Event Vendor installer selesai.\n";
echo "Routes : {$routePath}\n";
echo "ACL    : {$aclPath}\n";
echo "Menu   : {$menuPath}\n";
echo "Invoice Controller: {$invoiceControllerPath}\n";
