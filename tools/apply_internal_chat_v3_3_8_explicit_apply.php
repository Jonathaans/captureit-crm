<?php

/*
|--------------------------------------------------------------------------
| Internal Chat V3.3.8 - Explicit Apply Preferences
|--------------------------------------------------------------------------
|
| Tinker proved that muted_until, mute_forever, AND pinned_at are all still
| null. Therefore prior UI state was not enough proof of persistence.
|
| V3.3.8 makes browser submission completely explicit:
|
| PIN:
|   <button type="submit" name="action" value="pin|unpin">
|
| MUTE:
|   <select name="action" required>
|   <button type="submit">Apply</button>
|
| There is:
| - no form.submit()
| - no fetch() for final preference action
| - no click delegation for final preference action
| - no modal/details dependency
|
| The selected mute action is submitted by normal browser form semantics.
|
| No migration / route / provider / controller changes.
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
        'INTERNAL CHAT V3.3.8 EXPLICIT APPLY PREFERENCES'
    )
) {
    echo "[SKIP] Internal Chat V3.3.8 already installed.\n";

    exit(0);
}

$required = [
    'INTERNAL CHAT V3.3.7 MUTE STATE VALIDATION',
    'id="crm-chat-v333-config"',
    'data-preference-template=',
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
            "Current V3.3.7 Blade tidak dikenali: {$marker}\n"
            ."Patch dihentikan agar file customized tidak rusak.\n"
        );

        exit(4);
    }
}

$backup =
    $blade
    .'.before-internal-chat-v3-3-8-explicit-apply.bak';

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
        "            /* INTERNAL CHAT V3.3.7 MUTE STATE VALIDATION */"
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
        "V3.3.7 renderSidebar block tidak ditemukan.\n"
    );

    exit(6);
}

$renderer = <<<'JS'
            /* INTERNAL CHAT V3.3.8 EXPLICIT APPLY PREFERENCES */
            const renderSidebar = (
                rows
            ) => {
                if (! list) {
                    return;
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

                    form.style.margin =
                        '0';

                    form.style.padding =
                        '0';

                    const button =
                        document.createElement(
                            'button'
                        );

                    button.type =
                        'submit';

                    /*
                     * Critical V3.3.8 change:
                     * clicked submit button itself carries the action.
                     */
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
                        'width:26px;'
                        +'height:27px;'
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
                        +'gap:4px;';

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
                        'width:'
                        +(
                            row.muted
                                ? '86px'
                                : '70px'
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
                        'Apply';

                    apply.title =
                        'Apply notification setting';

                    apply.style.cssText =
                        'height:27px;'
                        +'padding:0 8px;'
                        +'border:1px solid #e5e7eb;'
                        +'border-radius:8px;'
                        +'background:#0f172a;'
                        +'color:#ffffff;'
                        +'font-size:9px;'
                        +'font-weight:800;'
                        +'cursor:pointer;';

                    /*
                     * Pause realtime redraw while the operator is choosing.
                     * The final POST itself remains pure browser form submit.
                     */
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
                                    ? '<span style="flex:0 0 auto;padding:1px 6px;border:1px solid #fde68a;border-radius:999px;background:#fffbeb;color:#92400e;font-size:8.5px;font-weight:800;">🔕 '
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

$marker = <<<'BLADE'

    {{-- INTERNAL CHAT V3.3.8 EXPLICIT APPLY FINAL --}}
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
        "Gagal menulis V3.3.8 Blade. Backup dipulihkan.\n"
    );

    exit(8);
}

$written =
    file_get_contents(
        $blade
    );

$postChecks = [
    'INTERNAL CHAT V3.3.8 EXPLICIT APPLY PREFERENCES',
    'const pinForm =',
    'const muteForm =',
    "button.name =\n                        'action'",
    "select.name =\n                        'action'",
    "apply.type =\n                        'submit'",
    "apply.textContent =\n                        'Apply'",
    'window.__crmMuteSelectOpen',
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

        exit(9);
    }
}

echo "[PASS] Pin action moved to clicked native submit button value.\n";
echo "[PASS] Mute action changed to select + explicit Apply submit.\n";
echo "[PASS] Final preference submission no longer uses form.submit().\n";
echo "[PASS] Mute/pin final actions use native browser POST semantics.\n";
echo "[PASS] Realtime sidebar pause while selecting mute preserved.\n";
echo "[PASS] No migration / controller / route / provider changes.\n";
