<?php

/*
|--------------------------------------------------------------------------
| Internal Chat V3.2.5 - Compact Image Preview Toolbar
|--------------------------------------------------------------------------
|
| Fixes image preview UX:
| - image preview too large
| - Download / Save button not visible without scrolling
| - Back / Close action not visible
|
| PDF preview is preserved exactly as the current iframe viewer.
|
| Scope:
| - chat.blade.php only
| - no controller / route / provider / DB / migration changes
|
*/

$root =
    realpath(
        __DIR__.'/..'
    );

if (! $root) {
    fwrite(
        STDERR,
        "Project root tidak ditemukan.\n"
    );

    exit(1);
}

$blade =
    $root
    .'/packages/Webkul/Admin/src/Resources/views/internal-communication/chat.blade.php';

if (! is_file($blade)) {
    fwrite(
        STDERR,
        "chat.blade.php tidak ditemukan.\n"
    );

    exit(2);
}

$source =
    file_get_contents(
        $blade
    );

if ($source === false) {
    fwrite(
        STDERR,
        "chat.blade.php tidak dapat dibaca.\n"
    );

    exit(3);
}

if (
    str_contains(
        $source,
        'INTERNAL CHAT V3.2.5 COMPACT IMAGE PREVIEW TOOLBAR'
    )
) {
    echo "[SKIP] Internal Chat V3.2.5 already installed.\n";

    exit(0);
}

$required = [
    'INTERNAL CHAT V3.2.4 MODERN IMAGE PREVIEW',
    'id="crm-chat-attachment-preview-modal"',
    'id="crm-chat-image-preview-stage"',
    'id="crm-chat-attachment-preview-image"',
    'id="crm-chat-attachment-preview-frame"',
    'id="crm-chat-attachment-preview-name"',
    'id="crm-chat-attachment-download"',
    '⬇ Download / Save',
    'window.crmChatPreviewAttachment',
    'window.crmChatPreviewClose',
];

foreach ($required as $marker) {
    if (
        ! str_contains(
            $source,
            $marker
        )
    ) {
        fwrite(
            STDERR,
            "Current V3.2.4 baseline tidak dikenali: {$marker}\n"
            ."Patch dihentikan agar Blade customized tidak rusak.\n"
        );

        exit(4);
    }
}

$backup =
    $blade
    .'.before-internal-chat-v3-2-5-compact-image-preview-toolbar.bak';

if (! is_file($backup)) {
    if (
        ! copy(
            $blade,
            $backup
        )
    ) {
        fwrite(
            STDERR,
            "Gagal membuat backup chat Blade.\n"
        );

        exit(5);
    }
}

/*
|--------------------------------------------------------------------------
| 1. Make the preview modal smaller and guaranteed inside viewport.
|--------------------------------------------------------------------------
*/

$source =
    str_replace(
        'width:min(94vw,980px);'
        .'height:min(84vh,760px);'
        .'max-height:84vh;',
        'width:min(92vw,920px);'
        .'height:min(78vh,680px);'
        .'max-height:78vh;',
        $source,
        $modalSizeCount
    );

if ($modalSizeCount !== 1) {
    fwrite(
        STDERR,
        "Modal size marker V3.2.4 tidak ditemukan atau count salah: {$modalSizeCount}\n"
    );

    exit(6);
}

/*
|--------------------------------------------------------------------------
| 2. Make preview body a real bounded flex child.
|--------------------------------------------------------------------------
*/

$bodyOld =
    '<div class="min-h-0 flex-1 bg-gray-100 p-2">';

$bodyNew =
    '<div'
    ."\n"
    .'                class="min-h-0 flex-1 bg-gray-100 p-2"'
    ."\n"
    .'                style="'
    .'min-height:0;'
    .'overflow:hidden;'
    .'display:flex;'
    .'flex-direction:column;'
    .'"'
    ."\n"
    .'            >';

if (
    ! str_contains(
        $source,
        $bodyOld
    )
) {
    fwrite(
        STDERR,
        "Preview body marker tidak ditemukan.\n"
    );

    exit(7);
}

$source =
    str_replace(
        $bodyOld,
        $bodyNew,
        $source,
        $bodyCount
    );

if ($bodyCount !== 1) {
    fwrite(
        STDERR,
        "Preview body replacement count salah: {$bodyCount}\n"
    );

    exit(8);
}

/*
|--------------------------------------------------------------------------
| 3. Constrain image stage further.
|--------------------------------------------------------------------------
*/

