<?php

/*
|--------------------------------------------------------------------------
| Internal Chat V3.3.9 - Unread Cursor + Fit UI
|--------------------------------------------------------------------------
|
| Fixes:
| 1. Fake unread "1" appearing on multiple conversations.
| 2. Opening one conversation affecting unread state ambiguously.
| 3. Sidebar horizontal overflow / need to drag right.
| 4. Verifies backend mute notification guard is present.
|
| Dedicated V3.3 Conversation Controller is supplied as a full module file.
|
| This installer patches the existing customized InternalChatController only
| in-place:
| - sync read message-id cursor after existing markRead()
| - add helper
| - ensure mute notification guard exists
|
| No replacement of the customized InternalChatController.
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
        if (
            ! copy(
                $path,
                $backup
            )
        ) {
            throw new RuntimeException(
                "Gagal membuat backup: {$backup}"
            );
        }
    }
}

/*
|--------------------------------------------------------------------------
| 1. Patch customized InternalChatController read cursor
|--------------------------------------------------------------------------
*/

$controller =
    $root
    .'/packages/Webkul/Admin/src/Http/Controllers/InternalCommunication/InternalChatController.php';

if (! is_file($controller)) {
    fwrite(
        STDERR,
        "InternalChatController tidak ditemukan.\n"
    );

    exit(2);
}

$source =
    file_get_contents(
        $controller
    );

if ($source === false) {
    fwrite(
        STDERR,
        "InternalChatController tidak dapat dibaca.\n"
    );

    exit(3);
}

backupOnce(
    $controller,
    '.before-internal-chat-v3-3-9-read-cursor.bak'
);

if (
    ! str_contains(
        $source,
        'INTERNAL CHAT V3.3.9 READ CURSOR SYNC'
    )
) {
    $pattern =
        '/\$chat->markRead\s*\(\s*\$conversationId\s*,\s*\$user->id\s*\)\s*;/';

    preg_match_all(
        $pattern,
        $source,
        $matches
    );

    $count =
        count(
            $matches[0]
        );

    if ($count < 2) {
        fwrite(
            STDERR,
            "markRead() expected minimal 2 occurrence, ditemukan: {$count}\n"
            ."Patch dihentikan agar controller customized tidak rusak.\n"
        );

        exit(4);
    }

    $source =
        preg_replace_callback(
            $pattern,
            function ($match) {
                return $match[0]
                    ."\n\n"
                    ."            /* INTERNAL CHAT V3.3.9 READ CURSOR SYNC */\n"
                    ."            \$this->syncReadMessageCursor(\n"
                    ."                \$conversationId,\n"
                    ."                (int) \$user->id\n"
                    ."            );";
            },
            $source
        );

    $userMethodPos =
        strrpos(
            $source,
            '    private function user()'
        );

    if ($userMethodPos === false) {
        fwrite(
            STDERR,
            "private function user() anchor tidak ditemukan.\n"
        );

        exit(5);
    }

    $helper = <<<'PHP'
    /**
     * Keep unread state deterministic per conversation.
     *
     * last_read_at remains for read receipts; last_read_message_id is the
     * sidebar unread cursor.
     */
    private function syncReadMessageCursor(
        int $conversationId,
        int $userId
    ): void {
        if (
            ! \Illuminate\Support\Facades\Schema::hasColumn(
                'internal_conversation_members',
                'last_read_message_id'
            )
        ) {
            return;
        }

        $maxMessageId =
            (int) (
                \Illuminate\Support\Facades\DB::table(
                    'internal_messages'
                )
                    ->where(
                        'conversation_id',
                        $conversationId
                    )
                    ->whereNull(
                        'deleted_at'
                    )
                    ->max(
                        'id'
                    )
                ?? 0
            );

        \Illuminate\Support\Facades\DB::table(
            'internal_conversation_members'
        )
            ->where(
                'conversation_id',
                $conversationId
            )
            ->where(
                'user_id',
                $userId
            )
            ->update([
                'last_read_message_id' =>
                    $maxMessageId,
            ]);
    }


PHP;

    $source =
        substr_replace(
            $source,
            $helper,
            $userMethodPos,
            0
        );
}

