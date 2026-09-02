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
            $parts =
                preg_split(
                    '/\s+/',
                    trim(
                        (string) $value
                    )
                )
                ?: [];

            $initials = '';

            foreach (
                array_slice(
                    $parts,
                    0,
                    2
                )
                as $part
            ) {
                $initials .=
                    strtoupper(
                        mb_substr(
                            $part,
                            0,
                            1
                        )
                    );
            }

            return $initials ?: 'U';
        };
    @endphp

    <div class="flex flex-col gap-4">
        {{-- Header --}}
        <div class="rounded-xl border bg-white p-5 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <div class="text-xs font-bold uppercase text-gray-500">
                        Internal Communication
                    </div>

                    <h1 class="mt-1 text-2xl font-bold text-gray-900">
                        Internal Chat
                    </h1>

                    <p class="mt-1 text-sm text-gray-500">
                        Direct message antar akun CRM untuk koordinasi sales, admin, warehouse, dan operasional.
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

        {{-- WhatsApp-style two column shell --}}
        <div class="flex min-h-screen flex-col overflow-hidden rounded-xl border bg-white shadow-sm lg:flex-row">
            {{-- LEFT: Chat list --}}
            <aside class="flex w-full flex-col border-b bg-white lg:w-1/3 lg:border-b-0 lg:border-r">
                <div class="border-b p-4">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <div class="text-lg font-bold text-gray-900">
                                Chats
                            </div>

                            <div class="mt-1 text-xs text-gray-500">
                                {{ $conversationList->count() }} conversation aktif
                            </div>
                        </div>

                        <div class="flex h-10 w-10 items-center justify-center rounded-full bg-gray-100 text-sm font-bold text-gray-700">
                            {{ $makeInitials($currentUser?->name ?: 'Me') }}
                        </div>
                    </div>

                    <div class="mt-4">
                        <div class="flex items-center gap-2 rounded-xl border bg-gray-50 px-3 py-2">
                            <span class="text-gray-400">🔎</span>

                            <input
                                type="text"
                                id="crm-wa-chat-search"
                                class="w-full border-0 bg-transparent text-sm text-gray-900 outline-none"
                                placeholder="Cari chat atau user..."
                            >
                        </div>
                    </div>
                </div>

                <div class="flex-1 overflow-y-auto">
                    {{-- Recent conversations --}}
                    <div class="border-b">
                        <div class="px-4 py-3 text-xs font-bold uppercase text-gray-400">
                            Recent Conversations
                        </div>

                        <div id="crm-wa-conversation-list">
                            @forelse ($conversationList as $row)
                                @php
                                    $otherName =
                                        $row->other?->name
                                        ?: 'User';

                                    $preview =
                                        $row->last_message?->body
                                            ? \Illuminate\Support\Str::limit(
                                                $row->last_message->body,
                                                64
                                            )
                                            : 'Belum ada pesan.';

                                    $lastTime = '';

                                    if (
                                        ! empty(
                                            $row->last_message?->created_at
                                        )
                                    ) {
                                        try {
                                            $lastTime =
                                                \Illuminate\Support\Carbon::parse(
                                                    $row->last_message->created_at
                                                )->format('H:i');
                                        } catch (\Throwable) {
                                            $lastTime = '';
                                        }
                                    }
                                @endphp

                                <a
                                    href="{{ route('admin.internal-chat.index', ['conversation' => $row->id]) }}"
                                    class="flex items-center gap-3 border-t px-4 py-3 no-underline hover:bg-gray-50 {{ $conversation?->id === $row->id ? 'bg-gray-100' : 'bg-white' }}"
                                    data-chat-search="{{ strtolower($otherName.' '.($row->other?->role_name ?: '').' '.$preview) }}"
                                >
                                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-gray-200 text-sm font-bold text-gray-700">
                                        {{ $makeInitials($otherName) }}
                                    </div>

                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center justify-between gap-3">
                                            <div class="truncate text-sm font-bold text-gray-900">
                                                {{ $otherName }}
                                            </div>

                                            @if ($lastTime)
                                                <div class="shrink-0 text-xs text-gray-400">
                                                    {{ $lastTime }}
                                                </div>
                                            @endif
                                        </div>

                                        <div class="mt-1 truncate text-xs font-semibold text-gray-500">
                                            {{ $row->other?->role_name ?: 'Internal User' }}
                                        </div>

                                        <div class="mt-1 truncate text-sm text-gray-500">
                                            {{ $preview }}
                                        </div>
                                    </div>
                                </a>
                            @empty
                                <div class="px-4 py-8 text-center text-sm text-gray-500">
                                    Belum ada conversation.
                                </div>
                            @endforelse
                        </div>
                    </div>

                    {{-- Start new chat --}}
                    <div>
                        <div class="px-4 py-3 text-xs font-bold uppercase text-gray-400">
                            Start New Chat
                        </div>

                        <div id="crm-wa-user-list">
                            @forelse ($users as $chatUser)
                                <form
                                    method="POST"
                                    action="{{ route('admin.internal-chat.direct', $chatUser->id) }}"
                                    class="m-0 border-t"
                                    data-chat-search="{{ strtolower($chatUser->name.' '.($chatUser->role_name ?: '').' '.$chatUser->email) }}"
                                >
                                    @csrf

                                    <button
                                        type="submit"
                                        class="flex w-full items-center gap-3 bg-white px-4 py-3 text-left hover:bg-gray-50"
                                    >
                                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-gray-100 text-xs font-bold text-gray-700">
                                            {{ $makeInitials($chatUser->name) }}
                                        </div>

                                        <div class="min-w-0 flex-1">
                                            <div class="truncate text-sm font-bold text-gray-900">
                                                {{ $chatUser->name }}
                                            </div>

                                            <div class="mt-1 truncate text-xs text-gray-500">
                                                {{ $chatUser->role_name ?: 'Internal User' }}
                                                · {{ $chatUser->email }}
                                            </div>
                                        </div>

                                        <span class="text-gray-400">
                                            ›
                                        </span>
                                    </button>
                                </form>
                            @empty
                                <div class="px-4 py-8 text-center text-sm text-gray-500">
                                    Tidak ada user lain.
                                </div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </aside>

            {{-- RIGHT: Active chat --}}
            <section class="flex w-full flex-1 flex-col bg-gray-50 lg:w-2/3">
                @if ($conversation)
                    {{-- Chat header --}}
                    <div class="flex items-center justify-between gap-4 border-b bg-white px-5 py-4">
                        <div class="flex min-w-0 items-center gap-3">
                            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-gray-200 text-sm font-bold text-gray-700">
                                {{ $makeInitials($activeOtherUser?->name ?: 'User') }}
                            </div>

                            <div class="min-w-0">
                                <div class="truncate text-base font-bold text-gray-900">
                                    {{ $activeOtherUser?->name ?: 'Internal Chat' }}
                                </div>

                                <div class="mt-1 truncate text-xs text-gray-500">
                                    {{ $activeOtherUser?->role_name ?: '-' }}
                                    · {{ $activeOtherUser?->email ?: '-' }}
                                </div>
                            </div>
                        </div>

                        <div class="rounded-full border bg-gray-50 px-3 py-2 text-xs font-semibold text-gray-500">
                            🔒 Private
                        </div>
                    </div>

                    {{-- Messages --}}
                    <div
                        id="crm-chat-messages"
                        class="flex flex-1 flex-col overflow-y-auto bg-gray-100 p-5"
                        data-last-id="{{ $lastMessageId }}"
                        data-current-user-id="{{ $currentUser->id }}"
                    >
                        <div class="mb-5 text-center">
                            <span class="rounded-full bg-white px-3 py-2 text-xs font-semibold text-gray-500 shadow-sm">
                                Direct Conversation
                            </span>
                        </div>

                        @foreach ($messages as $message)
                            @php
                                $isMine =
                                    (int) $message->user_id
                                    === (int) $currentUser->id;
                            @endphp

                            <div
                                class="mb-3 flex {{ $isMine ? 'justify-end' : 'justify-start' }}"
                                data-message-id="{{ $message->id }}"
                            >
                                <div class="max-w-2xl">
                                    @if (! $isMine)
                                        <div class="mb-1 px-1 text-xs font-bold text-gray-500">
                                            {{ $senderNames[$message->user_id] ?? 'User' }}
                                        </div>
                                    @endif

                                    <div class="rounded-xl px-4 py-3 shadow-sm {{ $isMine ? 'bg-gray-900 text-white' : 'border bg-white text-gray-900' }}">
                                        @if ($message->body)
                                            <div class="whitespace-pre-wrap break-words text-sm">
                                                {{ $message->body }}
                                            </div>
                                        @endif

                                        @if ($message->attachments->isNotEmpty())
                                            <div class="mt-3 flex flex-col gap-2">
                                                @foreach ($message->attachments as $attachment)
                                                    <a
                                                        href="{{ route('admin.internal-chat.attachments.download', $attachment->id) }}"
                                                        class="rounded-lg border px-3 py-2 text-xs font-semibold no-underline {{ $isMine ? 'border-gray-600 text-white' : 'bg-gray-50 text-gray-700' }}"
                                                    >
                                                        📎 {{ $attachment->original_name }}
                                                        · {{ number_format($attachment->size / 1024, 1) }} KB
                                                    </a>
                                                @endforeach
                                            </div>
                                        @endif

                                        <div class="mt-2 text-right text-xs {{ $isMine ? 'text-gray-300' : 'text-gray-400' }}">
                                            {{ $message->created_at?->format('d M · H:i') }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    {{-- Composer --}}
                    <div class="border-t bg-white p-4">
                        <form
                            id="crm-chat-send-form"
                            method="POST"
                            enctype="multipart/form-data"
                            action="{{ route('admin.internal-chat.send', $conversation->id) }}"
                            class="flex flex-col gap-3"
                        >
                            @csrf

                            <div class="rounded-xl border bg-gray-50 p-3">
                                <textarea
                                    name="body"
                                    id="crm-chat-body"
                                    class="min-h-24 w-full resize-y border-0 bg-transparent text-sm text-gray-900 outline-none"
                                    placeholder="Ketik pesan..."
                                ></textarea>

                                <input
                                    type="file"
                                    id="crm-chat-attachments"
                                    name="attachments[]"
                                    multiple
                                    hidden
                                >

                                <div
                                    id="crm-chat-file-list"
                                    class="mt-2 flex flex-wrap gap-2"
                                ></div>

                                <div
                                    id="crm-chat-feedback"
                                    class="mt-2 hidden rounded-lg px-3 py-2 text-xs font-semibold"
                                ></div>

                                <div class="mt-3 flex flex-wrap items-center justify-between gap-3 border-t pt-3">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <label
                                            for="crm-chat-attachments"
                                            class="secondary-button cursor-pointer"
                                        >
                                            📎 Attachment
                                        </label>

                                        <span class="text-xs text-gray-400">
                                            Max 5 file, 10 MB/file
                                        </span>
                                    </div>

                                    <button
                                        type="submit"
                                        class="primary-button"
                                    >
                                        Send Message
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                @else
                    {{-- Empty state --}}
                    <div class="flex flex-1 items-center justify-center p-10">
                        <div class="max-w-md text-center">
                            <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-white text-2xl shadow-sm">
                                💬
                            </div>

                            <h2 class="mt-4 text-xl font-bold text-gray-900">
                                Pilih Conversation
                            </h2>

                            <p class="mt-2 text-sm leading-6 text-gray-500">
                                Pilih chat di kolom kiri atau mulai conversation baru. Layout ini dibuat menyerupai pola WhatsApp Web agar daftar chat dan percakapan lebih mudah dipindai.
                            </p>
                        </div>
                    </div>
                @endif
            </section>
        </div>
    </div>

    {{-- Sidebar search --}}
    <script>
        (() => {
            const input =
                document.getElementById(
                    'crm-wa-chat-search'
                );

            if (! input) {
                return;
            }

            input.addEventListener(
                'input',
                () => {
                    const keyword =
                        String(
                            input.value || ''
                        )
                            .toLowerCase()
                            .trim();

                    document
                        .querySelectorAll(
                            '[data-chat-search]'
                        )
                        .forEach(
                            (node) => {
                                const text =
                                    String(
                                        node.dataset
                                            .chatSearch
                                        || ''
                                    )
                                        .toLowerCase();

                                node.style.display =
                                    keyword === ''
                                    || text.includes(
                                        keyword
                                    )
                                        ? ''
                                        : 'none';
                            }
                        );
                }
            );
        })();
    </script>

    @if ($conversation)
        <script>
            (() => {
                const currentUserId =
                    {{ (int) $currentUser->id }};

                const messagesRoot =
                    document.getElementById(
                        'crm-chat-messages'
                    );

                const form =
                    document.getElementById(
                        'crm-chat-send-form'
                    );

                const pollUrl =
                    @json(
                        route(
                            'admin.internal-chat.messages',
                            $conversation->id
                        )
                    );

                const fileInput =
                    document.getElementById(
                        'crm-chat-attachments'
                    );

                const fileList =
                    document.getElementById(
                        'crm-chat-file-list'
                    );

                const feedback =
                    document.getElementById(
                        'crm-chat-feedback'
                    );

                const showFeedback = (
                    message,
                    type = 'error'
                ) => {
                    if (! feedback) {
                        return;
                    }

                    feedback.textContent =
                        message;

                    feedback.classList.remove(
                        'hidden',
                        'bg-red-50',
                        'text-red-700',
                        'bg-green-50',
                        'text-green-700'
                    );

                    if (type === 'success') {
                        feedback.classList.add(
                            'bg-green-50',
                            'text-green-700'
                        );
                    } else {
                        feedback.classList.add(
                            'bg-red-50',
                            'text-red-700'
                        );
                    }

                    window.clearTimeout(
                        feedback.__crmTimer
                    );

                    feedback.__crmTimer =
                        window.setTimeout(
                            () => {
                                feedback.classList.add(
                                    'hidden'
                                );
                            },
                            3000
                        );
                };

                const renderFileList = () => {
                    if (
                        ! fileInput
                        || ! fileList
                    ) {
                        return;
                    }

                    fileList.innerHTML = '';

                    Array.from(
                        fileInput.files || []
                    ).forEach(
                        (file) => {
                            const chip =
                                document.createElement(
                                    'span'
                                );

                            chip.className =
                                'rounded-full border bg-white px-3 py-1 text-xs font-semibold text-gray-600';

                            chip.textContent =
                                '📎 '
                                + file.name;

                            fileList.appendChild(
                                chip
                            );
                        }
                    );
                };

                const appendMessage = (
                    message
                ) => {
                    if (
                        document.querySelector(
                            `[data-message-id="${message.id}"]`
                        )
                    ) {
                        return;
                    }

                    const mine =
                        Number(
                            message.user_id
                        )
                        === currentUserId;

                    const wrapper =
                        document.createElement(
                            'div'
                        );

                    wrapper.className =
                        'mb-3 flex '
                        + (
                            mine
                                ? 'justify-end'
                                : 'justify-start'
                        );

                    wrapper.dataset.messageId =
                        message.id;

                    const container =
                        document.createElement(
                            'div'
                        );

                    container.className =
                        'max-w-2xl';

                    if (! mine) {
                        const sender =
                            document.createElement(
                                'div'
                            );

                        sender.className =
                            'mb-1 px-1 text-xs font-bold text-gray-500';

                        sender.textContent =
                            message.sender_name
                            || 'User';

                        container.appendChild(
                            sender
                        );
                    }

                    const bubble =
                        document.createElement(
                            'div'
                        );

                    bubble.className =
                        'rounded-xl px-4 py-3 shadow-sm '
                        + (
                            mine
                                ? 'bg-gray-900 text-white'
                                : 'border bg-white text-gray-900'
                        );

                    if (message.body) {
                        const body =
                            document.createElement(
                                'div'
                            );

                        body.className =
                            'whitespace-pre-wrap break-words text-sm';

                        body.textContent =
                            message.body;

                        bubble.appendChild(
                            body
                        );
                    }

                    if (
                        (
                            message.attachments
                            || []
                        ).length
                    ) {
                        const attachments =
                            document.createElement(
                                'div'
                            );

                        attachments.className =
                            'mt-3 flex flex-col gap-2';

                        (
                            message.attachments
                            || []
                        ).forEach(
                            (attachment) => {
                                const link =
                                    document.createElement(
                                        'a'
                                    );

                                link.href =
                                    attachment.download_url;

                                link.className =
                                    'rounded-lg border px-3 py-2 text-xs font-semibold no-underline '
                                    + (
                                        mine
                                            ? 'border-gray-600 text-white'
                                            : 'bg-gray-50 text-gray-700'
                                    );

                                link.textContent =
                                    '📎 '
                                    + (
                                        attachment.name
                                        || 'Attachment'
                                    );

                                attachments
                                    .appendChild(
                                        link
                                    );
                            }
                        );

                        bubble.appendChild(
                            attachments
                        );
                    }

                    const meta =
                        document.createElement(
                            'div'
                        );

                    meta.className =
                        'mt-2 text-right text-xs '
                        + (
                            mine
                                ? 'text-gray-300'
                                : 'text-gray-400'
                        );

                    meta.textContent =
                        message.created_at
                        || '';

                    bubble.appendChild(
                        meta
                    );

                    container.appendChild(
                        bubble
                    );

                    wrapper.appendChild(
                        container
                    );

                    messagesRoot.appendChild(
                        wrapper
                    );

                    messagesRoot.dataset.lastId =
                        Math.max(
                            Number(
                                messagesRoot
                                    .dataset
                                    .lastId
                                || 0
                            ),
                            Number(
                                message.id
                                || 0
                            )
                        );

                    messagesRoot.scrollTop =
                        messagesRoot.scrollHeight;
                };

                const pollMessages =
                    async () => {
                        try {
                            const after =
                                Number(
                                    messagesRoot
                                        .dataset
                                        .lastId
                                    || 0
                                );

                            const response =
                                await fetch(
                                    pollUrl
                                    + '?after='
                                    + encodeURIComponent(
                                        after
                                    ),
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

                            (
                                data.messages
                                || []
                            ).forEach(
                                appendMessage
                            );
                        } catch (error) {
                            // Temporary network errors do not disable the chat.
                        }
                    };

                form.addEventListener(
                    'submit',
                    async (event) => {
                        event.preventDefault();

                        const button =
                            form.querySelector(
                                'button[type="submit"]'
                            );

                        const original =
                            button.textContent;

                        button.disabled =
                            true;

                        button.textContent =
                            'Sending...';

                        try {
                            const response =
                                await fetch(
                                    form.action,
                                    {
                                        method:
                                            'POST',

                                        headers: {
                                            'Accept':
                                                'application/json',

                                            'X-Requested-With':
                                                'XMLHttpRequest',
                                        },

                                        credentials:
                                            'same-origin',

                                        body:
                                            new FormData(
                                                form
                                            ),
                                    }
                                );

                            if (! response.ok) {
                                throw new Error(
                                    await response.text()
                                );
                            }

                            const message =
                                await response.json();

                            appendMessage(
                                message
                            );

                            form.reset();

                            renderFileList();

                            showFeedback(
                                'Pesan berhasil dikirim.',
                                'success'
                            );
                        } catch (error) {
                            showFeedback(
                                'Pesan gagal dikirim. Coba kembali.',
                                'error'
                            );
                        } finally {
                            button.disabled =
                                false;

                            button.textContent =
                                original;
                        }
                    }
                );

                if (fileInput) {
                    fileInput.addEventListener(
                        'change',
                        renderFileList
                    );
                }

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
