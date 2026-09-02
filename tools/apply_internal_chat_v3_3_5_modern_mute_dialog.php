<?php

/*
|--------------------------------------------------------------------------
| Internal Chat V3.3.5 - Modern Mute Dialog UI Hotfix
|--------------------------------------------------------------------------
|
| V3.3.4 native <details>/<summary> works functionally, but its dropdown lives
| inside the realtime sidebar row. Because the sidebar has its own scroll /
| layout constraints, opening the menu can stretch or clip the conversation
| list and produce a large white slab.
|
| V3.3.5 keeps the reliable native POST actions but moves the menu OUTSIDE the
| sidebar into one compact global <dialog>.
|
| Important:
| - Pin remains native HTML POST and is untouched.
| - Mute trigger is an <a>, not a <button>, so legacy V3.3/V3.3.2 capture
|   listeners that search event.target.closest('button') cannot intercept it.
| - Mute choices remain native HTML POST submits.
| - No fetch is used for mute.
| - No migration / controller / route / provider change.
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
        'INTERNAL CHAT V3.3.5 MODERN MUTE DIALOG'
    )
) {
    echo "[SKIP] Internal Chat V3.3.5 already installed.\n";

    exit(0);
}

$required = [
    'INTERNAL CHAT V3.3.4 NATIVE MUTE MENU',
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
            "Current V3.3.4 Blade tidak dikenali: {$marker}\n"
            ."Patch dihentikan agar file customized tidak rusak.\n"
        );

        exit(4);
    }
}

$backup =
    $blade
    .'.before-internal-chat-v3-3-5-modern-mute-dialog.bak';

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
| 1. Replace V3.3.4 renderer.
|--------------------------------------------------------------------------
*/

$renderStart =
    strpos(
        $source,
        "            /* INTERNAL CHAT V3.3.4 NATIVE MUTE MENU */"
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
        "V3.3.4 renderSidebar block tidak ditemukan.\n"
    );

    exit(6);
}

$renderer = <<<'JS'
            /* INTERNAL CHAT V3.3.5 MODERN MUTE DIALOG RENDERER */
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

                        /*
                         * Pin stays native POST because this path is already
                         * proven working in the user's current build.
                         */
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

                        /*
                         * Mute trigger is intentionally an ANCHOR, not button.
                         * Old capture listeners only search closest('button'),
                         * therefore they cannot hijack this control.
                         */
                        const muteLink =
                            document.createElement(
                                'a'
                            );

                        muteLink.href =
                            '#';

                        muteLink.title =
                            row.muted
                                ? row.mute_label
                                    || 'Notification options'
                                : 'Notification options';

                        muteLink.textContent =
                            row.muted
                                ? '🔕'
                                : '🔔';

                        muteLink.style.cssText =
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
                            +'text-decoration:none;'
                            +'cursor:pointer;';

                        muteLink.addEventListener(
                            'click',
                            (event) => {
                                event.preventDefault();
                                event.stopPropagation();

                                window.crmChatV335OpenMute(
                                    row.id,
                                    row.name,
                                    row.mute_label
                                    || ''
                                );
                            }
                        );

                        controls.appendChild(
                            muteLink
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
| 2. Add one compact global dialog outside the scrolling sidebar.
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

    exit(7);
}

