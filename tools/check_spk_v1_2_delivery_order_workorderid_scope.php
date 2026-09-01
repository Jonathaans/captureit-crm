<?php

$projectRoot = realpath(__DIR__.'/..');

$path =
    $projectRoot
    .'/packages/Webkul/Invoice/src/Services/DeliveryOrderService.php';

echo "SPK V1.2 WORKORDERID SCOPE CHECK\n";
echo "================================\n\n";

if (! is_file($path)) {
    echo "FAIL\n";
    echo " - DeliveryOrderService.php missing.\n";
    exit(1);
}

$source = file_get_contents($path);

$checks = [
    'SPK guard' =>
        str_contains(
            $source,
            'SPK WORK ORDER V1 GUARD'
        ),

    'SPK link' =>
        str_contains(
            $source,
            'SPK WORK ORDER V1 LINK'
        ),

    'Method accepts workOrderId' =>
        str_contains(
            $source,
            '?int $workOrderId = null'
        ),

    'Transaction imports workOrderId' =>
        (
            str_contains(
                $source,
                "function () use (\n                \$invoice,\n                \$createdBy,\n                \$workOrderId\n            ) {"
            )
            || str_contains(
                $source,
                'function () use ($invoice, $createdBy, $workOrderId) {'
            )
        ),

    'Delivery order writes work_order_id' =>
        str_contains(
            $source,
            "'work_order_id'"
        )
        && str_contains(
            $source,
            '$workOrderId'
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
