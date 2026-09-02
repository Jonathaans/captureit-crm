<?php

/*
|--------------------------------------------------------------------------
| Internal Chat V3.1.4 - Hard Action Fallback
|--------------------------------------------------------------------------
|
| Fixes Reply / Edit / Delete buttons that render correctly but do nothing.
|
| This patch deliberately does NOT depend on the large chat IIFE registering
| event listeners successfully. It adds:
|
| - explicit onclick handlers to server-rendered buttons
| - global window.crmChat* handlers
| - direct onclick handlers for messages appended by polling
|
| Existing routes/controllers stay unchanged.
|
*/

$root = realpath(__DIR__.'/..');

if (! $root) {
    fwrite(STDERR, "Project root tidak ditemukan.\n");
    exit(1);
}

$target =
    $root
    .'/packages/Webkul/Admin/src/Resources/views/internal-communication/chat.blade.php';

if (! is_file($target)) {
    fwrite(STDERR, "chat.blade.php tidak ditemukan.\n");
    exit(2);
}

$source = file_get_contents($target);

if ($source === false) {
    fwrite(STDERR, "Gagal membaca chat.blade.php.\n");
    exit(3);
}

if (
    str_contains(
        $source,
        'INTERNAL CHAT V3.1.4 HARD ACTION FALLBACK'
    )
) {
    echo "[SKIP] Internal Chat V3.1.4 already installed.\n";
    exit(0);
}

$required = [
    'data-reply-message="{{ $message->id }}"',
    'data-edit-message="{{ $message->id }}"',
    'data-delete-message="{{ $message->id }}"',
    'const replyButton =',
    'const editButton =',
    'const deleteButton =',
    'id="crm-chat-messages"',
    'id="crm-chat-send-form"',
];

foreach ($required as $marker) {
    if (! str_contains($source, $marker)) {
        fwrite(
            STDERR,
            "Baseline chat tidak dikenali: {$marker}\n"
            ."Patch dihentikan agar file customized tidak tertimpa sembarangan.\n"
        );

        exit(4);
    }
}

$backup =
    $target
    .'.before-internal-chat-v3-1-4-hard-action-fallback.bak';

if (! is_file($backup)) {
    if (! copy($target, $backup)) {
        fwrite(STDERR, "Gagal membuat backup chat Blade.\n");
        exit(5);
    }
}

/*
|--------------------------------------------------------------------------
| 1. Add stable action base to messages root
|--------------------------------------------------------------------------
*/

$oldMessagesRoot = <<<'BLADE'
                        data-sync-at="{{ now()->format('Y-m-d H:i:s.u') }}"
                    >
BLADE;

$newMessagesRoot = <<<'BLADE'
                        data-sync-at="{{ now()->format('Y-m-d H:i:s.u') }}"
                        data-action-base="{{ url('admin/internal-chat/'.$conversation->id.'/messages') }}"
                    >
BLADE;

if (! str_contains($source, $oldMessagesRoot)) {
    fwrite(STDERR, "Messages root marker tidak ditemukan.\n");
    exit(6);
}

$source = str_replace(
    $oldMessagesRoot,
    $newMessagesRoot,
    $source,
    $countRoot
);

if ($countRoot !== 1) {
    fwrite(
        STDERR,
        "Messages root replacement count salah: {$countRoot}\n"
    );
    exit(7);
}

/*
|--------------------------------------------------------------------------
| 2. Explicit inline action hooks for server-rendered messages
|--------------------------------------------------------------------------
*/

$serverHooks = [
    [
        'old' =>
<<<'BLADE'
                                                data-reply-message="{{ $message->id }}"
                                                data-reply-sender="{{ $senderNames[$message->user_id] ?? 'User' }}"
BLADE,
        'new' =>
<<<'BLADE'
                                                data-reply-message="{{ $message->id }}"
                                                onclick="return window.crmChatReplyAction(this, event);"
                                                data-reply-sender="{{ $senderNames[$message->user_id] ?? 'User' }}"
BLADE,
        'label' =>
            'Reply onclick',
    ],
    [
        'old' =>
<<<'BLADE'
                                                    data-edit-message="{{ $message->id }}"
                                                    data-edit-body="{{ e((string) $message->body) }}"
BLADE,
        'new' =>
<<<'BLADE'
                                                    data-edit-message="{{ $message->id }}"
                                                    onclick="return window.crmChatEditAction(this, event);"
                                                    data-edit-body="{{ e((string) $message->body) }}"
BLADE,
        'label' =>
            'Edit onclick',
    ],
    [
        'old' =>
<<<'BLADE'
                                                    data-delete-message="{{ $message->id }}"
                                                >
BLADE,
        'new' =>
<<<'BLADE'
                                                    data-delete-message="{{ $message->id }}"
                                                    onclick="return window.crmChatDeleteAction(this, event);"
                                                >
BLADE,
        'label' =>
            'Delete onclick',
    ],
];

