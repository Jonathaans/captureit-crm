<?php

/*
|--------------------------------------------------------------------------
| SPK Work Order Between Invoice and Surat Jalan V1.1
|--------------------------------------------------------------------------
|
| Final architecture:
|
| Invoice
|   -> 1 SPK
|       -> N Surat Jalan
|
| Safety rules:
| - Existing Invoice / Delivery Order files are patched surgically.
| - Existing multi-SJ inventory workflow remains in DeliveryOrderService.
| - Old Invoice -> SJ route is repointed to SPK generation.
| - Direct DeliveryOrderService::createFromInvoice without work_order_id
|   is blocked after this installer.
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
    string $path
): void {
    $backup =
        $path
        .'.before-spk-work-order-v1.bak';

    if (
        is_file($path)
        && ! is_file($backup)
    ) {
        if (! copy($path, $backup)) {
            throw new RuntimeException(
                "Gagal membuat backup {$backup}"
            );
        }
    }
}

function phpFilesUnder(
    string $root,
    string $suffix = '.php'
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
            && str_ends_with(
                strtolower(
                    $file->getFilename()
                ),
                strtolower(
                    $suffix
                )
            )
        ) {
            $result[] =
                $file->getPathname();
        }
    }

    return $result;
}

function filesContaining(
    string $root,
    string $needle,
    string $suffix = '.php'
): array {
    $matches = [];

    foreach (
        phpFilesUnder(
            $root,
            $suffix
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
                $needle
            )
        ) {
            $matches[] =
                $path;
        }
    }

    return $matches;
}

function routeStatementBounds(
    string $source,
    string $routeName
): ?array {
    $namePos =
        strpos(
            $source,
            $routeName
        );

    if ($namePos === false) {
        return null;
    }

    $start = false;

    foreach (
        [
            'Route::post',
            'Route::match',
            'Route::any',
        ]
        as $token
    ) {
        $candidate =
            strrpos(
                substr(
                    $source,
                    0,
                    $namePos
                ),
                $token
            );

        if (
            $candidate !== false
            && (
                $start === false
                || $candidate > $start
            )
        ) {
            $start =
                $candidate;
        }
    }

    if ($start === false) {
        return null;
    }

    $end =
        strpos(
            $source,
            ';',
            $namePos
        );

    if ($end === false) {
        return null;
    }

    return [
        $start,
        $end + 1,
    ];
}

/*
|--------------------------------------------------------------------------
| PRE-FLIGHT
|--------------------------------------------------------------------------
*/

$legacyRouteName =
    'admin.invoices.delivery-order.generate';

$routeCandidates =
    filesContaining(
        $projectRoot
            .'/packages/Webkul/Admin/src/Routes',
        $legacyRouteName,
        '.php'
    );

if (
    count(
        $routeCandidates
    ) !== 1
) {
    fwrite(
        STDERR,
        "Expected exactly 1 Invoice route file containing {$legacyRouteName}; found "
        .count(
            $routeCandidates
        )
        .".\n"
    );

    foreach ($routeCandidates as $candidate) {
        fwrite(
            STDERR,
            " - {$candidate}\n"
        );
    }

    exit(2);
}

$routePath =
    $routeCandidates[0];

$routeSource =
    file_get_contents(
        $routePath
    );

$routeBounds =
    routeStatementBounds(
        $routeSource,
        $legacyRouteName
    );

if (! $routeBounds) {
    fwrite(
        STDERR,
        "Legacy Invoice -> SJ route statement tidak dapat dibaca.\n"
    );
    exit(3);
}

[$routeStart, $routeEnd] =
    $routeBounds;

$legacyStatement =
    substr(
        $routeSource,
        $routeStart,
        $routeEnd - $routeStart
    );

if (
    ! preg_match(
        "/Route::(?:post|match|any)\\s*\\(\\s*(?:\\[[^\\]]+\\]\\s*,\\s*)?['\"]([^'\"]+)['\"]/",
        $legacyStatement,
        $uriMatch
    )
) {
    fwrite(
        STDERR,
        "URI legacy Invoice -> SJ tidak dapat dibaca.\n"
    );
    exit(4);
}

$legacyUri =
    $uriMatch[1];

$servicePath =
    $projectRoot
    .'/packages/Webkul/Invoice/src/Services/DeliveryOrderService.php';

if (! is_file($servicePath)) {
    fwrite(
        STDERR,
        "DeliveryOrderService.php tidak ditemukan.\n"
    );
    exit(5);
}

$serviceSource =
    file_get_contents(
        $servicePath
    );

$serviceAlreadyPatched =
    str_contains(
        $serviceSource,
        'SPK WORK ORDER V1 GUARD'
    );

