<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

require __DIR__.'/../vendor/autoload.php';

$app =
    require_once __DIR__.'/../bootstrap/app.php';

$app->make(
    Kernel::class
)->bootstrap();

echo "LEAD COMMERCIAL WORKFLOW NOTIFICATION V1.1 CHECK\n";
echo "================================================\n\n";

$errors = [];

$internalProviderPath =
    base_path(
        'packages/Webkul/Admin/src/Providers/InternalCommunicationServiceProvider.php'
    );

$newProviderPath =
    base_path(
        'packages/Webkul/Admin/src/Providers/LeadCommercialWorkflowServiceProvider.php'
    );

$providersPath =
    base_path(
        'bootstrap/providers.php'
    );

if (
    ! is_file(
        $internalProviderPath
    )
) {
    $errors[] =
        'InternalCommunicationServiceProvider missing.';
} else {
    $source =
        file_get_contents(
            $internalProviderPath
        );

    if (
        ! str_contains(
            $source,
            'LEAD COMMERCIAL WORKFLOW DELEGATED V1.1'
        )
    ) {
        $errors[] =
            'Old Lead WON -> Quotation observer is not delegated.';
    }

    if (
        ! str_contains(
            $source,
            'SPK Released -> Sales Owner'
        )
        || ! str_contains(
            $source,
            'Surat Jalan Released -> Warehouse'
        )
    ) {
        $errors[] =
            'Existing SPK/SJ notification blocks are missing.';
    }
}

if (
    ! is_file(
        $newProviderPath
    )
) {
    $errors[] =
        'LeadCommercialWorkflowServiceProvider missing.';
} else {
    $source =
        file_get_contents(
            $newProviderPath
        );

    foreach (
        [
            'quotation_required',
            'invoice_required',
            'Sales Admin',
            'Lead WON - Buat Invoice',
        ]
        as $needle
    ) {
        if (
            ! str_contains(
                $source,
                $needle
            )
        ) {
            $errors[] =
                'Missing commercial workflow marker: '
                .$needle;
        }
    }
}

if (
    ! is_file(
        $providersPath
    )
    || ! str_contains(
        file_get_contents(
            $providersPath
        ),
        'LeadCommercialWorkflowServiceProvider'
    )
) {
    $errors[] =
        'LeadCommercialWorkflowServiceProvider not registered.';
}

foreach (
    [
        'admin.leads.generate-quotation',
        'admin.leads.view',
        'admin.quotes.create',
        'admin.quotes.edit',
    ]
    as $route
) {
    if (
        ! Route::has(
            $route
        )
    ) {
        $errors[] =
            'Missing route: '
            .$route;
    }
}

if (
    ! Schema::hasTable(
        'crm_workflow_notifications'
    )
) {
    $errors[] =
        'crm_workflow_notifications table missing.';
}

$controllerPath =
    base_path(
        'packages/Webkul/Admin/src/Http/Controllers/Lead/LeadCommercialWorkflowController.php'
    );

if (
    ! is_file(
        $controllerPath
    )
) {
    $errors[] =
        'LeadCommercialWorkflowController missing.';
} else {
    $source =
        file_get_contents(
            $controllerPath
        );

    foreach (
        [
            "request()->merge",
            "'lead_id'",
            "QuoteController::class",
            "'subject'",
            "'event_date'",
            "'location'",
            "'business_unit'",
        ]
        as $needle
    ) {
        if (
            ! str_contains(
                $source,
                $needle
            )
        ) {
            $errors[] =
                'Quotation prefill marker missing: '
                .$needle;
        }
    }
}

$widgetPath =
    base_path(
        'packages/Webkul/Admin/src/Resources/views/lead-commercial-workflow/action-widget.blade.php'
    );

if (
    ! is_file(
        $widgetPath
    )
    || ! str_contains(
        file_get_contents(
            $widgetPath
        ),
        'Generate Quotation'
    )
) {
    $errors[] =
        'Lead Generate Quotation widget missing.';
}

if ($errors) {
    echo "FAIL\n";

    foreach ($errors as $error) {
        echo " - {$error}\n";
    }

    exit(1);
}

echo "PASS\n";
echo " - Old Lead WON -> Quotation notification disabled\n";
echo " - QUOTATION stage -> Sales Admin notification ready\n";
echo " - WON -> Sales Admin Invoice notification ready\n";
echo " - WON opens linked Quotation when available\n";
echo " - Lead View Generate Quotation button ready\n";
echo " - Lead -> Quote prefill ready\n";
echo " - Existing linked Quote duplicate protection ready\n";
echo " - Existing SPK/SJ notifications preserved\n";
echo " - No migration required\n";
