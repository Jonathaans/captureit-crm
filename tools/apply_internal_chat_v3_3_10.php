<?php

/*
|--------------------------------------------------------------------------
| Internal Chat V3.3.10 - Authoritative Unread + Fit UI
|--------------------------------------------------------------------------
|
| Audit proves backend unread cursor is already correct:
| - only the Salsabilla conversation has unread=1
| - Hafiz and Diana are unread=0
|
| Therefore the remaining duplicate red badges are frontend legacy badges
| being re-injected by older chat UI scripts.
|
| V3.3.10 makes one renderer authoritative:
| - current row.unread is the ONLY unread badge source
| - badge is rendered inside the row, never hanging outside
| - legacy unread badges inside the conversation list are removed
| - a MutationObserver removes legacy badges if an old script re-adds them
| - sidebar width is hard-bounded to 100%
| - compact pin / mute / apply controls remain visible
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
        'INTERNAL CHAT V3.3.10 AUTHORITATIVE UNREAD'
    )
) {
    echo "[SKIP] Internal Chat V3.3.10 already installed.\n";

    exit(0);
}

$required = [
    'INTERNAL CHAT V3.3.9 FIT SIDEBAR',
    'id="crm-wa-conversation-list"',
    'id="crm-chat-v333-config"',
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
            "Current V3.3.9 Blade tidak dikenali: {$marker}\n"
            ."Patch dihentikan agar file customized tidak rusak.\n"
        );

        exit(4);
    }
}

$backup =
    $blade
    .'.before-internal-chat-v3-3-10-authoritative-unread.bak';

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

$renderStart =
    strpos(
        $source,
        "            /* INTERNAL CHAT V3.3.9 FIT SIDEBAR */"
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
        "V3.3.9 renderSidebar block tidak ditemukan.\n"
    );

    exit(6);
}

$renderer = <<<'JS'
            /* INTERNAL CHAT V3.3.10 AUTHORITATIVE UNREAD */
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

                list.style.minWidth =
                    '0';

                list.style.overflowX =
                    'hidden';

                list.style.boxSizing =
                    'border-box';

                if (list.parentElement) {
                    list.parentElement.style.width =
                        '100%';

                    list.parentElement.style.maxWidth =
                        '100%';

                    list.parentElement.style.minWidth =
                        '0';

                    list.parentElement.style.overflowX =
                        'hidden';
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
                        'width:23px;'
                        +'height:24px;'
                        +'display:flex;'
                        +'align-items:center;'
                        +'justify-content:center;'
                        +'padding:0;'
                        +'border:1px solid #e5e7eb;'
                        +'border-radius:7px;'
                        +'background:#ffffff;'
                        +'font-size:9px;'
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
                        +'gap:2px;'
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

                    select.style.cssText =
                        'width:31px;'
                        +'height:24px;'
                        +'padding:0 1px;'
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
                        +'font-size:9px;'
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
                        'width:23px;'
                        +'height:24px;'
                        +'padding:0;'
                        +'border:1px solid #0f172a;'
                        +'border-radius:7px;'
                        +'background:#0f172a;'
                        +'color:#ffffff;'
                        +'font-size:9px;'
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

                        wrapper.dataset.crmV3310Row =
                            String(
                                row.id
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
                            +'grid-template-columns:minmax(0,1fr) 82px;'
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
                            +'gap:7px;'
                            +'padding:7px 4px 7px 9px;'
                            +'text-decoration:none;'
                            +'color:inherit;'
                            +'box-sizing:border-box;';

                        const unread =
                            Math.max(
                                0,
                                Number(
                                    row.unread
                                    || 0
                                )
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
                            +'<div style="width:30px;height:30px;border-radius:50%;display:flex;align-items:center;justify-content:center;background:#f1f5f9;color:#334155;font-size:9px;font-weight:800;">'
                            +escapeHtml(
                                row.initials
                            )
                            +'</div>'
                            +'<span style="position:absolute;right:-1px;bottom:0;width:8px;height:8px;border:2px solid #fff;border-radius:50%;background:'
                            +presenceColor
                            +';"></span>'
                            +'</div>'
                            +'<div style="min-width:0;max-width:100%;overflow:hidden;flex:1;">'
                            +'<div style="display:grid;grid-template-columns:minmax(0,1fr) auto auto;align-items:center;gap:5px;min-width:0;">'
                            +'<div style="min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:11px;font-weight:800;color:#0f172a;">'
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
                                        +'" style="flex:0 0 auto;padding:1px 4px;border:1px solid #fde68a;border-radius:999px;background:#fffbeb;color:#92400e;font-size:7.5px;font-weight:800;">🔕 Muted</span>'
                                    : ''
                            )
                            +'<div style="flex:0 0 auto;font-size:8px;color:#94a3b8;">'
                            +escapeHtml(
                                row.time
                            )
                            +'</div>'
                            +'</div>'
                            +'<div style="margin-top:1px;display:grid;grid-template-columns:minmax(0,1fr) auto;align-items:center;gap:5px;min-width:0;">'
                            +'<div style="min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:9.5px;line-height:12px;color:'
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
                                    ? '<span data-crm-v3310-unread="1" title="'
                                        +unread
                                        +' unread message(s)" style="position:static;flex:0 0 auto;min-width:18px;height:18px;padding:0 5px;border-radius:999px;display:inline-flex;align-items:center;justify-content:center;box-sizing:border-box;background:#dc2626;color:#fff;font-size:8.5px;font-weight:900;line-height:18px;">'
                                        +(
                                            unread > 99
                                                ? '99+'
                                                : unread
                                        )
                                        +'</span>'
                                    : ''
                            )
                            +'</div>'
                            +'<div style="margin-top:1px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:8px;line-height:10px;color:#94a3b8;">'
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
                            +'justify-content:flex-end;'
                            +'gap:2px;'
                            +'width:82px;'
                            +'max-width:82px;'
                            +'min-width:82px;'
                            +'overflow:visible;'
                            +'padding:0 4px 0 2px;'
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

                window.crmChatV3310CleanupLegacyUnread?.();

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

    exit(7);
}

