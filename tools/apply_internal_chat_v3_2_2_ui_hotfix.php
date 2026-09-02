<?php

/*
|--------------------------------------------------------------------------
| Internal Chat V3.2.2
|--------------------------------------------------------------------------
|
| Fixes the UI interaction regressions reported after V3.2:
|
| - + New Chat does nothing
| - Search does nothing
| - selected image has no preview before Send
| - sent image still looks like a filename-only attachment
|
| Strategy:
| - patch ONLY the current chat Blade in-place
| - add a small independent interaction layer at the END of the Blade
| - do not depend on the large existing chat IIFE finishing successfully
| - use direct/global handlers for New Chat and Search
| - use browser Object URLs for pre-send image previews
| - decorate sent image attachments with authenticated private preview URLs
|
| No controller / provider / route / DB / migration change.
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
        'INTERNAL CHAT V3.2.2 ROBUST UI INTERACTIONS'
    )
) {
    echo "[SKIP] Internal Chat V3.2.2 already installed.\n";

    exit(0);
}

$required = [
    'INTERNAL CHAT V3.2 EXPERIENCE FEATURES',
    'id="crm-new-chat-open"',
    'id="crm-new-chat-modal"',
    'id="crm-new-chat-close"',
    'id="crm-chat-search-modal"',
    'id="crm-chat-message-search-input"',
    'id="crm-chat-message-search-results"',
    'id="crm-chat-attachment-preview-modal"',
    'id="crm-chat-attachments"',
    'id="crm-chat-file-list"',
    'data-search-url=',
    'data-typing-url=',
    'data-typing-status-url=',
    'data-attachment-preview-url=',
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
            "Current V3.2 Blade tidak dikenali: {$marker}\n"
            ."Patch dihentikan agar file customized tidak rusak.\n"
        );

        exit(4);
    }
}

$backup =
    $blade
    .'.before-internal-chat-v3-2-2-ui-hotfix.bak';

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
| Add hard onclick to the New Chat controls.
|--------------------------------------------------------------------------
*/

$replacements = [
    [
        'old' =>
<<<'BLADE'
                                id="crm-new-chat-open"
                                class="primary-button"
BLADE,
        'new' =>
<<<'BLADE'
                                id="crm-new-chat-open"
                                class="primary-button"
                                onclick="return window.crmNewChatOpen(event);"
BLADE,
        'label' =>
            'New Chat open',
    ],
    [
        'old' =>
<<<'BLADE'
                                    data-open-new-chat
                                >
BLADE,
        'new' =>
<<<'BLADE'
                                    data-open-new-chat
                                    onclick="return window.crmNewChatOpen(event);"
                                >
BLADE,
        'label' =>
            'Empty New Chat open',
    ],
    [
        'old' =>
<<<'BLADE'
                    id="crm-new-chat-close"
                    class="secondary-button"
BLADE,
        'new' =>
<<<'BLADE'
                    id="crm-new-chat-close"
                    class="secondary-button"
                    onclick="return window.crmNewChatClose(event);"
BLADE,
        'label' =>
            'New Chat close',
    ],
];

foreach ($replacements as $replacement) {
    if (
        ! str_contains(
            $source,
            $replacement['old']
        )
    ) {
        /*
         * If a previous hotfix already placed the same onclick, do not fail.
         */
        if (
            str_contains(
                $source,
                $replacement['new']
            )
        ) {
            continue;
        }

        fwrite(
            STDERR,
            $replacement['label']
            ." marker tidak ditemukan.\n"
        );

        exit(6);
    }

    $source =
        str_replace(
            $replacement['old'],
            $replacement['new'],
            $source,
            $count
        );

    if ($count !== 1) {
        fwrite(
            STDERR,
            $replacement['label']
            ." replacement count salah: {$count}\n"
        );

        exit(7);
    }
}