foreach ($serverHooks as $hook) {
    if (! str_contains($source, $hook['old'])) {
        fwrite(
            STDERR,
            $hook['label']
            ." marker tidak ditemukan.\n"
        );
        exit(8);
    }

    $source = str_replace(
        $hook['old'],
        $hook['new'],
        $source,
        $count
    );

    if ($count !== 1) {
        fwrite(
            STDERR,
            $hook['label']
            ." replacement count salah: {$count}\n"
        );
        exit(9);
    }
}

/*
|--------------------------------------------------------------------------
| 3. Add self-contained global action handlers
|--------------------------------------------------------------------------
|
| These work even if the large chat event-binding block never reaches its
| bindMessageActionButtons() call.
|
*/

$insertAnchor = <<<'BLADE'
    @if ($conversation)
        <script>
            (() => {
BLADE;

if (! str_contains($source, $insertAnchor)) {
    fwrite(
        STDERR,
        "Conversation script anchor tidak ditemukan.\n"
    );
    exit(10);
}

$globalHandlers = <<<'BLADE'
    @if ($conversation)
        {{-- INTERNAL CHAT V3.1.4 HARD ACTION FALLBACK --}}
        <script>
            window.crmChatStopActionEvent = function (event) {
                if (! event) {
                    return;
                }

                event.preventDefault();
                event.stopPropagation();

                if (
                    typeof event.stopImmediatePropagation
                    === 'function'
                ) {
                    event.stopImmediatePropagation();
                }
            };

            window.crmChatCsrfToken = function () {
                const token =
                    document.querySelector(
                        '#crm-chat-send-form input[name="_token"]'
                    );

                return token
                    ? String(token.value || '')
                    : '';
            };

            window.crmChatMessageActionUrl = function (messageId) {
                const root =
                    document.getElementById(
                        'crm-chat-messages'
                    );

                const base =
                    root
                    ? String(
                        root.dataset.actionBase
                        || ''
                    )
                    : '';

                return base.replace(
                    /\/+$/,
                    ''
                )
                + '/'
                + encodeURIComponent(
                    String(messageId)
                );
            };

            window.crmChatReplyAction = function (button, event) {
                window.crmChatStopActionEvent(
                    event
                );

                const input =
                    document.getElementById(
                        'crm-chat-reply-to-message-id'
                    );

                const preview =
                    document.getElementById(
                        'crm-chat-reply-preview'
                    );

                const sender =
                    document.getElementById(
                        'crm-chat-reply-sender'
                    );

                const body =
                    document.getElementById(
                        'crm-chat-reply-body'
                    );

                const textarea =
                    document.getElementById(
                        'crm-chat-body'
                    );

                if (! input || ! preview) {
                    window.alert(
                        'Reply UI tidak ditemukan. Refresh halaman lalu coba kembali.'
                    );

                    return false;
                }

                input.value =
                    String(
                        button.dataset.replyMessage
                        || ''
                    );

                if (sender) {
                    sender.textContent =
                        String(
                            button.dataset.replySender
                            || 'User'
                        );
                }

                if (body) {
                    body.textContent =
                        String(
                            button.dataset.replyBody
                            || 'Attachment'
                        );
                }

                preview.classList.remove(
                    'hidden'
                );

                preview.classList.add(
                    'flex'
                );

                const editPreview =
                    document.getElementById(
                        'crm-chat-edit-preview'
                    );

                if (editPreview) {
                    editPreview.classList.add(
                        'hidden'
                    );

                    editPreview.classList.remove(
                        'flex'
                    );
                }

                if (textarea) {
                    textarea.focus();
                }

                return false;
            };

            window.crmChatEditAction = async function (button, event) {
                window.crmChatStopActionEvent(
                    event
                );

                const messageId =
                    Number(
                        button.dataset.editMessage
                        || 0
                    );

                const currentBody =
                    String(
                        button.dataset.editBody
                        || ''
                    );

                if (messageId < 1) {
                    window.alert(
                        'Message ID tidak valid.'
                    );

                    return false;
                }

                if (currentBody.trim() === '') {
                    window.alert(
                        'Pesan attachment-only tidak memiliki teks untuk diedit.'
                    );

                    return false;
                }

                const replacement =
                    window.prompt(
                        'Edit pesan:',
                        currentBody
                    );

                if (replacement === null) {
                    return false;
                }

                const body =
                    String(
                        replacement
                    ).trim();

                if (body === '') {
                    window.alert(
                        'Pesan tidak boleh kosong.'
                    );

                    return false;
                }

                button.disabled =
                    true;

                try {
                    const response =
                        await fetch(
                            window.crmChatMessageActionUrl(
                                messageId
                            ),
                            {
                                method:
                                    'PATCH',

                                headers: {
                                    'Accept':
                                        'application/json',

                                    'Content-Type':
                                        'application/json',

                                    'X-CSRF-TOKEN':
                                        window.crmChatCsrfToken(),

                                    'X-Requested-With':
                                        'XMLHttpRequest',
                                },

                                credentials:
                                    'same-origin',

                                body:
                                    JSON.stringify({
                                        body:
                                            body,
                                    }),
                            }
                        );

                    if (! response.ok) {
                        throw new Error(
                            await response.text()
                        );
                    }

                    const wrapper =
                        document.querySelector(
                            '[data-message-id="'
                            + messageId
                            + '"]'
                        );

                    if (wrapper) {
                        const textNode =
                            wrapper.querySelector(
                                '.whitespace-pre-wrap.break-words.text-sm'
                            );

                        if (textNode) {
                            textNode.textContent =
                                body;
                        }

                        const editButton =
                            wrapper.querySelector(
                                '[data-edit-message]'
                            );

                        if (editButton) {
                            editButton.dataset.editBody =
                                body;
                        }

                        const replyButton =
                            wrapper.querySelector(
                                '[data-reply-message]'
                            );

                        if (replyButton) {
                            replyButton.dataset.replyBody =
                                body;
                        }

                        if (
                            ! wrapper.querySelector(
                                '[data-message-edited-label]'
                            )
                        ) {
                            const receipt =
                                wrapper.querySelector(
                                    '[data-read-receipt]'
                                );

                            const meta =
                                receipt
                                    ? receipt.parentElement
                                    : null;

                            if (meta) {
                                const edited =
                                    document.createElement(
                                        'span'
                                    );

                                edited.dataset.messageEditedLabel =
                                    '1';

                                edited.className =
                                    'text-gray-400';

                                edited.textContent =
                                    '· edited';

                                if (receipt) {
                                    meta.insertBefore(
                                        edited,
                                        receipt
                                    );
                                } else {
                                    meta.appendChild(
                                        edited
                                    );
                                }
                            }
                        }
                    }
                } catch (error) {
                    console.error(
                        'Internal Chat edit failed:',
                        error
                    );

                    window.alert(
                        'Pesan gagal diedit. Lihat Console jika error berulang.'
                    );
                } finally {
                    button.disabled =
                        false;
                }

                return false;
            };

            window.crmChatDeleteAction = async function (button, event) {
                window.crmChatStopActionEvent(
                    event
                );

                const messageId =
                    Number(
                        button.dataset.deleteMessage
                        || 0
                    );

                if (messageId < 1) {
                    window.alert(
                        'Message ID tidak valid.'
                    );

                    return false;
                }

                if (
                    ! window.confirm(
                        'Hapus pesan ini? Pesan akan disembunyikan dari conversation tetapi tetap tersimpan untuk audit.'
                    )
                ) {
                    return false;
                }

                button.disabled =
                    true;

                try {
                    const response =
                        await fetch(
                            window.crmChatMessageActionUrl(
                                messageId
                            ),
                            {
                                method:
                                    'DELETE',

                                headers: {
                                    'Accept':
                                        'application/json',

                                    'X-CSRF-TOKEN':
                                        window.crmChatCsrfToken(),

                                    'X-Requested-With':
                                        'XMLHttpRequest',
                                },

                                credentials:
                                    'same-origin',
                            }
                        );

                    if (! response.ok) {
                        throw new Error(
                            await response.text()
                        );
                    }

                    const wrapper =
                        document.querySelector(
                            '[data-message-id="'
                            + messageId
                            + '"]'
                        );

                    if (wrapper) {
                        wrapper.remove();
                    }
                } catch (error) {
                    button.disabled =
                        false;

                    console.error(
                        'Internal Chat delete failed:',
                        error
                    );

                    window.alert(
                        'Pesan gagal dihapus. Lihat Console jika error berulang.'
                    );
                }

                return false;
            };
        </script>

        <script>
            (() => {
BLADE;

$source = str_replace(
    $insertAnchor,
    $globalHandlers,
    $source,
    $countHandlers
);

if ($countHandlers !== 1) {
    fwrite(
        STDERR,
        "Global handler insertion count salah: {$countHandlers}\n"
    );
    exit(11);
}

/*
|--------------------------------------------------------------------------
| 4. Polling-created buttons also get direct onclick properties
|--------------------------------------------------------------------------
*/

$dynamicHooks = [
    [
        'old' =>
<<<'JS'
                    replyButton.textContent =
                        'Reply';

                    actions.appendChild(
                        replyButton
                    );
JS,
        'new' =>
<<<'JS'
                    replyButton.textContent =
                        'Reply';

                    replyButton.onclick =
                        function (event) {
                            return window.crmChatReplyAction(
                                this,
                                event
                            );
                        };

                    actions.appendChild(
                        replyButton
                    );
JS,
        'label' =>
            'dynamic Reply',
    ],
    [
        'old' =>
<<<'JS'
                        editButton.textContent =
                            'Edit';

                        actions.appendChild(
                            editButton
                        );
JS,
        'new' =>
<<<'JS'
                        editButton.textContent =
                            'Edit';

                        editButton.onclick =
                            function (event) {
                                return window.crmChatEditAction(
                                    this,
                                    event
                                );
                            };

                        actions.appendChild(
                            editButton
                        );
JS,
        'label' =>
            'dynamic Edit',
    ],
    [
        'old' =>
<<<'JS'
                        deleteButton.textContent =
                            'Delete';

                        actions.appendChild(
                            deleteButton
                        );
JS,
        'new' =>
<<<'JS'
                        deleteButton.textContent =
                            'Delete';

                        deleteButton.onclick =
                            function (event) {
                                return window.crmChatDeleteAction(
                                    this,
                                    event
                                );
                            };

                        actions.appendChild(
                            deleteButton
                        );
JS,
        'label' =>
            'dynamic Delete',
    ],
];

foreach ($dynamicHooks as $hook) {
    if (! str_contains($source, $hook['old'])) {
        fwrite(
            STDERR,
            $hook['label']
            ." marker tidak ditemukan.\n"
        );
        exit(12);
    }

    $source = str_replace(
        $hook['old'],
        $hook['new'],
        $source,
        $count
    );

    if ($count !== 1) {
        fwrite(
            STDERR,
            $hook['label']
            ." replacement count salah: {$count}\n"
        );
        exit(13);
    }
}

if (
    file_put_contents(
        $target,
        $source
    ) === false
) {
    copy($backup, $target);

    fwrite(
        STDERR,
        "Gagal menulis chat Blade. Backup dipulihkan.\n"
    );

    exit(14);
}

$written =
    file_get_contents(
        $target
    );

$postMarkers = [
    'INTERNAL CHAT V3.1.4 HARD ACTION FALLBACK',
    'window.crmChatReplyAction',
    'window.crmChatEditAction',
    'window.crmChatDeleteAction',
    'onclick="return window.crmChatReplyAction(this, event);"',
    'onclick="return window.crmChatEditAction(this, event);"',
    'onclick="return window.crmChatDeleteAction(this, event);"',
    'data-action-base=',
    'crm-chat-attachments',
    'data-read-receipt',
    '5000',
];

foreach ($postMarkers as $marker) {
    if (
        $written === false
        || ! str_contains(
            $written,
            $marker
        )
    ) {
        copy(
            $backup,
            $target
        );

        fwrite(
            STDERR,
            "Post-write validation gagal: {$marker}\n"
            ."Backup dipulihkan otomatis.\n"
        );

        exit(15);
    }
}

echo "[PASS] Hard Reply handler installed.\n";
echo "[PASS] Hard Edit handler installed.\n";
echo "[PASS] Hard Delete handler installed.\n";
echo "[PASS] Server-rendered action buttons use explicit onclick.\n";
echo "[PASS] Polling-created action buttons use explicit onclick.\n";
echo "[PASS] Existing polling/read receipt/attachment UI preserved.\n";
echo "[PASS] No controller / route / migration changes.\n";