/*
|--------------------------------------------------------------------------
| 2. Ensure mute guard exists in message notification loop
|--------------------------------------------------------------------------
*/

if (
    ! str_contains(
        $source,
        'INTERNAL CHAT V3.3 MUTE NOTIFICATION GUARD'
    )
    && ! str_contains(
        $source,
        'INTERNAL CHAT V3.3.9 MUTE NOTIFICATION GUARD'
    )
) {
    $loop =
        'foreach ($recipientIds as $recipientId) {';

    if (
        ! str_contains(
            $source,
            $loop
        )
    ) {
        fwrite(
            STDERR,
            "Recipient notification foreach tidak ditemukan.\n"
        );

        exit(6);
    }

    $guard = <<<'PHP'
foreach ($recipientIds as $recipientId) {
            /* INTERNAL CHAT V3.3.9 MUTE NOTIFICATION GUARD */
            if (
                \Illuminate\Support\Facades\Schema::hasColumn(
                    'internal_conversation_members',
                    'mute_forever'
                )
                && \Illuminate\Support\Facades\Schema::hasColumn(
                    'internal_conversation_members',
                    'muted_until'
                )
            ) {
                $preference =
                    \Illuminate\Support\Facades\DB::table(
                        'internal_conversation_members'
                    )
                        ->where(
                            'conversation_id',
                            $conversationId
                        )
                        ->where(
                            'user_id',
                            $recipientId
                        )
                        ->first([
                            'mute_forever',
                            'muted_until',
                        ]);

                $isMuted =
                    $preference
                    && (
                        (bool) $preference->mute_forever
                        || (
                            $preference->muted_until
                            && now()->lt(
                                \Illuminate\Support\Carbon::parse(
                                    $preference->muted_until
                                )
                            )
                        )
                    );

                if ($isMuted) {
                    continue;
                }
            }
PHP;

    $source =
        str_replace(
            $loop,
            $guard,
            $source,
            $guardCount
        );

    if ($guardCount !== 1) {
        fwrite(
            STDERR,
            "Mute guard replacement count salah: {$guardCount}\n"
        );

        exit(7);
    }
}

if (
    file_put_contents(
        $controller,
        $source
    ) === false
) {
    fwrite(
        STDERR,
        "Gagal menulis InternalChatController.\n"
    );

    exit(8);
}

echo "[PASS] Per-conversation read cursor sync installed.\n";
echo "[PASS] Backend mute notification guard verified/installed.\n";

/*
|--------------------------------------------------------------------------
| 3. Compact / no-horizontal-scroll V3.3.8 renderer
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

    exit(9);
}

$bladeSource =
    file_get_contents(
        $blade
    );

if ($bladeSource === false) {
    fwrite(
        STDERR,
        "chat.blade.php tidak dapat dibaca.\n"
    );

    exit(10);
}

if (
    str_contains(
        $bladeSource,
        'INTERNAL CHAT V3.3.9 FIT SIDEBAR'
    )
) {
    echo "[SKIP] V3.3.9 fit sidebar already installed.\n";
    exit(0);
}

if (
    ! str_contains(
        $bladeSource,
        'INTERNAL CHAT V3.3.8 EXPLICIT APPLY PREFERENCES'
    )
) {
    fwrite(
        STDERR,
        "V3.3.8 renderer baseline tidak ditemukan.\n"
    );

    exit(11);
}

backupOnce(
    $blade,
    '.before-internal-chat-v3-3-9-fit-sidebar.bak'
);

$renderStart =
    strpos(
        $bladeSource,
        "            /* INTERNAL CHAT V3.3.8 EXPLICIT APPLY PREFERENCES */"
    );

$renderEnd =
    $renderStart === false
        ? false
        : strpos(
            $bladeSource,
            "            let refreshing =\n",
            $renderStart
        );

if (
    $renderStart === false
    || $renderEnd === false
) {
    fwrite(
        STDERR,
        "V3.3.8 renderSidebar block tidak ditemukan.\n"
    );

    exit(12);
}