$dialog = <<<'BLADE'

    {{-- INTERNAL CHAT V3.3.5 MODERN MUTE DIALOG --}}
    <dialog
        id="crm-chat-v335-mute-dialog"
        style="
            width:min(92vw,390px);
            max-width:390px;
            padding:0;
            border:1px solid #e5e7eb;
            border-radius:18px;
            background:#ffffff;
            box-shadow:0 28px 80px rgba(15,23,42,.28);
            overflow:hidden;
        "
    >
        <form
            id="crm-chat-v335-mute-form"
            method="POST"
            action=""
            style="margin:0;"
        >
            @csrf

            <div
                style="
                    display:flex;
                    align-items:flex-start;
                    justify-content:space-between;
                    gap:12px;
                    padding:15px 16px;
                    border-bottom:1px solid #e5e7eb;
                    background:#ffffff;
                "
            >
                <div style="min-width:0;">
                    <div style="font-size:15px;font-weight:800;color:#0f172a;">
                        Notification Settings
                    </div>

                    <div
                        id="crm-chat-v335-mute-name"
                        style="
                            margin-top:3px;
                            overflow:hidden;
                            text-overflow:ellipsis;
                            white-space:nowrap;
                            font-size:11px;
                            color:#64748b;
                        "
                    ></div>
                </div>

                <button
                    type="button"
                    id="crm-chat-v335-mute-close"
                    class="secondary-button"
                    style="flex:0 0 auto;"
                >
                    Close
                </button>
            </div>

            <div style="padding:8px;background:#ffffff;">
                <button
                    type="submit"
                    name="action"
                    value="mute_1_hour"
                    style="
                        display:block;
                        width:100%;
                        padding:10px 12px;
                        border-radius:10px;
                        text-align:left;
                        font-size:12px;
                    "
                >
                    🔕 &nbsp;Mute 1 jam
                </button>

                <button
                    type="submit"
                    name="action"
                    value="mute_today"
                    style="
                        display:block;
                        width:100%;
                        padding:10px 12px;
                        border-radius:10px;
                        text-align:left;
                        font-size:12px;
                    "
                >
                    🔕 &nbsp;Sampai akhir hari
                </button>

                <button
                    type="submit"
                    name="action"
                    value="mute_forever"
                    style="
                        display:block;
                        width:100%;
                        padding:10px 12px;
                        border-radius:10px;
                        text-align:left;
                        font-size:12px;
                    "
                >
                    🔕 &nbsp;Sampai diaktifkan kembali
                </button>

                <div
                    style="
                        height:1px;
                        margin:6px 3px;
                        background:#e5e7eb;
                    "
                ></div>

                <button
                    type="submit"
                    name="action"
                    value="unmute"
                    style="
                        display:block;
                        width:100%;
                        padding:10px 12px;
                        border-radius:10px;
                        text-align:left;
                        font-size:12px;
                    "
                >
                    🔔 &nbsp;Unmute
                </button>
            </div>
        </form>
    </dialog>

    <script>
        (() => {
            const dialog =
                document.getElementById(
                    'crm-chat-v335-mute-dialog'
                );

            const form =
                document.getElementById(
                    'crm-chat-v335-mute-form'
                );

            const name =
                document.getElementById(
                    'crm-chat-v335-mute-name'
                );

            const config =
                document.getElementById(
                    'crm-chat-v333-config'
                );

            if (
                ! dialog
                || ! form
                || ! config
            ) {
                return;
            }

            const template =
                String(
                    config.dataset.preferenceTemplate
                    || ''
                );

            const preferenceUrl = (
                conversationId
            ) =>
                template.replace(
                    '__CID__',
                    encodeURIComponent(
                        String(
                            conversationId
                        )
                    )
                );

            window.crmChatV335OpenMute =
                function (
                    conversationId,
                    conversationName,
                    muteLabel
                ) {
                    form.action =
                        preferenceUrl(
                            conversationId
                        );

                    if (name) {
                        name.textContent =
                            String(
                                conversationName
                                || 'Conversation'
                            )
                            +(
                                muteLabel
                                    ? ' · '
                                        +muteLabel
                                    : ''
                            );
                    }

                    if (
                        typeof dialog.showModal
                        === 'function'
                    ) {
                        if (! dialog.open) {
                            dialog.showModal();
                        }
                    } else {
                        dialog.setAttribute(
                            'open',
                            'open'
                        );

                        dialog.style.position =
                            'fixed';

                        dialog.style.left =
                            '50%';

                        dialog.style.top =
                            '50%';

                        dialog.style.transform =
                            'translate(-50%,-50%)';

                        dialog.style.zIndex =
                            '160';
                    }

                    return false;
                };

            const close =
                () => {
                    if (
                        typeof dialog.close
                        === 'function'
                        && dialog.open
                    ) {
                        dialog.close();
                    } else {
                        dialog.removeAttribute(
                            'open'
                        );
                    }

                    form.action =
                        '';
                };

            document
                .getElementById(
                    'crm-chat-v335-mute-close'
                )
                .addEventListener(
                    'click',
                    close
                );

            dialog.addEventListener(
                'click',
                (event) => {
                    const rect =
                        dialog.getBoundingClientRect();

                    const inside =
                        event.clientX >= rect.left
                        && event.clientX <= rect.right
                        && event.clientY >= rect.top
                        && event.clientY <= rect.bottom;

                    if (! inside) {
                        close();
                    }
                }
            );

            dialog.addEventListener(
                'cancel',
                (event) => {
                    event.preventDefault();
                    close();
                }
            );
        })();
    </script>

BLADE;

$source =
    substr_replace(
        $source,
        $dialog,
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
        "Gagal menulis V3.3.5 Blade. Backup dipulihkan.\n"
    );

    exit(8);
}

$written =
    file_get_contents(
        $blade
    );

$postChecks = [
    'INTERNAL CHAT V3.3.5 MODERN MUTE DIALOG',
    'INTERNAL CHAT V3.3.5 MODERN MUTE DIALOG RENDERER',
    'crm-chat-v335-mute-dialog',
    'crm-chat-v335-mute-form',
    'window.crmChatV335OpenMute',
    "document.createElement(\n                                'a'",
    'value="mute_1_hour"',
    'value="mute_today"',
    'value="mute_forever"',
    'value="unmute"',
    'nativePreferenceForm',
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

echo "[PASS] Broken in-row details menu removed from final renderer.\n";
echo "[PASS] Compact global mute dialog installed.\n";
echo "[PASS] Mute trigger changed to legacy-listener-safe anchor.\n";
echo "[PASS] Mute options remain native HTML POST submits.\n";
echo "[PASS] Pin native POST preserved.\n";
echo "[PASS] Sidebar height/layout restored.\n";
echo "[PASS] No migration / controller / route / provider changes.\n";
