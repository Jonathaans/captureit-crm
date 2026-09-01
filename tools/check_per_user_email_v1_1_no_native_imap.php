<?php

use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';

$app =
    require_once __DIR__.'/../bootstrap/app.php';

$app->make(
    Kernel::class
)->bootstrap();

echo "PER-USER EMAIL V1.1 NO-NATIVE-IMAP CHECK\n";
echo "=========================================\n\n";

$errors = [];
$warnings = [];

$base =
    base_path(
        'packages/Webkul/Admin/src/Services'
    );

$clientPath =
    $base
    .'/PurePhpImapClient.php';

$parserPath =
    $base
    .'/Rfc822EmailParser.php';

$connectionPath =
    $base
    .'/UserEmailConnectionService.php';

$syncPath =
    $base
    .'/UserEmailSyncService.php';

foreach (
    [
        $clientPath,
        $parserPath,
        $connectionPath,
        $syncPath,
    ]
    as $path
) {
    if (! is_file($path)) {
        $errors[] =
            'Missing: '
            .$path;
    }
}

if (
    is_file(
        $connectionPath
    )
    && preg_match(
        '/\bimap_[a-z_]+\s*\(/i',
        file_get_contents(
            $connectionPath
        )
    )
) {
    $errors[] =
        'UserEmailConnectionService masih memakai native imap_* function.';
}

if (
    is_file(
        $syncPath
    )
    && preg_match(
        '/\bimap_[a-z_]+\s*\(/i',
        file_get_contents(
            $syncPath
        )
    )
) {
    $errors[] =
        'UserEmailSyncService masih memakai native imap_* function.';
}

if (
    ! function_exists(
        'stream_socket_client'
    )
) {
    $errors[] =
        'stream_socket_client tidak tersedia.';
}

if (
    ! extension_loaded(
        'openssl'
    )
) {
    $errors[] =
        'OpenSSL belum aktif.';
}

if (
    ! function_exists(
        'mb_decode_mimeheader'
    )
    && ! function_exists(
        'iconv_mime_decode'
    )
) {
    $warnings[] =
        'mbstring/iconv MIME header decoder tidak tersedia; encoded subject/name tertentu mungkin tidak terdecode sempurna.';
}

if ($errors) {
    echo "FAIL\n";

    foreach ($errors as $error) {
        echo " - {$error}\n";
    }

    exit(1);
}

echo "PASS\n";
echo "Native ext-imap required : NO\n";
echo "php_imap.dll required    : NO\n";
echo "stream_socket_client     : ENABLED\n";
echo "OpenSSL                  : ENABLED\n";
echo "Pure PHP IMAP client     : READY\n";
echo "RFC822/MIME parser       : READY\n";

if ($warnings) {
    echo "\nWARNINGS\n";

    foreach ($warnings as $warning) {
        echo " - {$warning}\n";
    }
}

echo "\n";
echo "Next real test:\n";
echo "1. Login as one CRM user.\n";
echo "2. /admin/my-email/settings\n";
echo "3. Save exact Domainesia/cPanel IMAP+SMTP values.\n";
echo "4. Test IMAP.\n";
echo "5. Test SMTP.\n";
echo "6. /admin/my-email -> Sync Now.\n";