if (! $serviceAlreadyPatched) {
    if (
        ! preg_match(
            '/public\s+function\s+createFromInvoice\s*\(\s*Invoice\s+\$invoice\s*,\s*\?int\s+\$createdBy\s*=\s*null\s*\)\s*:\s*DeliveryOrder\s*\{/s',
            $serviceSource
        )
    ) {
        fwrite(
            STDERR,
            "DeliveryOrderService::createFromInvoice signature berbeda dari multi-SJ yang diketahui. "
            ."Patch dihentikan sebelum mengubah file apa pun.\n"
        );
        exit(6);
    }

    if (
        ! str_contains(
            $serviceSource,
            '$deliveryOrder = DeliveryOrder::create(['
        )
        || ! str_contains(
            $serviceSource,
            'First SJ Only: Copy Standard Equipment Requirement'
        )
    ) {
        fwrite(
            STDERR,
            "DeliveryOrderService marker tidak lengkap. Patch dihentikan.\n"
        );
        exit(7);
    }
}

/*
|--------------------------------------------------------------------------
| Invoice Show Blade selector V1.1
|--------------------------------------------------------------------------
|
| The legacy route also exists in:
| invoices/delivery-orders/index.blade.php
|
| Only invoices/show.blade.php is the Invoice action toolbar that must change
| from "Generate Surat Jalan" to "Generate Surat Perintah Kerja".
|
| Do NOT patch delivery-orders/index.blade.php.
|
*/

$invoiceViewPath =
    $projectRoot
    .'/packages/Webkul/Admin/src/Resources/views/invoices/show.blade.php';

if (! is_file($invoiceViewPath)) {
    fwrite(
        STDERR,
        "Invoice Show Blade tidak ditemukan: {$invoiceViewPath}\n"
    );

    exit(8);
}

$invoiceViewSource =
    file_get_contents(
        $invoiceViewPath
    );

if (
    $invoiceViewSource === false
    || ! str_contains(
        $invoiceViewSource,
        '$invoice'
    )
    || (
        ! str_contains(
            $invoiceViewSource,
            $legacyRouteName
        )
        && ! str_contains(
            $invoiceViewSource,
            'admin.invoices.work-orders.store'
        )
    )
) {
    fwrite(
        STDERR,
        "Invoice Show Blade ditemukan, tetapi tombol legacy/SPK tidak dikenali. "
        ."Patch dihentikan agar tidak mengubah Blade yang salah.\n"
    );

    exit(8);
}

echo "[PASS] Invoice Show Blade target resolved: {$invoiceViewPath}\n";

$aclPath =
    $projectRoot
    .'/packages/Webkul/Admin/src/Config/acl.php';

if (! is_file($aclPath)) {
    fwrite(
        STDERR,
        "ACL file tidak ditemukan.\n"
    );
    exit(9);
}

/*
|--------------------------------------------------------------------------
| 1. Register Provider
|--------------------------------------------------------------------------
*/

$providerPath =
    $projectRoot
    .'/bootstrap/providers.php';

$providerSource =
    file_get_contents(
        $providerPath
    );

$provider =
    '\\Webkul\\Admin\\Providers\\WorkOrderIntegrationServiceProvider::class';

