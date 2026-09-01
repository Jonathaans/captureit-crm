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
    @endphp

    <style>
        .crm-chat-shell {
            display: grid;
            grid-template-columns: 330px minmax(0, 1fr);
            gap: 14px;
            min-height: calc(100vh - 170px);
        }

        .crm-chat-sidebar,
        .crm-chat-main {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            overflow: hidden;
        }

        .crm-chat-sidebar-scroll {
            max-height: calc(100vh - 260px);
            overflow-y: auto;
        }

        .crm-chat-person {
            display: block;
            width: 100%;
            padding: 11px 13px;
            border: 0;
            border-bottom: 1px solid #f3f4f6;
            background: transparent;
            text-align: left;
            cursor: pointer;
            text-decoration: none;
            color: #111827;
        }

        .crm-chat-person:hover,
        .crm-chat-person.active {
            background: #f3f4f6;
        }

        .crm-chat-person-name {
            font-weight: 800;
            font-size: 13px;
        }

        .crm-chat-person-meta {
            margin-top: 2px;
            color: #6b7280;
            font-size: 10px;
        }

        .crm-chat-main {
            display: flex;
            flex-direction: column;
            min-height: 620px;
        }

        .crm-chat-header {
            padding: 14px 16px;
            border-bottom: 1px solid #e5e7eb;
        }

        .crm-chat-messages {
            flex: 1;
            overflow-y: auto;
            padding: 18px;
            background: #f9fafb;
            max-height: calc(100vh - 330px);
            min-height: 400px;
        }

        .crm-chat-message {
            margin-bottom: 12px;
            display: flex;
            flex-direction: column;
            align-items: flex-start;
        }

        .crm-chat-message.mine {
            align-items: flex-end;
        }

        .crm-chat-bubble {
            max-width: min(680px, 82%);
            padding: 9px 11px;
            border-radius: 12px;
            background: #ffffff;
            border: 1px solid #e5e7eb;
            white-space: pre-wrap;
            word-break: break-word;
        }

        .crm-chat-message.mine .crm-chat-bubble {
            background: #111827;
            color: #ffffff;
            border-color: #111827;
        }

        .crm-chat-meta {
            margin-top: 4px;
            color: #9ca3af;
            font-size: 9px;
        }

        .crm-chat-attachment {
            display: block;
            margin-top: 6px;
            font-size: 10px;
            color: inherit;
            text-decoration: underline;
        }

        .crm-chat-form {
            padding: 14px;
            border-top: 1px solid #e5e7eb;
        }

        .crm-chat-form textarea {
            width: 100%;
            min-height: 78px;
            resize: vertical;
            border: 1px solid #d1d5db;
            border-radius: 9px;
            padding: 10px;
        }

        @media (max-width: 900px) {
            .crm-chat-shell {
                grid-template-columns: 1fr;
            }

            .crm-chat-sidebar-scroll {
                max-height: 280px;
            }
        }
    </style>

    <div class="mb-4 rounded-xl border bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <div class="text-xs font-bold uppercase text-gray-500">
                    Internal Communication
                </div>

                <h1 class="mt-1 text-2xl font-bold">
                    Internal Chat
                </h1>

                <p class="mt-1 text-sm text-gray-500">
                    Direct message antar akun CRM. Semua akses conversation dikunci di backend.
                </p>
            </div>

            <a
                href="{{ route('admin.internal-notifications.index') }}"
                class="secondary-button"
            >
                🔔 Notifications
            </a>
        </div>
    </div>

    <div class="crm-chat-shell">
        <aside class="crm-chat-sidebar">
            <div class="border-b p-3">
                <div class="font-bold">
                    Conversations
                </div>
            </div>

            <div class="crm-chat-sidebar-scroll">
                @foreach ($conversationList as $row)
                    <a
                        href="{{ route('admin.internal-chat.index', ['conversation' => $row->id]) }}"
                        class="crm-chat-person {{ $conversation?->id === $row->id ? 'active' : '' }}"
                    >
                        <div class="crm-chat-person-name">
                            {{ $row->other?->name ?: 'User' }}
                        </div>

                        <div class="crm-chat-person-meta">
                            {{ $row->other?->role_name ?: '-' }}

                            @if ($row->last_message?->body)
                                · {{ \Illuminate\Support\Str::limit($row->last_message->body, 45) }}
                            @endif
                        </div>
                    </a>
                @endforeach

                <div class="border-y bg-gray-50 p-3 text-xs font-bold uppercase text-gray-500">
                    Start New Chat
                </div>

                @foreach ($users as $chatUser)
                    <form
                        method="POST"
                        action="{{ route('admin.internal-chat.direct', $chatUser->id) }}"
                    >
                        @csrf

                        <button
                            type="submit"
                            class="crm-chat-person"
                        >
                            <div class="crm-chat-person-name">
                                {{ $chatUser->name }}
                            </div>

                            <div class="crm-chat-person-meta">
                                {{ $chatUser->role_name ?: '-' }}
                                · {{ $chatUser->email }}
                            </div>
                        </button>
                    </form>
                @endforeach
            </div>
        </aside>

        <section class="crm-chat-main">
            @if ($conversation)
                <div class="crm-chat-header">
                    <div class="font-bold">
                        {{ $activeOtherUser?->name ?: 'Internal Chat' }}
                    </div>

                    <div class="mt-1 text-xs text-gray-500">
                        {{ $activeOtherUser?->role_name ?: '-' }}
                        · {{ $activeOtherUser?->email ?: '-' }}
                    </div>
                </div>

                <div
                    id="crm-chat-messages"
                    class="crm-chat-messages"
                    data-last-id="{{ $lastMessageId }}"
                    data-current-user-id="{{ $currentUser->id }}"
                >
                    @foreach ($messages as $message)
                        <div
                            class="crm-chat-message {{ (int) $message->user_id === (int) $currentUser->id ? 'mine' : '' }}"
                            data-message-id="{{ $message->id }}"
                        >
                            <div class="crm-chat-bubble">
                                @if ($message->body)
                                    {{ $message->body }}
                                @endif

                                @foreach ($message->attachments as $attachment)
                                    <a
                                        href="{{ route('admin.internal-chat.attachments.download', $attachment->id) }}"
                                        class="crm-chat-attachment"
                                    >
                                        📎 {{ $attachment->original_name }}
                                        ({{ number_format($attachment->size / 1024, 1) }} KB)
                                    </a>
                                @endforeach
                            </div>

                            <div class="crm-chat-meta">
                                {{ $senderNames[$message->user_id] ?? 'User' }}
                                · {{ $message->created_at?->format('d M H:i') }}
                            </div>
                        </div>
                    @endforeach
                </div>

                <form
                    id="crm-chat-send-form"
                    method="POST"
                    enctype="multipart/form-data"
                    action="{{ route('admin.internal-chat.send', $conversation->id) }}"
                    class="crm-chat-form"
                >
                    @csrf

                    <textarea
                        name="body"
                        placeholder="Tulis pesan internal..."
                    ></textarea>

                    <div class="mt-2 flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <input
                                type="file"
                                name="attachments[]"
                                multiple
                            >

                            <div class="mt-1 text-[10px] text-gray-500">
                                Max 5 files, 10 MB/file.
                            </div>
                        </div>

                        <button class="primary-button">
                            Send
                        </button>
                    </div>
                </form>
            @else
                <div class="flex flex-1 items-center justify-center p-10 text-center text-gray-500">
                    Pilih conversation atau mulai chat baru.
                </div>
            @endif
        </section>
    </div>

    @if ($conversation)
        <script>
            (() => {
                const currentUserId = {{ (int) $currentUser->id }};
                const messagesRoot = document.getElementById('crm-chat-messages');
                const form = document.getElementById('crm-chat-send-form');
                const pollUrl = @json(route('admin.internal-chat.messages', $conversation->id));

                const escapeHtml = (value) => {
                    const div = document.createElement('div');
                    div.textContent = value ?? '';
                    return div.innerHTML;
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

                    wrapper.className =
                        'crm-chat-message'
                        + (
                            Number(message.user_id) === currentUserId
                                ? ' mine'
                                : ''
                        );

                    wrapper.dataset.messageId = message.id;

                    const bubble = document.createElement('div');
                    bubble.className = 'crm-chat-bubble';

                    if (message.body) {
                        const text = document.createElement('div');
                        text.textContent = message.body;
                        bubble.appendChild(text);
                    }

                    (message.attachments || []).forEach((attachment) => {
                        const link = document.createElement('a');
                        link.href = attachment.download_url;
                        link.className = 'crm-chat-attachment';
                        link.textContent =
                            '📎 '
                            + attachment.name
                            + ' ('
                            + Math.round((attachment.size || 0) / 1024 * 10) / 10
                            + ' KB)';

                        bubble.appendChild(link);
                    });

                    const meta = document.createElement('div');
                    meta.className = 'crm-chat-meta';
                    meta.textContent =
                        (message.sender_name || 'User')
                        + ' · '
                        + (message.created_at || '');

                    wrapper.append(bubble, meta);
                    messagesRoot.appendChild(wrapper);

                    messagesRoot.dataset.lastId =
                        Math.max(
                            Number(messagesRoot.dataset.lastId || 0),
                            Number(message.id || 0)
                        );

                    messagesRoot.scrollTop =
                        messagesRoot.scrollHeight;
                };

                const pollMessages = async () => {
                    try {
                        const after =
                            Number(
                                messagesRoot.dataset.lastId || 0
                            );

                        const response = await fetch(
                            pollUrl
                            + '?after='
                            + encodeURIComponent(after),
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

                        (data.messages || []).forEach(
                            appendMessage
                        );
                    } catch (error) {
                        // Keep chat usable even during a temporary connection issue.
                    }
                };

                form.addEventListener('submit', async (event) => {
                    event.preventDefault();

                    const button = form.querySelector('button[type="submit"], button:not([type])');
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
                    } catch (error) {
                        alert('Pesan gagal dikirim. Coba kembali.');
                    } finally {
                        button.disabled = false;
                        button.textContent = original;
                    }
                });

                messagesRoot.scrollTop =
                    messagesRoot.scrollHeight;

                window.setInterval(
                    pollMessages,
                    5000
                );
            })();
        </script>
    @endif
</x-admin::layouts>
