<?php

/*
|--------------------------------------------------------------------------
| Internal Chat V3.1.3 - Action Buttons Hotfix
|--------------------------------------------------------------------------
|
| Fixes Reply / Edit / Delete buttons that are visible but do not react.
|
| V3.1 used one delegated click listener on #crm-chat-messages. On this CRM
| frontend build that delegated listener is not reliably receiving the action
| button clicks. V3.1.3 binds click listeners directly to the buttons and uses
| a MutationObserver so newly-polled messages get the same handlers.
|
| Scope: chat.blade.php ONLY.
| No controller / provider / route / migration / database changes.
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
        'INTERNAL CHAT V3.1.3 DIRECT ACTION BINDINGS'
    )
) {
    echo "[SKIP] V3.1.3 already installed.\n";
    exit(0);
}

$required = [
    'INTERNAL CHAT V3.1.2 SAFE ROUTE TEMPLATES',
    'data-reply-message',
    'data-edit-message',
    'data-delete-message',
    'const editUrlTemplate',
    'const deleteUrlTemplate',
    'const setReply',
    'const setEdit',
    'messagesRoot.addEventListener',
];

foreach ($required as $marker) {
    if (! str_contains($source, $marker)) {
        fwrite(
            STDERR,
            "Current V3.1.2 Blade tidak dikenali: {$marker}\n"
            ."Patch dihentikan agar tidak menimpa source yang berbeda.\n"
        );
        exit(4);
    }
}

$pattern = <<<'REGEX'
~                messagesRoot\.addEventListener\(\n                    'click',\n                    async \(event\) => \{.*?\n                    \}\n                \);~s
REGEX;

$replacement = <<<'JS'
                /* INTERNAL CHAT V3.1.3 DIRECT ACTION BINDINGS */
                const deleteMessageFromButton =
                    async (deleteButton) => {
                        const messageId =
                            Number(
                                deleteButton.dataset.deleteMessage
                                || 0
                            );

                        if (
                            messageId < 1
                            || ! window.confirm(
                                'Hapus pesan ini? Pesan akan disembunyikan dari conversation tetapi tetap tersimpan untuk audit.'
                            )
                        ) {
                            return;
                        }

                        deleteButton.disabled =
                            true;

                        try {
                            const response =
                                await fetch(
                                    messageUrl(
                                        deleteUrlTemplate,
                                        messageId
                                    ),
                                    {
                                        method:
                                            'DELETE',

                                        headers: {
                                            'Accept':
                                                'application/json',

                                            'X-CSRF-TOKEN':
                                                csrfToken,

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
                                    `[data-message-id="${messageId}"]`
                                );

                            if (wrapper) {
                                wrapper.remove();
                            }

                            if (
                                editingMessageId
                                === messageId
                            ) {
                                clearEdit();
                            }

                            showFeedback(
                                'Pesan dihapus.',
                                'success'
                            );
                        } catch (error) {
                            deleteButton.disabled =
                                false;

                            showFeedback(
                                'Pesan gagal dihapus.',
                                'error'
                            );
                        }
                    };

                const bindMessageActionButtons =
                    (rootNode) => {
                        if (! rootNode) {
                            return;
                        }

                        rootNode
                            .querySelectorAll(
                                '[data-reply-message]'
                            )
                            .forEach(
                                (button) => {
                                    if (
                                        button.dataset.crmReplyBound
                                        === '1'
                                    ) {
                                        return;
                                    }

                                    button.dataset.crmReplyBound =
                                        '1';

                                    button.addEventListener(
                                        'click',
                                        (event) => {
                                            event.preventDefault();
                                            event.stopPropagation();

                                            setReply(
                                                button.dataset.replyMessage,
                                                button.dataset.replySender,
                                                button.dataset.replyBody
                                            );
                                        }
                                    );
                                }
                            );

                        rootNode
                            .querySelectorAll(
                                '[data-edit-message]'
                            )
                            .forEach(
                                (button) => {
                                    if (
                                        button.dataset.crmEditBound
                                        === '1'
                                    ) {
                                        return;
                                    }

                                    button.dataset.crmEditBound =
                                        '1';

                                    button.addEventListener(
                                        'click',
                                        (event) => {
                                            event.preventDefault();
                                            event.stopPropagation();

                                            setEdit(
                                                button.dataset.editMessage,
                                                button.dataset.editBody
                                            );
                                        }
                                    );
                                }
                            );

                        rootNode
                            .querySelectorAll(
                                '[data-delete-message]'
                            )
                            .forEach(
                                (button) => {
                                    if (
                                        button.dataset.crmDeleteBound
                                        === '1'
                                    ) {
                                        return;
                                    }

                                    button.dataset.crmDeleteBound =
                                        '1';

                                    button.addEventListener(
                                        'click',
                                        async (event) => {
                                            event.preventDefault();
                                            event.stopPropagation();

                                            await deleteMessageFromButton(
                                                button
                                            );
                                        }
                                    );
                                }
                            );
                    };

                bindMessageActionButtons(
                    messagesRoot
                );

                const messageActionObserver =
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

                                            bindMessageActionButtons(
                                                node
                                            );
                                        }
                                    );
                                }
                            );
                        }
                    );

                messageActionObserver.observe(
                    messagesRoot,
                    {
                        childList: true,
                        subtree: true,
                    }
                );
JS;

$patched = preg_replace(
    $pattern,
    $replacement,
    $source,
    1,
    $count
);

if (
    $patched === null
    || $count !== 1
) {
    fwrite(
        STDERR,
        "Delegated action block tidak ditemukan secara tepat. Replacement count: {$count}\n"
    );
    exit(5);
}

$backup =
    $target
    .'.before-internal-chat-v3-1-3-action-buttons-hotfix.bak';

if (! is_file($backup)) {
    if (! copy($target, $backup)) {
        fwrite(STDERR, "Gagal membuat backup chat Blade.\n");
        exit(6);
    }
}

if (
    file_put_contents(
        $target,
        $patched
    ) === false
) {
    fwrite(STDERR, "Gagal menulis chat Blade.\n");
    exit(7);
}

$written = file_get_contents($target);

$postChecks = [
    'INTERNAL CHAT V3.1.3 DIRECT ACTION BINDINGS',
    'bindMessageActionButtons',
    'deleteMessageFromButton',
    'new MutationObserver',
    'button.dataset.crmReplyBound',
    'button.dataset.crmEditBound',
    'button.dataset.crmDeleteBound',
    'data-read-receipt',
    'crm-chat-send-form',
    'crm-chat-attachments',
    '5000',
];

foreach ($postChecks as $marker) {
    if (
        $written === false
        || ! str_contains($written, $marker)
    ) {
        copy($backup, $target);

        fwrite(
            STDERR,
            "Post-write validation gagal: {$marker}\n"
            ."Backup dipulihkan otomatis.\n"
        );
        exit(8);
    }
}

echo "[PASS] Reply button direct binding installed.\n";
echo "[PASS] Edit button direct binding installed.\n";
echo "[PASS] Delete button direct binding installed.\n";
echo "[PASS] New polled messages auto-bind through MutationObserver.\n";
echo "[PASS] Reply/Edit/Delete backend routes unchanged.\n";
echo "[PASS] Read receipts, attachments, send, polling preserved.\n";
echo "[PASS] No migration required.\n";
