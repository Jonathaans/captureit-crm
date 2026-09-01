<x-admin::layouts>
    <x-slot:title>
        Sent Email
    </x-slot>

    <div class="flex flex-col gap-4">
        <div class="flex items-center justify-between rounded-xl border bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div>
                <div class="text-xs font-bold uppercase text-gray-500">
                    Personal Mailbox
                </div>

                <h1 class="mt-1 text-2xl font-bold">
                    Sent Email
                </h1>
            </div>

            <div class="flex gap-2">
                <a
                    href="{{ route('admin.my-email.inbox') }}"
                    class="secondary-button"
                >
                    Inbox
                </a>

                <a
                    href="{{ route('admin.my-email.compose') }}"
                    class="primary-button"
                >
                    + Compose
                </a>
            </div>
        </div>

        <div class="overflow-x-auto rounded-xl border bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <table class="w-full text-left text-sm">
                <thead class="border-b bg-gray-50 dark:bg-gray-950">
                    <tr>
                        <th class="p-3">To</th>
                        <th class="p-3">Subject</th>
                        <th class="p-3">Sent</th>
                        <th class="p-3">Action</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($messages as $message)
                        @php
                            $to = json_decode($message->to_emails ?: '[]', true) ?: [];
                            $toLabel = collect($to)
                                ->pluck('email')
                                ->filter()
                                ->implode(', ');
                        @endphp

                        <tr class="border-b">
                            <td class="p-3">
                                {{ $toLabel ?: '-' }}
                            </td>

                            <td class="p-3 font-semibold">
                                {{ $message->subject ?: '(No Subject)' }}
                            </td>

                            <td class="p-3 whitespace-nowrap">
                                {{ $message->sent_at?->format('Y-m-d H:i') ?: $message->created_at?->format('Y-m-d H:i') }}
                            </td>

                            <td class="p-3">
                                <a
                                    href="{{ route('admin.my-email.sent.show', $message->id) }}"
                                    class="secondary-button"
                                >
                                    Open
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td
                                colspan="4"
                                class="p-8 text-center text-gray-500"
                            >
                                Belum ada email terkirim dari CRM.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $messages->links() }}
    </div>
</x-admin::layouts>
