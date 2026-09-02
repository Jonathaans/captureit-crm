<x-admin::layouts>
    <x-slot:title>
        Internal Chat Audit Detail
    </x-slot>

    @php
        $status =
            $message->deleted_at
                ? 'DELETED'
                : (
                    $message->edited_at
                        ? 'EDITED'
                        : 'ACTIVE'
                );
    @endphp

    <div class="flex flex-col gap-5">
        <div class="rounded-xl border bg-white p-5 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <div class="text-xs font-bold uppercase text-gray-500">
                        Operational Dashboard · Internal Chat Audit
                    </div>

                    <h1 class="mt-1 text-2xl font-bold text-gray-900">
                        Message #{{ $message->id }}
                    </h1>

                    <div class="mt-2 inline-flex rounded-full border px-3 py-1 text-xs font-bold">
                        {{ $status }}
                    </div>
                </div>

                <a
                    href="{{ route('admin.operational-dashboard.internal-chat-audit.index') }}"
                    class="secondary-button"
                >
                    ← Back to Audit
                </a>
            </div>
        </div>

        <div class="grid gap-5 lg:grid-cols-3">
            <div class="rounded-xl border bg-white p-5 shadow-sm lg:col-span-2">
                <div class="text-xs font-bold uppercase text-gray-400">
                    Message
                </div>

                <div class="mt-3 whitespace-pre-wrap break-words rounded-xl bg-gray-50 p-4 text-sm leading-6 text-gray-900">{{ $message->body ?: '[Attachment only]' }}</div>

                @if ($message->attachments->isNotEmpty())
                    <div class="mt-5">
                        <div class="text-xs font-bold uppercase text-gray-400">
                            Attachments
                        </div>

                        <div class="mt-2 flex flex-col gap-2">
                            @foreach ($message->attachments as $attachment)
                                <div class="rounded-lg border px-3 py-2 text-sm text-gray-700">
                                    📎 {{ $attachment->original_name }}
                                    · {{ number_format($attachment->size / 1024, 1) }} KB
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            <div class="rounded-xl border bg-white p-5 shadow-sm">
                <div class="text-xs font-bold uppercase text-gray-400">
                    Metadata
                </div>

                <dl class="mt-3 flex flex-col gap-3 text-sm">
                    <div>
                        <dt class="text-xs text-gray-400">Conversation</dt>
                        <dd class="font-semibold">#{{ $message->conversation_id }}</dd>
                    </div>

                    <div>
                        <dt class="text-xs text-gray-400">Sender</dt>
                        <dd class="font-semibold">{{ $sender?->name ?: 'Unknown' }}</dd>
                        <dd class="text-xs text-gray-500">{{ $sender?->role_name ?: '-' }} · {{ $sender?->email ?: '-' }}</dd>
                    </div>

                    <div>
                        <dt class="text-xs text-gray-400">Recipient</dt>
                        <dd class="font-semibold">
                            {{ $recipients->pluck('name')->implode(', ') ?: '-' }}
                        </dd>
                    </div>

                    <div>
                        <dt class="text-xs text-gray-400">Sent At</dt>
                        <dd>{{ $message->created_at?->format('d M Y H:i:s') }}</dd>
                    </div>

                    <div>
                        <dt class="text-xs text-gray-400">Read At</dt>
                        <dd>
                            {{ $readAt ? \Illuminate\Support\Carbon::parse($readAt)->format('d M Y H:i:s') : 'Belum dibaca / tidak tersedia' }}
                        </dd>
                    </div>

                    <div>
                        <dt class="text-xs text-gray-400">Edited At</dt>
                        <dd>{{ $message->edited_at?->format('d M Y H:i:s') ?: '-' }}</dd>
                    </div>

                    <div>
                        <dt class="text-xs text-gray-400">Deleted At</dt>
                        <dd>{{ $message->deleted_at?->format('d M Y H:i:s') ?: '-' }}</dd>
                    </div>

                    @if ($message->reply_to_message_id)
                        <div>
                            <dt class="text-xs text-gray-400">Reply To</dt>
                            <dd>Message #{{ $message->reply_to_message_id }}</dd>
                        </div>
                    @endif
                </dl>
            </div>
        </div>

        <div class="rounded-xl border bg-white p-5 shadow-sm">
            <div class="text-lg font-bold text-gray-900">
                Audit Timeline
            </div>

            <p class="mt-1 text-xs text-gray-500">
                Append-only history. Existing edits that happened before audit installation cannot reconstruct the previous text.
            </p>

            <div class="mt-4 flex flex-col gap-3">
                @forelse ($audits as $audit)
                    @php
                        $meta =
                            $audit->meta
                                ? json_decode(
                                    $audit->meta,
                                    true
                                )
                                : [];
                    @endphp

                    <div class="rounded-xl border p-4">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div class="font-bold uppercase text-gray-900">
                                {{ $audit->action }}
                            </div>

                            <div class="text-xs text-gray-400">
                                {{ \Illuminate\Support\Carbon::parse($audit->created_at)->format('d M Y H:i:s') }}
                            </div>
                        </div>

                        <div class="mt-1 text-xs text-gray-500">
                            Actor:
                            {{ $audit->actor_name ?: 'System' }}
                            @if ($audit->actor_email)
                                · {{ $audit->actor_email }}
                            @endif
                        </div>

                        @if ($audit->action === 'edited')
                            <div class="mt-4 grid gap-3 md:grid-cols-2">
                                <div>
                                    <div class="text-xs font-bold uppercase text-gray-400">
                                        Before
                                    </div>

                                    <div class="mt-1 whitespace-pre-wrap rounded-lg bg-red-50 p-3 text-sm text-gray-700">{{ $audit->old_body ?? '[Historical value unavailable]' }}</div>
                                </div>

                                <div>
                                    <div class="text-xs font-bold uppercase text-gray-400">
                                        After
                                    </div>

                                    <div class="mt-1 whitespace-pre-wrap rounded-lg bg-green-50 p-3 text-sm text-gray-700">{{ $audit->new_body ?? '-' }}</div>
                                </div>
                            </div>
                        @elseif ($audit->action === 'deleted')
                            <div class="mt-3 whitespace-pre-wrap rounded-lg bg-gray-50 p-3 text-sm text-gray-700">{{ $audit->old_body ?: $audit->new_body ?: '[Attachment only]' }}</div>
                        @endif

                        @if (! empty($meta['legacy_backfill']))
                            <div class="mt-3 text-xs font-semibold text-amber-700">
                                Historical snapshot created during audit installation.
                            </div>
                        @endif
                    </div>
                @empty
                    <div class="rounded-xl bg-gray-50 p-5 text-center text-sm text-gray-500">
                        Belum ada immutable audit event untuk message ini. Message masih tetap terlihat karena Operational Audit membaca row asli, termasuk soft-deleted messages.
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</x-admin::layouts>
