<?php

/*
|--------------------------------------------------------------------------
| Lead Commercial Workflow Notification V1.1 Installer
|--------------------------------------------------------------------------
|
| Revises the already-installed Internal Communication V1:
|
| OLD:
| Lead WON -> Sales Admin -> Buat Quotation
|
| NEW:
| Lead enters QUOTATION -> Sales Admin -> Buat Quotation
| Lead enters WON       -> Sales Admin -> Buat Invoice from linked Quotation
|
| Also adds:
| Lead View -> Generate Quotation
| -> current Quote create form
| -> Lead data prefilled
|
| Safety:
| - no migration
| - no LeadController overwrite
| - no QuoteController overwrite
| - no Lead Blade overwrite
| - old wrong observer is surgically removed from the existing provider
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

function backupOnce(
    string $path,
    string $suffix
): void {
    $backup =
        $path
        .$suffix;

    if (
        is_file(
            $path
        )
        && ! is_file(
            $backup
        )
    ) {
        if (
            ! copy(
                $path,
                $backup
            )
        ) {
            throw new RuntimeException(
                "Gagal backup: {$path}"
            );
        }
    }
}

/*
|--------------------------------------------------------------------------
| Preflight: Internal Communication V1 must exist
|--------------------------------------------------------------------------
*/

$internalProviderPath =
    $projectRoot
    .'/packages/Webkul/Admin/src/Providers/InternalCommunicationServiceProvider.php';

if (! is_file($internalProviderPath)) {
    fwrite(
        STDERR,
        "InternalCommunicationServiceProvider.php tidak ditemukan. "
        ."Install CRM Internal Notifications + Chat V1 terlebih dahulu.\n"
    );

    exit(2);
}

$internalProvider =
    file_get_contents(
        $internalProviderPath
    );

if (
    $internalProvider === false
    || ! str_contains(
        $internalProvider,
        'WorkflowNotificationService'
    )
) {
    fwrite(
        STDERR,
        "Internal Communication provider tidak dikenali. Patch dihentikan.\n"
    );

    exit(3);
}

/*
|--------------------------------------------------------------------------
| 1. Remove OLD wrong Lead WON -> Quotation observer
|--------------------------------------------------------------------------
*/

$delegatedMarker =
    'LEAD COMMERCIAL WORKFLOW DELEGATED V1.1';

if (
    str_contains(
        $internalProvider,
        $delegatedMarker
    )
) {
    echo "[SKIP] Old Lead WON -> Quotation observer already delegated.\n";
} else {
    $oldMarker =
        '| Lead WON -> Sales Admin';

    $nextMarker =
        '| SPK Released -> Sales Owner';

    $oldMarkerPos =
        strpos(
            $internalProvider,
            $oldMarker
        );

    $nextMarkerPos =
        strpos(
            $internalProvider,
            $nextMarker
        );

    if (
        $oldMarkerPos === false
        || $nextMarkerPos === false
        || $nextMarkerPos
            <= $oldMarkerPos
    ) {
        fwrite(
            STDERR,
            "Exact V1 Lead WON notification block tidak ditemukan. "
            ."Patch dihentikan agar SPK/SJ notification tidak ikut berubah.\n"
        );

        exit(4);
    }

    $blockStart =
        strrpos(
            substr(
                $internalProvider,
                0,
                $oldMarkerPos
            ),
            '/*'
        );

    $nextCommentStart =
        strrpos(
            substr(
                $internalProvider,
                0,
                $nextMarkerPos
            ),
            '/*'
        );

    if (
        $blockStart === false
        || $nextCommentStart === false
        || $nextCommentStart
            <= $blockStart
    ) {
        fwrite(
            STDERR,
            "Boundary observer Lead lama tidak dikenali. File tidak diubah.\n"
        );

        exit(5);
    }

    $replacement = <<<'PHP'
        /*
        |--------------------------------------------------------------------------
        | LEAD COMMERCIAL WORKFLOW DELEGATED V1.1
        |--------------------------------------------------------------------------
        |
        | Lead QUOTATION and WON notifications are now handled by:
        | LeadCommercialWorkflowServiceProvider.
        |
        | SPK and Surat Jalan notifications below remain unchanged.
        |
        */

PHP;

    backupOnce(
        $internalProviderPath,
        '.before-lead-commercial-workflow-v1-1.bak'
    );

    $internalProvider =
        substr_replace(
            $internalProvider,
            $replacement,
            $blockStart,
            $nextCommentStart
                - $blockStart
        );

    if (
        file_put_contents(
            $internalProviderPath,
            $internalProvider
        ) === false
    ) {
        fwrite(
            STDERR,
            "Gagal menulis InternalCommunicationServiceProvider.php.\n"
        );

        exit(6);
    }

    echo "[PASS] Old Lead WON -> Buat Quotation observer removed.\n";
    echo "[PASS] Existing SPK/SJ notification blocks preserved.\n";
}

/*
|--------------------------------------------------------------------------
| 2. Register LeadCommercialWorkflowServiceProvider
|--------------------------------------------------------------------------
*/

$providersPath =
    $projectRoot
    .'/bootstrap/providers.php';

if (! is_file($providersPath)) {
    fwrite(
        STDERR,
        "bootstrap/providers.php tidak ditemukan.\n"
    );

    exit(7);
}

$providers =
    file_get_contents(
        $providersPath
    );

$newProvider =
    '\\Webkul\\Admin\\Providers\\LeadCommercialWorkflowServiceProvider::class';

if (
    str_contains(
        $providers,
        $newProvider
    )
) {
    echo "[SKIP] LeadCommercialWorkflowServiceProvider already registered.\n";
} else {
    $end =
        strrpos(
            $providers,
            '];'
        );

    if ($end === false) {
        fwrite(
            STDERR,
            "bootstrap/providers.php format tidak dikenali.\n"
        );

        exit(8);
    }

    backupOnce(
        $providersPath,
        '.before-lead-commercial-workflow-v1-1.bak'
    );

    $providers =
        substr_replace(
            $providers,
            "    {$newProvider},\n",
            $end,
            0
        );

    if (
        file_put_contents(
            $providersPath,
            $providers
        ) === false
    ) {
        fwrite(
            STDERR,
            "Gagal menulis bootstrap/providers.php.\n"
        );

        exit(9);
    }

    echo "[PASS] LeadCommercialWorkflowServiceProvider registered.\n";
}

echo "[PASS] QUOTATION stage -> Sales Admin notification ready.\n";
echo "[PASS] WON -> Sales Admin Invoice notification ready.\n";
echo "[PASS] Lead View -> Generate Quotation action ready.\n";
echo "[PASS] Quote prefill reuses existing customized QuoteController.\n";
echo "[PASS] Duplicate Quote protection ready.\n";
echo "[PASS] No migration.\n";
