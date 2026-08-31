<?php

/**
 * Invoice Product Column + Product Filter patcher.
 *
 * This script intentionally patches the CURRENT customized InvoiceDataGrid
 * instead of replacing it with a guessed stock file.
 */

$base = __DIR__
    .'/../packages/Webkul/Admin/src/DataGrids';

$candidates = [];

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator(
        $base,
        FilesystemIterator::SKIP_DOTS
    )
);

foreach ($iterator as $file) {
    if (
        ! $file->isFile()
        || strtolower($file->getExtension()) !== 'php'
    ) {
        continue;
    }

    $source = file_get_contents(
        $file->getPathname()
    );

    if (
        $source !== false
        && (
            str_contains(
                $source,
                'class InvoiceDataGrid'
            )
            || str_contains(
                $source,
                'class InvoicesDataGrid'
            )
        )
    ) {
        $candidates[] = $file->getPathname();
    }
}

if (count($candidates) !== 1) {
    fwrite(
        STDERR,
        "Expected exactly one InvoiceDataGrid. Found: "
            .count($candidates)
            ."\n"
    );

    foreach ($candidates as $candidate) {
        fwrite(
            STDERR,
            " - {$candidate}\n"
        );
    }

    exit(1);
}

$path = $candidates[0];

$source = file_get_contents($path);

if ($source === false) {
    fwrite(
        STDERR,
        "Cannot read {$path}\n"
    );

    exit(1);
}

if (
    str_contains(
        $source,
        'invoice_product_items'
    )
    && str_contains(
        $source,
        "'index' => 'products'"
    )
) {
    echo "Invoice Product column/filter already installed.\n";
    exit(0);
}

$backup = $path
    .'.before-product-filter.bak';

if (! is_file($backup)) {
    copy(
        $path,
        $backup
    );
}

/*
|--------------------------------------------------------------------------
| Ensure DB facade
|--------------------------------------------------------------------------
*/

if (
    ! str_contains(
        $source,
        'use Illuminate\\Support\\Facades\\DB;'
    )
) {
    $namespaceEnd = strpos(
        $source,
        "\n\n",
        strpos(
            $source,
            'namespace '
        )
    );

    if ($namespaceEnd === false) {
        fwrite(
            STDERR,
            "Could not locate namespace import section.\n"
        );

        exit(2);
    }

    $source = substr_replace(
        $source,
        "\nuse Illuminate\\Support\\Facades\\DB;",
        $namespaceEnd,
        0
    );
}

/*
|--------------------------------------------------------------------------
| Extend current query after it has been built.
|--------------------------------------------------------------------------
|
| We do not reconstruct the existing query.
| We only:
| 1. join invoice_items under a dedicated alias for filtering,
| 2. add a correlated product-name snapshot column,
| 3. use DISTINCT so one invoice remains one grid row.
|
*/

$firstAddFilter = strpos(
    $source,
    '$this->addFilter('
);

if ($firstAddFilter === false) {
    fwrite(
        STDERR,
        "InvoiceDataGrid addFilter anchor not found.\n"
    );

    exit(3);
}

$queryPatch = <<<'PHP'

        /*
         * Product reporting/filter dimension.
         * invoice_items.name is the historical commercial snapshot.
         */
        $queryBuilder
            ->leftJoin(
                'invoice_items as invoice_product_items',
                'invoices.id',
                '=',
                'invoice_product_items.invoice_id'
            )
            ->addSelect(
                DB::raw(
                    "(SELECT GROUP_CONCAT(DISTINCT ii_product.name ORDER BY ii_product.name SEPARATOR ', ')"
                    ." FROM invoice_items ii_product"
                    ." WHERE ii_product.invoice_id = invoices.id"
                    .") as products"
                )
            )
            ->distinct();

        $this->addFilter(
            'products',
            'invoice_product_items.name'
        );

PHP;

$source = substr_replace(
    $source,
    $queryPatch,
    $firstAddFilter,
    0
);

/*
|--------------------------------------------------------------------------
| Add Product column before Grand Total.
|--------------------------------------------------------------------------
*/

$grandTotalPos = strpos(
    $source,
    "'label' => 'Grand Total'"
);

if ($grandTotalPos === false) {
    $grandTotalPos = strpos(
        $source,
        "'label'      => 'Grand Total'"
    );
}

if ($grandTotalPos === false) {
    fwrite(
        STDERR,
        "Grand Total column anchor not found. No Product column inserted.\n"
    );

    exit(4);
}

$columnStart = strrpos(
    substr(
        $source,
        0,
        $grandTotalPos
    ),
    '$this->addColumn(['
);

if ($columnStart === false) {
    fwrite(
        STDERR,
        "Could not determine Grand Total column start.\n"
    );

    exit(5);
}

$productColumn = <<<'PHP'

        $this->addColumn([
            'index'      => 'products',
            'label'      => 'Product',
            'type'       => 'string',
            'searchable' => false,
            'sortable'   => false,
            'filterable' => true,
            'filterable_type' => 'dropdown',
            'filterable_options' => DB::table('invoice_items')
                ->whereNotNull('name')
                ->where('name', '<>', '')
                ->select('name')
                ->distinct()
                ->orderBy('name')
                ->pluck('name')
                ->map(
                    fn ($name) => [
                        'label' => (string) $name,
                        'value' => (string) $name,
                    ]
                )
                ->values()
                ->all(),
            'closure' => function ($row) {
                $products = trim(
                    (string) (
                        $row->products
                        ?? ''
                    )
                );

                if ($products === '') {
                    return '-';
                }

                $badges = collect(
                    array_filter(
                        array_map(
                            'trim',
                            explode(
                                ',',
                                $products
                            )
                        )
                    )
                )
                    ->unique()
                    ->map(
                        fn ($product) => sprintf(
                            '<span style="display:inline-flex;margin:2px;padding:4px 8px;border-radius:9999px;background:#eff6ff;color:#1d4ed8;font-weight:600;font-size:10px;">%s</span>',
                            e($product)
                        )
                    )
                    ->implode('');

                return $badges;
            },
        ]);

PHP;

$source = substr_replace(
    $source,
    $productColumn,
    $columnStart,
    0
);

file_put_contents(
    $path,
    $source
);

echo "Invoice Product column/filter installed.\n";
echo "Patched: {$path}\n";
echo "Backup: {$backup}\n";
