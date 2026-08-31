<x-admin::layouts>
    <x-slot:title>
        Stock Opname
    </x-slot>

    @php
        $statusClass = static function ($status) {
            return match ($status) {
                'draft' => 'bg-gray-100 text-gray-700',
                'in_progress' => 'bg-blue-100 text-blue-700',
                'review' => 'bg-yellow-100 text-yellow-700',
                'finalized' => 'bg-green-100 text-green-700',
                default => 'bg-gray-100 text-gray-700',
            };
        };
    @endphp

    <div class="grid gap-5">
        <div class="flex items-center justify-between gap-4 rounded-lg border border-gray-200 bg-white p-5 max-sm:flex-wrap dark:border-gray-800 dark:bg-gray-900">
            <div>
                <p class="text-xl font-bold text-gray-800 dark:text-white">
                    Stock Opname
                </p>

                <p class="mt-1 text-sm text-gray-500">
                    Audit fisik serialized asset dengan QR scanner dan hitung actual quantity stock.
                </p>
            </div>

            @if (bouncer()->hasPermission('inventory.stock-opname.create'))
                <a
                    href="{{ route('admin.inventory.stock-opname.create') }}"
                    class="primary-button"
                >
                    + New Stock Opname
                </a>
            @endif
        </div>

        <div class="grid grid-cols-4 gap-3 max-lg:grid-cols-2 max-sm:grid-cols-1">
            <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                <p class="text-xs font-bold uppercase text-gray-500">Open Sessions</p>
                <p class="mt-2 text-2xl font-bold text-gray-800 dark:text-white">{{ $summary['open'] }}</p>
            </div>

            <div class="rounded-lg border border-blue-200 bg-blue-50 p-4">
                <p class="text-xs font-bold uppercase text-blue-700">In Progress</p>
                <p class="mt-2 text-2xl font-bold text-blue-700">{{ $summary['in_progress'] }}</p>
            </div>

            <div class="rounded-lg border border-yellow-200 bg-yellow-50 p-4">
                <p class="text-xs font-bold uppercase text-yellow-700">Review</p>
                <p class="mt-2 text-2xl font-bold text-yellow-700">{{ $summary['review'] }}</p>
            </div>

            <div class="rounded-lg border border-green-200 bg-green-50 p-4">
                <p class="text-xs font-bold uppercase text-green-700">Finalized</p>
                <p class="mt-2 text-2xl font-bold text-green-700">{{ $summary['finalized'] }}</p>
            </div>
        </div>

        <div class="overflow-hidden rounded-lg border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
            <div class="border-b border-gray-200 p-4 dark:border-gray-800">
                <p class="font-bold text-gray-800 dark:text-white">
                    Session History
                </p>
            </div>

            @if ($sessions->isEmpty())
                <div class="p-8 text-center text-sm text-gray-500">
                    Belum ada Stock Opname.
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="border-b border-gray-200 text-left text-xs uppercase text-gray-500 dark:border-gray-800">
                            <tr>
                                <th class="px-4 py-3">Reference</th>
                                <th class="px-4 py-3">Warehouse</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3">Started</th>
                                <th class="px-4 py-3">Finalized</th>
                                <th class="px-4 py-3 text-right">Action</th>
                            </tr>
                        </thead>

                        <tbody>
                            @foreach ($sessions as $session)
                                <tr class="border-b border-gray-100 last:border-b-0 dark:border-gray-800">
                                    <td class="px-4 py-3 font-semibold">
                                        {{ $session->reference_number ?: '#'.$session->id }}
                                    </td>

                                    <td class="px-4 py-3">
                                        {{ $session->warehouse?->name ?: '-' }}
                                    </td>

                                    <td class="px-4 py-3">
                                        <span class="rounded-full px-3 py-1 text-xs font-bold {{ $statusClass($session->status) }}">
                                            {{ strtoupper(str_replace('_', ' ', $session->status)) }}
                                        </span>
                                    </td>

                                    <td class="whitespace-nowrap px-4 py-3 text-sm">
                                        {{ $session->started_at?->format('d M Y H:i') ?: '-' }}
                                    </td>

                                    <td class="whitespace-nowrap px-4 py-3 text-sm">
                                        {{ $session->finalized_at?->format('d M Y H:i') ?: '-' }}
                                    </td>

                                    <td class="px-4 py-3 text-right">
                                        <a
                                            href="{{ route('admin.inventory.stock-opname.show', $session->id) }}"
                                            class="text-sm font-bold text-brandColor hover:underline"
                                        >
                                            Open
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="border-t border-gray-200 p-4 dark:border-gray-800">
                    {{ $sessions->links() }}
                </div>
            @endif
        </div>
    </div>
</x-admin::layouts>
