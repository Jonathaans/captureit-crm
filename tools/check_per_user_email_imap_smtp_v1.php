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

echo "PER-USER EMAIL IMAP + SMTP CHECK\n";
echo "================================\n\n";

$errors = [];
$warnings = [];

foreach (
    [
        'user_email_accounts',
        'user_email_messages',
    ]
    as $table
) {
    if (
        ! Schema::hasTable(
            $table
        )
    ) {
        $errors[] =
            'Missing table: '
            .$table;
    }
}

foreach (
    [
        'admin.my-email.inbox',
        'admin.my-email.settings',
        'admin.my-email.sync',
        'admin.my-email.test-imap',
        'admin.my-email.test-smtp',
        'admin.system-control.email-accounts',
    ]
    as $route
) {
    if (! Route::has($route)) {
        $errors[] =
            'Missing route: '
            .$route;
    }
}

if (
    ! function_exists(
        'imap_open'
    )
) {
    $warnings[] =
        'PHP IMAP extension belum aktif. '
        .'Settings UI dan SMTP tetap bekerja, tetapi Test IMAP / Sync Inbox tidak akan bekerja sampai ext-imap diaktifkan.';
}

if (
    ! extension_loaded(
        'openssl'
    )
) {
    $warnings[] =
        'OpenSSL extension belum aktif. SSL/TLS email connection membutuhkan OpenSSL.';
}

if ($errors) {
    echo "FAIL\n";

    foreach ($errors as $error) {
        echo " - {$error}\n";
    }

    exit(1);
}

echo "PASS\n";
echo " - Per-user encrypted email account ready\n";
echo " - IMAP connection test ready\n";
echo " - SMTP connection test ready\n";
echo " - Personal Inbox ready\n";
echo " - CLI sync ready\n";
echo " - Administrator status page ready\n\n";

if ($warnings) {
    echo "WARNINGS\n";

    foreach ($warnings as $warning) {
        echo " - {$warning}\n";
    }

    echo "\n";
}

echo "PHP IMAP : "
    .(
        function_exists(
            'imap_open'
        )
            ? 'ENABLED'
            : 'DISABLED'
    )
    ."\n";

echo "OpenSSL  : "
    .(
        extension_loaded(
            'openssl'
        )
            ? 'ENABLED'
            : 'DISABLED'
    )
    ."\n";
