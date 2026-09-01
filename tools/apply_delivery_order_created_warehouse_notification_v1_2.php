<?php

/*
|--------------------------------------------------------------------------
| Delivery Order Created -> Warehouse Notification V1.2
|--------------------------------------------------------------------------
|
| Existing behavior:
| Delivery Order / Surat Jalan RELEASED
| -> Head Warehouse + Warehouse User
|
| New behavior:
| Delivery Order / Surat Jalan CREATED
| -> Head Warehouse + Warehouse User
|
| This patch is surgical:
| - modifies only InternalCommunicationServiceProvider.php
| - preserves existing RELEASED notification
| - no migration
|
*/

$projectRoot = realpath(__DIR__.'/..');

if (! $projectRoot) {
    fwrite(STDERR, "Project root tidak ditemukan.\n");
    exit(1);
}

$providerPath =
    $projectRoot
    .'/packages/Webkul/Admin/src/Providers/InternalCommunicationServiceProvider.php';

if (! is_file($providerPath)) {
    fwrite(
        STDERR,
        "InternalCommunicationServiceProvider.php tidak ditemukan.\n"
        ."Install Internal Notifications + Chat V1 terlebih dahulu.\n"
    );

    exit(2);
}

$source = file_get_contents($providerPath);

if ($source === false) {
    fwrite(
        STDERR,
        "Provider tidak dapat dibaca.\n"
    );

    exit(3);
}

$requiredMarkers = [
    'Surat Jalan Released -> Warehouse',
    '$deliveryOrderClass',
    'WorkflowNotificationService::class',
    "'Head Warehouse'",
    "'Warehouse User'",
    "'delivery_order_released'",
];

foreach ($requiredMarkers as $marker) {
    if (! str_contains($source, $marker)) {
        fwrite(
            STDERR,
            "Required marker tidak ditemukan: {$marker}\n"
            ."Patch dihentikan agar provider tidak rusak.\n"
        );

        exit(4);
    }
}

$createdMarker =
    'Surat Jalan Created -> Warehouse V1.2';

if (
    str_contains(
        $source,
        $createdMarker
    )
) {
    echo "[SKIP] Delivery Order CREATED notification V1.2 already installed.\n";
    exit(0);
}

/*
|--------------------------------------------------------------------------
| Insert CREATED observer immediately before existing RELEASED observer block.
|--------------------------------------------------------------------------
*/

$releaseCommentMarker =
    '| Surat Jalan Released -> Warehouse';

$releaseMarkerPos =
    strpos(
        $source,
        $releaseCommentMarker
    );

if ($releaseMarkerPos === false) {
    fwrite(
        STDERR,
        "Existing Surat Jalan Released marker tidak ditemukan.\n"
    );

    exit(5);
}

$commentStart =
    strrpos(
        substr(
            $source,
            0,
            $releaseMarkerPos
        ),
        '/*'
    );

if ($commentStart === false) {
    fwrite(
        STDERR,
        "Boundary existing SJ Released block tidak dapat dibaca.\n"
    );

    exit(6);
}

$createdBlock = <<<'PHP'
        /*
        |--------------------------------------------------------------------------
        | Surat Jalan Created -> Warehouse V1.2
        |--------------------------------------------------------------------------
        |
        | As soon as a Delivery Order / Surat Jalan record is created,
        | notify all active:
        | - Head Warehouse
        | - Warehouse User
        |
        | The existing RELEASED notification below remains unchanged.
        |
        */

        $deliveryOrderClass =
            \Webkul\Invoice\Models\DeliveryOrder::class;

        if (class_exists($deliveryOrderClass)) {
            $deliveryOrderClass::created(
                function ($deliveryOrder) {
                    $service =
                        app(
                            WorkflowNotificationService::class
                        );

                    $recipientIds =
                        $service->usersByRoleNames([
                            'Head Warehouse',
                            'Warehouse User',
                        ]);

                    if ($recipientIds->isEmpty()) {
                        return;
                    }

                    $number =
                        $deliveryOrder
                            ->delivery_order_number
                        ?? (
                            'SJ #'
                            .$deliveryOrder->id
                        );

                    $message =
                        $number;

                    $meta = [
                        'work_order_id' =>
                            $deliveryOrder
                                ->work_order_id
                            ?? null,

                        'invoice_id' =>
                            $deliveryOrder
                                ->invoice_id
                            ?? null,
                    ];

                    if (
                        ! empty(
                            $deliveryOrder
                                ->work_order_id
                        )
                    ) {
                        $workOrder =
                            \Illuminate\Support\Facades\DB::table(
                                'work_orders'
                            )
                                ->where(
                                    'id',
                                    $deliveryOrder
                                        ->work_order_id
                                )
                                ->first();

                        if ($workOrder) {
                            if (
                                ! empty(
                                    $workOrder
                                        ->work_order_number
                                )
                            ) {
                                $message .=
                                    ' · '
                                    .$workOrder
                                        ->work_order_number;
                            }

                            if (
                                ! empty(
                                    $workOrder
                                        ->project_code
                                )
                            ) {
                                $message .=
                                    ' · '
                                    .$workOrder
                                        ->project_code;
                            }

                            $meta['work_order_number'] =
                                $workOrder
                                    ->work_order_number
                                ?? null;

                            $meta['project_code'] =
                                $workOrder
                                    ->project_code
                                ?? null;
                        }
                    }

                    $message .=
                        ' · Surat Jalan baru dibuat dan menunggu proses Warehouse.';

                    $service->notifyUsers(
                        $recipientIds,
                        'delivery_order_created',
                        'Surat Jalan Baru Dibuat',
                        $message,
                        route(
                            'admin.delivery-orders.show',
                            $deliveryOrder->id
                        ),
                        'delivery-order-created:'
                            .$deliveryOrder->id,
                        'delivery_order',
                        $deliveryOrder->id,
                        $meta
                    );
                }
            );
        }

PHP;

$patched =
    substr_replace(
        $source,
        $createdBlock,
        $commentStart,
        0
    );

/*
|--------------------------------------------------------------------------
| Validate before write.
|--------------------------------------------------------------------------
*/

$validationMarkers = [
    $createdMarker,
    "'delivery_order_created'",
    "'Surat Jalan Baru Dibuat'",
    "'Head Warehouse'",
    "'Warehouse User'",
    "'delivery-order-created:'",
    'Surat Jalan Released -> Warehouse',
    "'delivery_order_released'",
];

foreach ($validationMarkers as $marker) {
    if (! str_contains($patched, $marker)) {
        fwrite(
            STDERR,
            "Hasil patch gagal validasi: {$marker}\n"
        );

        exit(7);
    }
}

$backup =
    $providerPath
    .'.before-delivery-order-created-notification-v1-2.bak';

if (! is_file($backup)) {
    if (! copy($providerPath, $backup)) {
        fwrite(
            STDERR,
            "Gagal membuat backup provider.\n"
        );

        exit(8);
    }
}

if (
    file_put_contents(
        $providerPath,
        $patched
    ) === false
) {
    fwrite(
        STDERR,
        "Gagal menulis provider.\n"
    );

    exit(9);
}

echo "[PASS] Delivery Order CREATED -> Head Warehouse notification ready.\n";
echo "[PASS] Delivery Order CREATED -> Warehouse User notification ready.\n";
echo "[PASS] Existing Delivery Order RELEASED notification preserved.\n";
echo "[PASS] Duplicate protection uses delivery-order-created:{id}.\n";
echo "[PASS] No migration.\n";
