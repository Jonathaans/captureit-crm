<x-admin::layouts>
    <x-slot:title>
        Internal Chat
    </x-slot>

    @php
        $currentUser = auth()->guard('user')->user();

        $lastMessageId =
            $messages->isNotEmpty()
                ? $messages->max('id')
                : 0;

        $makeInitials = function ($value) {
            $parts = preg_split('/\s+/', trim((string) $value)) ?: [];
            $initials = '';

            foreach (array_slice($parts, 0, 2) as $part) {
                $initials .= strtoupper(mb_substr($part, 0, 1));
            }

            return $initials ?: 'U';
        };
    @endphp

    <style>
        .crm-modern-chat-page {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .crm-modern-chat-hero,
        .crm-modern-chat-shell,
        .crm-modern-chat-sidebar,
        .crm-modern-chat-main,
        .crm-modern-chat-card,
        .crm-modern-empty,
        .crm-modern-composer {
            background: linear-gradient(180deg, #ffffff 0%, #fcfcfd 100%);
            border: 1px solid #e5e7eb;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.04);
        }

        .crm-modern-chat-hero {
            padding: 22px 24px;
            position: relative;
            overflow: hidden;
        }

        .crm-modern-chat-hero::after {
            content: '';
            position: absolute;
            inset: auto -60px -60px auto;
            width: 180px;
            height: 180px;
            background: radial-gradient(circle, rgba(250, 204, 21, 0.18), rgba(250, 204, 21, 0));
            pointer-events: none;
        }

        .crm-modern-chat-eyebrow {
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: #6b7280;
        }

        .crm-modern-chat-title {
            margin-top: 6px;
            font-size: 34px;
            line-height: 1.05;
            font-weight: 800;
            color: #111827;
        }

        .crm-modern-chat-subtitle {
            margin-top: 8px;
            max-width: 760px;
            color: #6b7280;
            font-size: 14px;
        }

        .crm-modern-chat-hero-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
        }

        .crm-modern-pill-button,
        .crm-modern-outline-button,
        .crm-modern-send-button,
        .crm-modern-file-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            border-radius: 999px;
            font-weight: 700;
            font-size: 13px;
            transition: all 0.18s ease;
            text-decoration: none;
            cursor: pointer;
        }

        .crm-modern-pill-button {
            background: #111827;
            color: #ffffff;
            border: 1px solid #111827;
            padding: 10px 16px;
        }

        .crm-modern-pill-button:hover {
            background: #000000;
            transform: translateY(-1px);
        }

        .crm-modern-outline-button {
            padding: 10px 16px;
            border: 1px solid #d1d5db;
            color: #111827;
            background: #ffffff;
        }

        .crm-modern-outline-button:hover,
        .crm-modern-file-button:hover {
            border-color: #9ca3af;
            background: #f9fafb;
        }

        .crm-modern-chat-shell {
            display: grid;
            grid-template-columns: 360px minmax(0, 1fr);
            gap: 16px;
            padding: 16px;
            min-height: calc(100vh - 210px);
        }

        .crm-modern-chat-sidebar,
        .crm-modern-chat-main {
            min-width: 0;
            overflow: hidden;
        }

        .crm-modern-chat-sidebar {
            display: flex;
            flex-direction: column;
        }

        .crm-modern-sidebar-header {
            padding: 18px 18px 12px;
            border-bottom: 1px solid #eef2f7;
        }

        .crm-modern-sidebar-title {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }

        .crm-modern-sidebar-title h2 {
            font-size: 18px;
            font-weight: 800;
            color: #111827;
        }

        .crm-modern-sidebar-badge {
            min-width: 28px;
            height: 28px;
            border-radius: 999px;
            background: #f3f4f6;
            color: #374151;
            font-size: 12px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0 10px;
        }

        .crm-modern-search-wrap {
            margin-top: 14px;
            position: relative;
        }

        .crm-modern-search-wrap input {
            width: 100%;
            height: 44px;
            border-radius: 14px;
            border: 1px solid #e5e7eb;
            background: #f9fafb;
            padding: 0 14px 0 42px;
            font-size: 13px;
            color: #111827;
        }

        .crm-modern-search-wrap input:focus,
        .crm-modern-composer textarea:focus {
            outline: none;
            border-color: #f59e0b;
            box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.12);
            background: #ffffff;
        }

        .crm-modern-search-icon {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
            font-size: 14px;
        }

        .crm-modern-sidebar-scroll {
            flex: 1;
            overflow-y: auto;
            padding: 10px;
            max-height: calc(100vh - 320px);
        }

        .crm-modern-sidebar-section {
            margin-top: 8px;
        }

        .crm-modern-sidebar-label {
            padding: 10px 10px 8px;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: #9ca3af;
        }

        .crm-modern-conversation-card,
        .crm-modern-user-card {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            width: 100%;
            border: 1px solid transparent;
            background: #ffffff;
            border-radius: 16px;
            padding: 12px;
            margin-bottom: 10px;
            text-align: left;
            text-decoration: none;
            color: #111827;
            transition: all 0.18s ease;
            cursor: pointer;
        }

        .crm-modern-conversation-card:hover,
        .crm-modern-user-card:hover {
            border-color: #fde68a;
            background: #fffdf7;
            transform: translateY(-1px);
            box-shadow: 0 12px 24px rgba(245, 158, 11, 0.08);
        }

        .crm-modern-conversation-card.active {
            border-color: #f59e0b;
            background: linear-gradient(180deg, #fffaf0 0%, #fffdf8 100%);
            box-shadow: 0 14px 26px rgba(245, 158, 11, 0.12);
        }

        .crm-modern-avatar {
            width: 46px;
            height: 46px;
            flex: 0 0 46px;
            border-radius: 16px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            font-weight: 800;
            color: #92400e;
            background: linear-gradient(135deg, #fde68a 0%, #fcd34d 100%);
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.5);
        }

        .crm-modern-avatar.small {
            width: 38px;
            height: 38px;
            flex-basis: 38px;
            border-radius: 14px;
            font-size: 12px;
        }

        .crm-modern-card-body {
            min-width: 0;
            flex: 1;
        }

        .crm-modern-card-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
        }

        .crm-modern-card-name {
            font-size: 14px;
            font-weight: 800;
            color: #111827;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .crm-modern-card-role {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin-top: 4px;
            font-size: 11px;
            font-weight: 700;
            color: #92400e;
            background: #fffbeb;
            border: 1px solid #fde68a;
            border-radius: 999px;
            padding: 4px 8px;
        }

        .crm-modern-card-preview,
        .crm-modern-card-email {
            margin-top: 8px;
            font-size: 12px;
            color: #6b7280;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .crm-modern-list-empty {
            padding: 18px 14px;
            text-align: center;
            color: #9ca3af;
            font-size: 13px;
        }

        .crm-modern-chat-main {
            display: flex;
            flex-direction: column;
            min-height: 700px;
        }

        .crm-modern-main-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            padding: 18px 20px;
            border-bottom: 1px solid #eef2f7;
        }

        .crm-modern-main-header-left {
            display: flex;
            align-items: center;
            gap: 14px;
            min-width: 0;
        }

        .crm-modern-main-name {
            font-size: 20px;
            font-weight: 800;
            color: #111827;
        }

        .crm-modern-main-meta {
            margin-top: 4px;
            font-size: 12px;
            color: #6b7280;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .crm-modern-main-chip {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            border-radius: 999px;
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            color: #374151;
            font-size: 12px;
            font-weight: 700;
        }

        .crm-modern-messages {
            flex: 1;
            overflow-y: auto;
            padding: 24px 22px;
            background: linear-gradient(180deg, #fbfbfc 0%, #f8fafc 100%);
            max-height: calc(100vh - 410px);
            min-height: 470px;
        }

        .crm-modern-date-banner {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px;
            padding: 6px 12px;
            border-radius: 999px;
            background: #ffffff;
            border: 1px solid #e5e7eb;
            color: #6b7280;
            font-size: 11px;
            font-weight: 700;
        }

        .crm-modern-message {
            display: flex;
            margin-bottom: 16px;
        }

        .crm-modern-message.mine {
            justify-content: flex-end;
        }

        .crm-modern-message-group {
            display: flex;
            gap: 10px;
            max-width: min(760px, 88%);
        }

        .crm-modern-message.mine .crm-modern-message-group {
            flex-direction: row-reverse;
        }

        .crm-modern-message-content {
            min-width: 0;
        }

        .crm-modern-message-sender {
            margin-bottom: 6px;
            font-size: 11px;
            font-weight: 800;
            color: #6b7280;
        }

        .crm-modern-message.mine .crm-modern-message-sender {
            text-align: right;
        }

        .crm-modern-bubble {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            color: #111827;
            padding: 12px 14px;
            border-radius: 18px 18px 18px 6px;
            box-shadow: 0 10px 20px rgba(15, 23, 42, 0.03);
            white-space: pre-wrap;
            word-break: break-word;
        }

        .crm-modern-message.mine .crm-modern-bubble {
            background: linear-gradient(135deg, #111827 0%, #1f2937 100%);
            border-color: #111827;
            color: #ffffff;
            border-radius: 18px 18px 6px 18px;
        }

        .crm-modern-attachments {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 10px;
        }

        .crm-modern-attachment {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            max-width: 100%;
            padding: 8px 10px;
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.12);
            border: 1px solid rgba(148, 163, 184, 0.18);
            color: inherit;
            text-decoration: none;
            font-size: 11px;
            font-weight: 700;
        }

        .crm-modern-message:not(.mine) .crm-modern-attachment {
            background: #f9fafb;
            border-color: #e5e7eb;
            color: #374151;
        }

        .crm-modern-attachment:hover {
            text-decoration: none;
            opacity: 0.92;
        }

        .crm-modern-message-meta {
            margin-top: 7px;
            font-size: 10px;
            color: #9ca3af;
        }

        .crm-modern-message.mine .crm-modern-message-meta {
            text-align: right;
        }

        .crm-modern-composer-wrap {
            padding: 16px;
            border-top: 1px solid #eef2f7;
            background: #ffffff;
        }

        .crm-modern-composer {
            padding: 14px;
        }

        .crm-modern-composer textarea {
            width: 100%;
            min-height: 92px;
            border-radius: 16px;
            border: 1px solid #e5e7eb;
            background: #f9fafb;
            padding: 14px 15px;
            resize: vertical;
            font-size: 13px;
            color: #111827;
        }

        .crm-modern-composer-footer {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-top: 12px;
        }

        .crm-modern-composer-tools {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 10px;
        }

        .crm-modern-file-button {
            padding: 10px 14px;
            border: 1px solid #d1d5db;
            background: #ffffff;
            color: #111827;
        }

        .crm-modern-send-button {
            padding: 11px 18px;
            border: 0;
            background: linear-gradient(135deg, #f59e0b 0%, #fbbf24 100%);
            color: #111827;
            box-shadow: 0 10px 18px rgba(245, 158, 11, 0.18);
        }

        .crm-modern-send-button:hover {
            transform: translateY(-1px);
            box-shadow: 0 14px 24px rgba(245, 158, 11, 0.22);
        }

        .crm-modern-send-button:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .crm-modern-helper-text {
            font-size: 11px;
            color: #9ca3af;
        }

        .crm-modern-feedback {
            display: none;
            margin-top: 10px;
            padding: 10px 12px;
            border-radius: 14px;
            font-size: 12px;
            font-weight: 700;
        }

        .crm-modern-feedback.show {
            display: block;
        }

        .crm-modern-feedback.error {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #b91c1c;
        }

        .crm-modern-feedback.success {
            background: #ecfdf5;
            border: 1px solid #a7f3d0;
            color: #047857;
        }

        .crm-modern-file-list {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 10px;
        }

        .crm-modern-file-chip {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 7px 10px;
            border-radius: 999px;
            background: #fffbeb;
            border: 1px solid #fde68a;
            color: #92400e;
            font-size: 11px;
            font-weight: 700;
        }

        .crm-modern-empty {
            margin: auto;
            max-width: 520px;
            padding: 30px 24px;
            text-align: center;
        }

        .crm-modern-empty-icon {
            width: 72px;
            height: 72px;
            margin: 0 auto 16px;
            border-radius: 24px;
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            color: #92400e;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            font-weight: 800;
        }

        .crm-modern-empty h3 {
            font-size: 22px;
            font-weight: 800;
            color: #111827;
        }

        .crm-modern-empty p {
            margin-top: 8px;
            color: #6b7280;
            font-size: 14px;
            line-height: 1.6;
        }

        .crm-modern-fade {
            animation: crmModernFadeIn 0.18s ease;
        }

        @keyframes crmModernFadeIn {
            from {
                opacity: 0;
                transform: translateY(8px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @media (max-width: 1180px) {
            .crm-modern-chat-shell {
                grid-template-columns: 320px minmax(0, 1fr);
            }
        }

        @media (max-width: 980px) {
            .crm-modern-chat-shell {
                grid-template-columns: 1fr;
            }

            .crm-modern-sidebar-scroll {
                max-height: 360px;
            }

            .crm-modern-chat-main {
                min-height: 620px;
            }
        }

        @media (max-width: 640px) {
            .crm-modern-chat-hero,
            .crm-modern-chat-shell {
                padding: 14px;
            }

            .crm-modern-chat-title {
                font-size: 28px;
            }

            .crm-modern-main-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .crm-modern-messages {
                padding: 16px;
            }

            .crm-modern-message-group {
                max-width: 100%;
            }
        }
    </style>

    <div class="crm-modern-chat-page">
        <section class="crm-modern-chat-hero">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <div class="crm-modern-chat-eyebrow">
                        Internal Communication
                    </div>

                    <h1 class="crm-modern-chat-title">
                        Internal Chat
                    </h1>

                    <p class="crm-modern-chat-subtitle">
                        Direct message antar akun CRM dengan tampilan yang lebih rapi, fokus, dan nyaman dipakai untuk koordinasi cepat antar tim sales, admin, warehouse, dan operasional.
                    </p>
                </div>

                <div class="crm-modern-chat-hero-actions">
                    <div class="crm-modern-main-chip">
                        💬 {{ $conversationList->count() }} Conversation{{ $conversationList->count() === 1 ? '' : 's' }}
                    </div>

                    <a
                        href="{{ route('admin.internal-notifications.index') }}"
                        class="crm-modern-outline-button"
                    >
                        🔔 Notifications
                    </a>
                </div>
            </div>
        </section>

        <section class="crm-modern-chat-shell">
            <aside class="crm-modern-chat-sidebar">
                <div class="crm-modern-sidebar-header">
                    <div class="crm-modern-sidebar-title">
                        <h2>Percakapan</h2>

                        <span class="crm-modern-sidebar-badge">
                            {{ $conversationList->count() }}
                        </span>
                    </div>

                    <div class="crm-modern-search-wrap">
                        <span class="crm-modern-search-icon">🔎</span>

                        <input
                            type="text"
                            id="crm-modern-chat-search"
                            placeholder="Cari nama, role, email, atau isi percakapan..."
                        >
                    </div>
                </div>

                <div class="crm-modern-sidebar-scroll" id="crm-modern-chat-sidebar-scroll">
                    <div class="crm-modern-sidebar-section" data-chat-section="conversations">
                        <div class="crm-modern-sidebar-label">
                            Recent Conversations
                        </div>

                        <div data-chat-list="conversations">
                            @forelse ($conversationList as $row)
                                @php
                                    $otherName = $row->other?->name ?: 'User';
                                    $lastPreview = $row->last_message?->body
                                        ? \Illuminate\Support\Str::limit($row->last_message->body, 68)
                                        : 'Belum ada isi pesan. Buka conversation untuk mulai koordinasi.';
                                @endphp

                                <a
                                    href="{{ route('admin.internal-chat.index', ['conversation' => $row->id]) }}"
                                    class="crm-modern-conversation-card {{ $conversation?->id === $row->id ? 'active' : '' }}"
                                    data-search-text="{{ strtolower($otherName.' '.($row->other?->role_name ?: '').' '.$lastPreview) }}"
                                >
                                    <div class="crm-modern-avatar">
                                        {{ $makeInitials($otherName) }}
                                    </div>

                                    <div class="crm-modern-card-body">
                                        <div class="crm-modern-card-top">
                                            <div class="crm-modern-card-name">
                                                {{ $otherName }}
                                            </div>
                                        </div>

                                        <div class="crm-modern-card-role">
                                            {{ $row->other?->role_name ?: 'Internal User' }}
                                        </div>

                                        <div class="crm-modern-card-preview">
                                            {{ $lastPreview }}
                                        </div>
                                    </div>
                                </a>
                            @empty
                                <div class="crm-modern-list-empty">
                                    Belum ada conversation aktif.
                                </div>
                            @endforelse
                        </div>
                    </div>

                    <div class="crm-modern-sidebar-section" data-chat-section="users">
                        <div class="crm-modern-sidebar-label">
                            Mulai Chat Baru
                        </div>

                        <div data-chat-list="users">
                            @forelse ($users as $chatUser)
                                <form
                                    method="POST"
                                    action="{{ route('admin.internal-chat.direct', $chatUser->id) }}"
                                    class="m-0"
                                    data-search-text="{{ strtolower($chatUser->name.' '.($chatUser->role_name ?: '').' '.$chatUser->email) }}"
                                >
                                    @csrf

                                    <button
                                        type="submit"
                                        class="crm-modern-user-card"
                                    >
                                        <div class="crm-modern-avatar small">
                                            {{ $makeInitials($chatUser->name) }}
                                        </div>

                                        <div class="crm-modern-card-body">
                                            <div class="crm-modern-card-name">
                                                {{ $chatUser->name }}
                                            </div>

                                            <div class="crm-modern-card-role">
                                                {{ $chatUser->role_name ?: 'Internal User' }}
                                            </div>

                                            <div class="crm-modern-card-email">
                                                {{ $chatUser->email }}
                                            </div>
                                        </div>
                                    </button>
                                </form>
                            @empty
                                <div class="crm-modern-list-empty">
                                    Tidak ada user lain yang bisa diajak chat.
                                </div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </aside>

            <section class="crm-modern-chat-main">
                @if ($conversation)
                    <div class="crm-modern-main-header">
                        <div class="crm-modern-main-header-left">
                            <div class="crm-modern-avatar">
                                {{ $makeInitials($activeOtherUser?->name ?: 'User') }}
                            </div>

                            <div class="min-w-0">
                                <div class="crm-modern-main-name">
                                    {{ $activeOtherUser?->name ?: 'Internal Chat' }}
                                </div>

                                <div class="crm-modern-main-meta">
                                    {{ $activeOtherUser?->role_name ?: '-' }}
                                    · {{ $activeOtherUser?->email ?: '-' }}
                                </div>
                            </div>
                        </div>

                        <div class="crm-modern-main-chip">
                            🔒 Direct & Private Conversation
                        </div>
                    </div>

                    <div
                        id="crm-chat-messages"
                        class="crm-modern-messages"
                        data-last-id="{{ $lastMessageId }}"
                        data-current-user-id="{{ $currentUser->id }}"
                    >
                        <div class="text-center">
                            <span class="crm-modern-date-banner">
                                Conversation Ready
                            </span>
                        </div>

                        @foreach ($messages as $message)
                            @php
                                $isMine = (int) $message->user_id === (int) $currentUser->id;
                            @endphp

                            <div
                                class="crm-modern-message {{ $isMine ? 'mine' : '' }} crm-modern-fade"
                                data-message-id="{{ $message->id }}"
                            >
                                <div class="crm-modern-message-group">
                                    <div class="crm-modern-avatar small">
                                        {{ $makeInitials($senderNames[$message->user_id] ?? 'User') }}
                                    </div>

                                    <div class="crm-modern-message-content">
                                        <div class="crm-modern-message-sender">
                                            {{ $senderNames[$message->user_id] ?? 'User' }}
                                        </div>

                                        <div class="crm-modern-bubble">
                                            @if ($message->body)
                                                <div>{{ $message->body }}</div>
                                            @endif

                                            @if ($message->attachments->isNotEmpty())
                                                <div class="crm-modern-attachments">
                                                    @foreach ($message->attachments as $attachment)
                                                        <a
                                                            href="{{ route('admin.internal-chat.attachments.download', $attachment->id) }}"
                                                            class="crm-modern-attachment"
                                                        >
                                                            <span>📎</span>
                                                            <span>
                                                                {{ $attachment->original_name }}
                                                                ({{ number_format($attachment->size / 1024, 1) }} KB)
                                                            </span>
                                                        </a>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </div>

                                        <div class="crm-modern-message-meta">
                                            {{ $message->created_at?->format('d M Y · H:i') }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="crm-modern-composer-wrap">
                        <form
                            id="crm-chat-send-form"
                            method="POST"
                            enctype="multipart/form-data"
                            action="{{ route('admin.internal-chat.send', $conversation->id) }}"
                            class="crm-modern-composer"
                        >
                            @csrf

                            <textarea
                                name="body"
                                id="crm-chat-body"
                                placeholder="Tulis pesan internal, update progress, atau instruksi untuk tim..."
                            ></textarea>

                            <input
                                type="file"
                                id="crm-chat-attachments"
                                name="attachments[]"
                                multiple
                                hidden
                            >

                            <div id="crm-chat-file-list" class="crm-modern-file-list"></div>

                            <div id="crm-chat-feedback" class="crm-modern-feedback"></div>

                            <div class="crm-modern-composer-footer">
                                <div class="crm-modern-composer-tools">
                                    <label
                                        for="crm-chat-attachments"
                                        class="crm-modern-file-button"
                                    >
                                        📎 Tambah Attachment
                                    </label>

                                    <div class="crm-modern-helper-text">
                                        Max 5 file, 10 MB per file.
                                    </div>
                                </div>

                                <button
                                    type="submit"
                                    class="crm-modern-send-button"
                                >
                                    ➤ Send Message
                                </button>
                            </div>
                        </form>
                    </div>
                @else
                    <div class="crm-modern-empty">
                        <div class="crm-modern-empty-icon">
                            💬
                        </div>

                        <h3>Pilih Conversation</h3>

                        <p>
                            Pilih percakapan yang sudah ada di sisi kiri, atau mulai chat baru dengan user lain. Tampilan ini dibuat lebih modern agar tim lebih nyaman melakukan koordinasi harian langsung dari CRM.
                        </p>
                    </div>
                @endif
            </section>
        </section>
    </div>

    <script>
        (() => {
            const searchInput = document.getElementById('crm-modern-chat-search');

            if (! searchInput) {
                return;
            }

            const filterLists = () => {
                const keyword = (searchInput.value || '').toLowerCase().trim();
                const filterable = document.querySelectorAll('[data-search-text]');

                filterable.forEach((node) => {
                    const haystack = String(node.dataset.searchText || '').toLowerCase();
                    const match = keyword === '' || haystack.includes(keyword);
                    node.style.display = match ? '' : 'none';
                });
            };

            searchInput.addEventListener('input', filterLists);
        })();
    </script>

    @if ($conversation)
        <script>
            (() => {
                const currentUserId = {{ (int) $currentUser->id }};
                const messagesRoot = document.getElementById('crm-chat-messages');
                const form = document.getElementById('crm-chat-send-form');
                const pollUrl = @json(route('admin.internal-chat.messages', $conversation->id));
                const fileInput = document.getElementById('crm-chat-attachments');
                const fileList = document.getElementById('crm-chat-file-list');
                const feedback = document.getElementById('crm-chat-feedback');

                const makeInitials = (value) => {
                    const words = String(value || 'User').trim().split(/\s+/).filter(Boolean);
                    const initials = words.slice(0, 2).map((word) => word.charAt(0).toUpperCase()).join('');
                    return initials || 'U';
                };

                const showFeedback = (message, type = 'error') => {
                    if (! feedback) {
                        return;
                    }

                    feedback.className = 'crm-modern-feedback show ' + type;
                    feedback.textContent = message;

                    window.clearTimeout(feedback.__timer);
                    feedback.__timer = window.setTimeout(() => {
                        feedback.className = 'crm-modern-feedback';
                        feedback.textContent = '';
                    }, 3200);
                };

                const renderFileList = () => {
                    if (! fileList || ! fileInput) {
                        return;
                    }

                    fileList.innerHTML = '';

                    Array.from(fileInput.files || []).forEach((file) => {
                        const chip = document.createElement('div');
                        chip.className = 'crm-modern-file-chip';
                        chip.textContent = '📎 ' + file.name + ' (' + Math.round((file.size || 0) / 1024) + ' KB)';
                        fileList.appendChild(chip);
                    });
                };

                const appendMessage = (message) => {
                    if (
                        document.querySelector(
                            `[data-message-id="${message.id}"]`
                        )
                    ) {
                        return;
                    }

                    const wrapper = document.createElement('div');
                    const isMine = Number(message.user_id) === currentUserId;

                    wrapper.className = 'crm-modern-message crm-modern-fade' + (isMine ? ' mine' : '');
                    wrapper.dataset.messageId = message.id;

                    const group = document.createElement('div');
                    group.className = 'crm-modern-message-group';

                    const avatar = document.createElement('div');
                    avatar.className = 'crm-modern-avatar small';
                    avatar.textContent = makeInitials(message.sender_name || 'User');

                    const content = document.createElement('div');
                    content.className = 'crm-modern-message-content';

                    const sender = document.createElement('div');
                    sender.className = 'crm-modern-message-sender';
                    sender.textContent = message.sender_name || 'User';

                    const bubble = document.createElement('div');
                    bubble.className = 'crm-modern-bubble';

                    if (message.body) {
                        const text = document.createElement('div');
                        text.textContent = message.body;
                        bubble.appendChild(text);
                    }

                    if ((message.attachments || []).length) {
                        const attachments = document.createElement('div');
                        attachments.className = 'crm-modern-attachments';

                        (message.attachments || []).forEach((attachment) => {
                            const link = document.createElement('a');
                            link.href = attachment.download_url;
                            link.className = 'crm-modern-attachment';
                            link.innerHTML = '<span>📎</span><span>'
                                + (attachment.name || 'Attachment')
                                + ' ('
                                + (Math.round(((attachment.size || 0) / 1024) * 10) / 10)
                                + ' KB)</span>';
                            attachments.appendChild(link);
                        });

                        bubble.appendChild(attachments);
                    }

                    const meta = document.createElement('div');
                    meta.className = 'crm-modern-message-meta';
                    meta.textContent = message.created_at || '';

                    content.append(sender, bubble, meta);
                    group.append(avatar, content);
                    wrapper.appendChild(group);
                    messagesRoot.appendChild(wrapper);

                    messagesRoot.dataset.lastId = Math.max(
                        Number(messagesRoot.dataset.lastId || 0),
                        Number(message.id || 0)
                    );

                    messagesRoot.scrollTop = messagesRoot.scrollHeight;
                };

                const pollMessages = async () => {
                    try {
                        const after = Number(messagesRoot.dataset.lastId || 0);

                        const response = await fetch(
                            pollUrl + '?after=' + encodeURIComponent(after),
                            {
                                headers: {
                                    'Accept': 'application/json',
                                    'X-Requested-With': 'XMLHttpRequest',
                                },
                                credentials: 'same-origin',
                                cache: 'no-store',
                            }
                        );

                        if (! response.ok) {
                            return;
                        }

                        const data = await response.json();
                        (data.messages || []).forEach(appendMessage);
                    } catch (error) {
                        // Keep chat usable even during temporary connection issues.
                    }
                };

                form.addEventListener('submit', async (event) => {
                    event.preventDefault();

                    const button = form.querySelector('button[type="submit"]');
                    const bodyField = document.getElementById('crm-chat-body');
                    const original = button.textContent;

                    button.disabled = true;
                    button.textContent = 'Sending...';

                    try {
                        const response = await fetch(
                            form.action,
                            {
                                method: 'POST',
                                headers: {
                                    'Accept': 'application/json',
                                    'X-Requested-With': 'XMLHttpRequest',
                                },
                                credentials: 'same-origin',
                                body: new FormData(form),
                            }
                        );

                        if (! response.ok) {
                            const text = await response.text();
                            throw new Error(text || 'Send failed');
                        }

                        const message = await response.json();
                        appendMessage(message);
                        form.reset();
                        renderFileList();
                        if (bodyField) {
                            bodyField.focus();
                        }
                        showFeedback('Pesan berhasil dikirim.', 'success');
                    } catch (error) {
                        showFeedback('Pesan gagal dikirim. Coba kembali.', 'error');
                    } finally {
                        button.disabled = false;
                        button.textContent = original;
                    }
                });

                if (fileInput) {
                    fileInput.addEventListener('change', renderFileList);
                }

                messagesRoot.scrollTop = messagesRoot.scrollHeight;
                window.setInterval(pollMessages, 5000);
            })();
        </script>
    @endif
</x-admin::layouts>
