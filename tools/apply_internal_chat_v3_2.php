<?php

/*
|--------------------------------------------------------------------------
| Internal Chat V3.2
|--------------------------------------------------------------------------
|
| Adds:
| - Message Search
| - Image/PDF Attachment Preview
| - Typing Indicator
|
| It patches the CURRENT customized provider + chat Blade in-place.
| It does NOT replace InternalChatController.php.
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

function backupOnce(
    string $path,
    string $suffix
): void {
    $backup =
        $path
        .$suffix;

    if (! is_file($backup)) {
        if (! copy(
            $path,
            $backup
        )) {
            throw new RuntimeException(
                "Gagal membuat backup: {$backup}"
            );
        }
    }
}

/*
|--------------------------------------------------------------------------
| 1. Provider routes
|--------------------------------------------------------------------------
*/

$provider =
    $root
    .'/packages/Webkul/Admin/src/Providers/InternalCommunicationServiceProvider.php';

if (! is_file($provider)) {
    fwrite(
        STDERR,
        "InternalCommunicationServiceProvider tidak ditemukan.\n"
    );

    exit(2);
}

$providerSource =
    file_get_contents(
        $provider
    );

if ($providerSource === false) {
    fwrite(
        STDERR,
        "Provider tidak dapat dibaca.\n"
    );

    exit(3);
}

backupOnce(
    $provider,
    '.before-internal-chat-v3-2.bak'
);

$useLine =
    'use Webkul\Admin\Http\Controllers\InternalCommunication\InternalChatExperienceController;';

if (
    ! str_contains(
        $providerSource,
        $useLine
    )
) {
    $anchor =
        'use Webkul\Admin\Http\Controllers\InternalCommunication\InternalChatController;';

    if (
        ! str_contains(
            $providerSource,
            $anchor
        )
    ) {
        fwrite(
            STDERR,
            "Provider controller import anchor tidak ditemukan.\n"
        );

        exit(4);
    }

    $providerSource =
        str_replace(
            $anchor,
            $anchor
            ."\n"
            .$useLine,
            $providerSource
        );
}

if (
    ! str_contains(
        $providerSource,
        'admin.internal-chat.search'
    )
) {
    $routeName =
        'admin.internal-chat.attachments.download';

    $namePos =
        strpos(
            $providerSource,
            $routeName
        );

    if ($namePos === false) {
        fwrite(
            STDERR,
            "Attachment download route anchor tidak ditemukan.\n"
        );

        exit(5);
    }

    $statementEnd =
        strpos(
            $providerSource,
            ';',
            $namePos
        );

    if ($statementEnd === false) {
        fwrite(
            STDERR,
            "Akhir attachment route statement tidak ditemukan.\n"
        );

        exit(6);
    }

    $routes = <<<'PHP'

                    Route::get(
                        'internal-chat/{conversationId}/search',
                        [
                            InternalChatExperienceController::class,
                            'search',
                        ]
                    )->name(
                        'admin.internal-chat.search'
                    );

                    Route::post(
                        'internal-chat/{conversationId}/typing',
                        [
                            InternalChatExperienceController::class,
                            'typing',
                        ]
                    )->name(
                        'admin.internal-chat.typing'
                    );

                    Route::get(
                        'internal-chat/{conversationId}/typing-status',
                        [
                            InternalChatExperienceController::class,
                            'typingStatus',
                        ]
                    )->name(
                        'admin.internal-chat.typing-status'
                    );

                    Route::get(
                        'internal-chat/attachments/{id}/preview',
                        [
                            InternalChatExperienceController::class,
                            'previewAttachment',
                        ]
                    )->name(
                        'admin.internal-chat.attachments.preview'
                    );
PHP;

    $providerSource =
        substr_replace(
            $providerSource,
            "\n"
            .$routes,
            $statementEnd
            + 1,
            0
        );
}

file_put_contents(
    $provider,
    $providerSource
);

echo "[PASS] Search / typing / preview routes installed.\n";

/*
|--------------------------------------------------------------------------
| 2. Current chat Blade
|--------------------------------------------------------------------------
*/

$blade =
    $root
    .'/packages/Webkul/Admin/src/Resources/views/internal-communication/chat.blade.php';

