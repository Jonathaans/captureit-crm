<?php

/*
|--------------------------------------------------------------------------
| Internal Chat V3.3 - Conversation Management
|--------------------------------------------------------------------------
|
| Adds:
| - Pin Conversation
| - Mute Conversation
| - Online / Last Active presence
| - realtime-ish sidebar refresh (4 seconds)
|
| Existing V3.2.6 preview/search/typing/actions remain intact.
|
| This installer patches:
| - InternalCommunicationServiceProvider.php
| - InternalChatController.php notification loop (mute guard only)
| - chat.blade.php (V3.3 runtime only)
|
| Dedicated new files + migration are supplied by the package.
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
| 1. Provider routes
|--------------------------------------------------------------------------
*/

$provider =
    $root
    .'/packages/Webkul/Admin/src/Providers/InternalCommunicationServiceProvider.php';

if (! is_file($provider)) {
    fwrite(
        STDERR,
        "InternalCommunicationServiceProvider tidak ditemukan.\n"
    );

    exit(2);
}

$providerSource =
    file_get_contents(
        $provider
    );

if ($providerSource === false) {
    fwrite(
        STDERR,
        "Provider tidak dapat dibaca.\n"
    );

    exit(3);
}

backupOnce(
    $provider,
    '.before-internal-chat-v3-3.bak'
);

$useLine =
    'use Webkul\Admin\Http\Controllers\InternalCommunication\InternalChatConversationController;';

if (
    ! str_contains(
        $providerSource,
        $useLine
    )
) {
    $anchor =
        'use Webkul\Admin\Http\Controllers\InternalCommunication\InternalChatExperienceController;';

    if (
        ! str_contains(
            $providerSource,
            $anchor
        )
    ) {
        $anchor =
            'use Webkul\Admin\Http\Controllers\InternalCommunication\InternalChatController;';
    }

    if (
        ! str_contains(
            $providerSource,
            $anchor
        )
    ) {
        fwrite(
            STDERR,
            "Provider controller import anchor tidak ditemukan.\n"
        );

        exit(4);
    }

    $providerSource =
        str_replace(
            $anchor,
            $anchor
            ."\n"
            .$useLine,
            $providerSource
        );
}

if (
    ! str_contains(
        $providerSource,
        'admin.internal-chat.sidebar-summary'
    )
) {
    $routeAnchor =
        'admin.internal-chat.attachments.preview';

    $routePos =
        strpos(
            $providerSource,
            $routeAnchor
        );

    if ($routePos === false) {
        fwrite(
            STDERR,
            "V3.2 attachment preview route anchor tidak ditemukan.\n"
        );

        exit(5);
    }

    $statementEnd =
        strpos(
            $providerSource,
            ';',
            $routePos
        );

    if ($statementEnd === false) {
        fwrite(
            STDERR,
            "Akhir preview route statement tidak ditemukan.\n"
        );

        exit(6);
    }

    $routes = <<<'PHP'

                    Route::get(
                        'internal-chat/sidebar-summary',
                        [
                            InternalChatConversationController::class,
                            'sidebarSummary',
                        ]
                    )->name(
                        'admin.internal-chat.sidebar-summary'
                    );

                    Route::post(
                        'internal-chat/{conversationId}/preference',
                        [
                            InternalChatConversationController::class,
                            'updatePreference',
                        ]
                    )->name(
                        'admin.internal-chat.preference'
                    );

                    Route::post(
                        'internal-chat/presence/heartbeat',
                        [
                            InternalChatConversationController::class,
                            'heartbeat',
                        ]
                    )->name(
                        'admin.internal-chat.presence.heartbeat'
                    );
PHP;

    $providerSource =
        substr_replace(
            $providerSource,
            "\n"
            .$routes,
            $statementEnd
            + 1,
            0
        );
}

if (
    file_put_contents(
        $provider,
        $providerSource
    ) === false
) {
    fwrite(
        STDERR,
        "Gagal menulis provider.\n"
    );

    exit(7);
}

echo "[PASS] V3.3 routes installed.\n";

/*
|--------------------------------------------------------------------------
| 2. Notification mute guard
|--------------------------------------------------------------------------
*/

$chatController =
    $root
    .'/packages/Webkul/Admin/src/Http/Controllers/InternalCommunication/InternalChatController.php';

