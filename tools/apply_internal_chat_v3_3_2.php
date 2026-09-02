<?php

/*
|--------------------------------------------------------------------------
| Internal Chat V3.3.2
|--------------------------------------------------------------------------
|
| Adds:
| - activity-based presence across the CRM
| - Online / Idle / Last active
| - In Chat marker
| - hard form-submit fallback for Pin/Mute
| - compact action placement
|
| The dedicated Conversation Controller is supplied as a full package file.
| This installer patches only widget.blade.php + chat.blade.php.
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
| 1. Global activity-based presence in widget
|--------------------------------------------------------------------------
*/

$widget =
    $root
    .'/packages/Webkul/Admin/src/Resources/views/internal-communication/widget.blade.php';

if (! is_file($widget)) {
    fwrite(
        STDERR,
        "widget.blade.php tidak ditemukan.\n"
    );

    exit(2);
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

    exit(3);
}

backupOnce(
    $widget,
    '.before-internal-chat-v3-3-2-activity-presence.bak'
);

if (
    ! str_contains(
        $widgetSource,
        'INTERNAL CHAT V3.3.2 ACTIVITY PRESENCE'
    )
) {
    $activityPresence = <<<'BLADE'

{{-- INTERNAL CHAT V3.3.2 ACTIVITY PRESENCE --}}
<div
    id="crm-global-presence-v332"
    data-heartbeat-url="{{ route('admin.internal-chat.presence.heartbeat') }}"
    data-csrf="{{ csrf_token() }}"
    style="display:none;"
></div>

<script>
    (() => {
        if (window.__crmActivityPresenceV332) {
            return;
        }

        window.__crmActivityPresenceV332 =
            true;

        const config =
            document.getElementById(
                'crm-global-presence-v332'
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

        let lastActivityAt =
            Date.now();

        let lastSentAt =
            0;

        const markActivity =
            () => {
                lastActivityAt =
                    Date.now();

                /*
                 * Don't POST on every mousemove. Human beings generate enough
                 * events already.
                 */
                if (
                    Date.now()
                    - lastSentAt
                    > 5000
                ) {
                    sendPresence();
                }
            };

        const isInChat =
            () =>
                window.location.pathname
                    .toLowerCase()
                    .includes(
                        '/admin/internal-chat'
                    );

        const sendPresence =
            async () => {
                if (
                    document.visibilityState
                    === 'hidden'
                ) {
                    return;
                }

                const idleSeconds =
                    Math.max(
                        0,
                        Math.floor(
                            (
                                Date.now()
                                - lastActivityAt
                            )
                            / 1000
                        )
                    );

                lastSentAt =
                    Date.now();

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
                                JSON.stringify({
                                    idle_seconds:
                                        idleSeconds,

                                    in_chat:
                                        isInChat(),
                                }),
                        }
                    );
                } catch (error) {
                    // Presence must never break ordinary CRM work.
                }
            };

        [
            'pointerdown',
            'keydown',
            'touchstart',
            'scroll',
        ].forEach(
            (eventName) => {
                window.addEventListener(
                    eventName,
                    markActivity,
                    {
                        passive:
                            true,
                    }
                );
            }
        );

        window.addEventListener(
            'focus',
            markActivity
        );

        document.addEventListener(
            'visibilitychange',
            () => {
                if (
                    document.visibilityState
                    === 'visible'
                ) {
                    markActivity();
                }
            }
        );

        sendPresence();

        window.setInterval(
            sendPresence,
            15000
        );
    })();
</script>
BLADE;

    $widgetSource .=
        "\n"
        .$activityPresence
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
        "Gagal menulis V3.3.2 widget presence.\n"
    );

    exit(4);
}

echo "[PASS] Activity-based presence installed globally.\n";

/*
|--------------------------------------------------------------------------
| 2. Chat hard action fallback
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

    exit(5);
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

    exit(6);
}

if (
    str_contains(
        $source,
        'INTERNAL CHAT V3.3.2 HARD PIN MUTE'
    )
) {
    echo "[SKIP] V3.3.2 chat hardfix already installed.\n";

    exit(0);
}

$required = [
    'INTERNAL CHAT V3.3 CONVERSATION MANAGEMENT',
    'id="crm-wa-conversation-list"',
    'id="crm-chat-v33-config"',
    'data-preference-base=',
    'crm-chat-v33-mute-modal',
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
            "V3.3 chat baseline tidak dikenali: {$marker}\n"
            ."Patch dihentikan agar Blade customized tidak rusak.\n"
        );

        exit(7);
    }
}

backupOnce(
    $blade,
    '.before-internal-chat-v3-3-2-hard-pin-mute.bak'
);

/*
|--------------------------------------------------------------------------
| Idle dot support in the existing renderer.
|--------------------------------------------------------------------------
*/