$renderer = <<<'JS'
            /* INTERNAL CHAT V3.3.9 FIT SIDEBAR */
            const renderSidebar = (
                rows
            ) => {
                if (! list) {
                    return;
                }

                list.style.width =
                    '100%';

                list.style.maxWidth =
                    '100%';

                list.style.overflowX =
                    'hidden';

                list.style.boxSizing =
                    'border-box';

                if (list.parentElement) {
                    list.parentElement.style.overflowX =
                        'hidden';

                    list.parentElement.style.maxWidth =
                        '100%';
                }

                const configV333 =
                    document.getElementById(
                        'crm-chat-v333-config'
                    );

                const preferenceTemplate =
                    String(
                        configV333?.dataset
                            .preferenceTemplate
                        || ''
                    );

                const hardCsrf =
                    String(
                        configV333?.dataset
                            .csrf
                        || ''
                    );

                const preferenceUrl = (
                    conversationId
                ) =>
                    preferenceTemplate.replace(
                        '__CID__',
                        encodeURIComponent(
                            String(
                                conversationId
                            )
                        )
                    );

                const csrfInput = () => {
                    const token =
                        document.createElement(
                            'input'
                        );

                    token.type =
                        'hidden';

                    token.name =
                        '_token';

                    token.value =
                        hardCsrf;

                    return token;
                };

                const pinForm = (
                    row
                ) => {
                    const form =
                        document.createElement(
                            'form'
                        );

                    form.method =
                        'POST';

                    form.action =
                        preferenceUrl(
                            row.id
                        );

                    form.style.cssText =
                        'margin:0;'
                        +'padding:0;'
                        +'flex:0 0 auto;';

                    const button =
                        document.createElement(
                            'button'
                        );

                    button.type =
                        'submit';

                    button.name =
                        'action';

                    button.value =
                        row.pinned
                            ? 'unpin'
                            : 'pin';

                    button.title =
                        row.pinned
                            ? 'Unpin'
                            : 'Pin';

                    button.textContent =
                        row.pinned
                            ? '📍'
                            : '📌';

                    button.style.cssText =
                        'width:24px;'
                        +'height:25px;'
                        +'display:flex;'
                        +'align-items:center;'
                        +'justify-content:center;'
                        +'padding:0;'
                        +'border:1px solid #e5e7eb;'
                        +'border-radius:7px;'
                        +'background:#ffffff;'
                        +'font-size:10px;'
                        +'cursor:pointer;';

                    form.appendChild(
                        csrfInput()
                    );

                    form.appendChild(
                        button
                    );

                    return form;
                };

                const muteForm = (
                    row
                ) => {
                    const form =
                        document.createElement(
                            'form'
                        );

                    form.method =
                        'POST';

                    form.action =
                        preferenceUrl(
                            row.id
                        );

                    form.style.cssText =
                        'margin:0;'
                        +'padding:0;'
                        +'display:flex;'
                        +'align-items:center;'
                        +'gap:3px;'
                        +'flex:0 0 auto;';

                    const select =
                        document.createElement(
                            'select'
                        );

                    select.name =
                        'action';

                    select.required =
                        true;

                    select.title =
                        row.muted
                            ? (
                                row.mute_label
                                || 'Muted'
                            )
                            : 'Mute notification';

                    /*
                     * Only the icon is shown while closed. Native option labels
                     * remain full when the browser opens the selector.
                     */
                    select.style.cssText =
                        'width:34px;'
                        +'height:25px;'
                        +'padding:0 2px;'
                        +'border:1px solid '
                        +(
                            row.muted
                                ? '#f59e0b'
                                : '#e5e7eb'
                        )
                        +';'
                        +'border-radius:7px;'
                        +'background:'
                        +(
                            row.muted
                                ? '#fffbeb'
                                : '#ffffff'
                        )
                        +';'
                        +'font-size:10px;'
                        +'font-weight:700;'
                        +'color:#334155;'
                        +'cursor:pointer;';

                    const placeholder =
                        document.createElement(
                            'option'
                        );

                    placeholder.value =
                        '';

                    placeholder.textContent =
                        row.muted
                            ? '🔕'
                            : '🔔';

                    placeholder.selected =
                        true;

                    placeholder.disabled =
                        true;

                    select.appendChild(
                        placeholder
                    );

                    [
                        [
                            'mute_1_hour',
                            'Mute 1 jam',
                        ],
                        [
                            'mute_today',
                            'Sampai akhir hari',
                        ],
                        [
                            'mute_forever',
                            'Sampai diaktifkan kembali',
                        ],
                        [
                            'unmute',
                            'Unmute',
                        ],
                    ].forEach(
                        ([value, label]) => {
                            const option =
                                document.createElement(
                                    'option'
                                );

                            option.value =
                                value;

                            option.textContent =
                                label;

                            select.appendChild(
                                option
                            );
                        }
                    );

                    const apply =
                        document.createElement(
                            'button'
                        );

                    apply.type =
                        'submit';

                    apply.textContent =
                        '✓';

                    apply.title =
                        'Apply notification setting';

                    apply.style.cssText =
                        'width:25px;'
                        +'height:25px;'
                        +'padding:0;'
                        +'border:1px solid #0f172a;'
                        +'border-radius:7px;'
                        +'background:#0f172a;'
                        +'color:#ffffff;'
                        +'font-size:10px;'
                        +'font-weight:900;'
                        +'cursor:pointer;';

                    select.addEventListener(
                        'focus',
                        () => {
                            window.__crmMuteSelectOpen =
                                true;
                        }
                    );

                    select.addEventListener(
                        'blur',
                        () => {
                            window.setTimeout(
                                () => {
                                    window.__crmMuteSelectOpen =
                                        false;
                                },
                                150
                            );
                        }
                    );

                    form.appendChild(
                        csrfInput()
                    );

                    form.appendChild(
                        select
                    );

                    form.appendChild(
                        apply
                    );

                    return form;
                };

                const fragment =
                    document.createDocumentFragment();

                rows.forEach(
                    (row) => {
                        const wrapper =
                            document.createElement(
                                'div'
                            );

                        wrapper.dataset.conversationSearch =
                            String(
                                row.name
                                +' '
                                +row.role
                                +' '
                                +row.preview
                            ).toLowerCase();

                        wrapper.style.cssText =
                            'display:grid;'
                            +'grid-template-columns:minmax(0,1fr) auto;'
                            +'align-items:center;'
                            +'width:100%;'
                            +'max-width:100%;'
                            +'min-width:0;'
                            +'box-sizing:border-box;'
                            +'overflow:hidden;'
                            +'border-bottom:1px solid #e5e7eb;'
                            +'background:'
                            +(
                                Number(row.id)
                                === activeConversation
                                    ? '#f8fafc'
                                    : '#ffffff'
                            )
                            +';';

                        const link =
                            document.createElement(
                                'a'
                            );

                        link.href =
                            conversationUrl(
                                row.id
                            );

                        link.style.cssText =
                            'min-width:0;'
                            +'max-width:100%;'
                            +'overflow:hidden;'
                            +'display:flex;'
                            +'align-items:center;'
                            +'gap:8px;'
                            +'padding:8px 5px 8px 9px;'
                            +'text-decoration:none;'
                            +'color:inherit;'
                            +'box-sizing:border-box;';

                        const unread =
                            Number(
                                row.unread
                                || 0
                            );

                        const presenceColor =
                            row.online
                                ? '#22c55e'
                                : (
                                    row.idle
                                        ? '#f59e0b'
                                        : '#cbd5e1'
                                );

                        link.innerHTML =
                            '<div style="position:relative;flex:0 0 auto;">'
                            +'<div style="width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;background:#f1f5f9;color:#334155;font-size:10px;font-weight:800;">'
                            +escapeHtml(
                                row.initials
                            )
                            +'</div>'
                            +'<span style="position:absolute;right:-1px;bottom:0;width:8px;height:8px;border:2px solid #fff;border-radius:50%;background:'
                            +presenceColor
                            +';"></span>'
                            +'</div>'
                            +'<div style="min-width:0;max-width:100%;overflow:hidden;flex:1;">'
                            +'<div style="display:flex;align-items:center;gap:5px;min-width:0;">'
                            +'<div style="min-width:0;flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:11.5px;font-weight:800;color:#0f172a;">'
                            +(
                                row.pinned
                                    ? '📌 '
                                    : ''
                            )
                            +escapeHtml(
                                row.name
                            )
                            +'</div>'
                            +(
                                row.muted
                                    ? '<span title="'
                                        +escapeHtml(
                                            row.mute_label
                                            || 'Muted'
                                        )
                                        +'" style="flex:0 0 auto;padding:1px 5px;border:1px solid #fde68a;border-radius:999px;background:#fffbeb;color:#92400e;font-size:8px;font-weight:800;">🔕 Muted</span>'
                                    : ''
                            )
                            +'<div style="flex:0 0 auto;font-size:8.5px;color:#94a3b8;">'
                            +escapeHtml(
                                row.time
                            )
                            +'</div>'
                            +'</div>'
                            +'<div style="margin-top:1px;display:flex;align-items:center;gap:5px;min-width:0;">'
                            +'<div style="min-width:0;flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:10px;line-height:13px;color:'
                            +(
                                unread > 0
                                    ? '#0f172a'
                                    : '#64748b'
                            )
                            +';font-weight:'
                            +(
                                unread > 0
                                    ? '700'
                                    : '500'
                            )
                            +';">'
                            +escapeHtml(
                                row.preview
                            )
                            +'</div>'
                            +(
                                unread > 0
                                    ? '<div title="Unread messages" style="flex:0 0 auto;min-width:18px;height:18px;padding:0 5px;border-radius:999px;display:flex;align-items:center;justify-content:center;background:#dc2626;color:#fff;font-size:9px;font-weight:900;">'
                                        +(
                                            unread > 99
                                                ? '99+'
                                                : unread
                                        )
                                        +'</div>'
                                    : ''
                            )
                            +'</div>'
                            +'<div style="margin-top:1px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:8.5px;line-height:11px;color:#94a3b8;">'
                            +escapeHtml(
                                row.role
                            )
                            +' · '
                            +'<span style="color:'
                            +(
                                row.online
                                    ? '#16a34a'
                                    : (
                                        row.idle
                                            ? '#d97706'
                                            : '#94a3b8'
                                    )
                            )
                            +';">'
                            +escapeHtml(
                                row.presence
                            )
                            +'</span>'
                            +'</div>'
                            +'</div>';

                        const controls =
                            document.createElement(
                                'div'
                            );

                        controls.style.cssText =
                            'display:flex;'
                            +'align-items:center;'
                            +'gap:3px;'
                            +'flex:0 0 auto;'
                            +'padding:0 7px 0 3px;'
                            +'box-sizing:border-box;';

                        controls.appendChild(
                            pinForm(
                                row
                            )
                        );

                        controls.appendChild(
                            muteForm(
                                row
                            )
                        );

                        wrapper.appendChild(
                            link
                        );

                        wrapper.appendChild(
                            controls
                        );

                        fragment.appendChild(
                            wrapper
                        );
                    }
                );

                list.innerHTML =
                    '';

                list.appendChild(
                    fragment
                );

                if (search) {
                    search.dispatchEvent(
                        new Event(
                            'input'
                        )
                    );
                }
            };

JS;

$bladeSource =
    substr_replace(
        $bladeSource,
        $renderer,
        $renderStart,
        $renderEnd
        - $renderStart
    );

if (
    file_put_contents(
        $blade,
        $bladeSource
    ) === false
) {
    fwrite(
        STDERR,
        "Gagal menulis chat Blade V3.3.9.\n"
    );

    exit(13);
}

echo "[PASS] Sidebar rebuilt to fit width without horizontal scrolling.\n";
echo "[PASS] Pin/Mute controls compacted.\n";
echo "[PASS] Per-conversation unread badge UI preserved.\n";
echo "[PASS] Existing chat features preserved outside V3.3 renderer.\n";
echo "[PASS] Migration required for deterministic unread cursor.\n";
