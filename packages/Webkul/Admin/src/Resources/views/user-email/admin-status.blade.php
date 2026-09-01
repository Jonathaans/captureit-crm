<x-admin::layouts>
    <x-slot:title>
        User Email Connections
    </x-slot>

    <div class="flex flex-col gap-4">
        <div class="rounded-xl border bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="text-xs font-bold uppercase text-gray-500">
                Administrator Only
            </div>

            <h1 class="mt-1 text-2xl font-bold">
                User Email Connections
            </h1>

            <p class="mt-1 text-sm text-gray-500">
                Status koneksi saja. Password mailbox tidak pernah ditampilkan.
            </p>
        </div>

        <div class="overflow-x-auto rounded-xl border bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <table class="w-full text-left text-sm">
                <thead class="border-b bg-gray-50 dark:bg-gray-950">
                    <tr>
                        <th class="p-3">User</th>
                        <th class="p-3">Email</th>
                        <th class="p-3">IMAP</th>
                        <th class="p-3">SMTP</th>
                        <th class="p-3">Sync</th>
                        <th class="p-3">Last Sync</th>
                        <th class="p-3">Last Error</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($accounts as $account)
                        <tr class="border-b">
                            <td class="p-3">
                                {{ $userNames[$account->user_id] ?? '#'.$account->user_id }}
                            </td>

                            <td class="p-3">
                                {{ $account->email_address }}
                            </td>

                            <td class="p-3">
                                {{ strtoupper($account->imap_status) }}
                            </td>

                            <td class="p-3">
                                {{ strtoupper($account->smtp_status) }}
                            </td>

                            <td class="p-3">
                                {{ $account->sync_enabled ? 'ENABLED' : 'DISABLED' }}
                            </td>

                            <td class="p-3">
                                {{ $account->last_synced_at?->format('Y-m-d H:i:s') ?: '-' }}
                            </td>

                            <td class="p-3">
                                {{ $account->last_sync_error ?: '-' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td
                                colspan="7"
                                class="p-8 text-center text-gray-500"
                            >
                                Belum ada user email connection.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $accounts->links() }}
    </div>
</x-admin::layouts>
