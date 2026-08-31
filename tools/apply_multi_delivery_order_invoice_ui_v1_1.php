<?php

/*
|--------------------------------------------------------------------------
| Multi Delivery Order Invoice UI Patcher V1.1
|--------------------------------------------------------------------------
|
| Hotfix for projects whose route formatting differs from the earlier package.
| This version locates the GENERATE route by ROUTE NAME, not by exact formatting.
|
| It patches:
| 1. current invoice route file
| 2. current ACL file
| 3. current customized Invoice show Blade
|
| It never replaces those customized files wholesale.
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
        if (! copy($path, $backup)) {
            throw new RuntimeException(
                "Gagal membuat backup: {$backup}"
            );
        }
    }
}

function phpFilesUnder(
    string $root,
    string $suffix = '.php'
): array {
    $files = [];

    if (! is_dir($root)) {
        return $files;
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
                strtolower(
                    $file->getFilename()
                ),
                strtolower($suffix)
            )
        ) {
            $files[] =
                $file->getPathname();
        }
    }

    return $files;
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

/*
|--------------------------------------------------------------------------
| 1. ROUTE
|--------------------------------------------------------------------------
*/

$routeName =
    'admin.invoices.delivery-order.generate';

$routeCandidates =
    filesContaining(
        $projectRoot
            .'/packages/Webkul/Admin/src/Routes',
        $routeName,
        '.php'
    );

if (
    count(
        $routeCandidates
    ) !== 1
) {
    fwrite(
        STDERR,
        "Expected 1 route file containing {$routeName}, found "
            .count(
                $routeCandidates
            )
            .".\n"
    );

    foreach (
        $routeCandidates
        as $candidate
    ) {
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

if ($routeSource === false) {
    fwrite(
        STDERR,
        "Cannot read route file: {$routePath}\n"
    );

    exit(3);
}

backupFile(
    $routePath,
    '.before-multi-sj-v1-1.bak'
);

$listRouteName =
    'admin.invoices.delivery-orders.index';

if (
    ! str_contains(
        $routeSource,
        $listRouteName
    )
) {
    $namePos =
        strpos(
            $routeSource,
            $routeName
        );

    if ($namePos === false) {
        fwrite(
            STDERR,
            "Generate route name unexpectedly disappeared.\n"
        );

        exit(4);
    }

    /*
     * Find the Route::post statement that owns the route name.
     * This is deliberately format-agnostic.
     */
    $routeStart = false;

    foreach (
        [
            'Route::post',
            'Route::match',
            'Route::any',
        ]
        as $routeToken
    ) {
        $candidate =
            strrpos(
                substr(
                    $routeSource,
                    0,
                    $namePos
                ),
                $routeToken
            );

        if (
            $candidate !== false
            && (
                $routeStart === false
                || $candidate > $routeStart
            )
        ) {
            $routeStart =
                $candidate;
        }
    }

    if ($routeStart === false) {
        fwrite(
            STDERR,
            "Generate Delivery Order Route::post/match statement tidak ditemukan.\n"
        );

        exit(5);
    }

    $statementEnd =
        strpos(
            $routeSource,
            ';',
            $namePos
        );

    if ($statementEnd === false) {
        fwrite(
            STDERR,
            "Akhir statement generate route tidak ditemukan.\n"
        );

        exit(6);
    }

    $generateStatement =
        substr(
            $routeSource,
            $routeStart,
            $statementEnd
                - $routeStart
                + 1
        );

    /*
     * Extract URI from current generate route and derive a plural listing URI.
     *
     * Examples:
     * {id}/delivery-order
     * -> {id}/delivery-orders
     *
     * invoices/{id}/delivery-order
     * -> invoices/{id}/delivery-orders
     */
    if (
        ! preg_match(
            "/Route::(?:post|match|any)\\s*\\(\\s*(?:\\[[^\\]]+\\]\\s*,\\s*)?['\"]([^'\"]+)['\"]/",
            $generateStatement,
            $uriMatch
        )
    ) {
        fwrite(
            STDERR,
            "URI generate route tidak dapat dibaca.\n"
        );

        fwrite(
            STDERR,
            "Statement:\n{$generateStatement}\n"
        );

        exit(7);
    }

    $generateUri =
        $uriMatch[1];

    if (
        str_ends_with(
            $generateUri,
            '/delivery-order'
        )
    ) {
        $listUri =
            substr(
                $generateUri,
                0,
                -strlen(
                    '/delivery-order'
                )
            )
            .'/delivery-orders';
    } elseif (
        str_contains(
            $generateUri,
            'delivery-order'
        )
    ) {
        $listUri =
            preg_replace(
                '/delivery-order(?!s)/',
                'delivery-orders',
                $generateUri,
                1
            );
    } else {
        fwrite(
            STDERR,
            "Generate URI tidak mengandung delivery-order: {$generateUri}\n"
        );

        exit(8);
    }

    /*
     * Detect indentation of current route statement.
     */
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
            $routeStart
                - $lineStart
        );

    $newRoute =
        $indent
        ."Route::get(\n"
        .$indent
        ."    '"
        .$listUri
        ."',\n"
        .$indent
        ."    [\\Webkul\\Admin\\Http\\Controllers\\Invoice\\InvoiceDeliveryOrderController::class, 'index']\n"
        .$indent
        .")\n"
        .$indent
        ."    ->name('"
        .$listRouteName
        ."');\n\n";

    $routeSource =
        substr_replace(
            $routeSource,
            $newRoute,
            $routeStart,
            0
        );

    file_put_contents(
        $routePath,
        $routeSource
    );

    echo "[PASS] Route list SJ ditambahkan.\n";
    echo "       URI: {$listUri}\n";
} else {
    echo "[SKIP] Route list SJ sudah ada.\n";
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
        "ACL tidak ditemukan: {$aclPath}\n"
    );

    exit(9);
}

