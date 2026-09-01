<?php

/*
|--------------------------------------------------------------------------
| CRM Internal Notifications + Chat V1 Installer
|--------------------------------------------------------------------------
|
| New module files are provided by the ZIP.
|
| This installer only:
| - registers InternalCommunicationServiceProvider in bootstrap/providers.php
|
| It does NOT overwrite:
| Lead controller/model
| Invoice controller
| SPK controller
| DeliveryOrder controller
| Admin master layout
| Existing crm_notifications table
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
    'packages/Webkul/Admin/src/Providers/InternalCommunicationServiceProvider.php',
    'packages/Webkul/Admin/src/Http/Middleware/InjectInternalCommunicationUi.php',
    'packages/Webkul/Admin/src/Services/WorkflowNotificationService.php',
    'packages/Webkul/Admin/src/Services/InternalChatService.php',
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
            "Required file tidak ditemukan setelah extract: "
            .$relative
            ."\n"
        );

        exit(2);
    }
}

$providerPath =
    $projectRoot
    .'/bootstrap/providers.php';

if (! is_file($providerPath)) {
    fwrite(
        STDERR,
        "bootstrap/providers.php tidak ditemukan.\n"
    );

    exit(3);
}

$source =
    file_get_contents(
        $providerPath
    );

$provider =
    '\\Webkul\\Admin\\Providers\\InternalCommunicationServiceProvider::class';

if (
    str_contains(
        $source,
        $provider
    )
) {
    echo "[SKIP] InternalCommunicationServiceProvider already registered.\n";
} else {
    $end =
        strrpos(
            $source,
            '];'
        );

    if ($end === false) {
        fwrite(
            STDERR,
            "bootstrap/providers.php format tidak dikenali.\n"
        );

        exit(4);
    }

    $backup =
        $providerPath
        .'.before-internal-communication-v1.bak';

    if (! is_file($backup)) {
        if (! copy(
            $providerPath,
            $backup
        )) {
            fwrite(
                STDERR,
                "Gagal membuat backup bootstrap/providers.php.\n"
            );

            exit(5);
        }
    }

    $source =
        substr_replace(
            $source,
            "    {$provider},\n",
            $end,
            0
        );

    if (
        file_put_contents(
            $providerPath,
            $source
        ) === false
    ) {
        fwrite(
            STDERR,
            "Gagal menulis bootstrap/providers.php.\n"
        );

        exit(6);
    }

    echo "[PASS] InternalCommunicationServiceProvider registered.\n";
}

echo "[PASS] Global popup uses response middleware, master layout untouched.\n";
echo "[PASS] Lead WON -> Sales Admin observer ready.\n";
echo "[PASS] SPK Released -> Sales Owner observer ready.\n";
echo "[PASS] Surat Jalan Released -> Warehouse observer ready.\n";
echo "[PASS] Direct internal chat ready.\n";
echo "[PASS] Chat attachments ready.\n";
echo "\n";
echo "Next:\n";
echo "php artisan migrate\n";
echo "php artisan optimize:clear\n";
echo "php tools\\check_crm_internal_notifications_chat_v1.php\n";
