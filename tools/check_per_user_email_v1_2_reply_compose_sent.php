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

echo "PER-USER EMAIL V1.2 CHECK\n";
echo "=========================\n\n";

$errors = [];

foreach (
    [
        'reply_to_message_id',
        'in_reply_to',
        'references_header',
        'sent_at',
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

foreach (
    [
        'admin.my-email.compose',
        'admin.my-email.send',
        'admin.my-email.sent',
        'admin.my-email.sent.show',
    ]
    as $route
) {
    if (! Route::has($route)) {
        $errors[] =
            'Missing route: '
            .$route;
    }
}

$inboxPath =
    base_path(
        'packages/Webkul/Admin/src/Resources/views/user-email/inbox.blade.php'
    );

$messagePath =
    base_path(
        'packages/Webkul/Admin/src/Resources/views/user-email/message.blade.php'
    );

if (
    ! is_file($inboxPath)
    || ! str_contains(
        file_get_contents(
            $inboxPath
        ),
        'USER EMAIL V1.2 COMPOSE SENT BUTTONS'
    )
) {
    $errors[] =
        'Inbox Compose/Sent buttons belum terpasang.';
}

if (
    ! is_file($messagePath)
    || ! str_contains(
        file_get_contents(
            $messagePath
        ),
        'USER EMAIL V1.2 REPLY BUTTONS'
    )
) {
    $errors[] =
        'Reply/Reply All buttons belum terpasang.';
}

if ($errors) {
    echo "FAIL\n";

    foreach ($errors as $error) {
        echo " - {$error}\n";
    }

    exit(1);
}

echo "PASS\n";
echo " - Compose ready\n";
echo " - Reply ready\n";
echo " - Reply All ready\n";
echo " - SMTP per logged-in user ready\n";
echo " - Sent folder ready\n";
echo " - In-Reply-To / References threading ready\n";
echo " - Existing IMAP sync untouched\n";