if (
    ! str_contains(
        $providerSource,
        $provider
    )
) {
    $end =
        strrpos(
            $providerSource,
            '];'
        );

    if ($end === false) {
        fwrite(
            STDERR,
            "bootstrap/providers.php format tidak dikenali.\n"
        );
        exit(10);
    }

    backupOnce(
        $providerPath
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

    echo "[PASS] WorkOrderIntegrationServiceProvider registered.\n";
} else {
    echo "[SKIP] WorkOrderIntegrationServiceProvider already registered.\n";
}

/*
|--------------------------------------------------------------------------
| 2. Repoint legacy Invoice -> SJ route to SPK
|--------------------------------------------------------------------------
*/

$routeSource =
    file_get_contents(
        $routePath
    );

$routeBounds =
    routeStatementBounds(
        $routeSource,
        $legacyRouteName
    );

[$routeStart, $routeEnd] =
    $routeBounds;

$currentStatement =
    substr(
        $routeSource,
        $routeStart,
        $routeEnd - $routeStart
    );

if (
    str_contains(
        $currentStatement,
        'WorkOrderController'
    )
) {
    echo "[SKIP] Legacy Invoice -> SJ route already points to SPK.\n";
} else {
    backupOnce(
        $routePath
    );

    $lineStart =
        strrpos(
            substr(
                $routeSource,
                0,
                $routeStart
            ),
            "\n"
        );

    $lineStart =
        $lineStart === false
            ? 0
            : $lineStart + 1;

    $indent =
        substr(
            $routeSource,
            $lineStart,
            $routeStart - $lineStart
        );

    $replacement =
        $indent
        ."Route::post(\n"
        .$indent
        ."    '"
        .$legacyUri
        ."',\n"
        .$indent
        ."    [\\Webkul\\Admin\\Http\\Controllers\\WorkOrder\\WorkOrderController::class, 'storeFromInvoice']\n"
        .$indent
        .")\n"
        .$indent
        ."    ->name('"
        .$legacyRouteName
        ."');";

    $routeSource =
        substr_replace(
            $routeSource,
            $replacement,
            $routeStart,
            $routeEnd - $routeStart
        );

    file_put_contents(
        $routePath,
        $routeSource
    );

    echo "[PASS] Legacy Invoice -> Generate SJ route repointed to SPK.\n";
}

/*
|--------------------------------------------------------------------------
| 3. Guard DeliveryOrderService: SJ must come from SPK
|--------------------------------------------------------------------------
*/

$serviceSource =
    file_get_contents(
        $servicePath
    );

if (
    str_contains(
        $serviceSource,
        'SPK WORK ORDER V1 GUARD'
    )
) {
    echo "[SKIP] DeliveryOrderService SPK guard already installed.\n";
} else {
    backupOnce(
        $servicePath
    );

    $signaturePattern =
        '/public\s+function\s+createFromInvoice\s*\(\s*Invoice\s+\$invoice\s*,\s*\?int\s+\$createdBy\s*=\s*null\s*\)\s*:\s*DeliveryOrder\s*\{/s';

    $newSignature = <<<'PHP'
public function createFromInvoice(
        Invoice $invoice,
        ?int $createdBy = null,
        ?int $workOrderId = null
    ): DeliveryOrder {
        /*
         * SPK WORK ORDER V1 GUARD
         *
         * Surat Jalan is no longer allowed to be generated directly
         * from Invoice. Every new SJ must have an SPK parent.
         */
        if (! $workOrderId) {
            throw new \LogicException(
                'Surat Jalan harus dibuat dari Surat Perintah Kerja (SPK), bukan langsung dari Invoice.'
            );
        }
PHP;

    $serviceSource =
        preg_replace(
            $signaturePattern,
            $newSignature,
            $serviceSource,
            1,
            $signatureCount
        );

    if ($signatureCount !== 1) {
        fwrite(
            STDERR,
            "Gagal patch createFromInvoice signature. File tidak ditulis.\n"
        );
        exit(11);
    }

    $firstSjMarker =
        '                /*'
        ."\n"
        .'                |--------------------------------------------------------------------------'
        ."\n"
        .'                | First SJ Only: Copy Standard Equipment Requirement';

    $markerPos =
        strpos(
            $serviceSource,
            $firstSjMarker
        );

    if ($markerPos === false) {
        fwrite(
            STDERR,
            "First SJ marker tidak ditemukan setelah signature patch.\n"
        );
        exit(12);
    }

    $linkBlock = <<<'PHP'

                /*
                 * SPK WORK ORDER V1 LINK
                 */
                $deliveryOrder->forceFill([
                    'work_order_id' =>
                        $workOrderId,
                ])->save();

PHP;

    $serviceSource =
        substr_replace(
            $serviceSource,
            $linkBlock,
            $markerPos,
            0
        );

    file_put_contents(
        $servicePath,
        $serviceSource
    );

    echo "[PASS] DeliveryOrderService now requires work_order_id.\n";
    echo "[PASS] Existing multi-SJ logic preserved.\n";
}

/*
|--------------------------------------------------------------------------
| 4. Invoice Blade: Generate/View SPK instead of direct SJ
|--------------------------------------------------------------------------
*/

$invoiceView =
    file_get_contents(
        $invoiceViewPath
    );

$originalInvoiceView =
    $invoiceView;

$invoiceView =
    str_replace(
        'admin.invoices.delivery-order.generate',
        'admin.invoices.work-orders.store',
        $invoiceView
    );

$invoiceView =
    str_replace(
        'admin.invoices.delivery-orders.index',
        'admin.invoices.work-orders.open',
        $invoiceView
    );

$invoiceView =
    str_replace(
        'Generate Surat Jalan',
        'Generate Surat Perintah Kerja',
        $invoiceView
    );

$invoiceView =
    str_replace(
        '+ Generate Additional SJ',
        'Open / Generate SPK',
        $invoiceView
    );

$invoiceView =
    str_replace(
        'View Surat Jalan',
        'View SPK',
        $invoiceView
    );

if ($invoiceView === $originalInvoiceView) {
    fwrite(
        STDERR,
        "Invoice Blade tidak berubah. Target SPK tidak ditemukan.\n"
    );
    exit(13);
}

backupOnce(
    $invoiceViewPath
);

file_put_contents(
    $invoiceViewPath,
    $invoiceView
);

echo "[PASS] Invoice UI now points to Surat Perintah Kerja.\n";

/*
|--------------------------------------------------------------------------
| 5. ACL
|--------------------------------------------------------------------------
*/

$acl =
    file_get_contents(
        $aclPath
    );

if (
    str_contains(
        $acl,
        "'key'   => 'work-orders'"
    )
    || str_contains(
        $acl,
        "'key' => 'work-orders'"
    )
) {
    echo "[SKIP] Work Orders ACL already exists.\n";
} else {
    $end =
        strrpos(
            $acl,
            '];'
        );

    if ($end === false) {
        fwrite(
            STDERR,
            "ACL format tidak dikenali.\n"
        );
        exit(14);
    }

    $entries = <<<'PHP'

    [
        'key'   => 'work-orders',
        'name'  => 'Surat Perintah Kerja',
        'route' => 'admin.work-orders.index',
        'sort'  => 65,
    ], [
        'key'   => 'work-orders.view',
        'name'  => 'View SPK',
        'route' => [
            'admin.work-orders.index',
            'admin.work-orders.show',
            'admin.invoices.work-orders.open',
        ],
        'sort'  => 1,
    ], [
        'key'   => 'work-orders.generate',
        'name'  => 'Generate SPK from Invoice',
        'route' => [
            'admin.invoices.work-orders.store',
            'admin.invoices.delivery-order.generate',
        ],
        'sort'  => 2,
    ], [
        'key'   => 'work-orders.edit',
        'name'  => 'Edit SPK',
        'route' => [
            'admin.work-orders.edit',
            'admin.work-orders.update',
        ],
        'sort'  => 3,
    ], [
        'key'   => 'work-orders.print',
        'name'  => 'Print SPK',
        'route' => 'admin.work-orders.print',
        'sort'  => 4,
    ], [
        'key'   => 'work-orders.delivery-orders',
        'name'  => 'Generate Surat Jalan from SPK',
        'route' => 'admin.work-orders.delivery-orders.generate',
        'sort'  => 5,
    ], [
        'key'   => 'work-orders.status',
        'name'  => 'Update SPK Status',
        'route' => [
            'admin.work-orders.release',
            'admin.work-orders.complete',
            'admin.work-orders.cancel',
        ],
        'sort'  => 6,
    ],
PHP;

    backupOnce(
        $aclPath
    );

    $acl =
        substr_replace(
            $acl,
            $entries,
            $end,
            0
        );

    file_put_contents(
        $aclPath,
        $acl
    );

    echo "[PASS] SPK ACL added.\n";
}

/*
|--------------------------------------------------------------------------
| 6. Optional Sidebar Menu
|--------------------------------------------------------------------------
*/

$menuPath =
    $projectRoot
    .'/packages/Webkul/Admin/src/Config/menu.php';

if (! is_file($menuPath)) {
    echo "[WARN] menu.php tidak ditemukan. SPK tetap dapat diakses melalui Invoice.\n";
} else {
    $menu =
        file_get_contents(
            $menuPath
        );

    if (
        str_contains(
            $menu,
            "'key'        => 'work-orders'"
        )
        || str_contains(
            $menu,
            "'key'   => 'work-orders'"
        )
        || str_contains(
            $menu,
            "'key' => 'work-orders'"
        )
    ) {
        echo "[SKIP] SPK sidebar menu already exists.\n";
    } else {
        $end =
            strrpos(
                $menu,
                '];'
            );

        if ($end === false) {
            echo "[WARN] menu.php format tidak dikenali. Skip sidebar menu patch.\n";
        } else {
            $entry = <<<'PHP'

    [
        'key'        => 'work-orders',
        'name'       => 'Surat Perintah Kerja',
        'route'      => 'admin.work-orders.index',
        'sort'       => 57,
        'icon-class' => 'icon-note',
    ],
PHP;

            backupOnce(
                $menuPath
            );

            $menu =
                substr_replace(
                    $menu,
                    $entry,
                    $end,
                    0
                );

            file_put_contents(
                $menuPath,
                $menu
            );

            echo "[PASS] SPK sidebar menu added.\n";
        }
    }
}

echo "\n";
echo "SPK Work Order V1.1 installer selesai.\n";
echo "Route file  : {$routePath}\n";
echo "Invoice UI  : {$invoiceViewPath}\n";
echo "SJ Service  : {$servicePath}\n";
echo "\n";
echo "Next:\n";
echo "php artisan migrate\n";
echo "php artisan optimize:clear\n";
echo "php tools\\check_spk_work_order_v1.php\n";
