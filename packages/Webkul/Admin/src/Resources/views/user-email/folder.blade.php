<x-admin::layouts>
    <x-slot:title>
        {{ $folderTitle }}
    </x-slot>

    <div class="flex flex-col gap-4">
        <div class="rounded-xl border bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <div class="text-xs font-bold uppercase text-gray-500">
                        Personal Mailbox
                    </div>

                    <h1 class="mt-1 text-2xl font-bold">
                        {{ $folderTitle }}
                    </h1>
                </div>

                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('admin.my-email.inbox') }}" class="secondary-button">Inbox</a>
                    <a href="{{ route('admin.my-email.drafts') }}" class="secondary-button">Draft</a>
                    <a href="{{ route('admin.my-email.outbox') }}" class="secondary-button">Outbox</a>
                    <a href="{{ route('admin.my-email.sent') }}" class="secondary-button">Sent</a>
                    <a href="{{ route('admin.my-email.trash') }}" class="secondary-button">Trash</a>
                    <a href="{{ route('admin.my-email.compose') }}" class="primary-button">+ Compose</a>
                </div>
            </div>
        </div>

        <div class="overflow-x-auto rounded-xl border bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <table class="w-full text-left text-sm">
                <thead class="border-b bg-gray-50 dark:bg-gray-950">
                    <tr>
                        <th class="p-3">Status</th>
                        <th class="p-3">To / From</th>
                        <th class="p-3">Subject</th>
                        <th class="p-3">Info</th>
                        <th class="p-3">Action</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($messages as $message)
                        @php
                            $to = json_decode($message->to_emails ?: '[]', true) ?: [];
                            $toLabel = collect($to)->pluck('email')->filter()->implode(', ');
                        @endphp

                        <tr class="border-b align-top">
                            <td class="p-3 font-semibold">
                                @if ($folder === 'OUTBOX')
                                    {{ strtoupper($message->delivery_status ?: 'PENDING') }}
                                @elseif ($folder === 'DRAFT')
                                    DRAFT
                                @elseif ($folder === 'TRASH')
                                    TRASH
                                @else
                                    {{ strtoupper($message->delivery_status ?: $message->direction) }}
                                @endif
                            </td>

                            <td class="p-3">
                                @if ($message->direction === 'incoming')
                                    {{ $message->from_email ?: '-' }}
                                @else
                                    {{ $toLabel ?: '-' }}
                                @endif
                            </td>

                            <td class="p-3">
                                <div class="font-semibold">
                                    {{ $message->subject ?: '(No Subject)' }}
                                </div>

                                @if ($folder === 'OUTBOX' && $message->delivery_error)
                                    <div class="mt-1 max-w-[650px] text-xs text-red-600">
                                        {{ $message->delivery_error }}
                                    </div>
                                @endif
                            </td>

                            <td class="p-3">
                                @if ($folder === 'OUTBOX')
                                    Attempt:
                                    {{ $message->delivery_attempts ?? 0 }}

                                    @if ($message->failed_at)
                                        <br>
                                        Failed:
                                        {{ $message->failed_at->format('Y-m-d H:i:s') }}
                                    @endif
                                @elseif ($folder === 'DRAFT')
                                    Updated:
                                    {{ $message->updated_at?->format('Y-m-d H:i') }}
                                @else
                                    {{ $message->updated_at?->format('Y-m-d H:i') }}
                                @endif
                            </td>

                            <td class="p-3">
                                <div class="flex flex-wrap gap-2">
                                    @if ($folder === 'DRAFT')
                                        <a
                                            href="{{ route('admin.my-email.drafts.edit', $message->id) }}"
                                            class="secondary-button"
                                        >
                                            Edit
                                        </a>
                                    @elseif ($folder === 'OUTBOX')
                                        <form
                                            method="POST"
                                            action="{{ route('admin.my-email.outbox.retry', $message->id) }}"
                                        >
                                            @csrf
                                            <button class="primary-button">Retry</button>
                                        </form>
                                    @elseif ($folder === 'TRASH')
                                        <form
                                            method="POST"
                                            action="{{ route('admin.my-email.trash.restore', $message->id) }}"
                                        >
                                            @csrf
                                            <button class="secondary-button">Restore</button>
                                        </form>

                                        <form
                                            method="POST"
                                            action="{{ route('admin.my-email.trash.destroy', $message->id) }}"
                                            onsubmit="return confirm('Hapus permanen email ini dari CRM?')"
                                        >
                                            @csrf
                                            @method('DELETE')
                                            <button class="secondary-button">Delete Permanently</button>
                                        </form>
                                    @endif

                                    @if ($folder !== 'TRASH')
                                        <form
                                            method="POST"
                                            action="{{ route('admin.my-email.trash.move', $message->id) }}"
                                        >
                                            @csrf
                                            <button class="secondary-button">Trash</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="p-8 text-center text-gray-500">
                                {{ $folderTitle }} kosong.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $messages->links() }}
    </div>
</x-admin::layouts>
