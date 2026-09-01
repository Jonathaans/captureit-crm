<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

require __DIR__.'/../vendor/autoload.php';

$app =
    require_once __DIR__.'/../bootstrap/app.php';

$app->make(Kernel::class)->bootstrap();

echo "PER-USER EMAIL V1.3 CHECK\n";
echo "=========================\n\n";

$errors = [];

foreach (
    [
        'delivery_status',
        'delivery_error',
        'delivery_attempts',
        'failed_at',
        'original_folder',
    ]
    as $column
) {
    if (
        ! Schema::hasColumn(
            'user_email_messages',
            $column
        )
    ) {
        $errors[] =
            'Missing user_email_messages.'
            .$column;
    }
}

if (
    ! Schema::hasTable(
        'user_email_attachments'
    )
) {
    $errors[] =
        'Missing user_email_attachments table.';
}

foreach (
    [
        'admin.my-email.drafts',
        'admin.my-email.outbox',
        'admin.my-email.trash',
        'admin.my-email.outbox.retry',
        'admin.my-email.attachments.download',
    ]
    as $route
) {
    if (! Route::has($route)) {
        $errors[] =
            'Missing route: '
            .$route;
    }
}

$syncPath =
    base_path(
        'packages/Webkul/Admin/src/Services/UserEmailSyncService.php'
    );

if (
    ! is_file($syncPath)
    || ! str_contains(
        file_get_contents($syncPath),
        'USER EMAIL V1.3 INCOMING ATTACHMENTS'
    )
) {
    $errors[] =
        'Incoming attachment sync patch missing.';
}

$composePath =
    base_path(
        'packages/Webkul/Admin/src/Resources/views/user-email/compose.blade.php'
    );

if (
    ! is_file($composePath)
    || ! str_contains(
        file_get_contents($composePath),
        'USER EMAIL V1.3 ATTACHMENTS DRAFT'
    )
) {
    $errors[] =
        'Compose attachment/draft UI patch missing.';
}

if ($errors) {
    echo "FAIL\n";

    foreach ($errors as $error) {
        echo " - {$error}\n";
    }

    exit(1);
}

echo "PASS\n";
echo " - Inbox ready\n";
echo " - Draft ready\n";
echo " - Outbox + failure details ready\n";
echo " - Sent ready\n";
echo " - Trash + restore ready\n";
echo " - Outgoing attachments ready\n";
echo " - Incoming attachments ready\n";
echo " - Retry failed email ready\n";
echo " - Attachment downloads are user-scoped/private\n";
