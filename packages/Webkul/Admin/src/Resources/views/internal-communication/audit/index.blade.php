<x-admin::layouts>
    <x-slot:title>
        Internal Chat Audit
    </x-slot>

    <div class="flex flex-col gap-5">
        <div class="rounded-xl border bg-white p-5 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <div class="text-xs font-bold uppercase text-gray-500">
                        Operational Dashboard
                    </div>

                    <h1 class="mt-1 text-2xl font-bold text-gray-900">
                        Internal Chat Audit
                    </h1>

                    <p class="mt-1 max-w-3xl text-sm text-gray-500">
                        Read-only audit untuk pesan internal, termasuk pesan yang sudah diedit atau dihapus dari tampilan chat.
                    </p>
                </div>

                <a
                    href="{{ route('admin.internal-chat.index') }}"
                    class="secondary-button"
                >
                    Open Internal Chat
                </a>
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-3">
            <a
                href="{{ route('admin.operational-dashboard.internal-chat-audit.index') }}"
                class="rounded-xl border bg-white p-4 no-underline shadow-sm"
            >
                <div class="text-xs font-bold uppercase text-gray-400">
                    Total Messages
                </div>

                <div class="mt-2 text-3xl font-bold text-gray-900">
                    {{ number_format($summary['total']) }}
                </div>
            </a>

            <a
                href="{{ route('admin.operational-dashboard.internal-chat-audit.index', ['status' => 'edited']) }}"
                class="rounded-xl border bg-white p-4 no-underline shadow-sm"
            >
                <div class="text-xs font-bold uppercase text-gray-400">
                    Edited
                </div>

                <div class="mt-2 text-3xl font-bold text-gray-900">
                    {{ number_format($summary['edited']) }}
                </div>
            </a>

            <a
                href="{{ route('admin.operational-dashboard.internal-chat-audit.index', ['status' => 'deleted']) }}"
                class="rounded-xl border bg-white p-4 no-underline shadow-sm"
            >
                <div class="text-xs font-bold uppercase text-gray-400">
                    Deleted
                </div>

                <div class="mt-2 text-3xl font-bold text-gray-900">
                    {{ number_format($summary['deleted']) }}
                </div>
            </a>
        </div>

        <div class="rounded-xl border bg-white p-4 shadow-sm">
            <form
                method="GET"
                action="{{ route('admin.operational-dashboard.internal-chat-audit.index') }}"
                class="grid gap-3 md:grid-cols-5"
            >
                <input
                    type="text"
                    name="q"
                    value="{{ $search }}"
                    placeholder="Cari isi / sender..."
                    class="rounded-lg border px-3 py-2 text-sm"
                >

                <select
                    name="status"
                    class="rounded-lg border px-3 py-2 text-sm"
                >
                    <option value="">All Status</option>
                    <option value="active" @selected($status === 'active')>Active</option>
                    <option value="edited" @selected($status === 'edited')>Edited</option>
                    <option value="deleted" @selected($status === 'deleted')>Deleted</option>
                </select>

                <input
                    type="date"
                    name="from"
                    value="{{ $from }}"
                    class="rounded-lg border px-3 py-2 text-sm"
                >

                <input
                    type="date"
                    name="to"
                    value="{{ $to }}"
                    class="rounded-lg border px-3 py-2 text-sm"
                >

                <div class="flex gap-2">
                    <button
                        type="submit"
                        class="primary-button"
                    >
                        Filter
                    </button>

                    <a
                        href="{{ route('admin.operational-dashboard.internal-chat-audit.index') }}"
                        class="secondary-button"
                    >
                        Reset
                    </a>
                </div>
            </form>
        </div>

        <div class="overflow-hidden rounded-xl border bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full min-w-max text-left text-sm">
                    <thead class="border-b bg-gray-50">
                        <tr>
                            <th class="px-4 py-3">Date</th>
                            <th class="px-4 py-3">Sender</th>
                            <th class="px-4 py-3">Recipient</th>
                            <th class="px-4 py-3">Message</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">History</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse ($messages as $message)
                            @php
                                $messageStatus =
                                    $message->deleted_at
                                        ? 'Deleted'
                                        : (
                                            $message->edited_at
                                                ? 'Edited'
                                                : 'Active'
                                        );

                                $preview =
                                    trim(
                                        (string) $message->body
                                    )
                                    ?: '[Attachment only]';
                            @endphp

                            <tr class="border-b align-top">
                                <td class="whitespace-nowrap px-4 py-3 text-xs text-gray-500">
                                    {{ \Illuminate\Support\Carbon::parse($message->created_at)->format('d M Y H:i') }}
                                </td>

                                <td class="px-4 py-3">
                                    <div class="font-semibold text-gray-900">
                                        {{ $message->sender_name ?: 'Unknown' }}
                                    </div>

                                    <div class="mt-1 text-xs text-gray-400">
                                        {{ $message->sender_email ?: '-' }}
                                    </div>
                                </td>

                                <td class="px-4 py-3 text-gray-700">
                                    {{ $message->recipient_names ?: '-' }}
                                </td>

                                <td class="max-w-md px-4 py-3 text-gray-700">
                                    {{ \Illuminate\Support\Str::limit($preview, 100) }}
                                </td>

                                <td class="px-4 py-3">
                                    <span class="rounded-full border px-2 py-1 text-xs font-bold">
                                        {{ $messageStatus }}
                                    </span>
                                </td>

                                <td class="px-4 py-3 text-center">
                                    {{ $message->audit_count }}
                                </td>

                                <td class="px-4 py-3">
                                    <a
                                        href="{{ route('admin.operational-dashboard.internal-chat-audit.show', $message->id) }}"
                                        class="secondary-button"
                                    >
                                        Audit
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td
                                    colspan="7"
                                    class="px-4 py-10 text-center text-sm text-gray-500"
                                >
                                    Tidak ada message sesuai filter.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($messages->hasPages())
                <div class="border-t p-4">
                    {{ $messages->links() }}
                </div>
            @endif
        </div>

        <div class="rounded-xl border bg-gray-50 p-4 text-xs leading-5 text-gray-500">
            Audit ini read-only. Delete pada Internal Chat adalah soft delete: pesan hilang dari conversation biasa tetapi row asli tetap tersimpan. Riwayat edit/delete baru direkam sebagai append-only audit setelah modul audit diaktifkan.
        </div>
    </div>
</x-admin::layouts>