if (! is_file($blade)) {
    fwrite(
        STDERR,
        "chat.blade.php tidak ditemukan.\n"
    );

    exit(7);
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

    exit(8);
}

backupOnce(
    $blade,
    '.before-internal-chat-v3-2.bak'
);

if (
    str_contains(
        $source,
        'INTERNAL CHAT V3.2 EXPERIENCE FEATURES'
    )
) {
    echo "[SKIP] Internal Chat V3.2 UI already installed.\n";
    exit(0);
}

/*
 * V3.1.4 is the expected stable baseline.
 */
$requiredCurrent = [
    'id="crm-chat-messages"',
    'id="crm-chat-send-form"',
    'id="crm-chat-body"',
    'data-read-receipt',
    'window.crmChatReplyAction',
    'window.crmChatEditAction',
    'window.crmChatDeleteAction',
    'crm-chat-attachments',
    '5000',
];

foreach ($requiredCurrent as $marker) {
    if (
        ! str_contains(
            $source,
            $marker
        )
    ) {
        fwrite(
            STDERR,
            "Current chat baseline tidak dikenali: {$marker}\n"
            ."Patch dihentikan agar file customized tidak rusak.\n"
        );

        exit(9);
    }
}

/*
|--------------------------------------------------------------------------
| Add V3.2 endpoint data attributes on message root
|--------------------------------------------------------------------------
*/

$rootAnchor =
    'data-action-base="{{ url(\'admin/internal-chat/\'.$conversation->id.\'/messages\') }}"';

if (
    ! str_contains(
        $source,
        $rootAnchor
    )
) {
    fwrite(
        STDERR,
        "V3.1.4 data-action-base marker tidak ditemukan.\n"
    );

    exit(10);
}

$rootReplacement =
    $rootAnchor
    ."\n"
    .'                        data-search-url="{{ route(\'admin.internal-chat.search\', $conversation->id) }}"'
    ."\n"
    .'                        data-typing-url="{{ route(\'admin.internal-chat.typing\', $conversation->id) }}"'
    ."\n"
    .'                        data-typing-status-url="{{ route(\'admin.internal-chat.typing-status\', $conversation->id) }}"';

$source =
    str_replace(
        $rootAnchor,
        $rootReplacement,
        $source,
        $rootCount
    );

if ($rootCount !== 1) {
    fwrite(
        STDERR,
        "Endpoint data replacement count salah: {$rootCount}\n"
    );

    exit(11);
}

/*
|--------------------------------------------------------------------------
| Header: Search button + typing indicator
|--------------------------------------------------------------------------
*/

$privateBlock = <<<'BLADE'
                        <div class="shrink-0 rounded-full border bg-gray-50 px-3 py-2 text-xs font-semibold text-gray-500">
                            🔒 Private
                        </div>
BLADE;

if (
    ! str_contains(
        $source,
        $privateBlock
    )
) {
    fwrite(
        STDERR,
        "Private header block tidak ditemukan.\n"
    );

    exit(12);
}

$newPrivateBlock = <<<'BLADE'
                        <div class="flex shrink-0 items-center gap-2">
                            <button
                                type="button"
                                class="secondary-button"
                                onclick="return window.crmChatSearchOpen(event);"
                            >
                                🔎 Search
                            </button>

                            <div class="rounded-full border bg-gray-50 px-3 py-2 text-xs font-semibold text-gray-500">
                                🔒 Private
                            </div>
                        </div>
BLADE;

$source =
    str_replace(
        $privateBlock,
        $newPrivateBlock,
        $source,
        $headerCount
    );

if ($headerCount !== 1) {
    fwrite(
        STDERR,
        "Header replacement count salah: {$headerCount}\n"
    );

    exit(13);
}

$headerMeta = <<<'BLADE'
                                <div class="mt-0.5 truncate text-xs text-gray-500">
                                    {{ $activeOtherUser?->role_name ?: '-' }}
                                    · {{ $activeOtherUser?->email ?: '-' }}
                                </div>
BLADE;

if (
    ! str_contains(
        $source,
        $headerMeta
    )
) {
    fwrite(
        STDERR,
        "Header metadata block tidak ditemukan.\n"
    );

    exit(14);
}