$aclSource =
    file_get_contents(
        $aclPath
    );

if ($aclSource === false) {
    fwrite(
        STDERR,
        "Tidak dapat membaca ACL.\n"
    );

    exit(10);
}

backupFile(
    $aclPath,
    '.before-multi-sj-v1-1.bak'
);

if (
    str_contains(
        $aclSource,
        $listRouteName
    )
) {
    echo "[SKIP] ACL list SJ sudah ada.\n";
} else {
    $keyPatterns = [
        "'key'   => 'delivery-orders.view'",
        "'key' => 'delivery-orders.view'",
    ];

    $keyPos = false;

    foreach ($keyPatterns as $pattern) {
        $pos =
            strpos(
                $aclSource,
                $pattern
            );

        if ($pos !== false) {
            $keyPos =
                $pos;

            break;
        }
    }

    if ($keyPos === false) {
        fwrite(
            STDERR,
            "ACL key delivery-orders.view tidak ditemukan.\n"
        );

        exit(11);
    }

    $nextBlock =
        strpos(
            $aclSource,
            "    ], [",
            $keyPos
        );

    if ($nextBlock === false) {
        $nextBlock =
            strpos(
                $aclSource,
                "], [",
                $keyPos
            );
    }

    if ($nextBlock === false) {
        fwrite(
            STDERR,
            "Akhir ACL delivery-orders.view tidak ditemukan.\n"
        );

        exit(12);
    }

    $block =
        substr(
            $aclSource,
            $keyPos,
            $nextBlock
                - $keyPos
        );

    /*
     * Existing route can be:
     * 'route' => 'admin.delivery-orders.show',
     *
     * or:
     * 'route' => [
     *     'admin.delivery-orders.show',
     * ],
     */
    if (
        preg_match(
            "/'route'\\s*=>\\s*'admin\\.delivery-orders\\.show'/",
            $block
        )
    ) {
        $block =
            preg_replace(
                "/'route'\\s*=>\\s*'admin\\.delivery-orders\\.show'/",
                "'route' => [\n"
                ."            'admin.invoices.delivery-orders.index',\n"
                ."            'admin.delivery-orders.show',\n"
                ."        ]",
                $block,
                1
            );
    } elseif (
        preg_match(
            "/'route'\\s*=>\\s*\\[(.*?)\\]/s",
            $block,
            $routeArrayMatch
        )
    ) {
        $routeArray =
            $routeArrayMatch[0];

        $newRouteArray =
            preg_replace(
                "/('route'\\s*=>\\s*\\[)/",
                "$1\n            'admin.invoices.delivery-orders.index',",
                $routeArray,
                1
            );

        $block =
            str_replace(
                $routeArray,
                $newRouteArray,
                $block
            );
    } else {
        fwrite(
            STDERR,
            "Format route pada ACL delivery-orders.view tidak dikenali.\n"
        );

        fwrite(
            STDERR,
            "Block:\n{$block}\n"
        );

        exit(13);
    }

    $aclSource =
        substr_replace(
            $aclSource,
            $block,
            $keyPos,
            $nextBlock
                - $keyPos
        );

    file_put_contents(
        $aclPath,
        $aclSource
    );

    echo "[PASS] ACL View Surat Jalan diperluas ke list Invoice.\n";
}