$cleanup = <<<'BLADE'

    {{-- INTERNAL CHAT V3.3.10 LEGACY UNREAD CLEANUP --}}
    <script>
        (() => {
            const list =
                document.getElementById(
                    'crm-wa-conversation-list'
                );

            if (! list) {
                return;
            }

            const isRedBadge = (
                element
            ) => {
                if (
                    ! element
                    || element.nodeType !== 1
                ) {
                    return false;
                }

                if (
                    element.matches(
                        '[data-crm-v3310-unread]'
                    )
                    || element.closest(
                        '[data-crm-v3310-unread]'
                    )
                ) {
                    return false;
                }

                const text =
                    String(
                        element.textContent
                        || ''
                    ).trim();

                if (
                    ! /^(?:\d{1,2}|99\+)$/.test(
                        text
                    )
                ) {
                    return false;
                }

                const className =
                    String(
                        element.className
                        || ''
                    ).toLowerCase();

                const styleText =
                    String(
                        element.getAttribute(
                            'style'
                        )
                        || ''
                    ).toLowerCase();

                let background =
                    '';

                try {
                    background =
                        String(
                            window.getComputedStyle(
                                element
                            ).backgroundColor
                            || ''
                        ).toLowerCase();
                } catch (error) {
                    background =
                        '';
                }

                const hasBadgeHint =
                    className.includes(
                        'unread'
                    )
                    || className.includes(
                        'badge'
                    )
                    || styleText.includes(
                        '#dc2626'
                    )
                    || styleText.includes(
                        '#ef4444'
                    )
                    || styleText.includes(
                        'red'
                    )
                    || background ===
                        'rgb(220, 38, 38)'
                    || background ===
                        'rgb(239, 68, 68)'
                    || background ===
                        'rgb(220, 53, 69)'
                    || background ===
                        'rgb(255, 0, 0)';

                return hasBadgeHint;
            };

            const cleanup =
                () => {
                    list
                        .querySelectorAll(
                            '*'
                        )
                        .forEach(
                            (element) => {
                                if (
                                    isRedBadge(
                                        element
                                    )
                                ) {
                                    element.remove();
                                }
                            }
                        );

                    list.style.overflowX =
                        'hidden';

                    list.style.maxWidth =
                        '100%';

                    list
                        .querySelectorAll(
                            '[data-crm-v3310-row]'
                        )
                        .forEach(
                            (row) => {
                                row.style.maxWidth =
                                    '100%';

                                row.style.overflow =
                                    'hidden';
                            }
                        );
                };

            window.crmChatV3310CleanupLegacyUnread =
                cleanup;

            cleanup();

            let scheduled =
                false;

            const observer =
                new MutationObserver(
                    () => {
                        if (scheduled) {
                            return;
                        }

                        scheduled =
                            true;

                        window.requestAnimationFrame(
                            () => {
                                scheduled =
                                    false;

                                cleanup();
                            }
                        );
                    }
                );

            observer.observe(
                list,
                {
                    childList:
                        true,

                    subtree:
                        true,
                }
            );
        })();
    </script>

BLADE;

$source =
    substr_replace(
        $source,
        $cleanup,
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
        "Gagal menulis V3.3.10 Blade. Backup dipulihkan.\n"
    );

    exit(8);
}

$written =
    file_get_contents(
        $blade
    );

$postChecks = [
    'INTERNAL CHAT V3.3.10 AUTHORITATIVE UNREAD',
    'data-crm-v3310-unread',
    'crmV3310Row',
    'crmChatV3310CleanupLegacyUnread',
    'MutationObserver',
    'grid-template-columns:minmax(0,1fr) 82px',
    "list.style.overflowX =\n                    'hidden'",
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

        exit(9);
    }
}

echo "[PASS] Backend row.unread made authoritative in the sidebar.\n";
echo "[PASS] Legacy duplicate unread badges are removed automatically.\n";
echo "[PASS] Legacy unread re-injection is watched and cleaned.\n";
echo "[PASS] Unread badge moved inside bounded row content.\n";
echo "[PASS] Sidebar horizontal overflow hard-blocked.\n";
echo "[PASS] Pin/Mute/Apply controls compacted to fixed 82px column.\n";
echo "[PASS] No migration / controller / route / provider changes.\n";
