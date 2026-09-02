<?php

/*
|--------------------------------------------------------------------------
| Internal Chat V3.2.3
|--------------------------------------------------------------------------
|
| Fixes:
| 1. Image/PDF pre-send preview still not visible.
| 2. Sent image still shown as filename-only attachment.
| 3. Clicking sent image/PDF does not reliably open preview.
| 4. Start New Chat modal is visually too large.
|
| Root causes addressed:
| - previous image detection read link.textContent, but the visible label ends
|   with "KB", so ".jpg/.png/.pdf" extension detection can fail.
| - pre-send preview shared crm-chat-file-list with older listeners that may
|   rewrite that container.
| - max-width utility classes are not reliably limiting this modal build.
|
| V3.2.3:
| - adds explicit data-attachment-name
| - uses a dedicated pre-send preview container
| - adds direct onchange on the file input
| - uses inline modal dimensions for predictable compact sizing
|
| Scope: current chat.blade.php only.
| No route/controller/provider/database/migration changes.
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
        'INTERNAL CHAT V3.2.3 PREVIEW + MODERN MODAL'
    )
) {
    echo "[SKIP] Internal Chat V3.2.3 already installed.\n";

    exit(0);
}

$required = [
    'INTERNAL CHAT V3.2.2 ROBUST UI INTERACTIONS',
    'id="crm-new-chat-modal"',
    'id="crm-new-chat-search"',
    'id="crm-chat-search-modal"',
    'id="crm-chat-attachments"',
    'id="crm-chat-file-list"',
    'data-attachment-preview-url=',
    'data-attachment-download-url=',
    'window.crmChatPreviewAttachment',
    'window.crmChatReplyAction',
    'window.crmChatEditAction',
    'window.crmChatDeleteAction',
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
            "Current V3.2.2 baseline tidak dikenali: {$marker}\n"
            ."Patch dihentikan agar Blade customized tidak rusak.\n"
        );

        exit(4);
    }
}

$backup =
    $blade
    .'.before-internal-chat-v3-2-3-preview-modern-modal.bak';

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
| 1. Server-rendered attachments: preserve original file name in data attr.
|--------------------------------------------------------------------------
*/

$serverDownloadPattern =
    '/(data-attachment-download-url="\{\{\s*route\('
    ."'admin\\.internal-chat\\.attachments\\.download'"
    .',\s*\$attachment->id\)\s*\}\}")/';

if (
    preg_match(
        $serverDownloadPattern,
        $source
    ) === 1
) {
    $source =
        preg_replace(
            $serverDownloadPattern,
            '$1'
            ."\n"
            .'                                                        data-attachment-name="{{ $attachment->original_name }}"',
            $source,
            -1,
            $serverNameCount
        );
} elseif (
    ! str_contains(
        $source,
        'data-attachment-name="{{ $attachment->original_name }}"'
    )
) {
    fwrite(
        STDERR,
        "Server attachment data marker tidak ditemukan.\n"
    );

    exit(6);
}

/*
|--------------------------------------------------------------------------
| 2. Dynamic/polled attachments: keep attachment.name separately.
|--------------------------------------------------------------------------
*/

$dynamicMarker = <<<'JS'
                                link.dataset.attachmentDownloadUrl =
                                    attachment.download_url;
JS;

$dynamicNew = <<<'JS'
                                link.dataset.attachmentDownloadUrl =
                                    attachment.download_url;

                                link.dataset.attachmentName =
                                    String(
                                        attachment.name
                                        || 'Attachment'
                                    );
JS;

if (
    str_contains(
        $source,
        $dynamicMarker
    )
    && ! str_contains(
        $source,
        'link.dataset.attachmentName'
    )
) {
    $source =
        str_replace(
            $dynamicMarker,
            $dynamicNew,
            $source,
            $dynamicCount
        );

    if ($dynamicCount !== 1) {
        fwrite(
            STDERR,
            "Dynamic attachment-name replacement count salah: {$dynamicCount}\n"
        );

        exit(7);
    }
}

/*
|--------------------------------------------------------------------------
| 3. File input: hard onchange + dedicated preview container.
|--------------------------------------------------------------------------
*/

