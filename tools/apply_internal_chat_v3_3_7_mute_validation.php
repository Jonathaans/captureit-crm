<?php

/*
|--------------------------------------------------------------------------
| Internal Chat V3.3.7 - Mute State Validation
|--------------------------------------------------------------------------
|
| V3.3.6 made mute reliable by using a native <select> + HTML POST.
| This hotfix makes the resulting state obvious to the operator:
|
| - explicit "Muted" badge on each muted conversation
| - selector text changes from "Mute" to "Muted"
| - mute-until label is shown
| - confirmation before changing mute state
| - success toast after Laravel redirect
|
| No migration / route / provider changes.
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
| 1. Controller: action-specific redirect notice
|--------------------------------------------------------------------------
*/

$controller =
    $root
    .'/packages/Webkul/Admin/src/Http/Controllers/InternalCommunication/InternalChatConversationController.php';

if (! is_file($controller)) {
    fwrite(
        STDERR,
        "InternalChatConversationController tidak ditemukan.\n"
    );

    exit(2);
}

$controllerSource =
    file_get_contents(
        $controller
    );

if ($controllerSource === false) {
    fwrite(
        STDERR,
        "Conversation controller tidak dapat dibaca.\n"
    );

    exit(3);
}

backupOnce(
    $controller,
    '.before-internal-chat-v3-3-7-mute-validation.bak'
);

if (
    ! str_contains(
        $controllerSource,
        'INTERNAL CHAT V3.3.7 PREFERENCE NOTICE'
    )
) {
    $oldRedirect = <<<'PHP'
        return redirect()
            ->back()
            ->with(
                'success',
                'Conversation preference diperbarui.'
            );
PHP;

    $newRedirect = <<<'PHP'
        /* INTERNAL CHAT V3.3.7 PREFERENCE NOTICE */
        $notice =
            match ($action) {
                'pin' =>
                    'Conversation berhasil dipin.',

                'unpin' =>
                    'Pin conversation berhasil dilepas.',

                'mute_1_hour' =>
                    'Conversation dimute selama 1 jam.',

                'mute_today' =>
                    'Conversation dimute sampai akhir hari.',

                'mute_forever' =>
                    'Conversation dimute sampai diaktifkan kembali.',

                'unmute' =>
                    'Mute conversation berhasil dinonaktifkan.',

                default =>
                    'Conversation preference diperbarui.',
            };

        return redirect()
            ->back()
            ->with(
                'internal_chat_preference_notice',
                $notice
            );
PHP;

    if (
        ! str_contains(
            $controllerSource,
            $oldRedirect
        )
    ) {
        fwrite(
            STDERR,
            "Redirect preference baseline tidak ditemukan.\n"
        );

        exit(4);
    }

    $controllerSource =
        str_replace(
            $oldRedirect,
            $newRedirect,
            $controllerSource,
            $redirectCount
        );

    if ($redirectCount !== 1) {
        fwrite(
            STDERR,
            "Redirect replacement count salah: {$redirectCount}\n"
        );

        exit(5);
    }
}

if (
    file_put_contents(
        $controller,
        $controllerSource
    ) === false
) {
    fwrite(
        STDERR,
        "Gagal menulis Conversation Controller.\n"
    );

    exit(6);
}

echo "[PASS] Preference success notice installed.\n";

/*
|--------------------------------------------------------------------------
| 2. Blade: replace V3.3.6 renderer
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

if (
    str_contains(
        $source,
        'INTERNAL CHAT V3.3.7 MUTE STATE VALIDATION'
    )
) {
    echo "[SKIP] Internal Chat V3.3.7 already installed.\n";

    exit(0);
}

$required = [
    'INTERNAL CHAT V3.3.6 NATIVE MUTE SELECT',
    'nativeMuteSelect',
    'nativePreferenceForm',
    'window.__crmMuteSelectOpen',
    'let refreshing =',
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
            "Current V3.3.6 Blade tidak dikenali: {$marker}\n"
            ."Patch dihentikan agar file customized tidak rusak.\n"
        );

        exit(9);
    }
}

backupOnce(
    $blade,
    '.before-internal-chat-v3-3-7-mute-state-validation.bak'
);

$renderStart =
    strpos(
        $source,
        "            /* INTERNAL CHAT V3.3.6 NATIVE MUTE SELECT */"
    );

