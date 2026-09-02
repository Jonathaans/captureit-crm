<?php

/*
|--------------------------------------------------------------------------
| Internal Chat V3.2.6 - Universal Preview Toolbar Hotfix
|--------------------------------------------------------------------------
|
| Works from V3.2.3 OR V3.2.4.
|
| Fixes:
| - V3.2.5 refusing to install when V3.2.4 was never installed
| - PDF preview hides Back / Download controls
| - Image preview hides Back / Download controls
| - preview content can grow taller than modal toolbar
|
| Strategy:
| - patch ONLY current chat.blade.php
| - require stable V3.2.3 preview modal markers, not V3.2.4 marker
| - ensure a bounded modal shell
| - keep header + footer outside scroll/content area
| - provide always-visible top and bottom Download / Back actions
| - preserve PDF iframe
| - add/normalize dedicated image preview if missing
|
| No controller / provider / route / database / migration changes.
|
*/

$root = realpath(__DIR__.'/..');

if (! $root) {
    fwrite(STDERR, "Project root tidak ditemukan.\n");
    exit(1);
}

$blade =
    $root
    .'/packages/Webkul/Admin/src/Resources/views/internal-communication/chat.blade.php';

if (! is_file($blade)) {
    fwrite(STDERR, "chat.blade.php tidak ditemukan.\n");
    exit(2);
}

$source = file_get_contents($blade);

if ($source === false) {
    fwrite(STDERR, "chat.blade.php tidak dapat dibaca.\n");
    exit(3);
}

if (
    str_contains(
        $source,
        'INTERNAL CHAT V3.2.6 UNIVERSAL PREVIEW TOOLBAR'
    )
) {
    echo "[SKIP] Internal Chat V3.2.6 already installed.\n";
    exit(0);
}

$required = [
    'INTERNAL CHAT V3.2.3 PREVIEW + MODERN MODAL',
    'id="crm-chat-attachment-preview-modal"',
    'id="crm-chat-attachment-preview-frame"',
    'id="crm-chat-attachment-download"',
    'window.crmChatPreviewAttachment',
    'window.crmChatPreviewClose',
    'data-attachment-preview-url=',
    'data-attachment-download-url=',
];

foreach ($required as $marker) {
    if (! str_contains($source, $marker)) {
        fwrite(
            STDERR,
            "Current preview baseline tidak dikenali: {$marker}\n"
            ."Patch dihentikan agar Blade customized tidak rusak.\n"
        );
        exit(4);
    }
}

$backup =
    $blade
    .'.before-internal-chat-v3-2-6-universal-preview-toolbar.bak';

if (! is_file($backup)) {
    if (! copy($blade, $backup)) {
        fwrite(STDERR, "Gagal membuat backup chat Blade.\n");
        exit(5);
    }
}

/*
|--------------------------------------------------------------------------
| 1. Normalize preview modal inner card sizing
|--------------------------------------------------------------------------
*/

$modalIdPos =
    strpos(
        $source,
        'id="crm-chat-attachment-preview-modal"'
    );

if ($modalIdPos === false) {
    fwrite(STDERR, "Preview modal tidak ditemukan.\n");
    exit(6);
}

$cardPos =
    strpos(
        $source,
        '<div',
        $modalIdPos + strlen('id="crm-chat-attachment-preview-modal"')
    );

if ($cardPos === false) {
    fwrite(STDERR, "Preview card tidak ditemukan.\n");
    exit(7);
}

$cardEnd =
    strpos(
        $source,
        '>',
        $cardPos
    );

if ($cardEnd === false) {
    fwrite(STDERR, "Preview card opening tag tidak lengkap.\n");
    exit(8);
}

$cardTag =
    substr(
        $source,
        $cardPos,
        $cardEnd - $cardPos + 1
    );

$newCardTag = preg_replace(
    '/\sstyle="[^"]*"/s',
    '',
    $cardTag
);

if ($newCardTag === null) {
    fwrite(STDERR, "Gagal normalize preview card style.\n");
    exit(9);
}

$newCardTag =
    rtrim(
        substr(
            $newCardTag,
            0,
            -1
        )
    )
    .' style="'
    .'width:min(92vw,920px);'
    .'height:min(78vh,680px);'
    .'max-height:78vh;'
    .'display:flex;'
    .'flex-direction:column;'
    .'overflow:hidden;'
    .'border-radius:18px;'
    .'background:#ffffff;'
    .'box-shadow:0 28px 90px rgba(15,23,42,.30);'
    .'">'
;

$source =
    substr_replace(
        $source,
        $newCardTag,
        $cardPos,
        strlen($cardTag)
    );

/*
|--------------------------------------------------------------------------
| 2. Replace the preview modal's entire inner content with a stable shell
|--------------------------------------------------------------------------
|
| We locate the first child card and its matching closing </div> using a
| simple tag-depth scanner. This avoids relying on utility classes from
| V3.2.3/V3.2.4.
|
*/

