<?php

/*
|--------------------------------------------------------------------------
| Multi Delivery Order Invoice UI Patcher
|--------------------------------------------------------------------------
|
| Patches CURRENT customized files in-place.
| It does NOT replace InvoiceController, invoice-routes.php, acl.php,
| or the entire Invoice show Blade with guessed older copies.
|
*/

$projectRoot = realpath(
    __DIR__.'/..'
);

if (! $projectRoot) {
    fwrite(
        STDERR,
        "Project root tidak ditemukan.\n"
    );

    exit(1);
}

function backupFile(
    string $path,
    string $suffix
): void {
    $backup = $path.$suffix;

    if (! is_file($backup)) {
        copy(
            $path,
            $backup
        );
    }
}

function findFilesContaining(
    string $root,
    string $needle,
    string $extension
): array {
    $matches = [];

    if (! is_dir($root)) {
        return $matches;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(
            $root,
            FilesystemIterator::SKIP_DOTS
        )
    );

    foreach ($iterator as $file) {
        if (
            ! $file->isFile()
            || ! str_ends_with(
                strtolower(
                    $file->getFilename()
                ),
                strtolower($extension)
            )
        ) {
            continue;
        }

        $source = file_get_contents(
            $file->getPathname()
        );

        if (
            $source !== false
            && str_contains(
                $source,
                $needle
            )
        ) {
            $matches[] =
                $file->getPathname();
        }
    }

    return $matches;
}

/*
|--------------------------------------------------------------------------
| 1. invoice-routes.php
|--------------------------------------------------------------------------
*/

$routeCandidates = findFilesContaining(
    $projectRoot
        .'/packages/Webkul/Admin/src/Routes',
    'admin.invoices.delivery-order.generate',
    '.php'
);

if (count($routeCandidates) !== 1) {
    fwrite(
        STDERR,
        "Expected 1 invoice routes file, found "
            .count($routeCandidates)
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

backupFile(
    $routePath,
    '.before-multi-sj.bak'
);

if (
    ! str_contains(
        $routeSource,
        'InvoiceDeliveryOrderController'
    )
) {
    $invoiceControllerUse =
        'use Webkul\\Admin\\Http\\Controllers\\Invoice\\InvoiceController;';

    if (
        ! str_contains(
            $routeSource,
            $invoiceControllerUse
        )
    ) {
        fwrite(
            STDERR,
            "InvoiceController import anchor tidak ditemukan di routes.\n"
        );

        exit(3);
    }

    $routeSource = str_replace(
        $invoiceControllerUse,
        $invoiceControllerUse
            .PHP_EOL
            .'use Webkul\\Admin\\Http\\Controllers\\Invoice\\InvoiceDeliveryOrderController;',
        $routeSource
    );
}

if (
    ! str_contains(
        $routeSource,
        'admin.invoices.delivery-orders.index'
    )
) {
    $generateMarker =
        "        Route::post(\n"
        ."            '{id}/delivery-order',";

    $generatePosition =
        strpos(
            $routeSource,
            $generateMarker
        );

    if ($generatePosition === false) {
        /*
         * Fallback for compact formatting.
         */
        $generatePosition =
            strpos(
                $routeSource,
                "Route::post('{id}/delivery-order'"
            );
    }

    if ($generatePosition === false) {
        fwrite(
            STDERR,
            "Generate Delivery Order route anchor tidak ditemukan.\n"
        );

        exit(4);
    }

    $listRoute = <<<'PHP'

        /*
        |--------------------------------------------------------------------------
        | All Surat Jalan for one Invoice / Event
        |--------------------------------------------------------------------------
        */

        Route::get(
            '{id}/delivery-orders',
            [
                InvoiceDeliveryOrderController::class,
                'index',
            ]
        )->name(
            'admin.invoices.delivery-orders.index'
        );

PHP;

    $routeSource =
        substr_replace(
            $routeSource,
            $listRoute,
            $generatePosition,
            0
        );
}

file_put_contents(
    $routePath,
    $routeSource
);

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
        "ACL file tidak ditemukan: {$aclPath}\n"
    );

    exit(5);
}

$aclSource =
    file_get_contents(
        $aclPath
    );

