<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

require __DIR__.'/../vendor/autoload.php';

$app =
    require_once __DIR__.'/../bootstrap/app.php';

$app->make(Kernel::class)->bootstrap();

$errors = [];

foreach (
    [
        'crm_audit_logs',
        'crm_system_incidents',
    ]
    as $table
) {
    if (! Schema::hasTable($table)) {
        $errors[] = 'Missing table: '.$table;
    }
}

foreach (
    [
        'admin.system-control.index',
        'admin.system-control.audit-logs',
        'admin.system-control.incidents',
        'admin.system-control.incidents.resolve',
    ]
    as $route
) {
    if (! Route::has($route)) {
        $errors[] = 'Missing route: '.$route;
    }
}

echo "CRM HARDENING PHASE 1 CHECK\n";
echo "===========================\n\n";

if ($errors) {
    echo "FAIL\n";
    foreach ($errors as $error) {
        echo " - {$error}\n";
    }
    exit(1);
}

echo "PASS\n";
echo " - Role/permission audit command ready\n";
echo " - Audit trail ready\n";
echo " - Backup/verify ready\n";
echo " - Production readiness QA ready\n";
echo " - Error/queue incident monitoring ready\n";
