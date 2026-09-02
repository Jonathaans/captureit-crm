<?php

/*
|--------------------------------------------------------------------------
| Internal Chat V3.3.1 - Presence / Actions / Compact UI Hotfix
|--------------------------------------------------------------------------
|
| Fixes:
| - Presence only works while user is inside Internal Chat.
| - Dashboard/other admin pages therefore show the user as Offline.
| - Pin/Mute POST can fail on /admin/internal-chat because CSRF was read from
|   crm-chat-send-form, which does not exist when no conversation is open.
| - V3.3 sidebar rows are too tall and the pin/mute controls are visually
|   detached from the conversation row.
|
| Scope:
| - InternalChatConversationController.php: presence label only
| - chat.blade.php: V3.3 CSRF + compact renderer
| - widget.blade.php: global presence heartbeat
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
| 1. Presence label
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
    '.before-internal-chat-v3-3-1.bak'
);

$oldPresence = <<<'PHP'
        if (! $lastSeen) {
            return 'Offline';
        }
PHP;

$newPresence = <<<'PHP'
        if (! $lastSeen) {
            return 'Last active belum tercatat';
        }
PHP;

if (
    str_contains(
        $controllerSource,
        $oldPresence
    )
) {
    $controllerSource =
        str_replace(
            $oldPresence,
            $newPresence,
            $controllerSource,
            $presenceCount
        );

    if ($presenceCount !== 1) {
        fwrite(
            STDERR,
            "Presence label replacement count salah: {$presenceCount}\n"
        );

        exit(4);
    }
} elseif (
    ! str_contains(
        $controllerSource,
        "return 'Last active belum tercatat';"
    )
) {
    fwrite(
        STDERR,
        "Presence label baseline tidak dikenali.\n"
    );

    exit(5);
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

echo "[PASS] Presence fallback changed from Offline to Last active status.\n";

/*
|--------------------------------------------------------------------------
| 2. Global heartbeat in Chat widget
|--------------------------------------------------------------------------
|
| The Chat widget is rendered across admin pages, so presence now tracks a
| logged-in CRM user even while they are on Dashboard, Invoice, Inventory, etc.
|
*/

$widget =
    $root
    .'/packages/Webkul/Admin/src/Resources/views/internal-communication/widget.blade.php';

if (! is_file($widget)) {
    fwrite(
        STDERR,
        "Internal communication widget tidak ditemukan.\n"
    );

    exit(7);
}

$widgetSource =
    file_get_contents(
        $widget
    );

if ($widgetSource === false) {
    fwrite(
        STDERR,
        "widget.blade.php tidak dapat dibaca.\n"
    );

    exit(8);
}

backupOnce(
    $widget,
    '.before-internal-chat-v3-3-1-global-presence.bak'
);

if (
    ! str_contains(
        $widgetSource,
        'INTERNAL CHAT V3.3.1 GLOBAL PRESENCE'
    )
) {
    $globalPresence = <<<'BLADE'

{{-- INTERNAL CHAT V3.3.1 GLOBAL PRESENCE --}}
<div
    id="crm-global-presence-v331"
    data-heartbeat-url="{{ route('admin.internal-chat.presence.heartbeat') }}"
    data-csrf="{{ csrf_token() }}"
    style="display:none;"
></div>

<script>
    (() => {
        if (window.__crmGlobalPresenceHeartbeatV331) {
            return;
        }

        window.__crmGlobalPresenceHeartbeatV331 =
            true;

        const config =
            document.getElementById(
                'crm-global-presence-v331'
            );

        if (! config) {
            return;
        }

        const url =
            String(
                config.dataset.heartbeatUrl
                || ''
            );

        const csrf =
            String(
                config.dataset.csrf
                || ''
            );

        if (! url) {
            return;
        }

        const ping =
            async () => {
                if (
                    document.visibilityState
                    === 'hidden'
                ) {
                    return;
                }

                try {
                    await fetch(
                        url,
                        {
                            method:
                                'POST',

                            headers: {
                                'Accept':
                                    'application/json',

                                'Content-Type':
                                    'application/json',

                                'X-CSRF-TOKEN':
                                    csrf,

                                'X-Requested-With':
                                    'XMLHttpRequest',
                            },

                            credentials:
                                'same-origin',

                            body:
                                '{}',
                        }
                    );
                } catch (error) {
                    // Presence is best-effort and must never block CRM pages.
                }
            };

        ping();

        window.setInterval(
            ping,
            15000
        );

        window.addEventListener(
            'focus',
            ping
        );

        document.addEventListener(
            'visibilitychange',
            () => {
                if (
                    document.visibilityState
                    === 'visible'
                ) {
                    ping();
                }
            }
        );
    })();
</script>
BLADE;

    $widgetSource .=
        "\n"
        .$globalPresence
        ."\n";
}

