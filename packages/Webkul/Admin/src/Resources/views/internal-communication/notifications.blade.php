<x-admin::layouts>
    <x-slot:title>
        Notifications
    </x-slot>

    <div class="flex flex-col gap-4">
        <div class="rounded-xl border bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <div class="text-xs font-bold uppercase text-gray-500">
                        Internal Communication
                    </div>

                    <h1 class="mt-1 text-2xl font-bold">
                        Notifications
                    </h1>

                    <p class="mt-1 text-sm text-gray-500">
                        Lead WON, SPK release, Surat Jalan release, dan pesan internal.
                    </p>
                </div>

                <div class="flex flex-wrap gap-2">
                    <button
                        type="button"
                        class="secondary-button"
                        onclick="
                            if (!('Notification' in window)) {
                                alert('Browser ini tidak mendukung desktop notification.');
                            } else {
                                Notification.requestPermission().then((result) => {
                                    alert('Desktop notification: ' + result);
                                });
                            }
                        "
                    >
                        Enable Desktop Notification
                    </button>

                    <a
                        href="{{ route('admin.internal-chat.index') }}"
                        class="secondary-button"
                    >
                        💬 Internal Chat
                    </a>

                    <form
                        method="POST"
                        action="{{ route('admin.internal-notifications.read-all') }}"
                    >
                        @csrf

                        <button class="primary-button">
                            Mark All Read
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="overflow-hidden rounded-xl border bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
            @forelse ($notifications as $notification)
                <a
                    href="{{ route('admin.internal-notifications.open', $notification->id) }}"
                    class="block border-b p-4 no-underline last:border-b-0"
                    style="{{ $notification->read_at ? '' : 'background:#eff6ff;' }}"
                >
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <div class="font-bold text-gray-900 dark:text-white">
                                {{ $notification->title }}
                            </div>

                            <div class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                                {{ $notification->message }}
                            </div>

                            <div class="mt-2 text-xs text-gray-400">
                                {{ $notification->created_at?->format('d M Y H:i:s') }}
                            </div>
                        </div>

                        @if (! $notification->read_at)
                            <span
                                style="
                                    display:inline-flex;
                                    padding:4px 8px;
                                    border-radius:9999px;
                                    background:#2563eb;
                                    color:#fff;
                                    font-size:10px;
                                    font-weight:800;
                                "
                            >
                                NEW
                            </span>
                        @endif
                    </div>
                </a>
            @empty
                <div class="p-10 text-center text-gray-500">
                    Belum ada notifikasi.
                </div>
            @endforelse
        </div>

        {{ $notifications->links() }}
    </div>
</x-admin::layouts>
