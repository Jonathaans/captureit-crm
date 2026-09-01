<?php

use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';

$app =
    require_once __DIR__.'/../bootstrap/app.php';

$app->make(
    Kernel::class
)->bootstrap();

$path =
    base_path(
        'packages/Webkul/Admin/src/Providers/InternalCommunicationServiceProvider.php'
    );

echo "DELIVERY ORDER CREATED WAREHOUSE NOTIFICATION V1.2 CHECK\n";
echo "========================================================\n\n";

if (! is_file($path)) {
    echo "FAIL\n";
    echo " - InternalCommunicationServiceProvider missing.\n";
    exit(1);
}

$source =
    file_get_contents(
        $path
    );

$checks = [
    'Created observer marker' =>
        str_contains(
            $source,
            'Surat Jalan Created -> Warehouse V1.2'
        ),

    'Created event observer' =>
        str_contains(
            $source,
            '$deliveryOrderClass::created('
        ),

    'Head Warehouse recipient' =>
        str_contains(
            $source,
            "'Head Warehouse'"
        ),

    'Warehouse User recipient' =>
        str_contains(
            $source,
            "'Warehouse User'"
        ),

    'Created notification type' =>
        str_contains(
            $source,
            "'delivery_order_created'"
        ),

    'Created dedupe key' =>
        str_contains(
            $source,
            "'delivery-order-created:'"
        ),

    'Open Surat Jalan action' =>
        str_contains(
            $source,
            "'admin.delivery-orders.show'"
        ),

    'Existing Released notification preserved' =>
        str_contains(
            $source,
            'Surat Jalan Released -> Warehouse'
        )
        && str_contains(
            $source,
            "'delivery_order_released'"
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
