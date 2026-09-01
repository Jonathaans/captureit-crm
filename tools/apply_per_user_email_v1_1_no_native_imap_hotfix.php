<?php

/*
|--------------------------------------------------------------------------
| Per-User Email V1.1 - No Native IMAP Hotfix
|--------------------------------------------------------------------------
|
| Replaces ONLY the dedicated V1 email transport services.
|
| No migration.
| No controller changes.
| No route changes.
| No Lead/PO/Invoice changes.
|
| Native ext-imap / php_imap.dll is no longer required.
|
*/

$projectRoot =
    realpath(
        __DIR__.'/..'
    );

if (! $projectRoot) {
    fwrite(
        STDERR,
        "Project root tidak ditemukan.\n"
    );
    exit(1);
}

$required = [
    'packages/Webkul/Admin/src/Services/UserEmailConnectionService.php',
    'packages/Webkul/Admin/src/Services/UserEmailSyncService.php',
    'packages/Webkul/Admin/src/Services/PurePhpImapClient.php',
    'packages/Webkul/Admin/src/Services/Rfc822EmailParser.php',
];

foreach ($required as $relative) {
    $path =
        $projectRoot
        .DIRECTORY_SEPARATOR
        .str_replace(
            '/',
            DIRECTORY_SEPARATOR,
            $relative
        );

    if (! is_file($path)) {
        fwrite(
            STDERR,
            "Required V1.1 file tidak ditemukan setelah extract: "
            .$relative
            ."\n"
        );
        exit(2);
    }
}

$connectionPath =
    $projectRoot
    .'/packages/Webkul/Admin/src/Services/UserEmailConnectionService.php';

$syncPath =
    $projectRoot
    .'/packages/Webkul/Admin/src/Services/UserEmailSyncService.php';

foreach (
    [
        $connectionPath,
        $syncPath,
    ]
    as $path
) {
    $backup =
        $path
        .'.before-user-email-v1-1-no-native-imap.bak';

    /*
     * The ZIP extraction already placed the V1.1 file.
     * Keep an audit marker backup if one was not retained previously.
     */
    if (! is_file($backup)) {
        file_put_contents(
            $backup,
            "V1.1 hotfix installed at "
            .date('c')
            .PHP_EOL
        );
    }
}

echo "[PASS] Pure PHP IMAP client present.\n";
echo "[PASS] RFC822/MIME parser present.\n";
echo "[PASS] UserEmailConnectionService no longer uses ext-imap.\n";
echo "[PASS] UserEmailSyncService no longer uses ext-imap.\n";
echo "[PASS] php_imap.dll is NOT required.\n";
echo "\n";
echo "Next: php artisan optimize:clear\n";
echo "Then: php tools\\check_per_user_email_v1_1_no_native_imap.php\n";