if (! is_file($chatController)) {
    fwrite(
        STDERR,
        "InternalChatController tidak ditemukan.\n"
    );

    exit(8);
}

$controllerSource =
    file_get_contents(
        $chatController
    );

if ($controllerSource === false) {
    fwrite(
        STDERR,
        "InternalChatController tidak dapat dibaca.\n"
    );

    exit(9);
}

backupOnce(
    $chatController,
    '.before-internal-chat-v3-3-mute-notification.bak'
);

if (
    ! str_contains(
        $controllerSource,
        'INTERNAL CHAT V3.3 MUTE NOTIFICATION GUARD'
    )
) {
    $foreachMarker =
        'foreach ($recipientIds as $recipientId) {';

    if (
        ! str_contains(
            $controllerSource,
            $foreachMarker
        )
    ) {
        fwrite(
            STDERR,
            "Recipient notification loop tidak ditemukan.\n"
            ."Patch dihentikan agar controller customized tidak rusak.\n"
        );

        exit(10);
    }

    $guard = <<<'PHP'
foreach ($recipientIds as $recipientId) {
            /* INTERNAL CHAT V3.3 MUTE NOTIFICATION GUARD */
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
                $memberPreference =
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

                $muted =
                    $memberPreference
                    && (
                        (bool) $memberPreference
                            ->mute_forever
                        || (
                            $memberPreference
                                ->muted_until
                            && now()->lt(
                                \Illuminate\Support\Carbon::parse(
                                    $memberPreference
                                        ->muted_until
                                )
                            )
                        )
                    );

                if ($muted) {
                    continue;
                }
            }
PHP;

    $controllerSource =
        str_replace(
            $foreachMarker,
            $guard,
            $controllerSource,
            $guardCount
        );

    if ($guardCount !== 1) {
        fwrite(
            STDERR,
            "Mute guard replacement count salah: {$guardCount}\n"
        );

        exit(11);
    }

    if (
        file_put_contents(
            $chatController,
            $controllerSource
        ) === false
    ) {
        fwrite(
            STDERR,
            "Gagal menulis InternalChatController.\n"
        );

        exit(12);
    }
}

echo "[PASS] Muted conversations now suppress workflow chat notifications.\n";

