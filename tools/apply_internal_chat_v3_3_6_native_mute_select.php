<?php

/*
|--------------------------------------------------------------------------
| Internal Chat V3.3.6 - Native Mute Select Final Hotfix
|--------------------------------------------------------------------------
|
| V3.3.5 still required JavaScript to open its dialog. On this customized
| Blade, several old chat listeners are already present, so the safest final
| solution is to remove the mute click/dialog dependency entirely.
|
| V3.3.6:
| - Pin keeps the proven native POST form.
| - Mute becomes a native <select> inside a POST form.
| - Selecting an option submits the form directly.
| - Old listeners targeting <button> cannot intercept the <select>.
| - Sidebar auto-refresh pauses while the mute select is focused/open.
|
| No migration / controller / route / provider changes.
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
        'INTERNAL CHAT V3.3.6 NATIVE MUTE SELECT'
    )
) {
    echo "[SKIP] Internal Chat V3.3.6 already installed.\n";

    exit(0);
}

$required = [
    'INTERNAL CHAT V3.3.5 MODERN MUTE DIALOG RENDERER',
    'INTERNAL CHAT V3.3.3 NATIVE PIN MUTE',
    'nativePreferenceForm',
    'let refreshing =',
    'window.crmChatV33RefreshSidebar',
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
            "Current V3.3.5 Blade tidak dikenali: {$marker}\n"
            ."Patch dihentikan agar file customized tidak rusak.\n"
        );

        exit(4);
    }
}

$backup =
    $blade
    .'.before-internal-chat-v3-3-6-native-mute-select.bak';

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
| Replace current renderer with native select renderer.
|--------------------------------------------------------------------------
*/

$renderStart =
    strpos(
        $source,
        "            /* INTERNAL CHAT V3.3.5 MODERN MUTE DIALOG RENDERER */"
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
        "V3.3.5 renderSidebar block tidak ditemukan.\n"
    );

    exit(6);
}

$renderer = <<<'JS'
            /* INTERNAL CHAT V3.3.6 NATIVE MUTE SELECT */
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
                            ? row.mute_label
                                || 'Notification settings'
                            : 'Notification settings';

                    select.setAttribute(
                        'aria-label',
                        'Notification settings for '
                        + String(
                            row.name
                            || 'conversation'
                        )
                    );

                    select.style.cssText =
                        'width:30px;'
                        +'height:26px;'
                        +'padding:0 2px;'
                        +'border:1px solid #e5e7eb;'
                        +'border-radius:8px;'
                        +'background:#ffffff;'
                        +'font-size:11px;'
                        +'cursor:pointer;'
                        +'color:#334155;';

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
                            if (! select.value) {
                                return;
                            }

                            /*
                             * Native form POST. No fetch, no old mute listener.
                             */
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
                            +'min-height:60px;'
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
                            +'<div style="margin-top:1px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:9px;line-height:12px;color:#94a3b8;">'
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
                            +(
                                row.muted
                                    ? ' · 🔕'
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
| Pause realtime rebuild while the native select is open/focused.
|--------------------------------------------------------------------------
*/

$refreshMarker = <<<'JS'
            const refreshSidebar =
                async () => {
                    if (
                        refreshing
                        || ! summaryUrl
                    ) {
                        return;
                    }
JS;

$refreshNew = <<<'JS'
            const refreshSidebar =
                async () => {
                    if (
                        window.__crmMuteSelectOpen
                        || refreshing
                        || ! summaryUrl
                    ) {
                        return;
                    }
JS;

if (
    str_contains(
        $source,
        $refreshMarker
    )
) {
    $source =
        str_replace(
            $refreshMarker,
            $refreshNew,
            $source,
            $refreshCount
        );

    if ($refreshCount !== 1) {
        copy(
            $backup,
            $blade
        );

        fwrite(
            STDERR,
            "refreshSidebar replacement count salah: {$refreshCount}\n"
        );

        exit(7);
    }
} elseif (
    ! str_contains(
        $source,
        'window.__crmMuteSelectOpen'
    )
) {
    copy(
        $backup,
        $blade
    );

    fwrite(
        STDERR,
        "refreshSidebar marker tidak ditemukan.\n"
    );

    exit(8);
}

/*
|--------------------------------------------------------------------------
| Add marker only. Old V3.3.5 dialog may remain in source, but the new
| renderer never calls it, so it is inert and cannot affect layout.
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

    exit(9);
}

$marker = <<<'BLADE'

    {{-- INTERNAL CHAT V3.3.6 NATIVE MUTE SELECT FINAL --}}
BLADE;

$source =
    substr_replace(
        $source,
        $marker,
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
        "Gagal menulis V3.3.6 Blade. Backup dipulihkan.\n"
    );

    exit(10);
}

$written =
    file_get_contents(
        $blade
    );

$postChecks = [
    'INTERNAL CHAT V3.3.6 NATIVE MUTE SELECT',
    'nativeMuteSelect',
    "document.createElement(\n                            'select'",
    "'mute_1_hour'",
    "'mute_today'",
    "'mute_forever'",
    "'unmute'",
    'window.__crmMuteSelectOpen',
    'form.submit()',
    'nativePreferenceForm',
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

        exit(11);
    }
}

echo "[PASS] Mute replaced with native select control.\n";
echo "[PASS] Mute no longer depends on dialog click handlers.\n";
echo "[PASS] Mute options submit through native HTML POST form.\n";
echo "[PASS] Sidebar refresh pauses while mute selector is focused.\n";
echo "[PASS] Pin native POST remains unchanged.\n";
echo "[PASS] No migration / controller / route / provider changes.\n";
