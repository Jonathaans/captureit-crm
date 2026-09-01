<?php

$projectRoot =
    realpath(
        __DIR__.'/..'
    );

$path =
    $projectRoot
    .'/packages/Webkul/Admin/src/Resources/views/work-orders/print.blade.php';

echo "SPK PDF V1.2.1 CHECK\n";
echo "====================\n\n";

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
    'V1.2.1 marker' =>
        str_contains(
            $source,
            'SPK PDF V1.2.1'
        ),

    'Quotation KOP' =>
        str_contains(
            $source,
            'companyLegalName'
        )
        && str_contains(
            $source,
            'logo-varbel.png'
        )
        && str_contains(
            $source,
            '#d5aa2a'
        ),

    'Simple Invoice / Project grid' =>
        str_contains(
            $source,
            'Invoice'
        )
        && str_contains(
            $source,
            'Project Code'
        )
        && str_contains(
            $source,
            'Customer'
        )
        && str_contains(
            $source,
            'Sales'
        )
        && str_contains(
            $source,
            'Event Date'
        )
        && str_contains(
            $source,
            'Location'
        )
        && str_contains(
            $source,
            'Project / Event'
        ),

    'Product name only' =>
        str_contains(
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

    'Notes' =>
        str_contains(
            $source,
            'Notes / Operational Instruction'
        ),

    'Three signatures' =>
        str_contains(
            $source,
            'Admin Sales'
        )
        && str_contains(
            $source,
            'Operational'
        )
        && str_contains(
            $source,
            'height: 78px'
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