/*
|--------------------------------------------------------------------------
| 3. INVOICE SHOW BUTTON
|--------------------------------------------------------------------------
*/

$viewCandidates =
    filesContaining(
        $projectRoot
            .'/packages/Webkul/Admin/src/Resources/views',
        'View Surat Jalan',
        '.blade.php'
    );

$invoiceViewCandidates = [];

foreach (
    $viewCandidates
    as $candidate
) {
    $source =
        file_get_contents(
            $candidate
        );

    if (
        $source !== false
        && str_contains(
            $source,
            'admin.invoices.delivery-order.generate'
        )
        && str_contains(
            $source,
            '$invoice'
        )
    ) {
        $invoiceViewCandidates[] =
            $candidate;
    }
}

if (
    count(
        $invoiceViewCandidates
    ) !== 1
) {
    fwrite(
        STDERR,
        "Expected 1 Invoice Blade containing View + Generate Surat Jalan, found "
            .count(
                $invoiceViewCandidates
            )
            .".\n"
    );

    foreach (
        $invoiceViewCandidates
        as $candidate
    ) {
        fwrite(
            STDERR,
            " - {$candidate}\n"
        );
    }

    exit(14);
}

$viewPath =
    $invoiceViewCandidates[0];

$viewSource =
    file_get_contents(
        $viewPath
    );

if ($viewSource === false) {
    fwrite(
        STDERR,
        "Tidak dapat membaca Invoice Blade.\n"
    );

    exit(15);
}

backupFile(
    $viewPath,
    '.before-multi-sj-v1-1.bak'
);

if (
    str_contains(
        $viewSource,
        $listRouteName
    )
    && str_contains(
        $viewSource,
        'Generate Additional SJ'
    )
) {
    echo "[SKIP] Invoice buttons multi-SJ sudah terpasang.\n";
} else {
    /*
     * Find the anchor containing visible text "View Surat Jalan".
     */
    $textPos =
        strpos(
            $viewSource,
            'View Surat Jalan'
        );

    if ($textPos === false) {
        fwrite(
            STDERR,
            "Text View Surat Jalan tidak ditemukan.\n"
        );

        exit(16);
    }

    $anchorStart =
        strrpos(
            substr(
                $viewSource,
                0,
                $textPos
            ),
            '<a'
        );

    $anchorEnd =
        strpos(
            $viewSource,
            '</a>',
            $textPos
        );

    if (
        $anchorStart === false
        || $anchorEnd === false
    ) {
        fwrite(
            STDERR,
            "Anchor View Surat Jalan tidak dapat dibaca.\n"
        );

        exit(17);
    }

    $anchorEnd +=
        strlen(
            '</a>'
        );

    $oldAnchor =
        substr(
            $viewSource,
            $anchorStart,
            $anchorEnd
                - $anchorStart
        );

    /*
     * Keep the current classes/styling but redirect to ALL SJ for this invoice.
     */
    $newAnchor =
        preg_replace(
            "/href\\s*=\\s*\"\\{\\{.*?\\}\\}\"/s",
            "href=\"{{ route('admin.invoices.delivery-orders.index', \$invoice->id) }}\"",
            $oldAnchor,
            1,
            $hrefCount
        );

    if (
        $hrefCount !== 1
    ) {
        fwrite(
            STDERR,
            "href View Surat Jalan tidak dapat dipatch.\n"
        );

        exit(18);
    }

    /*
     * Add a second button in the existing-SJ branch.
     * The original no-SJ branch still keeps the first Generate Surat Jalan.
     */
    $additionalForm = <<<'BLADE'

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
            + Generate Additional SJ
        </button>
    </form>
@endif
BLADE;

    $replacement =
        $newAnchor
        .$additionalForm;

    $viewSource =
        substr_replace(
            $viewSource,
            $replacement,
            $anchorStart,
            $anchorEnd
                - $anchorStart
        );

    file_put_contents(
        $viewPath,
        $viewSource
    );

    echo "[PASS] Invoice View Surat Jalan -> list semua SJ.\n";
    echo "[PASS] Tombol + Generate Additional SJ ditambahkan.\n";
}

echo "\n";
echo "Multi-SJ UI patch V1.1 selesai.\n";
echo "Route file : {$routePath}\n";
echo "ACL file   : {$aclPath}\n";
echo "Invoice UI : {$viewPath}\n";