$headerMetaNew =
    $headerMeta
    ."\n\n"
    .<<<'BLADE'
                                <div
                                    id="crm-chat-typing-indicator"
                                    class="mt-1 hidden text-xs font-semibold text-green-600"
                                >
                                    sedang mengetik...
                                </div>
BLADE;

$source =
    str_replace(
        $headerMeta,
        $headerMetaNew,
        $source,
        $typingIndicatorCount
    );

if ($typingIndicatorCount !== 1) {
    fwrite(
        STDERR,
        "Typing indicator insertion count salah: {$typingIndicatorCount}\n"
    );

    exit(15);
}

/*
|--------------------------------------------------------------------------
| Existing attachment links become preview links.
|--------------------------------------------------------------------------
*/

$attachmentHref =
    'href="{{ route(\'admin.internal-chat.attachments.download\', $attachment->id) }}"';

$attachmentHrefNew =
    $attachmentHref
    ."\n"
    .'                                                        data-attachment-download-url="{{ route(\'admin.internal-chat.attachments.download\', $attachment->id) }}"'
    ."\n"
    .'                                                        data-attachment-preview-url="{{ route(\'admin.internal-chat.attachments.preview\', $attachment->id) }}"'
    ."\n"
    .'                                                        onclick="return window.crmChatPreviewAttachment(this, event);"';

if (
    ! str_contains(
        $source,
        $attachmentHref
    )
) {
    fwrite(
        STDERR,
        "Server attachment link marker tidak ditemukan.\n"
    );

    exit(16);
}

$source =
    str_replace(
        $attachmentHref,
        $attachmentHrefNew,
        $source,
        $serverAttachmentCount
    );

if ($serverAttachmentCount < 1) {
    fwrite(
        STDERR,
        "Attachment server link tidak dipatch.\n"
    );

    exit(17);
}

/*
|--------------------------------------------------------------------------
| Polling-created attachment links
|--------------------------------------------------------------------------
*/

$dynamicHref = <<<'JS'
                                link.href =
                                    attachment.download_url;
JS;

if (
    ! str_contains(
        $source,
        $dynamicHref
    )
) {
    fwrite(
        STDERR,
        "Dynamic attachment href marker tidak ditemukan.\n"
    );

    exit(18);
}

$dynamicHrefNew = <<<'JS'
                                link.href =
                                    attachment.download_url;

                                link.dataset.attachmentDownloadUrl =
                                    attachment.download_url;

                                link.dataset.attachmentPreviewUrl =
                                    String(
                                        attachment.download_url
                                        || ''
                                    ).replace(
                                        /\/download(?:\?.*)?$/,
                                        '/preview'
                                    );

                                link.onclick =
                                    function (event) {
                                        return window.crmChatPreviewAttachment(
                                            this,
                                            event
                                        );
                                    };
JS;

$source =
    str_replace(
        $dynamicHref,
        $dynamicHrefNew,
        $dynamicAttachmentCount
    );

if ($dynamicAttachmentCount !== 1) {
    fwrite(
        STDERR,
        "Dynamic attachment replacement count salah: {$dynamicAttachmentCount}\n"
    );

    exit(19);
}

/*
|--------------------------------------------------------------------------
| Search + preview modals and standalone robust JS
|--------------------------------------------------------------------------
*/

$modalAnchor =
    '    {{-- New Chat modal. The long user list no longer consumes the sidebar. --}}';

if (
    ! str_contains(
        $source,
        $modalAnchor
    )
) {
    fwrite(
        STDERR,
        "New Chat modal anchor tidak ditemukan.\n"
    );

    exit(20);
}