if (
    file_put_contents(
        $widget,
        $widgetSource
    ) === false
) {
    fwrite(
        STDERR,
        "Gagal menulis global presence widget.\n"
    );

    exit(9);
}

echo "[PASS] Global presence heartbeat installed on admin Chat widget.\n";

/*
|--------------------------------------------------------------------------
| 3. V3.3 Blade CSRF + compact row renderer
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

    exit(10);
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

    exit(11);
}

if (
    str_contains(
        $source,
        'INTERNAL CHAT V3.3.1 COMPACT SIDEBAR'
    )
) {
    echo "[SKIP] V3.3.1 compact chat Blade already installed.\n";

    exit(0);
}

$required = [
    'INTERNAL CHAT V3.3 CONVERSATION MANAGEMENT',
    'id="crm-chat-v33-config"',
    'data-summary-url=',
    'data-preference-base=',
    'data-heartbeat-url=',
    'const renderSidebar =',
    'let refreshing =',
    'crm-chat-v33-mute-modal',
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
            "V3.3 Blade baseline tidak dikenali: {$marker}\n"
            ."Patch dihentikan agar file customized tidak rusak.\n"
        );

        exit(12);
    }
}

backupOnce(
    $blade,
    '.before-internal-chat-v3-3-1.bak'
);

/*
 * Stable CSRF is embedded directly in V3.3 config.
 */
$heartbeatAttr =
    'data-heartbeat-url="{{ route(\'admin.internal-chat.presence.heartbeat\') }}"';

$csrfAttr =
    $heartbeatAttr
    ."\n"
    .'        data-csrf="{{ csrf_token() }}"';

if (
    str_contains(
        $source,
        $heartbeatAttr
    )
    && ! str_contains(
        $source,
        'data-csrf="{{ csrf_token() }}"'
    )
) {
    $source =
        str_replace(
            $heartbeatAttr,
            $csrfAttr,
            $source,
            $csrfAttrCount
        );

    if ($csrfAttrCount !== 1) {
        fwrite(
            STDERR,
            "CSRF config replacement count salah: {$csrfAttrCount}\n"
        );

        exit(13);
    }
}

/*
 * Replace form-derived CSRF with config-derived CSRF.
 */
$csrfStart =
    strpos(
        $source,
        "            const csrfInput =\n"
    );

$csrfEndMarker =
    "            const list =\n";

$csrfEnd =
    $csrfStart === false
        ? false
        : strpos(
            $source,
            $csrfEndMarker,
            $csrfStart
        );

if (
    $csrfStart === false
    || $csrfEnd === false
) {
    fwrite(
        STDERR,
        "V3.3 CSRF block tidak ditemukan.\n"
    );

    exit(14);
}

$newCsrf = <<<'JS'
            /*
             * V3.3.1: use Blade-provided token.
             * /admin/internal-chat may not have crm-chat-send-form yet.
             */
            const csrf =
                String(
                    config.dataset.csrf
                    || ''
                );

JS;

$source =
    substr_replace(
        $source,
        $newCsrf,
        $csrfStart,
        $csrfEnd
        - $csrfStart
    );

/*
|--------------------------------------------------------------------------
| Compact renderer
|--------------------------------------------------------------------------
*/

$renderStart =
    strpos(
        $source,
        "            const renderSidebar = (\n"
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
        "V3.3 renderSidebar block tidak ditemukan.\n"
    );

    exit(15);
}

