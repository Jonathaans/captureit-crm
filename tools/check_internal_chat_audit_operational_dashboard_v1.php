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

echo "INTERNAL CHAT AUDIT / OPERATIONAL DASHBOARD CHECK\n";
echo "================================================\n\n";

$provider =
    base_path(
        'packages/Webkul/Admin/src/Providers/InternalCommunicationServiceProvider.php'
    );

$menu =
    base_path(
        'packages/Webkul/Admin/src/Config/menu.php'
    );

$providerSource =
    is_file($provider)
        ? file_get_contents($provider)
        : '';

$menuSource =
    is_file($menu)
        ? file_get_contents($menu)
        : '';

$checks = [
    'Audit table' =>
        Schema::hasTable(
            'internal_message_audits'
        ),

    'Audit table old/new body' =>
        Schema::hasColumn(
            'internal_message_audits',
            'old_body'
        )
        && Schema::hasColumn(
            'internal_message_audits',
            'new_body'
        ),

    'Observer registered' =>
        str_contains(
            $providerSource,
            'InternalMessageAuditObserver::class'
        )
        && str_contains(
            $providerSource,
            'InternalMessage::observe('
        ),

    'Audit index route' =>
        Route::has(
            'admin.operational-dashboard.internal-chat-audit.index'
        ),

    'Audit detail route' =>
        Route::has(
            'admin.operational-dashboard.internal-chat-audit.show'
        ),

    'Audit controller' =>
        is_file(
            base_path(
                'packages/Webkul/Admin/src/Http/Controllers/InternalCommunication/InternalChatAuditController.php'
            )
        ),

    'Audit list view' =>
        is_file(
            base_path(
                'packages/Webkul/Admin/src/Resources/views/internal-communication/audit/index.blade.php'
            )
        ),

    'Audit detail view' =>
        is_file(
            base_path(
                'packages/Webkul/Admin/src/Resources/views/internal-communication/audit/show.blade.php'
            )
        ),

    'Operational Dashboard menu link' =>
        str_contains(
            $menuSource,
            'admin.operational-dashboard.internal-chat-audit.index'
        ),

    'Role restriction config' =>
        is_file(
            base_path(
                'config/internal_chat_audit.php'
            )
        ),
];

$failed = [];

foreach ($checks as $label => $ok) {
    if (! $ok) {
        $failed[] =
            $label;
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