$v32Ui = <<<'BLADE'
    {{-- INTERNAL CHAT V3.2 EXPERIENCE FEATURES --}}
    <div
        id="crm-chat-search-modal"
        class="fixed inset-0 z-50 hidden items-center justify-center bg-black bg-opacity-40 p-4"
        aria-hidden="true"
    >
        <div class="flex max-h-screen w-full max-w-2xl flex-col overflow-hidden rounded-xl border bg-white shadow-lg">
            <div class="flex items-center justify-between gap-3 border-b p-4">
                <div>
                    <div class="text-lg font-bold text-gray-900">
                        Search Messages
                    </div>

                    <div class="mt-1 text-xs text-gray-500">
                        Cari isi pesan pada conversation ini.
                    </div>
                </div>

                <button
                    type="button"
                    class="secondary-button"
                    onclick="return window.crmChatSearchClose(event);"
                >
                    Close
                </button>
            </div>

            <div class="border-b p-4">
                <div class="flex gap-2">
                    <input
                        type="text"
                        id="crm-chat-message-search-input"
                        class="w-full rounded-lg border px-3 py-2 text-sm"
                        placeholder="Contoh: SPK, invoice, Semarang..."
                        onkeydown="if (event.key === 'Enter') { event.preventDefault(); window.crmChatSearchRun(); }"
                    >

                    <button
                        type="button"
                        class="primary-button"
                        onclick="return window.crmChatSearchRun(event);"
                    >
                        Search
                    </button>
                </div>

                <div
                    id="crm-chat-message-search-feedback"
                    class="mt-2 text-xs text-gray-500"
                ></div>
            </div>

            <div
                id="crm-chat-message-search-results"
                class="flex-1 overflow-y-auto"
            ></div>
        </div>
    </div>

    <div
        id="crm-chat-attachment-preview-modal"
        class="fixed inset-0 z-50 hidden items-center justify-center bg-black bg-opacity-60 p-4"
        aria-hidden="true"
    >
        <div class="flex h-full max-h-screen w-full max-w-5xl flex-col overflow-hidden rounded-xl bg-white shadow-xl">
            <div class="flex items-center justify-between gap-3 border-b p-3">
                <div class="font-bold text-gray-900">
                    Attachment Preview
                </div>

                <div class="flex items-center gap-2">
                    <a
                        id="crm-chat-attachment-download"
                        href="#"
                        class="secondary-button"
                    >
                        Download
                    </a>

                    <button
                        type="button"
                        class="secondary-button"
                        onclick="return window.crmChatPreviewClose(event);"
                    >
                        Close
                    </button>
                </div>
            </div>

            <div class="min-h-0 flex-1 bg-gray-100 p-2">
                <iframe
                    id="crm-chat-attachment-preview-frame"
                    class="h-full w-full rounded-lg border bg-white"
                    title="Attachment preview"
                ></iframe>
            </div>

            <div class="border-t px-4 py-2 text-xs text-gray-500">
                Preview tersedia untuk image dan PDF. File lain tetap dapat di-download.
            </div>
        </div>
    </div>

    <script>
        (() => {
            const root =
                document.getElementById(
                    'crm-chat-messages'
                );

            if (! root) {
                return;
            }

            const csrf =
                document.querySelector(
                    '#crm-chat-send-form input[name="_token"]'
                );

            const csrfToken =
                csrf
                    ? String(
                        csrf.value
                        || ''
                    )
                    : '';

            const searchModal =
                document.getElementById(
                    'crm-chat-search-modal'
                );

            const searchInput =
                document.getElementById(
                    'crm-chat-message-search-input'
                );

            const searchResults =
                document.getElementById(
                    'crm-chat-message-search-results'
                );

            const searchFeedback =
                document.getElementById(
                    'crm-chat-message-search-feedback'
                );

            const previewModal =
                document.getElementById(
                    'crm-chat-attachment-preview-modal'
                );

            const previewFrame =
                document.getElementById(
                    'crm-chat-attachment-preview-frame'
                );

            const previewDownload =
                document.getElementById(
                    'crm-chat-attachment-download'
                );

            const typingIndicator =
                document.getElementById(
                    'crm-chat-typing-indicator'
                );

            const bodyInput =
                document.getElementById(
                    'crm-chat-body'
                );

            const searchUrl =
                String(
                    root.dataset.searchUrl
                    || ''
                );

            const typingUrl =
                String(
                    root.dataset.typingUrl
                    || ''
                );

            const typingStatusUrl =
                String(
                    root.dataset.typingStatusUrl
                    || ''
                );

            const stopEvent = (event) => {
                if (! event) {
                    return;
                }

                event.preventDefault();
                event.stopPropagation();
            };

            window.crmChatSearchOpen =
                function (event) {
                    stopEvent(
                        event
                    );

                    if (! searchModal) {
                        return false;
                    }

                    searchModal.classList.remove(
                        'hidden'
                    );

                    searchModal.classList.add(
                        'flex'
                    );

                    searchModal.setAttribute(
                        'aria-hidden',
                        'false'
                    );

                    window.setTimeout(
                        () => {
                            if (searchInput) {
                                searchInput.focus();
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

                    if (! searchModal) {
                        return false;
                    }

                    searchModal.classList.add(
                        'hidden'
                    );

                    searchModal.classList.remove(
                        'flex'
                    );

                    searchModal.setAttribute(
                        'aria-hidden',
                        'true'
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
                    if (searchFeedback) {
                        searchFeedback.textContent =
                            'Pesan ditemukan di histori tetapi belum dimuat pada panel chat saat ini.';
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

                message.classList.add(
                    'ring-2',
                    'ring-blue-400'
                );

                window.setTimeout(
                    () => {
                        message.classList.remove(
                            'ring-2',
                            'ring-blue-400'
                        );
                    },
                    2200
                );
            };

            window.crmChatSearchRun =
                async function (event) {
                    stopEvent(
                        event
                    );

                    const q =
                        String(
                            searchInput
                                ? searchInput.value
                                : ''
                        ).trim();

                    if (! searchResults) {
                        return false;
                    }

                    searchResults.innerHTML =
                        '';

                    if (q.length < 2) {
                        if (searchFeedback) {
                            searchFeedback.textContent =
                                'Masukkan minimal 2 karakter.';
                        }

                        return false;
                    }

                    if (searchFeedback) {
                        searchFeedback.textContent =
                            'Searching...';
                    }

                    try {
                        const response =
                            await fetch(
                                searchUrl
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

                        const results =
                            data.results
                            || [];

                        if (searchFeedback) {
                            searchFeedback.textContent =
                                results.length
                                + ' hasil ditemukan';
                        }

                        if (results.length === 0) {
                            const empty =
                                document.createElement(
                                    'div'
                                );

                            empty.className =
                                'p-8 text-center text-sm text-gray-500';

                            empty.textContent =
                                'Tidak ada pesan yang cocok.';

                            searchResults.appendChild(
                                empty
                            );

                            return false;
                        }

                        results.forEach(
                            (result) => {
                                const button =
                                    document.createElement(
                                        'button'
                                    );

                                button.type =
                                    'button';

                                button.className =
                                    'block w-full border-b px-4 py-3 text-left hover:bg-gray-50';

                                const header =
                                    document.createElement(
                                        'div'
                                    );

                                header.className =
                                    'flex items-center justify-between gap-3';

                                const sender =
                                    document.createElement(
                                        'div'
                                    );

                                sender.className =
                                    'text-sm font-bold text-gray-900';

                                sender.textContent =
                                    result.sender_name
                                    || 'User';

                                const date =
                                    document.createElement(
                                        'div'
                                    );

                                date.className =
                                    'shrink-0 text-xs text-gray-400';

                                date.textContent =
                                    result.created_at
                                    || '';

                                const body =
                                    document.createElement(
                                        'div'
                                    );

                                body.className =
                                    'mt-1 text-sm text-gray-600';

                                body.textContent =
                                    String(
                                        result.body
                                        || ''
                                    ).slice(
                                        0,
                                        180
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
                                            result.id
                                        );
                                    };

                                searchResults.appendChild(
                                    button
                                );
                            }
                        );
                    } catch (error) {
                        console.error(
                            'Internal Chat search failed:',
                            error
                        );

                        if (searchFeedback) {
                            searchFeedback.textContent =
                                'Search gagal. Coba kembali.';
                        }
                    }

                    return false;
                };

            window.crmChatPreviewAttachment =
                function (link, event) {
                    stopEvent(
                        event
                    );

                    if (
                        ! previewModal
                        || ! previewFrame
                        || ! previewDownload
                    ) {
                        return true;
                    }

                    const previewUrl =
                        String(
                            link.dataset
                                .attachmentPreviewUrl
                            || ''
                        );

                    const downloadUrl =
                        String(
                            link.dataset
                                .attachmentDownloadUrl
                            || link.href
                            || ''
                        );

                    previewDownload.href =
                        downloadUrl;

                    previewFrame.src =
                        previewUrl;

                    previewModal.classList.remove(
                        'hidden'
                    );

                    previewModal.classList.add(
                        'flex'
                    );

                    previewModal.setAttribute(
                        'aria-hidden',
                        'false'
                    );

                    return false;
                };

            window.crmChatPreviewClose =
                function (event) {
                    stopEvent(
                        event
                    );

                    if (! previewModal) {
                        return false;
                    }

                    previewModal.classList.add(
                        'hidden'
                    );

                    previewModal.classList.remove(
                        'flex'
                    );

                    previewModal.setAttribute(
                        'aria-hidden',
                        'true'
                    );

                    if (previewFrame) {
                        previewFrame.src =
                            'about:blank';
                    }

                    return false;
                };

            const postTyping =
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
                        // Typing is a best-effort UX signal.
                    }
                };

            let typingTimer =
                null;

            let typingThrottleAt =
                0;

            if (bodyInput) {
                bodyInput.addEventListener(
                    'input',
                    () => {
                        const now =
                            Date.now();

                        if (
                            now
                            - typingThrottleAt
                            > 1400
                        ) {
                            typingThrottleAt =
                                now;

                            postTyping(
                                true
                            );
                        }

                        window.clearTimeout(
                            typingTimer
                        );

                        typingTimer =
                            window.setTimeout(
                                () => {
                                    postTyping(
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
                        postTyping(
                            false
                        );
                    }
                );
            }

            const pollTyping =
                async () => {
                    if (
                        ! typingStatusUrl
                        || ! typingIndicator
                    ) {
                        return;
                    }

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
                            && (
                                data.users
                                || []
                            ).length
                        ) {
                            const names =
                                data.users
                                    .map(
                                        (user) =>
                                            user.name
                                            || 'User'
                                    )
                                    .join(
                                        ', '
                                    );

                            typingIndicator.textContent =
                                names
                                + ' sedang mengetik...';

                            typingIndicator.classList.remove(
                                'hidden'
                            );
                        } else {
                            typingIndicator.classList.add(
                                'hidden'
                            );
                        }
                    } catch (error) {
                        // Typing status failure does not affect chat.
                    }
                };

            window.setInterval(
                pollTyping,
                2000
            );

            if (searchModal) {
                searchModal.addEventListener(
                    'click',
                    (event) => {
                        if (
                            event.target
                            === searchModal
                        ) {
                            window.crmChatSearchClose(
                                event
                            );
                        }
                    }
                );
            }

            if (previewModal) {
                previewModal.addEventListener(
                    'click',
                    (event) => {
                        if (
                            event.target
                            === previewModal
                        ) {
                            window.crmChatPreviewClose(
                                event
                            );
                        }
                    }
                );
            }
        })();
    </script>

BLADE;

$source =
    str_replace(
        $modalAnchor,
        $v32Ui
        .$modalAnchor,
        $source,
        $modalCount
    );

if ($modalCount !== 1) {
    fwrite(
        STDERR,
        "V3.2 UI insertion count salah: {$modalCount}\n"
    );

    exit(21);
}

if (
    file_put_contents(
        $blade,
        $source
    ) === false
) {
    fwrite(
        STDERR,
        "Gagal menulis chat Blade.\n"
    );

    exit(22);
}

$written =
    file_get_contents(
        $blade
    );

$postChecks = [
    'INTERNAL CHAT V3.2 EXPERIENCE FEATURES',
    'crm-chat-search-modal',
    'crm-chat-attachment-preview-modal',
    'crm-chat-typing-indicator',
    'data-search-url',
    'data-typing-url',
    'data-typing-status-url',
    'admin.internal-chat.attachments.preview',
    'window.crmChatSearchRun',
    'window.crmChatPreviewAttachment',
    'pollTyping',
    'window.crmChatReplyAction',
    'window.crmChatEditAction',
    'window.crmChatDeleteAction',
    'data-read-receipt',
    '5000',
];

foreach ($postChecks as $marker) {
    if (
        $written === false
        || ! str_contains(
            $written,
            $marker
        )
    ) {
        fwrite(
            STDERR,
            "Post-write validation gagal: {$marker}\n"
        );

        exit(23);
    }
}

echo "[PASS] Message Search UI installed.\n";
echo "[PASS] Attachment Preview UI installed.\n";
echo "[PASS] Typing Indicator UI installed.\n";
echo "[PASS] Reply/Edit/Delete preserved.\n";
echo "[PASS] Read receipts and 5-second message polling preserved.\n";
echo "[PASS] No migration required.\n";
