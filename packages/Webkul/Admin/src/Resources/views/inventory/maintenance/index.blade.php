<x-admin::layouts>
    <x-slot:title>
        Inventory Maintenance
    </x-slot>

    <div class="flex items-center justify-between gap-4 rounded-lg border border-gray-200 bg-white p-4 max-sm:flex-wrap dark:border-gray-800 dark:bg-gray-900">
        <div>
            <p class="text-xl font-bold text-gray-800 dark:text-white">
                Maintenance & Repair
            </p>

            <p class="mt-1 text-sm text-gray-500">
                DAMAGED &rarr; MAINTENANCE &rarr; AVAILABLE / RETIRED.
            </p>
        </div>

        <div class="flex flex-wrap gap-2">
            <a
                href="{{ route('admin.inventory.assets.index') }}"
                class="secondary-button"
            >
                Inventory Assets
            </a>

            @if (bouncer()->hasPermission('inventory.maintenance.start'))
                <a
                    href="{{ route('admin.inventory.maintenance.create') }}"
                    class="primary-button"
                >
                    + Start Maintenance
                </a>
            @endif
        </div>
    </div>

    <div class="mt-4 grid grid-cols-4 gap-3 max-lg:grid-cols-2 max-sm:grid-cols-1">
        <div class="rounded-lg border border-red-200 bg-red-50 p-4">
            <p class="text-xs font-bold uppercase text-red-600">Damaged Waiting</p>
            <p class="mt-1 text-2xl font-bold text-red-800">{{ $summary['damaged'] }}</p>
        </div>

        <div class="rounded-lg border border-yellow-200 bg-yellow-50 p-4">
            <p class="text-xs font-bold uppercase text-yellow-600">In Maintenance</p>
            <p class="mt-1 text-2xl font-bold text-yellow-800">{{ $summary['in_progress'] }}</p>
        </div>

        <div class="rounded-lg border border-green-200 bg-green-50 p-4">
            <p class="text-xs font-bold uppercase text-green-600">Completed</p>
            <p class="mt-1 text-2xl font-bold text-green-800">{{ $summary['completed'] }}</p>
        </div>

        <div class="rounded-lg border border-gray-300 bg-gray-50 p-4">
            <p class="text-xs font-bold uppercase text-gray-600">Retired</p>
            <p class="mt-1 text-2xl font-bold text-gray-800">{{ $summary['retired'] }}</p>
        </div>
    </div>

    <div class="mt-5">
        <div class="mb-3">
            <p class="text-lg font-bold text-gray-800 dark:text-white">
                Damaged Assets Waiting for Maintenance
            </p>

            <p class="mt-1 text-xs text-gray-500">
                Asset muncul di sini setelah Return difinalisasi sebagai DAMAGED.
            </p>
        </div>

        <div class="grid gap-3">
            @forelse ($damagedAssets as $asset)
                <div class="rounded-lg border border-red-200 bg-white p-4 dark:bg-gray-900">
                    <div class="flex items-center justify-between gap-4 max-sm:flex-wrap">
                        <div>
                            <p class="font-bold text-gray-800 dark:text-white">
                                {{ $asset->asset_code }}
                            </p>

                            <p class="mt-1 text-sm text-gray-500">
                                {{ $asset->item?->code ?: '-' }}
                                &middot;
                                {{ $asset->item?->name ?: '-' }}
                            </p>
                        </div>

                        <div class="flex items-center gap-2">
                            <span class="rounded-full bg-red-100 px-3 py-1 text-xs font-bold text-red-700">
                                DAMAGED
                            </span>

                            @if (bouncer()->hasPermission('inventory.maintenance.start'))
                                <a
                                    href="{{ route(
                                        'admin.inventory.maintenance.create',
                                        ['asset_id' => $asset->id]
                                    ) }}"
                                    class="primary-button"
                                >
                                    Send to Maintenance
                                </a>
                            @endif
                        </div>
                    </div>
                </div>
            @empty
                <div class="rounded-lg border border-dashed border-gray-300 bg-white p-6 text-center text-sm text-gray-500 dark:border-gray-700 dark:bg-gray-900">
                    Tidak ada asset DAMAGED yang menunggu Maintenance.
                </div>
            @endforelse
        </div>
    </div>

    <div class="mt-6">
        <p class="mb-3 text-lg font-bold text-gray-800 dark:text-white">
            Active Maintenance
        </p>

        <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-gray-200 bg-gray-50 text-xs uppercase text-gray-500 dark:border-gray-800 dark:bg-gray-950">
                    <tr>
                        <th class="px-4 py-3">Reference</th>
                        <th class="px-4 py-3">Asset</th>
                        <th class="px-4 py-3">Problem</th>
                        <th class="px-4 py-3">Technician</th>
                        <th class="px-4 py-3">Started</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($activeMaintenances as $maintenance)
                        <tr class="border-b border-gray-100 last:border-0 dark:border-gray-800">
                            <td class="px-4 py-3 font-mono font-bold">
                                {{ $maintenance->reference_number }}
                            </td>

                            <td class="px-4 py-3">
                                <div class="font-bold">
                                    {{ $maintenance->asset?->asset_code ?: '-' }}
                                </div>

                                <div class="mt-1 text-xs text-gray-500">
                                    {{ $maintenance->asset?->item?->name ?: '-' }}
                                </div>
                            </td>

                            <td class="max-w-sm px-4 py-3">
                                {{ \Illuminate\Support\Str::limit($maintenance->problem, 90) }}
                            </td>

                            <td class="px-4 py-3">
                                {{ $maintenance->technician_name ?: '-' }}
                            </td>

                            <td class="px-4 py-3 whitespace-nowrap">
                                {{ optional($maintenance->started_at)->format('d M Y H:i') ?: '-' }}
                            </td>

                            <td class="px-4 py-3 text-right">
                                <a
                                    href="{{ route('admin.inventory.maintenance.show', $maintenance->id) }}"
                                    class="text-sm font-semibold text-blue-600 hover:underline"
                                >
                                    Open
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-gray-500">
                                Tidak ada Maintenance aktif.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-6">
        <p class="mb-3 text-lg font-bold text-gray-800 dark:text-white">
            Repair History
        </p>

        <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-gray-200 bg-gray-50 text-xs uppercase text-gray-500 dark:border-gray-800 dark:bg-gray-950">
                    <tr>
                        <th class="px-4 py-3">Reference</th>
                        <th class="px-4 py-3">Asset</th>
                        <th class="px-4 py-3">Result</th>
                        <th class="px-4 py-3">Repair Cost</th>
                        <th class="px-4 py-3">Date</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($history as $maintenance)
                        <tr class="border-b border-gray-100 last:border-0 dark:border-gray-800">
                            <td class="px-4 py-3 font-mono font-bold">
                                {{ $maintenance->reference_number }}
                            </td>

                            <td class="px-4 py-3">
                                <div class="font-bold">
                                    {{ $maintenance->asset?->asset_code ?: '-' }}
                                </div>

                                <div class="mt-1 text-xs text-gray-500">
                                    {{ $maintenance->asset?->item?->name ?: '-' }}
                                </div>
                            </td>

                            <td class="px-4 py-3">
                                @if ($maintenance->status === 'completed')
                                    <span class="rounded-full bg-green-100 px-3 py-1 text-xs font-bold text-green-700">
                                        {{ strtoupper($maintenance->result_condition ?: 'COMPLETED') }}
                                    </span>
                                @else
                                    <span class="rounded-full bg-gray-200 px-3 py-1 text-xs font-bold text-gray-700">
                                        RETIRED
                                    </span>
                                @endif
                            </td>

                            <td class="px-4 py-3">
                                Rp {{ number_format((float) $maintenance->repair_cost, 0, ',', '.') }}
                            </td>

                            <td class="px-4 py-3 whitespace-nowrap">
                                {{
                                    optional(
                                        $maintenance->completed_at
                                            ?: $maintenance->retired_at
                                    )->format('d M Y H:i') ?: '-'
                                }}
                            </td>

                            <td class="px-4 py-3 text-right">
                                <a
                                    href="{{ route('admin.inventory.maintenance.show', $maintenance->id) }}"
                                    class="text-sm font-semibold text-blue-600 hover:underline"
                                >
                                    View
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-gray-500">
                                Belum ada repair history.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($history->hasPages())
            <div class="mt-3">
                {{ $history->links() }}
            </div>
        @endif
    </div>
</x-admin::layouts>