function findMatchingDivEnd(
    string $html,
    int $openingDivPos
): int {
    $pattern =
        '/<div\b[^>]*>|<\/div>/i';

    preg_match_all(
        $pattern,
        $html,
        $matches,
        PREG_OFFSET_CAPTURE,
        $openingDivPos
    );

    $depth = 0;

    foreach ($matches[0] as [$tag, $offset]) {
        if ($offset < $openingDivPos) {
            continue;
        }

        if (stripos($tag, '</div') === 0) {
            $depth--;

            if ($depth === 0) {
                return $offset + strlen($tag);
            }
        } else {
            $depth++;
        }
    }

    return -1;
}

$cardEndPos =
    findMatchingDivEnd(
        $source,
        $cardPos
    );

if ($cardEndPos < 0) {
    copy($backup, $blade);
    fwrite(STDERR, "Preview card closing div tidak ditemukan.\n");
    exit(10);
}

$currentCard =
    substr(
        $source,
        $cardPos,
        $cardEndPos - $cardPos
    );

/*
 * Keep the opening card tag we already normalized, replace only its inside.
 */
$openingTagEnd =
    strpos(
        $currentCard,
        '>'
    );

if ($openingTagEnd === false) {
    copy($backup, $blade);
    fwrite(STDERR, "Preview card opening tag invalid.\n");
    exit(11);
}

$openingTag =
    substr(
        $currentCard,
        0,
        $openingTagEnd + 1
    );

$newInner = <<<'BLADE'

            <div
                style="
                    flex:0 0 auto;
                    display:flex;
                    align-items:center;
                    justify-content:space-between;
                    gap:12px;
                    padding:12px 14px;
                    border-bottom:1px solid #e5e7eb;
                    background:#ffffff;
                    position:relative;
                    z-index:3;
                "
            >
                <div style="min-width:0;flex:1;">
                    <div style="font-size:14px;font-weight:800;color:#0f172a;">
                        Attachment Preview
                    </div>

                    <div
                        id="crm-chat-attachment-preview-name"
                        style="
                            margin-top:2px;
                            max-width:520px;
                            overflow:hidden;
                            text-overflow:ellipsis;
                            white-space:nowrap;
                            font-size:11px;
                            font-weight:600;
                            color:#64748b;
                        "
                    ></div>
                </div>

                <div style="display:flex;gap:8px;align-items:center;flex:0 0 auto;">
                    <a
                        id="crm-chat-attachment-download"
                        href="#"
                        class="secondary-button"
                        download
                    >
                        ⬇ Download / Save
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

            <div
                style="
                    flex:1 1 auto;
                    min-height:0;
                    overflow:hidden;
                    padding:10px;
                    background:#e5e7eb;
                "
            >
                <div
                    id="crm-chat-image-preview-stage"
                    style="
                        display:none;
                        width:100%;
                        height:100%;
                        min-height:0;
                        align-items:center;
                        justify-content:center;
                        overflow:auto;
                        padding:14px;
                        background:#0f172a;
                        border-radius:12px;
                    "
                >
                    <img
                        id="crm-chat-attachment-preview-image"
                        alt="Image preview"
                        style="
                            display:block;
                            max-width:88%;
                            max-height:100%;
                            width:auto;
                            height:auto;
                            object-fit:contain;
                            border-radius:10px;
                            background:#ffffff;
                            box-shadow:0 12px 40px rgba(0,0,0,.28);
                        "
                    >
                </div>

                <iframe
                    id="crm-chat-attachment-preview-frame"
                    title="Attachment preview"
                    style="
                        display:block;
                        width:100%;
                        height:100%;
                        border:0;
                        border-radius:10px;
                        background:#ffffff;
                    "
                ></iframe>
            </div>

            <div
                style="
                    flex:0 0 auto;
                    display:flex;
                    align-items:center;
                    justify-content:space-between;
                    gap:12px;
                    padding:10px 14px;
                    border-top:1px solid #e5e7eb;
                    background:#ffffff;
                    position:relative;
                    z-index:3;
                "
            >
                <div style="font-size:11px;color:#64748b;">
                    Image dan PDF dapat dipreview. File lain tetap didownload.
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

$newCard =
    $openingTag
    .$newInner
    ."\n        </div>";

$source =
    substr_replace(
        $source,
        $newCard,
        $cardPos,
        strlen($currentCard)
    );

