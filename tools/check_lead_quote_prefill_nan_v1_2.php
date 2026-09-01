<?php

$projectRoot = realpath(__DIR__.'/..');

$path =
    $projectRoot
    .'/packages/Webkul/Admin/src/Resources/views/quotes/create.blade.php';

echo "LEAD -> QUOTE PREFILL NAN V1.2 CHECK\n";
echo "====================================\n\n";

if (! is_file($path)) {
    echo "FAIL\n";
    echo " - Quote create Blade missing.\n";
    exit(1);
}

$source = file_get_contents($path);

$checks = [
    'V1.2 marker' =>
        str_contains(
            $source,
            'LEAD QUOTE PREFILL NAN V1.2'
        ),

    'Initial Lead products normalized' =>
        str_contains(
            $source,
            'this.products = this.normalizeLeadProducts(this.products);'
        ),

    'Fetched Lead products normalized' =>
        str_contains(
            $source,
            'this.normalizeLeadProducts(response.data?.data ?? [])'
        ),

    'Missing day defaults to 1' =>
        str_contains(
            $source,
            'product.price * product.quantity * (product.day || 1)'
        ),

    'Tax defaults to zero' =>
        str_contains(
            $source,
            'parseFloat(product.tax_amount || 0)'
        ),

    'Discount defaults to zero' =>
        str_contains(
            $source,
            'parseFloat(product.discount_amount || 0)'
        ),

    'Quote summary safe calculator still present' =>
        str_contains(
            $source,
            'getProductBaseTotal(product)'
        )
        && str_contains(
            $source,
            'parseDecimal(value)'
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