backupFile(
    $aclPath,
    '.before-multi-sj.bak'
);

if (
    ! str_contains(
        $aclSource,
        'admin.invoices.delivery-orders.index'
    )
) {
    $viewKeyPosition =
        strpos(
            $aclSource,
            "'key'   => 'delivery-orders.view'"
        );

    if ($viewKeyPosition === false) {
        $viewKeyPosition =
            strpos(
                $aclSource,
                "'key' => 'delivery-orders.view'"
            );
    }

    if ($viewKeyPosition === false) {
        fwrite(
            STDERR,
            "ACL delivery-orders.view tidak ditemukan.\n"
        );

        exit(6);
    }

    $routePosition =
        strpos(
            $aclSource,
            "'route'",
            $viewKeyPosition
        );

    if ($routePosition === false) {
        fwrite(
            STDERR,
            "ACL route delivery-orders.view tidak ditemukan.\n"
        );

        exit(7);
    }

    $lineEnd =
        strpos(
            $aclSource,
            "\n",
            $routePosition
        );

    if ($lineEnd === false) {
        fwrite(
            STDERR,
            "ACL route line tidak valid.\n"
        );

        exit(8);
    }

    $routeLine =
        substr(
            $aclSource,
            $routePosition,
            $lineEnd - $routePosition
        );

    if (
        ! str_contains(
            $routeLine,
            'admin.delivery-orders.show'
        )
    ) {
        fwrite(
            STDERR,
            "ACL delivery-orders.view route format tidak dikenali.\n"
        );

        exit(9);
    }

    $indent = substr(
        $routeLine,
        0,
        strlen($routeLine)
            - strlen(
                ltrim(
                    $routeLine
                )
            )
    );

    $replacement =
        "'route' => [\n"
        ."        'admin.invoices.delivery-orders.index',\n"
        ."        'admin.delivery-orders.show',\n"
        ."    ],";

    /*
     * Preserve surrounding indentation reasonably.
     */
    $replacement = preg_replace(
        '/^/m',
        $indent,
        $replacement
    );

    $aclSource =
        substr_replace(
            $aclSource,
            $replacement,
            $routePosition,
            $lineEnd - $routePosition
        );
}

file_put_contents(
    $aclPath,
    $aclSource
);

/*
|--------------------------------------------------------------------------
| 3. Invoice Show Blade
|--------------------------------------------------------------------------
|
| Locate current customized Blade containing the existing Generate SJ button.
| Replace only the old $deliveryOrder conditional block.
|
*/

$viewCandidates =
    findFilesContaining(
        $projectRoot
            .'/packages/Webkul/Admin/src/Resources/views',
        'admin.invoices.delivery-order.generate',
        '.blade.php'
    );

$invoiceShowCandidates = [];

foreach ($viewCandidates as $candidate) {
    $source =
        file_get_contents(
            $candidate
        );

    if (
        $source !== false
        && str_contains(
            $source,
            'View Surat Jalan'
        )
        && str_contains(
            $source,
            '$invoice'
        )
    ) {
        $invoiceShowCandidates[] =
            $candidate;
    }
}

if (
    count(
        $invoiceShowCandidates
    ) !== 1
) {
    fwrite(
        STDERR,
        "Expected 1 Invoice Blade with existing SJ buttons, found "
            .count(
                $invoiceShowCandidates
            )
            .".\n"
    );

    foreach (
        $invoiceShowCandidates
        as $candidate
    ) {
        fwrite(
            STDERR,
            " - {$candidate}\n"
        );
    }

    exit(10);
}

$viewPath =
    $invoiceShowCandidates[0];

$viewSource =
    file_get_contents(
        $viewPath
    );

backupFile(
    $viewPath,
    '.before-multi-sj.bak'
);

