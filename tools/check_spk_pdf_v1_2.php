<?php

$projectRoot =
    realpath(
        __DIR__.'/..'
    );

$path =
    $projectRoot
    .'/packages/Webkul/Admin/src/Resources/views/work-orders/print.blade.php';

echo "SPK PDF V1.2 CHECK\n";
echo "==================\n\n";

if (! is_file($path)) {
    echo "FAIL\n";
    echo " - SPK print Blade missing.\n";
    exit(1);
}

$source =
    file_get_contents(
        $path
    );

$checks = [
    'V1.2 marker'
        => str_contains(
            $source,
            'SPK PDF V1.2'
        ),

    'Quotation KOP'
        => str_contains(
            $source,
            'companyLegalName'
        )
        && str_contains(
            $source,
            'logo-varbel.png'
        ),

    'Gold header rule'
        => str_contains(
            $source,
            '#d5aa2a'
        ),

    'Bill To'
        => str_contains(
            $source,
            'Bill To'
        ),

    'Expanded details'
        => str_contains(
            $source,
            'Quote Ref.'
        )
        && str_contains(
            $source,
            'Invoice Date'
        )
        && str_contains(
            $source,
            'SPK Status'
        ),

    'Product name only'
        => str_contains(
            $source,
            'Product / Service Name'
        )
        && ! str_contains(
            strtolower(
                $source
            ),
            'grand_total'
        )
        && ! str_contains(
            strtolower(
                $source
            ),
            'unit price'
        ),

    'Three signatures'
        => str_contains(
            $source,
            'Admin Sales'
        )
        && str_contains(
            $source,
            'Operational'
        )
        && str_contains(
            $source,
            'height: 92px'
        ),

    'Quotation footer'
        => str_contains(
            $source,
            'Member of Rental Indonesia.'
        ),
];

$failed = [];

foreach ($checks as $label => $ok) {
    if (! $ok) {
        $failed[] = $label;
    }
}

if ($failed) {
    echo "FAIL\n";

    foreach ($failed as $label) {
        echo " - {$label}\n";
    }

    exit(1);
}

echo "PASS\n";

foreach (array_keys($checks) as $label) {
    echo " - {$label}\n";
}
