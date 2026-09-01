<x-admin::layouts>
    <x-slot:title>
        My Email Inbox
    </x-slot>

    <div class="flex flex-col gap-4">
        <div class="flex items-center justify-between rounded-xl border bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div>
                <div class="text-xs font-bold uppercase text-gray-500">
                    Personal Mailbox
                </div>

                <h1 class="mt-1 text-2xl font-bold">
                    My Email Inbox
                </h1>

                <p class="mt-1 text-sm text-gray-500">
                    Hanya email milik user yang sedang login.
                </p>
            </div>

            <div class="flex gap-2">
                <a
                    href="{{ route('admin.my-email.settings') }}"
                    class="secondary-button"
                >
                    Email Settings
                </a>

                @if ($account)
                    <form
                        method="POST"
                        action="{{ route('admin.my-email.sync') }}"
                    >
                        @csrf

                        <button class="primary-button">
                            Sync Now
                        </button>
                    </form>
                @endif
            </div>
        </div>

        @if (! $account)
            <div class="rounded-xl border bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="font-bold">
                    Email account belum dikonfigurasi.
                </div>

                <div class="mt-1 text-sm text-gray-500">
                    Buka Email Settings untuk menghubungkan IMAP + SMTP.
                </div>
            </div>
        @else
            <div class="rounded-xl border bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="flex flex-wrap gap-4 text-sm">
                    <span>
                        <strong>{{ $account->email_address }}</strong>
                    </span>

                    <span>
                        IMAP:
                        {{ strtoupper($account->imap_status) }}
                    </span>

                    <span>
                        SMTP:
                        {{ strtoupper($account->smtp_status) }}
                    </span>

                    <span>
                        Last Sync:
                        {{ $account->last_synced_at?->format('Y-m-d H:i:s') ?: '-' }}
                    </span>
                </div>
            </div>
        @endif

        <div class="overflow-x-auto rounded-xl border bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <table class="w-full text-left text-sm">
                <thead class="border-b bg-gray-50 dark:bg-gray-950">
                    <tr>
                        <th class="p-3">Status</th>
                        <th class="p-3">From</th>
                        <th class="p-3">Subject</th>
                        <th class="p-3">Received</th>
                        <th class="p-3">Action</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($messages as $message)
                        <tr class="border-b {{ $message->read_at ? '' : 'font-semibold' }}">
                            <td class="p-3">
                                {{ $message->read_at ? 'READ' : 'NEW' }}
                            </td>

                            <td class="p-3">
                                <div>{{ $message->from_name ?: '-' }}</div>
                                <div class="text-xs text-gray-500">
                                    {{ $message->from_email ?: '-' }}
                                </div>
                            </td>

                            <td class="p-3">
                                {{ $message->subject ?: '(No Subject)' }}
                            </td>

                            <td class="p-3 whitespace-nowrap">
                                {{ $message->received_at?->format('Y-m-d H:i') ?: '-' }}
                            </td>

                            <td class="p-3">
                                <a
                                    href="{{ route('admin.my-email.messages.show', $message->id) }}"
                                    class="secondary-button"
                                >
                                    Open
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td
                                colspan="5"
                                class="p-8 text-center text-gray-500"
                            >
                                Belum ada email tersinkron.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $messages->links() }}
    </div>
</x-admin::layouts>