$source =
    str_replace(
        "row.online\n                                ? '#22c55e'\n                                : '#cbd5e1'",
        "row.online\n                                ? '#22c55e'\n                                : (\n                                    row.idle\n                                        ? '#f59e0b'\n                                        : '#cbd5e1'\n                                )",
        $source
    );

$source =
    str_replace(
        "row.online\n                                    ? '#16a34a'\n                                    : '#94a3b8'",
        "row.online\n                                    ? '#16a34a'\n                                    : (\n                                        row.idle\n                                            ? '#d97706'\n                                            : '#94a3b8'\n                                    )",
        $source
    );

/*
|--------------------------------------------------------------------------
| Append independent Pin/Mute form-submit layer.
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

    exit(8);
}

$hardfix = <<<'BLADE'

    {{-- INTERNAL CHAT V3.3.2 HARD PIN MUTE --}}
    <div
        id="crm-chat-v332-hard-config"
        data-preference-base="{{ url('admin/internal-chat') }}"
        data-csrf="{{ csrf_token() }}"
        style="display:none;"
    ></div>

    <div
        id="crm-chat-v332-mute-modal"
        style="
            display:none;
            position:fixed;
            inset:0;
            z-index:120;
            align-items:center;
            justify-content:center;
            padding:18px;
            background:rgba(15,23,42,.44);
            backdrop-filter:blur(3px);
        "
    >
        <div
            style="
                width:min(92vw,350px);
                overflow:hidden;
                border:1px solid #e5e7eb;
                border-radius:18px;
                background:#ffffff;
                box-shadow:0 28px 80px rgba(15,23,42,.25);
            "
        >
            <div style="padding:15px 16px;border-bottom:1px solid #e5e7eb;">
                <div style="font-size:15px;font-weight:800;color:#0f172a;">
                    Mute Conversation
                </div>

                <div
                    id="crm-chat-v332-mute-name"
                    style="margin-top:3px;font-size:12px;color:#64748b;"
                ></div>
            </div>

            <div style="padding:8px;">
                <button type="button" data-v332-pref="mute_1_hour" style="width:100%;padding:10px 12px;text-align:left;border-radius:10px;">🔕 1 jam</button>
                <button type="button" data-v332-pref="mute_today" style="width:100%;padding:10px 12px;text-align:left;border-radius:10px;">🔕 Sampai akhir hari</button>
                <button type="button" data-v332-pref="mute_forever" style="width:100%;padding:10px 12px;text-align:left;border-radius:10px;">🔕 Sampai diaktifkan kembali</button>
                <button type="button" data-v332-pref="unmute" style="width:100%;padding:10px 12px;text-align:left;border-radius:10px;">🔔 Unmute</button>
            </div>

            <div style="padding:10px 16px;border-top:1px solid #e5e7eb;text-align:right;">
                <button
                    type="button"
                    id="crm-chat-v332-mute-close"
                    class="secondary-button"
                >
                    Close
                </button>
            </div>
        </div>
    </div>

    <script>
        (() => {
            const config =
                document.getElementById(
                    'crm-chat-v332-hard-config'
                );

            const list =
                document.getElementById(
                    'crm-wa-conversation-list'
                );

            const muteModal =
                document.getElementById(
                    'crm-chat-v332-mute-modal'
                );

            if (
                ! config
                || ! list
                || ! muteModal
            ) {
                return;
            }

            const preferenceBase =
                String(
                    config.dataset.preferenceBase
                    || ''
                ).replace(
                    /\/+$/,
                    ''
                );

            const csrf =
                String(
                    config.dataset.csrf
                    || ''
                );

            let muteConversationId =
                0;

            const conversationIdFromButton = (
                button
            ) => {
                if (! button) {
                    return 0;
                }

                let row =
                    button.parentElement;

                while (
                    row
                    && row !== list
                ) {
                    const link =
                        row.querySelector(
                            'a[href*="conversation="]'
                        );

                    if (link) {
                        try {
                            const url =
                                new URL(
                                    link.href,
                                    window.location.origin
                                );

                            return Number(
                                url.searchParams.get(
                                    'conversation'
                                )
                                || 0
                            );
                        } catch (error) {
                            return 0;
                        }
                    }

                    row =
                        row.parentElement;
                }

                return 0;
            };

            const submitPreference = (
                conversationId,
                action
            ) => {
                if (
                    conversationId < 1
                    || ! action
                ) {
                    return;
                }

                const form =
                    document.createElement(
                        'form'
                    );

                form.method =
                    'POST';

                form.action =
                    preferenceBase
                    + '/'
                    + encodeURIComponent(
                        String(
                            conversationId
                        )
                    )
                    + '/preference';

                form.style.display =
                    'none';

                const token =
                    document.createElement(
                        'input'
                    );

                token.type =
                    'hidden';

                token.name =
                    '_token';

                token.value =
                    csrf;

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

                form.appendChild(
                    token
                );

                form.appendChild(
                    actionInput
                );

                document.body.appendChild(
                    form
                );

                form.submit();
            };

            const openMute = (
                conversationId,
                name
            ) => {
                muteConversationId =
                    Number(
                        conversationId
                    );

                const label =
                    document.getElementById(
                        'crm-chat-v332-mute-name'
                    );

                if (label) {
                    label.textContent =
                        name
                        || 'Conversation';
                }

                muteModal.style.display =
                    'flex';
            };

            const closeMute = () => {
                muteModal.style.display =
                    'none';

                muteConversationId =
                    0;
            };

            /*
             * Capture phase intentionally wins over old V3.3 listeners.
             * We use ordinary POST form submission instead of fetch.
             */
            list.addEventListener(
                'click',
                (event) => {
                    const button =
                        event.target.closest(
                            'button'
                        );

                    if (
                        ! button
                        || ! list.contains(
                            button
                        )
                    ) {
                        return;
                    }

                    const title =
                        String(
                            button.title
                            || ''
                        ).toLowerCase();

                    const conversationId =
                        conversationIdFromButton(
                            button
                        );

                    if (
                        title.includes(
                            'pin conversation'
                        )
                        || title.includes(
                            'unpin conversation'
                        )
                    ) {
                        event.preventDefault();
                        event.stopImmediatePropagation();

                        submitPreference(
                            conversationId,
                            title.includes(
                                'unpin'
                            )
                                ? 'unpin'
                                : 'pin'
                        );

                        return;
                    }

                    if (
                        title.includes(
                            'mute conversation'
                        )
                        || title.includes(
                            'muted'
                        )
                    ) {
                        event.preventDefault();
                        event.stopImmediatePropagation();

                        let name =
                            'Conversation';

                        const row =
                            button.parentElement
                                ? button.parentElement.parentElement
                                : null;

                        const link =
                            row
                                ? row.querySelector(
                                    'a[href*="conversation="]'
                                )
                                : null;

                        if (link) {
                            const nameNode =
                                link.querySelector(
                                    'div[style*="font-weight:800"]'
                                );

                            if (nameNode) {
                                name =
                                    String(
                                        nameNode.textContent
                                        || ''
                                    )
                                        .replace(
                                            '📌',
                                            ''
                                        )
                                        .trim();
                            }
                        }

                        openMute(
                            conversationId,
                            name
                        );
                    }
                },
                true
            );

            muteModal
                .querySelectorAll(
                    '[data-v332-pref]'
                )
                .forEach(
                    (button) => {
                        button.addEventListener(
                            'click',
                            () => {
                                submitPreference(
                                    muteConversationId,
                                    button.dataset
                                        .v332Pref
                                );
                            }
                        );
                    }
                );

            document
                .getElementById(
                    'crm-chat-v332-mute-close'
                )
                .addEventListener(
                    'click',
                    closeMute
                );

            muteModal.addEventListener(
                'click',
                (event) => {
                    if (
                        event.target
                        === muteModal
                    ) {
                        closeMute();
                    }
                }
            );

            /*
             * Keep action controls tight even if V3.3 renderer runs.
             */
            const compactActions = () => {
                list
                    .querySelectorAll(
                        'button'
                    )
                    .forEach(
                        (button) => {
                            const title =
                                String(
                                    button.title
                                    || ''
                                ).toLowerCase();

                            if (
                                ! title.includes(
                                    'conversation'
                                )
                                && ! title.includes(
                                    'muted'
                                )
                            ) {
                                return;
                            }

                            button.style.width =
                                '24px';

                            button.style.height =
                                '24px';

                            button.style.padding =
                                '0';

                            button.style.fontSize =
                                '11px';

                            button.style.border =
                                '1px solid #e5e7eb';

                            button.style.background =
                                '#ffffff';
                        }
                    );
            };

            compactActions();

            const observer =
                new MutationObserver(
                    compactActions
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
        $hardfix,
        $closingPos,
        0
    );

if (
    file_put_contents(
        $blade,
        $source
    ) === false
) {
    fwrite(
        STDERR,
        "Gagal menulis V3.3.2 chat Blade.\n"
    );

    exit(9);
}

$written =
    file_get_contents(
        $blade
    );

$postChecks = [
    'INTERNAL CHAT V3.3.2 HARD PIN MUTE',
    'crm-chat-v332-hard-config',
    'crm-chat-v332-mute-modal',
    'submitPreference',
    "form.method =\n                    'POST'",
    "event.stopImmediatePropagation()",
    "data-v332-pref",
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

        exit(10);
    }
}

echo "[PASS] Hard POST-form Pin/Unpin installed.\n";
echo "[PASS] Hard POST-form Mute/Unmute installed.\n";
echo "[PASS] Old fetch listeners bypassed in capture phase.\n";
echo "[PASS] Idle presence color support installed.\n";
echo "[PASS] No migration / route / provider changes.\n";