$renderEnd =
    $renderStart === false
        ? false
        : strpos(
            $source,
            "            let refreshing =\n",
            $renderStart
        );

if (
    $renderStart === false
    || $renderEnd === false
) {
    fwrite(
        STDERR,
        "V3.3.6 renderSidebar block tidak ditemukan.\n"
    );

    exit(10);
}

$renderer = <<<'JS'
            /* INTERNAL CHAT V3.3.7 MUTE STATE VALIDATION */
            const renderSidebar = (
                rows
            ) => {
                if (! list) {
                    return;
                }

                const preferenceTemplate =
                    String(
                        document
                            .getElementById(
                                'crm-chat-v333-config'
                            )
                            ?.dataset
                            .preferenceTemplate
                        || ''
                    );

                const hardCsrf =
                    String(
                        document
                            .getElementById(
                                'crm-chat-v333-config'
                            )
                            ?.dataset
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

                const nativePreferenceForm = (
                    conversationId,
                    action,
                    label,
                    title
                ) => {
                    const form =
                        document.createElement(
                            'form'
                        );

                    form.method =
                        'POST';

                    form.action =
                        preferenceUrl(
                            conversationId
                        );

                    form.style.margin =
                        '0';

                    form.style.padding =
                        '0';

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

                    const actionInput =
                        document.createElement(
                            'input'
                        );

                    actionInput.type =
                        'hidden';

                    actionInput.name =
                        'action';

                    actionInput.value =
                        action;

                    const button =
                        document.createElement(
                            'button'
                        );

                    button.type =
                        'submit';

                    button.title =
                        title;

                    button.textContent =
                        label;

                    button.style.cssText =
                        'width:26px;'
                        +'height:26px;'
                        +'display:flex;'
                        +'align-items:center;'
                        +'justify-content:center;'
                        +'padding:0;'
                        +'border:1px solid #e5e7eb;'
                        +'border-radius:8px;'
                        +'background:#ffffff;'
                        +'font-size:11px;'
                        +'cursor:pointer;';

                    form.appendChild(
                        token
                    );

                    form.appendChild(
                        actionInput
                    );

                    form.appendChild(
                        button
                    );

                    return form;
                };

                const nativeMuteSelect = (
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

                    form.style.margin =
                        '0';

                    form.style.padding =
                        '0';

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

                    const select =
                        document.createElement(
                            'select'
                        );

                    select.name =
                        'action';

                    select.title =
                        row.muted
                            ? (
                                row.mute_label
                                || 'Muted'
                            )
                            : 'Mute conversation';

                    select.setAttribute(
                        'aria-label',
                        'Notification settings for '
                        + String(
                            row.name
                            || 'conversation'
                        )
                    );

                    select.style.cssText =
                        'width:'
                        +(
                            row.muted
                                ? '82px'
                                : '66px'
                        )
                        +';'
                        +'height:27px;'
                        +'padding:0 4px;'
                        +'border:1px solid '
                        +(
                            row.muted
                                ? '#f59e0b'
                                : '#e5e7eb'
                        )
                        +';'
                        +'border-radius:8px;'
                        +'background:'
                        +(
                            row.muted
                                ? '#fffbeb'
                                : '#ffffff'
                        )
                        +';'
                        +'font-size:10px;'
                        +'font-weight:700;'
                        +'cursor:pointer;'
                        +'color:'
                        +(
                            row.muted
                                ? '#92400e'
                                : '#334155'
                        )
                        +';';

                    const placeholder =
                        document.createElement(
                            'option'
                        );

                    placeholder.value =
                        '';

                    placeholder.textContent =
                        row.muted
                            ? '🔕 Muted'
                            : '🔔 Mute';

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
                            '🔕 Mute 1 jam',
                        ],
                        [
                            'mute_today',
                            '🔕 Sampai akhir hari',
                        ],
                        [
                            'mute_forever',
                            '🔕 Sampai diaktifkan kembali',
                        ],
                        [
                            'unmute',
                            '🔔 Unmute',
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

                    select.addEventListener(
                        'change',
                        () => {
                            const action =
                                select.value;

                            if (! action) {
                                return;
                            }

                            const confirmation =
                                {
                                    mute_1_hour:
                                        'Mute '
                                        +row.name
                                        +' selama 1 jam?',

                                    mute_today:
                                        'Mute '
                                        +row.name
                                        +' sampai akhir hari?',

                                    mute_forever:
                                        'Mute '
                                        +row.name
                                        +' sampai diaktifkan kembali?',

                                    unmute:
                                        'Aktifkan kembali notifikasi '
                                        +row.name
                                        +'?',
                                }[action]
                                || 'Ubah notification setting?';

                            if (
                                ! window.confirm(
                                    confirmation
                                )
                            ) {
                                select.value =
                                    '';

                                return;
                            }

                            form.submit();
                        }
                    );

                    form.appendChild(
                        token
                    );

                    form.appendChild(
                        select
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
                            'display:flex;'
                            +'align-items:stretch;'
                            +'min-height:62px;'
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
                            +'flex:1 1 auto;'
                            +'display:flex;'
                            +'align-items:center;'
                            +'gap:9px;'
                            +'padding:8px 6px 8px 10px;'
                            +'text-decoration:none;'
                            +'color:inherit;';

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
                            +'<div style="width:34px;height:34px;border-radius:50%;display:flex;align-items:center;justify-content:center;background:#f1f5f9;color:#334155;font-size:10px;font-weight:800;">'
                            +escapeHtml(
                                row.initials
                            )
                            +'</div>'
                            +'<span style="position:absolute;right:-1px;bottom:0;width:9px;height:9px;border:2px solid #fff;border-radius:50%;background:'
                            +presenceColor
                            +';"></span>'
                            +'</div>'
                            +'<div style="min-width:0;flex:1;">'
                            +'<div style="display:flex;align-items:center;justify-content:space-between;gap:7px;">'
                            +'<div style="min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:12px;font-weight:800;color:#0f172a;">'
                            +(
                                row.pinned
                                    ? '📌 '
                                    : ''
                            )
                            +escapeHtml(
                                row.name
                            )
                            +'</div>'
                            +'<div style="flex:0 0 auto;font-size:9px;color:#94a3b8;">'
                            +escapeHtml(
                                row.time
                            )
                            +'</div>'
                            +'</div>'
                            +'<div style="margin-top:1px;display:flex;align-items:center;gap:6px;">'
                            +'<div style="min-width:0;flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:10.5px;line-height:14px;color:'
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
                                    ? '<div style="flex:0 0 auto;min-width:18px;height:18px;padding:0 5px;border-radius:999px;display:flex;align-items:center;justify-content:center;background:#0f172a;color:#fff;font-size:9px;font-weight:800;">'
                                        +(
                                            unread > 99
                                                ? '99+'
                                                : unread
                                        )
                                        +'</div>'
                                    : ''
                            )
                            +'</div>'
                            +'<div style="margin-top:1px;display:flex;align-items:center;gap:5px;overflow:hidden;white-space:nowrap;font-size:9px;line-height:12px;color:#94a3b8;">'
                            +'<span style="overflow:hidden;text-overflow:ellipsis;">'
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
                            +'</span>'
                            +(
                                row.muted
                                    ? '<span title="'
                                        +escapeHtml(
                                            row.mute_label
                                            || 'Muted'
                                        )
                                        +'" style="flex:0 0 auto;padding:1px 6px;border:1px solid #fde68a;border-radius:999px;background:#fffbeb;color:#92400e;font-size:8.5px;font-weight:800;">🔕 '
                                        +escapeHtml(
                                            row.mute_label
                                            || 'Muted'
                                        )
                                        +'</span>'
                                    : ''
                            )
                            +'</div>'
                            +'</div>';

                        const controls =
                            document.createElement(
                                'div'
                            );

                        controls.style.cssText =
                            'flex:0 0 auto;'
                            +'display:flex;'
                            +'align-items:flex-start;'
                            +'gap:4px;'
                            +'padding:8px 8px 0 0;';

                        controls.appendChild(
                            nativePreferenceForm(
                                row.id,
                                row.pinned
                                    ? 'unpin'
                                    : 'pin',
                                row.pinned
                                    ? '📍'
                                    : '📌',
                                row.pinned
                                    ? 'Unpin'
                                    : 'Pin'
                            )
                        );

                        controls.appendChild(
                            nativeMuteSelect(
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

$source =
    substr_replace(
        $source,
        $renderer,
        $renderStart,
        $renderEnd
        - $renderStart
    );

/*
|--------------------------------------------------------------------------
| 3. Toast after redirect
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
    copy(
        $backup,
        $blade
    );

    fwrite(
        STDERR,
        "Closing x-admin::layouts tidak ditemukan.\n"
    );

    exit(11);
}

$toast = <<<'BLADE'

    {{-- INTERNAL CHAT V3.3.7 PREFERENCE TOAST --}}
    @if (session('internal_chat_preference_notice'))
        <div
            id="crm-chat-v337-toast"
            style="
                position:fixed;
                right:22px;
                top:92px;
                z-index:180;
                max-width:360px;
                padding:11px 14px;
                border:1px solid #bbf7d0;
                border-radius:12px;
                background:#f0fdf4;
                color:#166534;
                box-shadow:0 14px 36px rgba(15,23,42,.15);
                font-size:12px;
                font-weight:700;
            "
        >
            ✓ {{ session('internal_chat_preference_notice') }}
        </div>

        <script>
            window.setTimeout(
                () => {
                    const toast =
                        document.getElementById(
                            'crm-chat-v337-toast'
                        );

                    if (toast) {
                        toast.remove();
                    }
                },
                3500
            );
        </script>
    @endif
BLADE;

$source =
    substr_replace(
        $source,
        $toast,
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
        "Gagal menulis V3.3.7 Blade. Backup dipulihkan.\n"
    );

    exit(12);
}

$written =
    file_get_contents(
        $blade
    );

$postChecks = [
    'INTERNAL CHAT V3.3.7 MUTE STATE VALIDATION',
    '🔕 Muted',
    'row.mute_label',
    'window.confirm',
    'internal_chat_preference_notice',
    'crm-chat-v337-toast',
    'nativePreferenceForm',
    'nativeMuteSelect',
];

foreach ($postChecks as $check) {
    if (
        $written === false
        || ! str_contains(
            $written,
            $check
        )
    ) {
        copy(
            $backup,
            $blade
        );

        fwrite(
            STDERR,
            "Post-write validation gagal: {$check}\n"
            ."Backup dipulihkan otomatis.\n"
        );

        exit(13);
    }
}

echo "[PASS] Explicit muted-state badge installed.\n";
echo "[PASS] Mute selector now displays Mute / Muted state.\n";
echo "[PASS] Mute-until label displayed on muted conversations.\n";
echo "[PASS] Confirmation installed before mute preference change.\n";
echo "[PASS] Success toast installed after preference redirect.\n";
echo "[PASS] Pin native POST preserved.\n";
echo "[PASS] No migration / route / provider changes.\n";