if (
    ! str_contains(
        $viewSource,
        'admin.invoices.delivery-orders.index'
    )
) {
    $generatePosition =
        strpos(
            $viewSource,
            'admin.invoices.delivery-order.generate'
        );

    if ($generatePosition === false) {
        fwrite(
            STDERR,
            "Generate SJ button tidak ditemukan di Invoice Blade.\n"
        );

        exit(11);
    }

    /*
     * Find the closest @if ($deliveryOrder...) before Generate route.
     */
    $before =
        substr(
            $viewSource,
            0,
            $generatePosition
        );

    $blockStart =
        strrpos(
            $before,
            '@if ($deliveryOrder'
        );

    if ($blockStart === false) {
        /*
         * Some views may use "@if($deliveryOrder)" without a space.
         */
        $blockStart =
            strrpos(
                $before,
                '@if($deliveryOrder'
            );
    }

    if ($blockStart === false) {
        fwrite(
            STDERR,
            "Old Invoice -> Delivery Order conditional block tidak ditemukan.\n"
        );

        exit(12);
    }

    /*
     * Blade-aware nested @if / @endif scan.
     */
    $tail =
        substr(
            $viewSource,
            $blockStart
        );

    preg_match_all(
        '/@(if|unless|isset|empty)\b|@endif\b|@endunless\b|@endisset\b|@endempty\b/',
        $tail,
        $tokens,
        PREG_OFFSET_CAPTURE
    );

    $depth = 0;
    $blockEnd = null;

    foreach ($tokens[0] as $token) {
        $text =
            $token[0];

        $offset =
            $token[1];

        if (
            preg_match(
                '/^@(if|unless|isset|empty)\b/',
                $text
            )
        ) {
            $depth++;
            continue;
        }

        $depth--;

        if ($depth === 0) {
            $blockEnd =
                $offset
                + strlen(
                    $text
                );

            break;
        }
    }

    if ($blockEnd === null) {
        fwrite(
            STDERR,
            "Tidak dapat menentukan akhir blok SJ lama.\n"
        );

        exit(13);
    }

    $newButtons = <<<'BLADE'
@php
    $invoiceDeliveryOrders = \Webkul\Invoice\Models\DeliveryOrder::query()
        ->where(
            'invoice_id',
            $invoice->id
        )
        ->orderBy('id')
        ->get([
            'id',
            'delivery_order_number',
            'status',
        ]);

    $invoiceDeliveryOrderCount =
        $invoiceDeliveryOrders->count();
@endphp

@if (
    $invoiceDeliveryOrderCount > 0
    && bouncer()->hasPermission(
        'delivery-orders.view'
    )
)
    <a
        href="{{ route(
            'admin.invoices.delivery-orders.index',
            $invoice->id
        ) }}"
        class="secondary-button"
    >
        View Surat Jalan ({{ $invoiceDeliveryOrderCount }})
    </a>
@endif

@if (
    $invoiceDeliveryOrderCount === 1
    && bouncer()->hasPermission(
        'delivery-orders.print'
    )
)
    <a
        href="{{ route(
            'admin.delivery-orders.print',
            $invoiceDeliveryOrders->first()->id
        ) }}"
        class="secondary-button"
    >
        Print Surat Jalan
    </a>
@endif

@if (
    bouncer()->hasPermission(
        'delivery-orders.generate'
    )
)
    <form
        method="POST"
        action="{{ route(
            'admin.invoices.delivery-order.generate',
            $invoice->id
        ) }}"
        onsubmit="
            const button = this.querySelector('button');
            if (button) {
                button.disabled = true;
                button.textContent = 'Generating...';
            }
        "
        style="margin:0;"
    >
        @csrf

        <button
            type="submit"
            class="secondary-button"
        >
            {{ $invoiceDeliveryOrderCount > 0
                ? '+ Generate Additional SJ'
                : 'Generate Surat Jalan' }}
        </button>
    </form>
@endif
BLADE;

    $viewSource =
        substr_replace(
            $viewSource,
            $newButtons,
            $blockStart,
            $blockEnd
        );
}

file_put_contents(
    $viewPath,
    $viewSource
);

echo "Multi Delivery Order Invoice UI berhasil dipatch.\n";
echo "Routes : {$routePath}\n";
echo "ACL    : {$aclPath}\n";
echo "Invoice: {$viewPath}\n";
echo "\n";
echo "Target flow:\n";
echo "- Invoice -> View Surat Jalan (N)\n";
echo "- Invoice -> Generate Additional SJ tetap tersedia\n";
echo "- 1 Invoice dapat memiliki banyak SJ\n";
echo "- setiap SJ memiliki nomor sequence sendiri\n";