/*
|--------------------------------------------------------------------------
| 3. V3.3 Blade runtime
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

    exit(13);
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

    exit(14);
}

if (
    str_contains(
        $source,
        'INTERNAL CHAT V3.3 CONVERSATION MANAGEMENT'
    )
) {
    echo "[SKIP] V3.3 Blade already installed.\n";

    exit(0);
}

$requiredBlade = [
    'INTERNAL CHAT V3.2.6 UNIVERSAL PREVIEW TOOLBAR',
    'id="crm-wa-conversation-list"',
    'id="crm-wa-chat-search"',
    'id="crm-chat-messages"',
    'id="crm-chat-typing-indicator"',
    'window.crmChatReplyAction',
    'window.crmChatEditAction',
    'window.crmChatDeleteAction',
];

foreach ($requiredBlade as $marker) {
    if (
        ! str_contains(
            $source,
            $marker
        )
    ) {
        fwrite(
            STDERR,
            "Current V3.2.6 Blade tidak dikenali: {$marker}\n"
            ."Patch dihentikan agar UI customized tidak rusak.\n"
        );

        exit(15);
    }
}

backupOnce(
    $blade,
    '.before-internal-chat-v3-3.bak'
);

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

    exit(16);
}

$runtime = <<<'BLADE'

    {{-- INTERNAL CHAT V3.3 CONVERSATION MANAGEMENT --}}
    <div
        id="crm-chat-v33-config"
        data-summary-url="{{ route('admin.internal-chat.sidebar-summary') }}"
        data-preference-base="{{ url('admin/internal-chat') }}"
        data-heartbeat-url="{{ route('admin.internal-chat.presence.heartbeat') }}"
        data-active-conversation="{{ (int) ($conversation?->id ?? 0) }}"
        style="display:none;"
    ></div>

    <script>
        (() => {
            const config =
                document.getElementById(
                    'crm-chat-v33-config'
                );

            if (! config) {
                return;
            }

            const summaryUrl =
                String(
                    config.dataset.summaryUrl
                    || ''
                );

            const preferenceBase =
                String(
                    config.dataset.preferenceBase
                    || ''
                ).replace(
                    /\/+$/,
                    ''
                );

            const heartbeatUrl =
                String(
                    config.dataset.heartbeatUrl
                    || ''
                );

            const activeConversation =
                Number(
                    config.dataset.activeConversation
                    || 0
                );

            const csrfInput =
                document.querySelector(
                    '#crm-chat-send-form input[name="_token"], input[name="_token"]'
                );

            const csrf =
                csrfInput
                    ? String(
                        csrfInput.value
                        || ''
                    )
                    : '';

            const list =
                document.getElementById(
                    'crm-wa-conversation-list'
                );

            const search =
                document.getElementById(
                    'crm-wa-chat-search'
                );

            const typingIndicator =
                document.getElementById(
                    'crm-chat-typing-indicator'
                );

            /*
            |--------------------------------------------------------------------------
            | Presence label in active conversation
            |--------------------------------------------------------------------------
            */

            let activePresence =
                document.getElementById(
                    'crm-chat-v33-presence'
                );

            if (
                ! activePresence
                && typingIndicator
            ) {
                activePresence =
                    document.createElement(
                        'div'
                    );

                activePresence.id =
                    'crm-chat-v33-presence';

                activePresence.style.marginTop =
                    '3px';

                activePresence.style.fontSize =
                    '11px';

                activePresence.style.fontWeight =
                    '700';

                activePresence.style.color =
                    '#64748b';

                typingIndicator.parentNode
                    .insertBefore(
                        activePresence,
                        typingIndicator
                    );
            }

            const escapeHtml = (
                value
            ) => {
                const div =
                    document.createElement(
                        'div'
                    );

                div.textContent =
                    String(
                        value
                        || ''
                    );

                return div.innerHTML;
            };

            const conversationUrl = (
                id
            ) => {
                const url =
                    new URL(
                        window.location.href
                    );

                url.searchParams.set(
                    'conversation',
                    String(
                        id
                    )
                );

                return url.pathname
                    + url.search;
            };

            /*
            |--------------------------------------------------------------------------
            | Mute modal
            |--------------------------------------------------------------------------
            */

            const muteOverlay =
                document.createElement(
                    'div'
                );

            muteOverlay.id =
                'crm-chat-v33-mute-modal';

            muteOverlay.style.cssText =
                'display:none;'
                +'position:fixed;'
                +'inset:0;'
                +'z-index:90;'
                +'align-items:center;'
                +'justify-content:center;'
                +'padding:18px;'
                +'background:rgba(15,23,42,.42);'
                +'backdrop-filter:blur(3px);';

            muteOverlay.innerHTML =
                '<div style="'
                +'width:min(92vw,360px);'
                +'overflow:hidden;'
                +'border:1px solid #e5e7eb;'
                +'border-radius:18px;'
                +'background:#fff;'
                +'box-shadow:0 28px 80px rgba(15,23,42,.25);'
                +'">'
                +'<div style="padding:15px 16px;border-bottom:1px solid #e5e7eb;">'
                +'<div style="font-size:15px;font-weight:800;color:#0f172a;">Mute Conversation</div>'
                +'<div id="crm-chat-v33-mute-name" style="margin-top:3px;font-size:12px;color:#64748b;"></div>'
                +'</div>'
                +'<div style="padding:8px;">'
                +'<button type="button" data-v33-mute-action="mute_1_hour" style="width:100%;padding:10px 12px;text-align:left;border-radius:10px;">🔕 1 jam</button>'
                +'<button type="button" data-v33-mute-action="mute_today" style="width:100%;padding:10px 12px;text-align:left;border-radius:10px;">🔕 Sampai akhir hari</button>'
                +'<button type="button" data-v33-mute-action="mute_forever" style="width:100%;padding:10px 12px;text-align:left;border-radius:10px;">🔕 Sampai diaktifkan kembali</button>'
                +'<button type="button" data-v33-mute-action="unmute" style="width:100%;padding:10px 12px;text-align:left;border-radius:10px;">🔔 Unmute</button>'
                +'</div>'
                +'<div style="padding:10px 16px;border-top:1px solid #e5e7eb;text-align:right;">'
                +'<button type="button" id="crm-chat-v33-mute-close" class="secondary-button">Close</button>'
                +'</div>'
                +'</div>';

            document.body.appendChild(
                muteOverlay
            );

            let muteConversationId =
                0;

            const closeMute = () => {
                muteOverlay.style.display =
                    'none';

                muteConversationId =
                    0;
            };

            document
                .getElementById(
                    'crm-chat-v33-mute-close'
                )
                .addEventListener(
                    'click',
                    closeMute
                );

            muteOverlay.addEventListener(
                'click',
                (event) => {
                    if (
                        event.target
                        === muteOverlay
                    ) {
                        closeMute();
                    }
                }
            );

            const preference = async (
                conversationId,
                action
            ) => {
                const response =
                    await fetch(
                        preferenceBase
                        + '/'
                        + encodeURIComponent(
                            String(
                                conversationId
                            )
                        )
                        + '/preference',
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
                                    action:
                                        action,
                                }),
                        }
                    );

                if (! response.ok) {
                    throw new Error(
                        await response.text()
                    );
                }

                return response.json();
            };

            muteOverlay
                .querySelectorAll(
                    '[data-v33-mute-action]'
                )
                .forEach(
                    (button) => {
                        button.addEventListener(
                            'click',
                            async () => {
                                if (
                                    muteConversationId
                                    < 1
                                ) {
                                    return;
                                }

                                button.disabled =
                                    true;

                                try {
                                    await preference(
                                        muteConversationId,
                                        button.dataset
                                            .v33MuteAction
                                    );

                                    closeMute();

                                    await refreshSidebar();
                                } catch (error) {
                                    console.error(
                                        'Mute preference failed:',
                                        error
                                    );

                                    window.alert(
                                        'Mute conversation gagal.'
                                    );
                                } finally {
                                    button.disabled =
                                        false;
                                }
                            }
                        );
                    }
                );

            /*
            |--------------------------------------------------------------------------
            | Modern realtime sidebar
            |--------------------------------------------------------------------------
            */

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
                            'position:relative;'
                            +'display:flex;'
                            +'align-items:stretch;'
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
                            +'flex:1;'
                            +'display:flex;'
                            +'align-items:center;'
                            +'gap:11px;'
                            +'padding:11px 10px 11px 14px;'
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

                        link.innerHTML =
                            '<div style="position:relative;flex:0 0 auto;">'
                            +'<div style="width:40px;height:40px;border-radius:50%;display:flex;align-items:center;justify-content:center;background:#f1f5f9;color:#334155;font-size:12px;font-weight:800;">'
                            +escapeHtml(
                                row.initials
                            )
                            +'</div>'
                            +'<span style="position:absolute;right:-1px;bottom:0;width:10px;height:10px;border:2px solid #fff;border-radius:50%;background:'
                            +presenceColor
                            +';"></span>'
                            +'</div>'
                            +'<div style="min-width:0;flex:1;">'
                            +'<div style="display:flex;align-items:center;justify-content:space-between;gap:8px;">'
                            +'<div style="min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:13px;font-weight:800;color:#0f172a;">'
                            +(
                                row.pinned
                                    ? '📌 '
                                    : ''
                            )
                            +escapeHtml(
                                row.name
                            )
                            +'</div>'
                            +'<div style="flex:0 0 auto;font-size:10px;color:#94a3b8;">'
                            +escapeHtml(
                                row.time
                            )
                            +'</div>'
                            +'</div>'
                            +'<div style="margin-top:2px;display:flex;align-items:center;gap:7px;">'
                            +'<div style="min-width:0;flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:11px;color:'
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
                                    ? '<div style="flex:0 0 auto;min-width:20px;height:20px;padding:0 6px;border-radius:999px;display:flex;align-items:center;justify-content:center;background:#0f172a;color:#fff;font-size:10px;font-weight:800;">'
                                        +(
                                            unread > 99
                                                ? '99+'
                                                : unread
                                        )
                                        +'</div>'
                                    : ''
                            )
                            +'</div>'
                            +'<div style="margin-top:3px;display:flex;gap:6px;align-items:center;font-size:10px;color:#94a3b8;">'
                            +'<span>'
                            +escapeHtml(
                                row.role
                            )
                            +'</span>'
                            +'<span>·</span>'
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
                                    ? '<span>· 🔕</span>'
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
                            +'flex-direction:column;'
                            +'justify-content:center;'
                            +'gap:3px;'
                            +'padding:8px 8px 8px 0;';

                        const pin =
                            document.createElement(
                                'button'
                            );

                        pin.type =
                            'button';

                        pin.title =
                            row.pinned
                                ? 'Unpin conversation'
                                : 'Pin conversation';

                        pin.textContent =
                            row.pinned
                                ? '📍'
                                : '📌';

                        pin.style.cssText =
                            'width:28px;height:28px;border-radius:8px;font-size:12px;';

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
                                        'Pin conversation gagal.'
                                    );
                                } finally {
                                    pin.disabled =
                                        false;
                                }
                            }
                        );

                        const mute =
                            document.createElement(
                                'button'
                            );

                        mute.type =
                            'button';

                        mute.title =
                            row.muted
                                ? row.mute_label
                                : 'Mute conversation';

                        mute.textContent =
                            row.muted
                                ? '🔕'
                                : '🔔';

                        mute.style.cssText =
                            'width:28px;height:28px;border-radius:8px;font-size:12px;';

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

                /*
                 * Preserve the existing conversation search behavior.
                 */
                if (search) {
                    search.dispatchEvent(
                        new Event(
                            'input'
                        )
                    );
                }
            };

            let refreshing =
                false;

            const refreshSidebar =
                async () => {
                    if (
                        refreshing
                        || ! summaryUrl
                    ) {
                        return;
                    }

                    refreshing =
                        true;

                    try {
                        const response =
                            await fetch(
                                summaryUrl,
                                {
                                    headers: {
                                        'Accept':
                                            'application/json',

                                        'X-Requested-With':
                                            'XMLHttpRequest',
                                    },

                                    credentials:
                                        'same-origin',

                                    cache:
                                        'no-store',
                                }
                            );

                        if (! response.ok) {
                            return;
                        }

                        const data =
                            await response.json();

                        const rows =
                            Array.isArray(
                                data.conversations
                            )
                                ? data.conversations
                                : [];

                        renderSidebar(
                            rows
                        );

                        if (
                            activeConversation > 0
                            && activePresence
                        ) {
                            const active =
                                rows.find(
                                    (row) =>
                                        Number(
                                            row.id
                                        )
                                        === activeConversation
                                );

                            if (active) {
                                activePresence.textContent =
                                    (
                                        active.online
                                            ? '● '
                                            : ''
                                    )
                                    +active.presence;

                                activePresence.style.color =
                                    active.online
                                        ? '#16a34a'
                                        : '#64748b';
                            }
                        }
                    } catch (error) {
                        console.error(
                            'Sidebar refresh failed:',
                            error
                        );
                    } finally {
                        refreshing =
                            false;
                    }
                };

            /*
            |--------------------------------------------------------------------------
            | Presence heartbeat
            |--------------------------------------------------------------------------
            */

            const heartbeat =
                async () => {
                    if (! heartbeatUrl) {
                        return;
                    }

                    try {
                        await fetch(
                            heartbeatUrl,
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
                                    JSON.stringify({}),
                            }
                        );
                    } catch (error) {
                        // Presence is a best-effort UX signal.
                    }
                };

            heartbeat();

            refreshSidebar();

            window.setInterval(
                heartbeat,
                15000
            );

            window.setInterval(
                refreshSidebar,
                4000
            );

            window.crmChatV33RefreshSidebar =
                refreshSidebar;
        })();
    </script>

BLADE;

$source =
    substr_replace(
        $source,
        $runtime,
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
        "Gagal menulis V3.3 chat Blade.\n"
    );

    exit(17);
}

$written =
    file_get_contents(
        $blade
    );

$postChecks = [
    'INTERNAL CHAT V3.3 CONVERSATION MANAGEMENT',
    'crm-chat-v33-config',
    'crm-chat-v33-mute-modal',
    'window.crmChatV33RefreshSidebar',
    '15000',
    '4000',
    '📌',
    '🔕',
    'crm-chat-v33-presence',
    'window.crmChatReplyAction',
    'window.crmChatEditAction',
    'window.crmChatDeleteAction',
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

        exit(18);
    }
}

echo "[PASS] Pin Conversation UI installed.\n";
echo "[PASS] Mute Conversation UI installed.\n";
echo "[PASS] Presence heartbeat / Last Active installed.\n";
echo "[PASS] Sidebar auto-refresh every 4 seconds installed.\n";
echo "[PASS] Existing V3.2.6 features preserved.\n";
echo "[PASS] Migration required before runtime test.\n";
