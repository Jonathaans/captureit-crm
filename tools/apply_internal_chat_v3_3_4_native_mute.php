<?php

/*
|--------------------------------------------------------------------------
| Internal Chat V3.3.4 - Native Mute Menu Hotfix
|--------------------------------------------------------------------------
|
| Pin already works in V3.3.3 because it uses a real HTML POST form.
|
| Mute still depended on JavaScript to open a modal:
|     window.crmChatV333OpenMute(...)
|
| This hotfix removes that final JS dependency.
|
| New mute UI:
| - Native <details>/<summary> menu
| - Every mute option is a real HTML POST form
| - No modal
| - No fetch
| - No delegated mute click handler
| - Old V3.3/V3.3.2 listeners cannot intercept the summary control
|
| Scope:
| - chat.blade.php renderer only
| - no controller / route / provider / database changes
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
        'INTERNAL CHAT V3.3.4 NATIVE MUTE MENU'
    )
) {
    echo "[SKIP] Internal Chat V3.3.4 already installed.\n";

    exit(0);
}

$required = [
    'INTERNAL CHAT V3.3.3 NATIVE PIN MUTE',
    'INTERNAL CHAT V3.3.3 NATIVE PIN MUTE RENDERER',
    'nativePreferenceForm',
    'window.crmChatV333OpenMute',
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
            "Current V3.3.3 Blade tidak dikenali: {$marker}\n"
            ."Patch dihentikan agar file customized tidak rusak.\n"
        );

        exit(4);
    }
}

$backup =
    $blade
    .'.before-internal-chat-v3-3-4-native-mute-menu.bak';

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
| Replace the whole V3.3.3 renderer with V3.3.4 renderer.
|--------------------------------------------------------------------------
*/

$renderStart =
    strpos(
        $source,
        "            /* INTERNAL CHAT V3.3.3 NATIVE PIN MUTE RENDERER */"
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
        "V3.3.3 renderSidebar block tidak ditemukan.\n"
    );

    exit(6);
}

$renderer = <<<'JS'
            /* INTERNAL CHAT V3.3.4 NATIVE MUTE MENU */
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
                    title,
                    fullWidth
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
                        title
                        || '';

                    button.textContent =
                        label;

                    if (fullWidth) {
                        button.style.cssText =
                            'display:block;'
                            +'width:100%;'
                            +'padding:9px 11px;'
                            +'border:0;'
                            +'border-radius:8px;'
                            +'background:#ffffff;'
                            +'text-align:left;'
                            +'font-size:11px;'
                            +'font-weight:600;'
                            +'color:#334155;'
                            +'cursor:pointer;';

                        button.addEventListener(
                            'mouseenter',
                            () => {
                                button.style.background =
                                    '#f8fafc';
                            }
                        );

                        button.addEventListener(
                            'mouseleave',
                            () => {
                                button.style.background =
                                    '#ffffff';
                            }
                        );
                    } else {
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
                    }

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

                const nativeMuteMenu = (
                    row
                ) => {
                    const details =
                        document.createElement(
                            'details'
                        );

                    details.style.cssText =
                        'position:relative;'
                        +'margin:0;'
                        +'padding:0;';

                    const summary =
                        document.createElement(
                            'summary'
                        );

                    summary.title =
                        row.muted
                            ? row.mute_label
                                || 'Muted'
                            : 'Mute';

                    summary.textContent =
                        row.muted
                            ? '🔕'
                            : '🔔';

                    summary.style.cssText =
                        'list-style:none;'
                        +'width:26px;'
                        +'height:26px;'
                        +'display:flex;'
                        +'align-items:center;'
                        +'justify-content:center;'
                        +'padding:0;'
                        +'border:1px solid #e5e7eb;'
                        +'border-radius:8px;'
                        +'background:#ffffff;'
                        +'font-size:11px;'
                        +'cursor:pointer;'
                        +'user-select:none;';

                    summary.addEventListener(
                        'click',
                        (event) => {
                            /*
                             * Prevent the row/link from receiving the click,
                             * but DO NOT preventDefault. Native <details>
                             * still opens itself.
                             */
                            event.stopPropagation();
                        }
                    );

                    const menu =
                        document.createElement(
                            'div'
                        );

                    menu.style.cssText =
                        'position:absolute;'
                        +'top:31px;'
                        +'right:0;'
                        +'z-index:100;'
                        +'width:205px;'
                        +'padding:6px;'
                        +'border:1px solid #e5e7eb;'
                        +'border-radius:12px;'
                        +'background:#ffffff;'
                        +'box-shadow:0 14px 38px rgba(15,23,42,.18);';

                    const title =
                        document.createElement(
                            'div'
                        );

                    title.style.cssText =
                        'padding:5px 8px 7px;'
                        +'font-size:10px;'
                        +'font-weight:800;'
                        +'color:#64748b;'
                        +'text-transform:uppercase;'
                        +'letter-spacing:.04em;';

                    title.textContent =
                        row.name;

                    menu.appendChild(
                        title
                    );

                    menu.appendChild(
                        nativePreferenceForm(
                            row.id,
                            'mute_1_hour',
                            '🔕  Mute 1 jam',
                            '1 jam',
                            true
                        )
                    );

                    menu.appendChild(
                        nativePreferenceForm(
                            row.id,
                            'mute_today',
                            '🔕  Sampai akhir hari',
                            'Sampai akhir hari',
                            true
                        )
                    );

                    menu.appendChild(
                        nativePreferenceForm(
                            row.id,
                            'mute_forever',
                            '🔕  Sampai diaktifkan kembali',
                            'Sampai diaktifkan kembali',
                            true
                        )
                    );

                    const divider =
                        document.createElement(
                            'div'
                        );

                    divider.style.cssText =
                        'height:1px;'
                        +'margin:5px 3px;'
                        +'background:#e5e7eb;';

                    menu.appendChild(
                        divider
                    );

                    menu.appendChild(
                        nativePreferenceForm(
                            row.id,
                            'unmute',
                            '🔔  Unmute',
                            'Unmute',
                            true
                        )
                    );

                    details.appendChild(
                        summary
                    );

                    details.appendChild(
                        menu
                    );

                    return details;
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
                            +'padding:8px 8px 0 0;'
                            +'position:relative;'
                            +'z-index:20;';

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
                                    : 'Pin',
                                false
                            )
                        );

                        controls.appendChild(
                            nativeMuteMenu(
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
        "Gagal menulis V3.3.4 Blade. Backup dipulihkan.\n"
    );

    exit(7);
}

$written =
    file_get_contents(
        $blade
    );

$postChecks = [
    'INTERNAL CHAT V3.3.4 NATIVE MUTE MENU',
    'nativeMuteMenu',
    "document.createElement(\n                            'details'",
    "document.createElement(\n                            'summary'",
    "'mute_1_hour'",
    "'mute_today'",
    "'mute_forever'",
    "'unmute'",
    'nativePreferenceForm',
    'window.crmChatV33RefreshSidebar',
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

        exit(8);
    }
}

echo "[PASS] Mute control changed to native details/summary menu.\n";
echo "[PASS] Mute 1 hour uses native HTML POST form.\n";
echo "[PASS] Mute today uses native HTML POST form.\n";
echo "[PASS] Mute forever uses native HTML POST form.\n";
echo "[PASS] Unmute uses native HTML POST form.\n";
echo "[PASS] Pin native POST form preserved.\n";
echo "[PASS] No controller / route / provider / database changes.\n";
