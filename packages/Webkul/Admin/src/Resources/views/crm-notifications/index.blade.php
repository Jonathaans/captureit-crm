<x-admin::layouts>
    <x-slot:title>Notifications</x-slot>

    <div class="flex flex-col gap-4">
        <div class="flex items-center justify-between rounded-xl border bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div>
                <h1 class="text-2xl font-bold">Notification Center</h1>
                <p class="mt-1 text-sm text-gray-500">Event reminder, overdue invoice, PO draft, dan sync error.</p>
            </div>

            <form method="POST" action="{{ route('admin.crm-notifications.read-all') }}">
                @csrf
                <button class="secondary-button">Mark All Read</button>
            </form>
        </div>

        @forelse ($notifications as $notification)
            <div class="rounded-xl border bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <div class="text-xs font-bold uppercase text-gray-500">
                            {{ strtoupper($notification->severity) }}
                            · {{ $notification->type }}
                        </div>

                        <div class="mt-1 font-bold">{{ $notification->title }}</div>

                        @if ($notification->message)
                            <div class="mt-1 text-sm text-gray-600">{{ $notification->message }}</div>
                        @endif

                        <div class="mt-2 text-xs text-gray-500">
                            {{ $notification->due_at?->format('Y-m-d H:i') }}
                            {{ $notification->read_at ? ' · READ' : ' · UNREAD' }}
                        </div>
                    </div>

                    <div class="flex gap-2">
                        @if ($notification->action_url)
                            <a href="{{ $notification->action_url }}" class="secondary-button">Open</a>
                        @endif

                        @if (! $notification->read_at)
                            <form method="POST" action="{{ route('admin.crm-notifications.read', $notification->id) }}">
                                @csrf
                                <button class="secondary-button">Read</button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <div class="rounded-xl border bg-white p-8 text-center text-gray-500 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                Tidak ada notification aktif.
            </div>
        @endforelse

        {{ $notifications->links() }}
    </div>
</x-admin::layouts>