$stageOld =
    'display:none;'
    .'width:100%;'
    .'height:100%;'
    .'align-items:center;'
    .'justify-content:center;'
    .'overflow:auto;'
    .'padding:18px;'
    .'background:#0f172a;'
    .'border-radius:12px;';

$stageNew =
    'display:none;'
    .'width:100%;'
    .'height:100%;'
    .'min-height:0;'
    .'align-items:center;'
    .'justify-content:center;'
    .'overflow:hidden;'
    .'padding:18px;'
    .'background:#0f172a;'
    .'border-radius:12px;';

if (
    ! str_contains(
        $source,
        $stageOld
    )
) {
    fwrite(
        STDERR,
        "Image stage marker tidak ditemukan.\n"
    );

    exit(9);
}

$source =
    str_replace(
        $stageOld,
        $stageNew,
        $source,
        $stageCount
    );

if ($stageCount !== 1) {
    fwrite(
        STDERR,
        "Image stage replacement count salah: {$stageCount}\n"
    );

    exit(10);
}

$imageOld =
    'display:block;'
    .'max-width:100%;'
    .'max-height:100%;'
    .'width:auto;'
    .'height:auto;'
    .'object-fit:contain;'
    .'border-radius:10px;'
    .'box-shadow:0 12px 40px rgba(0,0,0,.28);'
    .'background:#ffffff;';

$imageNew =
    'display:block;'
    .'max-width:86%;'
    .'max-height:calc(78vh - 155px);'
    .'width:auto;'
    .'height:auto;'
    .'object-fit:contain;'
    .'border-radius:12px;'
    .'box-shadow:0 12px 40px rgba(0,0,0,.28);'
    .'background:#ffffff;';

if (
    ! str_contains(
        $source,
        $imageOld
    )
) {
    fwrite(
        STDERR,
        "Image style marker tidak ditemukan.\n"
    );

    exit(11);
}

$source =
    str_replace(
        $imageOld,
        $imageNew,
        $source,
        $imageCount
    );

if ($imageCount !== 1) {
    fwrite(
        STDERR,
        "Image style replacement count salah: {$imageCount}\n"
    );

    exit(12);
}

/*
|--------------------------------------------------------------------------
| 4. Keep top toolbar always visible and make Back obvious.
|--------------------------------------------------------------------------
*/

$toolbarOld =
    '<div class="flex items-center justify-between gap-3 border-b p-3">';

$toolbarNew =
    '<div'
    ."\n"
    .'                class="flex items-center justify-between gap-3 border-b p-3"'
    ."\n"
    .'                style="'
    .'flex:0 0 auto;'
    .'position:relative;'
    .'z-index:2;'
    .'background:#ffffff;'
    .'"'
    ."\n"
    .'            >';

if (
    ! str_contains(
        $source,
        $toolbarOld
    )
) {
    fwrite(
        STDERR,
        "Preview toolbar marker tidak ditemukan.\n"
    );

    exit(13);
}

$source =
    str_replace(
        $toolbarOld,
        $toolbarNew,
        $source,
        $toolbarCount
    );

if ($toolbarCount < 1) {
    fwrite(
        STDERR,
        "Preview toolbar tidak dipatch.\n"
    );

    exit(14);
}

/*
 * Only change the Close button inside the attachment preview block.
 */
$downloadPos =
    strpos(
        $source,
        'id="crm-chat-attachment-download"'
    );

if ($downloadPos === false) {
    fwrite(
        STDERR,
        "Download action tidak ditemukan.\n"
    );

    exit(15);
}

$previewClosePos =
    strpos(
        $source,
        'onclick="return window.crmChatPreviewClose(event);"',
        $downloadPos
    );

if ($previewClosePos === false) {
    fwrite(
        STDERR,
        "Preview Close action tidak ditemukan.\n"
    );

    exit(16);
}

$closeTextPos =
    strpos(
        $source,
        'Close',
        $previewClosePos
    );

if ($closeTextPos === false) {
    fwrite(
        STDERR,
        "Preview Close label tidak ditemukan.\n"
    );

    exit(17);
}

$source =
    substr_replace(
        $source,
        '← Back',
        $closeTextPos,
        strlen('Close')
    );

/*
|--------------------------------------------------------------------------
| 5. Add a bottom toolbar as a second escape hatch.
|--------------------------------------------------------------------------
*/

$footerOld = <<<'BLADE'
            <div class="border-t px-4 py-2 text-xs text-gray-500">
                Preview tersedia untuk image dan PDF. File lain tetap dapat di-download.
            </div>