$fileInputOld = <<<'BLADE'
                                <input
                                    type="file"
                                    id="crm-chat-attachments"
                                    name="attachments[]"
                                    multiple
                                    hidden
                                >
BLADE;

$fileInputNew = <<<'BLADE'
                                <input
                                    type="file"
                                    id="crm-chat-attachments"
                                    name="attachments[]"
                                    multiple
                                    hidden
                                    onchange="window.crmChatRenderSelectedPreview(this);"
                                >

                                <div
                                    id="crm-chat-selected-preview"
                                    style="
                                        display:none;
                                        margin-top:10px;
                                        padding:10px;
                                        border:1px solid #e5e7eb;
                                        border-radius:12px;
                                        background:#ffffff;
                                    "
                                ></div>
BLADE;

if (
    str_contains(
        $source,
        $fileInputOld
    )
) {
    $source =
        str_replace(
            $fileInputOld,
            $fileInputNew,
            $source,
            $fileInputCount
        );

    if ($fileInputCount !== 1) {
        fwrite(
            STDERR,
            "File input replacement count salah: {$fileInputCount}\n"
        );

        exit(8);
    }
} elseif (
    ! str_contains(
        $source,
        'id="crm-chat-selected-preview"'
    )
) {
    fwrite(
        STDERR,
        "Composer file input marker tidak ditemukan.\n"
    );

    exit(9);
}

/*
|--------------------------------------------------------------------------
| 4. Start New Chat: compact modern modal with inline sizing.
|--------------------------------------------------------------------------
*/

$newChatOverlayPattern =
    '/(<div\s+'
    .'id="crm-new-chat-modal"\s+'
    .'class="[^"]*")'
    .'(\s+aria-hidden="true"\s*>)/s';

if (
    preg_match(
        $newChatOverlayPattern,
        $source
    ) !== 1
) {
    fwrite(
        STDERR,
        "New Chat overlay tag tidak ditemukan.\n"
    );

    exit(10);
}

$source =
    preg_replace(
        $newChatOverlayPattern,
        '$1'
        ."\n"
        .'        style="'
        .'background:rgba(15,23,42,.38);'
        .'backdrop-filter:blur(3px);'
        .'-webkit-backdrop-filter:blur(3px);'
        .'padding:20px;'
        .'align-items:center;'
        .'justify-content:center;'
        .'"'
        .'$2',
        $source,
        1
    );

$newChatInnerOld =
    'class="flex max-h-screen w-full max-w-lg flex-col overflow-hidden rounded-xl border bg-white shadow-lg"';

$newChatInnerNew =
    'class="flex flex-col overflow-hidden border bg-white shadow-lg"'
    ."\n"
    .'            style="'
    .'width:min(92vw,460px);'
    .'max-height:min(72vh,620px);'
    .'border-radius:18px;'
    .'box-shadow:0 28px 80px rgba(15,23,42,.24);'
    .'"';

if (
    str_contains(
        $source,
        $newChatInnerOld
    )
) {
    $source =
        str_replace(
            $newChatInnerOld,
            $newChatInnerNew,
            $source,
            $newChatInnerCount
        );

    if ($newChatInnerCount !== 1) {
        fwrite(
            STDERR,
            "New Chat card replacement count salah: {$newChatInnerCount}\n"
        );

        exit(11);
    }
}

/*
 * Compact user rows a little further.
 */
$source =
    str_replace(
        'class="flex w-full items-center gap-3 bg-white px-4 py-3 text-left hover:bg-gray-50"',
        'class="flex w-full items-center gap-3 bg-white px-4 py-2.5 text-left hover:bg-gray-50"',
        $source
    );

/*
|--------------------------------------------------------------------------
| 5. Search modal also gets a predictable modern width.
|--------------------------------------------------------------------------
*/

$searchInnerOld =
    'class="flex max-h-screen w-full max-w-2xl flex-col overflow-hidden rounded-xl border bg-white shadow-lg"';

$searchInnerNew =
    'class="flex flex-col overflow-hidden border bg-white shadow-lg"'
    ."\n"
    .'            style="'
    .'width:min(94vw,620px);'
    .'max-height:min(76vh,680px);'
    .'border-radius:18px;'
    .'box-shadow:0 28px 80px rgba(15,23,42,.24);'
    .'"';