$compactRenderer = <<<'JS'
            /* INTERNAL CHAT V3.3.1 COMPACT SIDEBAR */
            const renderSidebar = (
                rows
            ) => {
                if (! list) {
                    return;
                }

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

                        const presenceColor =
                            row.online
                                ? '#22c55e'
                                : '#cbd5e1';

                        const unread =
                            Number(
                                row.unread
                                || 0
                            );

                        const pinPrefix =
                            row.pinned
                                ? '<span title="Pinned" style="font-size:10px;">📌</span> '
                                : '';

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
                            +pinPrefix
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
                                    : '#94a3b8'
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
                            +'gap:2px;'
                            +'padding:8px 7px 0 0;';

                        const makeActionButton = (
                            title,
                            text
                        ) => {
                            const button =
                                document.createElement(
                                    'button'
                                );

                            button.type =
                                'button';

                            button.title =
                                title;

                            button.textContent =
                                text;

                            button.style.cssText =
                                'width:25px;'
                                +'height:25px;'
                                +'display:flex;'
                                +'align-items:center;'
                                +'justify-content:center;'
                                +'border:1px solid #e5e7eb;'
                                +'border-radius:8px;'
                                +'background:#fff;'
                                +'font-size:11px;'
                                +'cursor:pointer;';

                            return button;
                        };

                        const pin =
                            makeActionButton(
                                row.pinned
                                    ? 'Unpin conversation'
                                    : 'Pin conversation',
                                row.pinned
                                    ? '📍'
                                    : '📌'
                            );

                        pin.addEventListener(
                            'click',
                            async (event) => {
                                event.preventDefault();
                                event.stopPropagation();

                                pin.disabled =
                                    true;

                                try {
                                    await preference(
                                        row.id,
                                        row.pinned
                                            ? 'unpin'
                                            : 'pin'
                                    );

                                    await refreshSidebar();
                                } catch (error) {
                                    console.error(
                                        'Pin preference failed:',
                                        error
                                    );

                                    window.alert(
                                        'Pin conversation gagal. Refresh halaman jika sesi login berubah.'
                                    );
                                } finally {
                                    pin.disabled =
                                        false;
                                }
                            }
                        );

                        const mute =
                            makeActionButton(
                                row.muted
                                    ? row.mute_label
                                    : 'Mute conversation',
                                row.muted
                                    ? '🔕'
                                    : '🔔'
                            );

                        mute.addEventListener(
                            'click',
                            (event) => {
                                event.preventDefault();
                                event.stopPropagation();

                                muteConversationId =
                                    Number(
                                        row.id
                                    );

                                const name =
                                    document.getElementById(
                                        'crm-chat-v33-mute-name'
                                    );

                                if (name) {
                                    name.textContent =
                                        row.name
                                        +(
                                            row.muted
                                                ? ' · '
                                                    +row.mute_label
                                                : ''
                                        );
                                }

                                muteOverlay.style.display =
                                    'flex';
                            }
                        );

                        controls.appendChild(
                            pin
                        );

                        controls.appendChild(
                            mute
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
        $compactRenderer,
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
    fwrite(
        STDERR,
        "Gagal menulis V3.3.1 chat Blade.\n"
    );

    exit(16);
}

$written =
    file_get_contents(
        $blade
    );

$postChecks = [
    'INTERNAL CHAT V3.3.1 COMPACT SIDEBAR',
    'data-csrf="{{ csrf_token() }}"',
    'config.dataset.csrf',
    'min-height:62px',
    'width:34px',
    'width:25px',
    'Pin conversation gagal. Refresh halaman jika sesi login berubah.',
    'crm-chat-v33-mute-modal',
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
        fwrite(
            STDERR,
            "Post-write validation gagal: {$marker}\n"
        );

        exit(17);
    }
}

echo "[PASS] V3.3 CSRF fixed for Pin/Mute on chat index.\n";
echo "[PASS] Pin/Mute controls compacted into each conversation row.\n";
echo "[PASS] Sidebar row height reduced.\n";
echo "[PASS] Presence remains visible as Online / Last active.\n";
echo "[PASS] No migration / route / provider changes.\n";