BLADE;

$footerNew = <<<'BLADE'
            <div
                class="border-t px-4 py-2"
                style="
                    flex:0 0 auto;
                    display:flex;
                    align-items:center;
                    justify-content:space-between;
                    gap:12px;
                    background:#ffffff;
                "
            >
                <div class="text-xs text-gray-500">
                    Preview tersedia untuk image dan PDF.
                </div>

                <div style="display:flex;gap:8px;align-items:center;">
                    <a
                        id="crm-chat-attachment-download-bottom"
                        href="#"
                        class="secondary-button"
                        download
                    >
                        ⬇ Download
                    </a>

                    <button
                        type="button"
                        class="secondary-button"
                        onclick="return window.crmChatPreviewClose(event);"
                    >
                        ← Back
                    </button>
                </div>
            </div>
BLADE;

if (
    ! str_contains(
        $source,
        $footerOld
    )
) {
    fwrite(
        STDERR,
        "Preview footer marker tidak ditemukan.\n"
    );

    exit(18);
}

$source =
    str_replace(
        $footerOld,
        $footerNew,
        $source,
        $footerCount
    );

if ($footerCount !== 1) {
    fwrite(
        STDERR,
        "Preview footer replacement count salah: {$footerCount}\n"
    );

    exit(19);
}

/*
|--------------------------------------------------------------------------
| 6. Sync top and bottom download links.
|--------------------------------------------------------------------------
*/

$closing =
    '</x-admin::layouts>';

$closingPos =
    strrpos(
        $source,
        $closing
    );

if ($closingPos === false) {
    fwrite(
        STDERR,
        "Closing x-admin::layouts tidak ditemukan.\n"
    );

    exit(20);
}

$runtime = <<<'BLADE'

    {{-- INTERNAL CHAT V3.2.5 COMPACT IMAGE PREVIEW TOOLBAR --}}
    <script>
        (() => {
            const previousPreview =
                window.crmChatPreviewAttachment;

            window.crmChatPreviewAttachment =
                function (link, event) {
                    const result =
                        typeof previousPreview
                        === 'function'
                            ? previousPreview(
                                link,
                                event
                            )
                            : true;

                    const top =
                        document.getElementById(
                            'crm-chat-attachment-download'
                        );

                    const bottom =
                        document.getElementById(
                            'crm-chat-attachment-download-bottom'
                        );

                    if (
                        top
                        && bottom
                    ) {
                        bottom.href =
                            top.href;

                        const filename =
                            top.getAttribute(
                                'download'
                            );

                        if (filename) {
                            bottom.setAttribute(
                                'download',
                                filename
                            );
                        } else {
                            bottom.removeAttribute(
                                'download'
                            );
                        }
                    }

                    return result;
                };
        })();
    </script>

BLADE;

$source =
    substr_replace(
        $source,
        $runtime,
        $closingPos,
        0
    );

if (
    file_put_contents(
        $blade,
        $source
    ) === false
) {
    copy(
        $backup,
        $blade
    );

    fwrite(
        STDERR,
        "Gagal menulis chat Blade. Backup dipulihkan.\n"
    );

    exit(21);
}

$written =
    file_get_contents(
        $blade
    );

$postChecks = [
    'INTERNAL CHAT V3.2.5 COMPACT IMAGE PREVIEW TOOLBAR',
    'height:min(78vh,680px)',
    'max-width:86%',
    'max-height:calc(78vh - 155px)',
    'id="crm-chat-attachment-download-bottom"',
    '⬇ Download / Save',
    '← Back',
    'window.crmChatPreviewAttachment',
    'window.crmChatReplyAction',
    'window.crmChatEditAction',
    'window.crmChatDeleteAction',
];

foreach ($postChecks as $marker) {
    if (
        $written === false
        || ! str_contains(
            $written,
            $marker
        )
    ) {
        copy(
            $backup,
            $blade
        );

        fwrite(
            STDERR,
            "Post-write validation gagal: {$marker}\n"
            ."Backup dipulihkan otomatis.\n"
        );

        exit(22);
    }
}

echo "[PASS] Image preview reduced to compact viewport.\n";
echo "[PASS] Top Download / Save toolbar kept visible.\n";
echo "[PASS] Top Back button installed.\n";
echo "[PASS] Bottom Download + Back toolbar installed.\n";
echo "[PASS] PDF iframe preview preserved.\n";
echo "[PASS] Reply/Edit/Delete preserved.\n";
echo "[PASS] No controller / provider / route / database changes.\n";