if (
    str_contains(
        $source,
        $searchInnerOld
    )
) {
    $source =
        str_replace(
            $searchInnerOld,
            $searchInnerNew,
            $source
        );
}

/*
|--------------------------------------------------------------------------
| 6. Independent V3.2.3 preview runtime at END of Blade.
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

    exit(12);
}

$runtime = <<<'BLADE'

    {{-- INTERNAL CHAT V3.2.3 PREVIEW + MODERN MODAL --}}
    <script>
        (() => {
            const fileNameFromLink = (
                link
            ) => {
                if (! link) {
                    return '';
                }

                const explicit =
                    String(
                        link.dataset.attachmentName
                        || ''
                    ).trim();

                if (explicit) {
                    return explicit;
                }

                /*
                 * Fallback for older rows: strip the "· 123 KB" suffix.
                 */
                return String(
                    link.textContent
                    || ''
                )
                    .replace(
                        /^\s*📎\s*/,
                        ''
                    )
                    .replace(
                        /\s*·\s*[\d,.]+\s*KB\s*$/i,
                        ''
                    )
                    .trim();
            };

            const isImageFileName = (
                value
            ) => /\.(png|jpe?g|webp|gif|bmp)$/i.test(
                String(
                    value
                    || ''
                ).trim()
            );

            const isPdfFileName = (
                value
            ) => /\.pdf$/i.test(
                String(
                    value
                    || ''
                ).trim()
            );

            /*
            |--------------------------------------------------------------------------
            | PRE-SEND PREVIEW
            |--------------------------------------------------------------------------
            |
            | Uses a dedicated container, so older crm-chat-file-list listeners
            | cannot erase the visual preview.
            |
            */

            let selectedObjectUrls =
                [];

            const revokeSelectedUrls =
                () => {
                    selectedObjectUrls.forEach(
                        (url) => {
                            try {
                                URL.revokeObjectURL(
                                    url
                                );
                            } catch (error) {
                                // Ignore already revoked URLs.
                            }
                        }
                    );

                    selectedObjectUrls =
                        [];
                };

            const clearSelectedPreview =
                () => {
                    const preview =
                        document.getElementById(
                            'crm-chat-selected-preview'
                        );

                    revokeSelectedUrls();

                    if (! preview) {
                        return;
                    }

                    preview.innerHTML =
                        '';

                    preview.style.display =
                        'none';
                };

            window.crmChatRenderSelectedPreview =
                function (input) {
                    const preview =
                        document.getElementById(
                            'crm-chat-selected-preview'
                        );

                    if (
                        ! input
                        || ! preview
                    ) {
                        return;
                    }

                    revokeSelectedUrls();

                    preview.innerHTML =
                        '';

                    const files =
                        Array.from(
                            input.files
                            || []
                        );

                    if (files.length === 0) {
                        preview.style.display =
                            'none';

                        return;
                    }

                    preview.style.display =
                        'grid';

                    preview.style.gridTemplateColumns =
                        'repeat(auto-fill,minmax(150px,1fr))';

                    preview.style.gap =
                        '10px';

                    files.forEach(
                        (file) => {
                            const card =
                                document.createElement(
                                    'div'
                                );

                            card.style.minWidth =
                                '0';

                            card.style.padding =
                                '8px';

                            card.style.border =
                                '1px solid #e5e7eb';

                            card.style.borderRadius =
                                '10px';

                            card.style.background =
                                '#f8fafc';

                            if (
                                String(
                                    file.type
                                    || ''
                                ).startsWith(
                                    'image/'
                                )
                                || isImageFileName(
                                    file.name
                                )
                            ) {
                                const url =
                                    URL.createObjectURL(
                                        file
                                    );

                                selectedObjectUrls.push(
                                    url
                                );

                                const image =
                                    document.createElement(
                                        'img'
                                    );

                                image.src =
                                    url;

                                image.alt =
                                    file.name;

                                image.style.display =
                                    'block';

                                image.style.width =
                                    '100%';

                                image.style.height =
                                    '120px';

                                image.style.objectFit =
                                    'cover';

                                image.style.borderRadius =
                                    '8px';

                                image.style.background =
                                    '#ffffff';

                                card.appendChild(
                                    image
                                );
                            } else if (
                                String(
                                    file.type
                                    || ''
                                ) === 'application/pdf'
                                || isPdfFileName(
                                    file.name
                                )
                            ) {
                                const pdf =
                                    document.createElement(
                                        'div'
                                    );

                                pdf.style.height =
                                    '120px';

                                pdf.style.display =
                                    'flex';

                                pdf.style.alignItems =
                                    'center';

                                pdf.style.justifyContent =
                                    'center';

                                pdf.style.borderRadius =
                                    '8px';

                                pdf.style.background =
                                    '#eef2f7';

                                pdf.style.fontSize =
                                    '34px';

                                pdf.textContent =
                                    '📄';

                                card.appendChild(
                                    pdf
                                );
                            } else {
                                const generic =
                                    document.createElement(
                                        'div'
                                    );

                                generic.style.height =
                                    '80px';

                                generic.style.display =
                                    'flex';

                                generic.style.alignItems =
                                    'center';

                                generic.style.justifyContent =
                                    'center';

                                generic.style.borderRadius =
                                    '8px';

                                generic.style.background =
                                    '#eef2f7';

                                generic.style.fontSize =
                                    '28px';

                                generic.textContent =
                                    '📎';

                                card.appendChild(
                                    generic
                                );
                            }

                            const name =
                                document.createElement(
                                    'div'
                                );

                            name.style.marginTop =
                                '7px';

                            name.style.fontSize =
                                '11px';

                            name.style.fontWeight =
                                '700';

                            name.style.color =
                                '#334155';

                            name.style.whiteSpace =
                                'nowrap';

                            name.style.overflow =
                                'hidden';

                            name.style.textOverflow =
                                'ellipsis';

                            name.title =
                                file.name;

                            name.textContent =
                                file.name;

                            card.appendChild(
                                name
                            );

                            preview.appendChild(
                                card
                            );
                        }
                    );
                };

            const fileInput =
                document.getElementById(
                    'crm-chat-attachments'
                );

            if (
                fileInput
                && fileInput.dataset.crmV323Bound !== '1'
            ) {
                fileInput.dataset.crmV323Bound =
                    '1';

                fileInput.addEventListener(
                    'change',
                    () => {
                        window.crmChatRenderSelectedPreview(
                            fileInput
                        );
                    }
                );
            }

            const form =
                document.getElementById(
                    'crm-chat-send-form'
                );

            if (
                form
                && form.dataset.crmV323ResetBound !== '1'
            ) {
                form.dataset.crmV323ResetBound =
                    '1';

                form.addEventListener(
                    'reset',
                    () => {
                        window.setTimeout(
                            clearSelectedPreview,
                            0
                        );
                    }
                );
            }

            /*
            |--------------------------------------------------------------------------
            | SENT ATTACHMENT INLINE PREVIEW
            |--------------------------------------------------------------------------
            */

            const makeSentImagePreview = (
                link
            ) => {
                if (
                    ! link
                    || link.dataset.crmV323Decorated === '1'
                ) {
                    return;
                }

                link.dataset.crmV323Decorated =
                    '1';

                const fileName =
                    fileNameFromLink(
                        link
                    );

                link.dataset.attachmentName =
                    fileName;

                if (! isImageFileName(fileName)) {
                    return;
                }

                const previewUrl =
                    String(
                        link.dataset.attachmentPreviewUrl
                        || ''
                    );

                if (! previewUrl) {
                    return;
                }

                const image =
                    document.createElement(
                        'img'
                    );

                image.src =
                    previewUrl;

                image.alt =
                    fileName;

                image.loading =
                    'lazy';

                image.style.display =
                    'block';

                image.style.width =
                    'min(320px,100%)';

                image.style.maxHeight =
                    '260px';

                image.style.objectFit =
                    'cover';

                image.style.borderRadius =
                    '10px';

                image.style.marginBottom =
                    '8px';

                image.style.background =
                    '#ffffff';

                image.onerror =
                    function () {
                        this.remove();
                    };

                link.insertBefore(
                    image,
                    link.firstChild
                );

                link.style.display =
                    'block';

                link.style.maxWidth =
                    '340px';
            };

            const decorateSentAttachments = (
                scope
            ) => {
                const root =
                    scope
                    || document;

                if (
                    root.matches
                    && root.matches(
                        '[data-attachment-preview-url]'
                    )
                ) {
                    makeSentImagePreview(
                        root
                    );
                }

                if (root.querySelectorAll) {
                    root
                        .querySelectorAll(
                            '[data-attachment-preview-url]'
                        )
                        .forEach(
                            makeSentImagePreview
                        );
                }
            };

            decorateSentAttachments(
                document
            );

            const messagesRoot =
                document.getElementById(
                    'crm-chat-messages'
                );

            if (messagesRoot) {
                const observer =
                    new MutationObserver(
                        (mutations) => {
                            mutations.forEach(
                                (mutation) => {
                                    mutation.addedNodes.forEach(
                                        (node) => {
                                            if (
                                                node.nodeType
                                                !== Node.ELEMENT_NODE
                                            ) {
                                                return;
                                            }

                                            decorateSentAttachments(
                                                node
                                            );
                                        }
                                    );
                                }
                            );
                        }
                    );

                observer.observe(
                    messagesRoot,
                    {
                        childList:
                            true,

                        subtree:
                            true,
                    }
                );
            }

            /*
            |--------------------------------------------------------------------------
            | PREVIEW CLICK
            |--------------------------------------------------------------------------
            |
            | Uses data-attachment-name rather than visible text ending with KB.
            |
            */

            const previousPreviewHandler =
                window.crmChatPreviewAttachment;

            window.crmChatPreviewAttachment =
                function (link, event) {
                    const fileName =
                        fileNameFromLink(
                            link
                        );

                    const previewable =
                        isImageFileName(
                            fileName
                        )
                        || isPdfFileName(
                            fileName
                        );

                    if (! previewable) {
                        return true;
                    }

                    if (event) {
                        event.preventDefault();
                        event.stopPropagation();
                    }

                    const modal =
                        document.getElementById(
                            'crm-chat-attachment-preview-modal'
                        );

                    const frame =
                        document.getElementById(
                            'crm-chat-attachment-preview-frame'
                        );

                    const download =
                        document.getElementById(
                            'crm-chat-attachment-download'
                        );

                    const previewUrl =
                        String(
                            link.dataset.attachmentPreviewUrl
                            || ''
                        );

                    const downloadUrl =
                        String(
                            link.dataset.attachmentDownloadUrl
                            || link.href
                            || ''
                        );

                    if (
                        ! modal
                        || ! frame
                        || ! download
                        || ! previewUrl
                    ) {
                        if (
                            typeof previousPreviewHandler
                            === 'function'
                        ) {
                            return previousPreviewHandler(
                                link,
                                event
                            );
                        }

                        return true;
                    }

                    frame.src =
                        previewUrl;

                    download.href =
                        downloadUrl;

                    modal.classList.remove(
                        'hidden'
                    );

                    modal.classList.add(
                        'flex'
                    );

                    modal.style.display =
                        'flex';

                    modal.style.alignItems =
                        'center';

                    modal.style.justifyContent =
                        'center';

                    modal.setAttribute(
                        'aria-hidden',
                        'false'
                    );

                    return false;
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

    exit(13);
}

$written =
    file_get_contents(
        $blade
    );

$postChecks = [
    'INTERNAL CHAT V3.2.3 PREVIEW + MODERN MODAL',
    'data-attachment-name="{{ $attachment->original_name }}"',
    'link.dataset.attachmentName',
    'id="crm-chat-selected-preview"',
    'onchange="window.crmChatRenderSelectedPreview(this);"',
    'window.crmChatRenderSelectedPreview',
    'URL.createObjectURL',
    'makeSentImagePreview',
    'fileNameFromLink',
    'width:min(92vw,460px)',
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

        exit(14);
    }
}

echo "[PASS] Dedicated pre-send image/PDF preview installed.\n";
echo "[PASS] Sent image detection fixed using data-attachment-name.\n";
echo "[PASS] Sent image inline thumbnail installed.\n";
echo "[PASS] Image/PDF click preview detection fixed.\n";
echo "[PASS] Start New Chat modal changed to compact modern card.\n";
echo "[PASS] Search modal sizing normalized.\n";
echo "[PASS] Reply/Edit/Delete preserved.\n";
echo "[PASS] No controller / provider / route / database changes.\n";