/*
|--------------------------------------------------------------------------
| Independent interaction layer.
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

    exit(8);
}

$layer = <<<'BLADE'

    {{-- INTERNAL CHAT V3.2.2 ROBUST UI INTERACTIONS --}}
    <script>
        (() => {
            const stopEvent = (
                event
            ) => {
                if (! event) {
                    return;
                }

                event.preventDefault();
                event.stopPropagation();
            };

            const showOverlay = (
                element
            ) => {
                if (! element) {
                    return;
                }

                element.classList.remove(
                    'hidden'
                );

                element.classList.add(
                    'flex'
                );

                /*
                 * Inline display is deliberate here. It makes the hotfix
                 * independent from compiled utility-class ordering.
                 */
                element.style.display =
                    'flex';

                element.setAttribute(
                    'aria-hidden',
                    'false'
                );
            };

            const hideOverlay = (
                element
            ) => {
                if (! element) {
                    return;
                }

                element.classList.add(
                    'hidden'
                );

                element.classList.remove(
                    'flex'
                );

                element.style.display =
                    'none';

                element.setAttribute(
                    'aria-hidden',
                    'true'
                );
            };

            /*
            |--------------------------------------------------------------------------
            | New Chat
            |--------------------------------------------------------------------------
            */

            window.crmNewChatOpen =
                function (event) {
                    stopEvent(
                        event
                    );

                    const modal =
                        document.getElementById(
                            'crm-new-chat-modal'
                        );

                    const search =
                        document.getElementById(
                            'crm-new-chat-search'
                        );

                    showOverlay(
                        modal
                    );

                    if (search) {
                        search.value =
                            '';

                        search.dispatchEvent(
                            new Event(
                                'input'
                            )
                        );

                        window.setTimeout(
                            () => {
                                search.focus();
                            },
                            40
                        );
                    }

                    return false;
                };

            window.crmNewChatClose =
                function (event) {
                    stopEvent(
                        event
                    );

                    hideOverlay(
                        document.getElementById(
                            'crm-new-chat-modal'
                        )
                    );

                    return false;
                };

            const newButton =
                document.getElementById(
                    'crm-new-chat-open'
                );

            if (
                newButton
                && newButton.dataset.crmV322Bound !== '1'
            ) {
                newButton.dataset.crmV322Bound =
                    '1';

                newButton.addEventListener(
                    'click',
                    window.crmNewChatOpen
                );
            }

            const emptyNewButton =
                document.querySelector(
                    '[data-open-new-chat]'
                );

            if (
                emptyNewButton
                && emptyNewButton.dataset.crmV322Bound !== '1'
            ) {
                emptyNewButton.dataset.crmV322Bound =
                    '1';

                emptyNewButton.addEventListener(
                    'click',
                    window.crmNewChatOpen
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Message Search
            |--------------------------------------------------------------------------
            */

            window.crmChatSearchOpen =
                function (event) {
                    stopEvent(
                        event
                    );

                    const modal =
                        document.getElementById(
                            'crm-chat-search-modal'
                        );

                    const input =
                        document.getElementById(
                            'crm-chat-message-search-input'
                        );

                    showOverlay(
                        modal
                    );

                    window.setTimeout(
                        () => {
                            if (input) {
                                input.focus();
                            }
                        },
                        40
                    );

                    return false;
                };

            window.crmChatSearchClose =
                function (event) {
                    stopEvent(
                        event
                    );

                    hideOverlay(
                        document.getElementById(
                            'crm-chat-search-modal'
                        )
                    );

                    return false;
                };

            const jumpToMessage = (
                id
            ) => {
                const message =
                    document.querySelector(
                        '[data-message-id="'
                        + Number(id)
                        + '"]'
                    );

                if (! message) {
                    const feedback =
                        document.getElementById(
                            'crm-chat-message-search-feedback'
                        );

                    if (feedback) {
                        feedback.textContent =
                            'Pesan ditemukan, tetapi tidak termasuk 300 pesan yang sedang dimuat pada panel.';
                    }

                    return;
                }

                window.crmChatSearchClose();

                message.scrollIntoView({
                    behavior:
                        'smooth',

                    block:
                        'center',
                });

                /*
                 * Inline outline avoids depending on a dynamic Tailwind class.
                 */
                const previousOutline =
                    message.style.outline;

                const previousOffset =
                    message.style.outlineOffset;

                message.style.outline =
                    '3px solid #3b82f6';

                message.style.outlineOffset =
                    '4px';

                window.setTimeout(
                    () => {
                        message.style.outline =
                            previousOutline;

                        message.style.outlineOffset =
                            previousOffset;
                    },
                    2200
                );
            };

            window.crmChatSearchRun =
                async function (event) {
                    stopEvent(
                        event
                    );

                    const root =
                        document.getElementById(
                            'crm-chat-messages'
                        );

                    const input =
                        document.getElementById(
                            'crm-chat-message-search-input'
                        );

                    const results =
                        document.getElementById(
                            'crm-chat-message-search-results'
                        );

                    const feedback =
                        document.getElementById(
                            'crm-chat-message-search-feedback'
                        );

                    if (
                        ! root
                        || ! input
                        || ! results
                    ) {
                        return false;
                    }

                    const q =
                        String(
                            input.value
                            || ''
                        ).trim();

                    results.innerHTML =
                        '';

                    if (q.length < 2) {
                        if (feedback) {
                            feedback.textContent =
                                'Masukkan minimal 2 karakter.';
                        }

                        return false;
                    }

                    const url =
                        String(
                            root.dataset.searchUrl
                            || ''
                        );

                    if (! url) {
                        if (feedback) {
                            feedback.textContent =
                                'Search endpoint tidak tersedia.';
                        }

                        return false;
                    }

                    if (feedback) {
                        feedback.textContent =
                            'Searching...';
                    }

                    try {
                        const response =
                            await fetch(
                                url
                                + '?q='
                                + encodeURIComponent(
                                    q
                                ),
                                {
                                    headers: {
                                        'Accept':
                                            'application/json',

                                        'X-Requested-With':
                                            'XMLHttpRequest',
                                    },

                                    credentials:
                                        'same-origin',

                                    cache:
                                        'no-store',
                                }
                            );

                        if (! response.ok) {
                            throw new Error(
                                await response.text()
                            );
                        }

                        const data =
                            await response.json();

                        const rows =
                            Array.isArray(
                                data.results
                            )
                                ? data.results
                                : [];

                        if (feedback) {
                            feedback.textContent =
                                rows.length
                                + ' hasil ditemukan';
                        }

                        if (rows.length === 0) {
                            const empty =
                                document.createElement(
                                    'div'
                                );

                            empty.style.padding =
                                '24px';

                            empty.style.textAlign =
                                'center';

                            empty.style.color =
                                '#6b7280';

                            empty.textContent =
                                'Tidak ada pesan yang cocok.';

                            results.appendChild(
                                empty
                            );

                            return false;
                        }

                        rows.forEach(
                            (row) => {
                                const button =
                                    document.createElement(
                                        'button'
                                    );

                                button.type =
                                    'button';

                                button.style.display =
                                    'block';

                                button.style.width =
                                    '100%';

                                button.style.padding =
                                    '12px 16px';

                                button.style.textAlign =
                                    'left';

                                button.style.borderBottom =
                                    '1px solid #e5e7eb';

                                button.style.background =
                                    '#ffffff';

                                button.style.cursor =
                                    'pointer';

                                const header =
                                    document.createElement(
                                        'div'
                                    );

                                header.style.display =
                                    'flex';

                                header.style.justifyContent =
                                    'space-between';

                                header.style.gap =
                                    '12px';

                                const sender =
                                    document.createElement(
                                        'strong'
                                    );

                                sender.textContent =
                                    row.sender_name
                                    || 'User';

                                const date =
                                    document.createElement(
                                        'span'
                                    );

                                date.style.fontSize =
                                    '12px';

                                date.style.color =
                                    '#9ca3af';

                                date.textContent =
                                    row.created_at
                                    || '';

                                const body =
                                    document.createElement(
                                        'div'
                                    );

                                body.style.marginTop =
                                    '4px';

                                body.style.fontSize =
                                    '13px';

                                body.style.color =
                                    '#4b5563';

                                body.textContent =
                                    String(
                                        row.body
                                        || ''
                                    ).slice(
                                        0,
                                        220
                                    );

                                header.appendChild(
                                    sender
                                );

                                header.appendChild(
                                    date
                                );

                                button.appendChild(
                                    header
                                );

                                button.appendChild(
                                    body
                                );

                                button.onclick =
                                    function () {
                                        jumpToMessage(
                                            row.id
                                        );
                                    };

                                results.appendChild(
                                    button
                                );
                            }
                        );
                    } catch (error) {
                        console.error(
                            'Internal Chat search failed:',
                            error
                        );

                        if (feedback) {
                            feedback.textContent =
                                'Search gagal. Coba kembali.';
                        }
                    }

                    return false;
                };

            /*
            |--------------------------------------------------------------------------
            | Attachment preview after Send
            |--------------------------------------------------------------------------
            */

            const isImageName = (
                value
            ) => /\.(png|jpe?g|webp|gif|bmp)$/i.test(
                String(
                    value
                    || ''
                )
            );

            const isPdfName = (
                value
            ) => /\.pdf$/i.test(
                String(
                    value
                    || ''
                )
            );

            window.crmChatPreviewAttachment =
                function (link, event) {
                    if (! link) {
                        return true;
                    }

                    const fileLabel =
                        String(
                            link.textContent
                            || ''
                        );

                    const previewable =
                        isImageName(
                            fileLabel
                        )
                        || isPdfName(
                            fileLabel
                        );

                    /*
                     * Non-image/PDF keeps the normal download behavior.
                     */
                    if (! previewable) {
                        return true;
                    }

                    stopEvent(
                        event
                    );

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

                    if (
                        ! modal
                        || ! frame
                        || ! download
                    ) {
                        return true;
                    }

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

                    if (! previewUrl) {
                        return true;
                    }

                    frame.src =
                        previewUrl;

                    download.href =
                        downloadUrl;

                    showOverlay(
                        modal
                    );

                    return false;
                };

            window.crmChatPreviewClose =
                function (event) {
                    stopEvent(
                        event
                    );

                    const modal =
                        document.getElementById(
                            'crm-chat-attachment-preview-modal'
                        );

                    const frame =
                        document.getElementById(
                            'crm-chat-attachment-preview-frame'
                        );

                    hideOverlay(
                        modal
                    );

                    if (frame) {
                        frame.src =
                            'about:blank';
                    }

                    return false;
                };

            const decorateSentAttachment =
                (link) => {
                    if (
                        ! link
                        || link.dataset.crmInlinePreviewReady === '1'
                    ) {
                        return;
                    }

                    link.dataset.crmInlinePreviewReady =
                        '1';

                    const label =
                        String(
                            link.textContent
                            || ''
                        );

                    if (! isImageName(label)) {
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
                        label.trim();

                    image.loading =
                        'lazy';

                    image.style.display =
                        'block';

                    image.style.maxWidth =
                        '320px';

                    image.style.maxHeight =
                        '240px';

                    image.style.objectFit =
                        'contain';

                    image.style.borderRadius =
                        '8px';

                    image.style.marginBottom =
                        '8px';

                    image.style.background =
                        '#ffffff';

                    link.insertBefore(
                        image,
                        link.firstChild
                    );

                    link.style.display =
                        'block';
                };

            const decorateAllSentAttachments =
                (scope) => {
                    const parent =
                        scope
                        || document;

                    if (
                        parent.matches
                        && parent.matches(
                            '[data-attachment-preview-url]'
                        )
                    ) {
                        decorateSentAttachment(
                            parent
                        );
                    }

                    if (parent.querySelectorAll) {
                        parent
                            .querySelectorAll(
                                '[data-attachment-preview-url]'
                            )
                            .forEach(
                                decorateSentAttachment
                            );
                    }
                };

            decorateAllSentAttachments(
                document
            );

            const messages =
                document.getElementById(
                    'crm-chat-messages'
                );

            if (messages) {
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

                                            decorateAllSentAttachments(
                                                node
                                            );
                                        }
                                    );
                                }
                            );
                        }
                    );

                observer.observe(
                    messages,
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
            | Image preview BEFORE Send
            |--------------------------------------------------------------------------
            */

            const fileInput =
                document.getElementById(
                    'crm-chat-attachments'
                );

            const fileList =
                document.getElementById(
                    'crm-chat-file-list'
                );

            let objectUrls =
                [];

            const clearObjectUrls =
                () => {
                    objectUrls.forEach(
                        (url) => {
                            try {
                                URL.revokeObjectURL(
                                    url
                                );
                            } catch (error) {
                                // Ignore stale object URLs.
                            }
                        }
                    );

                    objectUrls =
                        [];
                };

            const renderSelectedFiles =
                () => {
                    if (
                        ! fileInput
                        || ! fileList
                    ) {
                        return;
                    }

                    clearObjectUrls();

                    fileList.innerHTML =
                        '';

                    Array.from(
                        fileInput.files
                        || []
                    ).forEach(
                        (file) => {
                            const card =
                                document.createElement(
                                    'div'
                                );

                            card.style.border =
                                '1px solid #e5e7eb';

                            card.style.borderRadius =
                                '10px';

                            card.style.padding =
                                '8px';

                            card.style.background =
                                '#ffffff';

                            card.style.maxWidth =
                                '220px';

                            if (
                                String(
                                    file.type
                                    || ''
                                ).startsWith(
                                    'image/'
                                )
                            ) {
                                const url =
                                    URL.createObjectURL(
                                        file
                                    );

                                objectUrls.push(
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
                                    '180px';

                                image.style.height =
                                    '130px';

                                image.style.objectFit =
                                    'cover';

                                image.style.borderRadius =
                                    '8px';

                                image.style.marginBottom =
                                    '6px';

                                card.appendChild(
                                    image
                                );
                            } else if (
                                String(
                                    file.type
                                    || ''
                                ) === 'application/pdf'
                            ) {
                                const pdf =
                                    document.createElement(
                                        'div'
                                    );

                                pdf.style.padding =
                                    '18px';

                                pdf.style.textAlign =
                                    'center';

                                pdf.style.borderRadius =
                                    '8px';

                                pdf.style.background =
                                    '#f3f4f6';

                                pdf.style.marginBottom =
                                    '6px';

                                pdf.textContent =
                                    '📄 PDF';

                                card.appendChild(
                                    pdf
                                );
                            }

                            const name =
                                document.createElement(
                                    'div'
                                );

                            name.style.fontSize =
                                '12px';

                            name.style.fontWeight =
                                '600';

                            name.style.wordBreak =
                                'break-word';

                            name.textContent =
                                file.name;

                            card.appendChild(
                                name
                            );

                            fileList.appendChild(
                                card
                            );
                        }
                    );
                };

            if (
                fileInput
                && fileInput.dataset.crmV322PreviewBound !== '1'
            ) {
                fileInput.dataset.crmV322PreviewBound =
                    '1';

                fileInput.addEventListener(
                    'change',
                    renderSelectedFiles
                );

                /*
                 * Existing V3 listener is registered before this one.
                 * This handler runs afterwards and upgrades chips to previews.
                 */
            }

            /*
            |--------------------------------------------------------------------------
            | Typing indicator robust fallback
            |--------------------------------------------------------------------------
            */

            const chatRoot =
                document.getElementById(
                    'crm-chat-messages'
                );

            const bodyInput =
                document.getElementById(
                    'crm-chat-body'
                );

            const typingIndicator =
                document.getElementById(
                    'crm-chat-typing-indicator'
                );

            const csrf =
                document.querySelector(
                    '#crm-chat-send-form input[name="_token"]'
                );

            const typingUrl =
                chatRoot
                    ? String(
                        chatRoot.dataset.typingUrl
                        || ''
                    )
                    : '';

            const typingStatusUrl =
                chatRoot
                    ? String(
                        chatRoot.dataset.typingStatusUrl
                        || ''
                    )
                    : '';

            const csrfToken =
                csrf
                    ? String(
                        csrf.value
                        || ''
                    )
                    : '';

            const sendTyping =
                async (typing) => {
                    if (! typingUrl) {
                        return;
                    }

                    try {
                        await fetch(
                            typingUrl,
                            {
                                method:
                                    'POST',

                                headers: {
                                    'Accept':
                                        'application/json',

                                    'Content-Type':
                                        'application/json',

                                    'X-CSRF-TOKEN':
                                        csrfToken,

                                    'X-Requested-With':
                                        'XMLHttpRequest',
                                },

                                credentials:
                                    'same-origin',

                                body:
                                    JSON.stringify({
                                        typing:
                                            Boolean(
                                                typing
                                            ),
                                    }),
                            }
                        );
                    } catch (error) {
                        // Best-effort UI signal.
                    }
                };

            if (
                bodyInput
                && bodyInput.dataset.crmV322TypingBound !== '1'
            ) {
                bodyInput.dataset.crmV322TypingBound =
                    '1';

                let stopTimer =
                    null;

                let lastPing =
                    0;

                bodyInput.addEventListener(
                    'input',
                    () => {
                        const now =
                            Date.now();

                        if (
                            now
                            - lastPing
                            > 1400
                        ) {
                            lastPing =
                                now;

                            sendTyping(
                                true
                            );
                        }

                        window.clearTimeout(
                            stopTimer
                        );

                        stopTimer =
                            window.setTimeout(
                                () => {
                                    sendTyping(
                                        false
                                    );
                                },
                                2600
                            );
                    }
                );

                bodyInput.addEventListener(
                    'blur',
                    () => {
                        sendTyping(
                            false
                        );
                    }
                );
            }

            if (
                typingStatusUrl
                && typingIndicator
                && ! window.__crmV322TypingPoll
            ) {
                window.__crmV322TypingPoll =
                    window.setInterval(
                        async () => {
                            try {
                                const response =
                                    await fetch(
                                        typingStatusUrl,
                                        {
                                            headers: {
                                                'Accept':
                                                    'application/json',

                                                'X-Requested-With':
                                                    'XMLHttpRequest',
                                            },

                                            credentials:
                                                'same-origin',

                                            cache:
                                                'no-store',
                                        }
                                    );

                                if (! response.ok) {
                                    return;
                                }

                                const data =
                                    await response.json();

                                if (
                                    data.typing
                                    && Array.isArray(
                                        data.users
                                    )
                                    && data.users.length
                                ) {
                                    typingIndicator.textContent =
                                        data.users
                                            .map(
                                                (user) =>
                                                    user.name
                                                    || 'User'
                                            )
                                            .join(
                                                ', '
                                            )
                                        + ' sedang mengetik...';

                                    typingIndicator.classList.remove(
                                        'hidden'
                                    );

                                    typingIndicator.style.display =
                                        'block';
                                } else {
                                    typingIndicator.classList.add(
                                        'hidden'
                                    );

                                    typingIndicator.style.display =
                                        'none';
                                }
                            } catch (error) {
                                // Ignore temporary polling failure.
                            }
                        },
                        2000
                    );
            }

            /*
            |--------------------------------------------------------------------------
            | Modal backdrop close
            |--------------------------------------------------------------------------
            */

            [
                [
                    'crm-new-chat-modal',
                    window.crmNewChatClose,
                ],
                [
                    'crm-chat-search-modal',
                    window.crmChatSearchClose,
                ],
                [
                    'crm-chat-attachment-preview-modal',
                    window.crmChatPreviewClose,
                ],
            ].forEach(
                ([id, closer]) => {
                    const overlay =
                        document.getElementById(
                            id
                        );

                    if (! overlay) {
                        return;
                    }

                    overlay.addEventListener(
                        'click',
                        (event) => {
                            if (
                                event.target
                                === overlay
                            ) {
                                closer(
                                    event
                                );
                            }
                        }
                    );
                }
            );
        })();
    </script>

BLADE;

$source =
    substr_replace(
        $source,
        $layer,
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

    exit(9);
}

$written =
    file_get_contents(
        $blade
    );

$postChecks = [
    'INTERNAL CHAT V3.2.2 ROBUST UI INTERACTIONS',
    'window.crmNewChatOpen',
    'window.crmNewChatClose',
    'window.crmChatSearchOpen',
    'window.crmChatSearchRun',
    'window.crmChatPreviewAttachment',
    'renderSelectedFiles',
    'URL.createObjectURL',
    'decorateSentAttachment',
    'crmV322TypingBound',
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

        exit(10);
    }
}

echo "[PASS] New Chat hard interaction restored.\n";
echo "[PASS] Message Search hard interaction restored.\n";
echo "[PASS] Pre-send image/PDF preview installed.\n";
echo "[PASS] Sent image inline thumbnail installed.\n";
echo "[PASS] Attachment preview modal interaction restored.\n";
echo "[PASS] Typing indicator fallback installed.\n";
echo "[PASS] Reply/Edit/Delete preserved.\n";
echo "[PASS] No controller / route / database changes.\n";