/*
|--------------------------------------------------------------------------
| 3. Add a final runtime override that works for both V3.2.3 and V3.2.4
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
    copy($backup, $blade);
    fwrite(STDERR, "Closing x-admin::layouts tidak ditemukan.\n");
    exit(12);
}

$runtime = <<<'BLADE'

    {{-- INTERNAL CHAT V3.2.6 UNIVERSAL PREVIEW TOOLBAR --}}
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

            const isImage = (
                name
            ) =>
                /\.(png|jpe?g|webp|gif|bmp)$/i.test(
                    String(
                        name
                        || ''
                    )
                );

            const isPdf = (
                name
            ) =>
                /\.pdf$/i.test(
                    String(
                        name
                        || ''
                    )
                );

            const showModal = (
                modal
            ) => {
                if (! modal) {
                    return;
                }

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

                modal.style.padding =
                    '18px';

                modal.style.background =
                    'rgba(15,23,42,.56)';

                modal.style.backdropFilter =
                    'blur(3px)';

                modal.setAttribute(
                    'aria-hidden',
                    'false'
                );
            };

            const hideModal = (
                modal
            ) => {
                if (! modal) {
                    return;
                }

                modal.classList.add(
                    'hidden'
                );

                modal.classList.remove(
                    'flex'
                );

                modal.style.display =
                    'none';

                modal.setAttribute(
                    'aria-hidden',
                    'true'
                );
            };

            const syncDownloadLinks = (
                url,
                filename
            ) => {
                [
                    'crm-chat-attachment-download',
                    'crm-chat-attachment-download-bottom',
                ].forEach(
                    (id) => {
                        const link =
                            document.getElementById(
                                id
                            );

                        if (! link) {
                            return;
                        }

                        link.href =
                            url
                            || '#';

                        if (filename) {
                            link.setAttribute(
                                'download',
                                filename
                            );
                        } else {
                            link.removeAttribute(
                                'download'
                            );
                        }
                    }
                );
            };

            window.crmChatPreviewAttachment =
                function (
                    link,
                    event
                ) {
                    const fileName =
                        fileNameFromLink(
                            link
                        );

                    const imageFile =
                        isImage(
                            fileName
                        );

                    const pdfFile =
                        isPdf(
                            fileName
                        );

                    if (
                        ! imageFile
                        && ! pdfFile
                    ) {
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

                    const imageStage =
                        document.getElementById(
                            'crm-chat-image-preview-stage'
                        );

                    const image =
                        document.getElementById(
                            'crm-chat-attachment-preview-image'
                        );

                    const nameLabel =
                        document.getElementById(
                            'crm-chat-attachment-preview-name'
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
                        || ! imageStage
                        || ! image
                        || ! previewUrl
                    ) {
                        return true;
                    }

                    if (nameLabel) {
                        nameLabel.textContent =
                            fileName;
                    }

                    syncDownloadLinks(
                        downloadUrl,
                        fileName
                    );

                    if (imageFile) {
                        frame.src =
                            'about:blank';

                        frame.style.display =
                            'none';

                        image.src =
                            previewUrl;

                        image.alt =
                            fileName;

                        imageStage.style.display =
                            'flex';
                    } else {
                        image.removeAttribute(
                            'src'
                        );

                        imageStage.style.display =
                            'none';

                        frame.style.display =
                            'block';

                        frame.src =
                            previewUrl;
                    }

                    showModal(
                        modal
                    );

                    return false;
                };

            window.crmChatPreviewClose =
                function (
                    event
                ) {
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

                    const imageStage =
                        document.getElementById(
                            'crm-chat-image-preview-stage'
                        );

                    const image =
                        document.getElementById(
                            'crm-chat-attachment-preview-image'
                        );

                    if (frame) {
                        frame.src =
                            'about:blank';

                        frame.style.display =
                            'block';
                    }

                    if (image) {
                        image.removeAttribute(
                            'src'
                        );
                    }

                    if (imageStage) {
                        imageStage.style.display =
                            'none';
                    }

                    hideModal(
                        modal
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
    copy($backup, $blade);
    fwrite(STDERR, "Gagal menulis chat Blade. Backup dipulihkan.\n");
    exit(13);
}

$written =
    file_get_contents(
        $blade
    );

$postChecks = [
    'INTERNAL CHAT V3.2.6 UNIVERSAL PREVIEW TOOLBAR',
    'id="crm-chat-image-preview-stage"',
    'id="crm-chat-attachment-preview-image"',
    'id="crm-chat-attachment-preview-frame"',
    'id="crm-chat-attachment-preview-name"',
    'id="crm-chat-attachment-download-bottom"',
    '⬇ Download / Save',
    '← Back',
    'height:min(78vh,680px)',
    'window.crmChatPreviewAttachment',
    'window.crmChatPreviewClose',
];

foreach ($postChecks as $marker) {
    if (
        $written === false
        || ! str_contains(
            $written,
            $marker
        )
    ) {
        copy($backup, $blade);

        fwrite(
            STDERR,
            "Post-write validation gagal: {$marker}\n"
            ."Backup dipulihkan otomatis.\n"
        );

        exit(14);
    }
}

echo "[PASS] Universal preview shell installed from V3.2.3 baseline.\n";
echo "[PASS] Top Download / Save + Back always visible.\n";
echo "[PASS] Bottom Download + Back always visible.\n";
echo "[PASS] Image preview constrained inside modal.\n";
echo "[PASS] PDF iframe preview preserved.\n";
echo "[PASS] No controller / provider / route / database changes.\n";
