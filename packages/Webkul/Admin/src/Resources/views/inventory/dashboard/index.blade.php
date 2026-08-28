<x-admin::layouts>
    <x-slot:title>
        Inventory Dashboard
    </x-slot>

    @php
        $formatQty = static function ($value) {
            return rtrim(
                rtrim(
                    number_format((float) $value, 2, '.', ''),
                    '0'
                ),
                '.'
            );
        };

        $movementLabel = static function ($type) {
            return strtoupper(
                str_replace('_', ' ', (string) $type)
            );
        };

        $assetStatusLabel = static function ($status) {
            return ucwords(
                str_replace('_', ' ', (string) $status)
            );
        };
    @endphp

    <div class="grid gap-4">
        <div class="flex items-center justify-between gap-4 rounded-lg border border-gray-200 bg-white p-4 max-sm:flex-wrap dark:border-gray-800 dark:bg-gray-900">
            <div>
                <p class="text-xl font-bold leading-6 text-gray-800 dark:text-white">
                    Inventory Dashboard
                </p>

                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Monitoring Gudang Utama: asset serialized, quantity stock, dan movement terbaru.
                </p>
            </div>

            <div class="flex flex-wrap gap-2">
                @if (bouncer()->hasPermission('inventory.items.create'))
                    <a
                        href="{{ route('admin.inventory.items.create') }}"
                        class="secondary-button"
                    >
                        + Inventory Item
                    </a>
                @endif

                @if (bouncer()->hasPermission('inventory.assets.create'))
                    <a
                        href="{{ route('admin.inventory.assets.create') }}"
                        class="primary-button"
                    >
                        + Asset
                    </a>
                @endif
            </div>
        </div>

        <div class="grid grid-cols-5 gap-3 max-xl:grid-cols-3 max-md:grid-cols-2 max-sm:grid-cols-1">
            <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                <p class="text-xs font-medium uppercase text-gray-500">Total Assets</p>
                <p class="mt-2 text-2xl font-bold text-gray-800 dark:text-white">
                    {{ $summary['total_assets'] }}
                </p>
            </div>

            <div class="rounded-lg border border-green-200 bg-green-50 p-4 dark:border-green-900 dark:bg-gray-900">
                <p class="text-xs font-medium uppercase text-green-700">Available</p>
                <p class="mt-2 text-2xl font-bold text-green-700">
                    {{ $summary['available'] }}
                </p>
            </div>

            <div class="rounded-lg border border-blue-200 bg-blue-50 p-4 dark:border-blue-900 dark:bg-gray-900">
                <p class="text-xs font-medium uppercase text-blue-700">Allocated / Picked</p>
                <p class="mt-2 text-2xl font-bold text-blue-700">
                    {{ $summary['allocated'] + $summary['picked'] }}
                </p>
                <p class="mt-1 text-xs text-blue-600">
                    {{ $summary['allocated'] }} allocated · {{ $summary['picked'] }} picked
                </p>
            </div>

            <div class="rounded-lg border border-indigo-200 bg-indigo-50 p-4 dark:border-indigo-900 dark:bg-gray-900">
                <p class="text-xs font-medium uppercase text-indigo-700">Out / Return Pending</p>
                <p class="mt-2 text-2xl font-bold text-indigo-700">
                    {{ $summary['out'] + $summary['return_pending'] }}
                </p>
                <p class="mt-1 text-xs text-indigo-600">
                    {{ $summary['out'] }} out · {{ $summary['return_pending'] }} pending
                </p>
            </div>

            <div class="rounded-lg border border-red-200 bg-red-50 p-4 dark:border-red-900 dark:bg-gray-900">
                <p class="text-xs font-medium uppercase text-red-700">Problem Assets</p>
                <p class="mt-2 text-2xl font-bold text-red-700">
                    {{ $summary['damaged'] + $summary['missing'] + $summary['maintenance'] }}
                </p>
                <p class="mt-1 text-xs text-red-600">
                    {{ $summary['damaged'] }} damaged ·
                    {{ $summary['missing'] }} missing ·
                    {{ $summary['maintenance'] }} maintenance
                </p>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4 max-lg:grid-cols-1">
            <div class="rounded-lg border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
                <div class="flex items-center justify-between border-b border-gray-200 p-4 dark:border-gray-800">
                    <div>
                        <p class="font-semibold text-gray-800 dark:text-white">
                            Quantity Stock
                        </p>

                        <p class="mt-1 text-xs text-gray-500">
                            {{ $summary['quantity_items'] }} active quantity items
                        </p>
                    </div>

                    @if ($summary['low_stock_items'] > 0)
                        <span class="rounded-full bg-red-100 px-3 py-1 text-xs font-bold text-red-700">
                            {{ $summary['low_stock_items'] }} LOW STOCK
                        </span>
                    @else
                        <span class="rounded-full bg-green-100 px-3 py-1 text-xs font-bold text-green-700">
                            STOCK HEALTHY
                        </span>
                    @endif
                </div>

                @if ($lowStockItems->isEmpty())
                    <div class="p-5 text-sm text-gray-500">
                        Tidak ada quantity item yang menyentuh minimum stock.
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="border-b border-gray-200 text-left text-xs uppercase text-gray-500 dark:border-gray-800">
                                <tr>
                                    <th class="px-4 py-3">Item</th>
                                    <th class="px-4 py-3">Current</th>
                                    <th class="px-4 py-3">Minimum</th>
                                </tr>
                            </thead>

                            <tbody>
                                @foreach ($lowStockItems as $item)
                                    <tr class="border-b border-gray-100 last:border-b-0 dark:border-gray-800">
                                        <td class="px-4 py-3">
                                            <a
                                                href="{{ route('admin.inventory.items.edit', $item->id) }}"
                                                class="font-medium text-brandColor hover:underline"
                                            >
                                                {{ $item->code }} — {{ $item->name }}
                                            </a>
                                        </td>

                                        <td class="px-4 py-3 font-semibold text-red-700">
                                            {{ $formatQty($item->quantity_on_hand) }} {{ $item->unit }}
                                        </td>

                                        <td class="px-4 py-3">
                                            {{ $formatQty($item->minimum_stock) }} {{ $item->unit }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            <div class="rounded-lg border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
                <div class="flex items-center justify-between border-b border-gray-200 p-4 dark:border-gray-800">
                    <div>
                        <p class="font-semibold text-gray-800 dark:text-white">
                            Asset Attention
                        </p>

                        <p class="mt-1 text-xs text-gray-500">
                            Out, return pending, maintenance, damaged, dan missing.
                        </p>
                    </div>

                    <a
                        href="{{ route('admin.inventory.assets.index') }}"
                        class="text-sm font-medium text-brandColor hover:underline"
                    >
                        View Assets
                    </a>
                </div>

                @if ($attentionAssets->isEmpty())
                    <div class="p-5 text-sm text-gray-500">
                        Tidak ada asset yang membutuhkan perhatian saat ini.
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="border-b border-gray-200 text-left text-xs uppercase text-gray-500 dark:border-gray-800">
                                <tr>
                                    <th class="px-4 py-3">Asset</th>
                                    <th class="px-4 py-3">Item</th>
                                    <th class="px-4 py-3">Status</th>
                                </tr>
                            </thead>

                            <tbody>
                                @foreach ($attentionAssets as $asset)
                                    <tr class="border-b border-gray-100 last:border-b-0 dark:border-gray-800">
                                        <td class="px-4 py-3">
                                            <a
                                                href="{{ route('admin.inventory.assets.edit', $asset->id) }}"
                                                class="font-medium text-brandColor hover:underline"
                                            >
                                                {{ $asset->asset_code }}
                                            </a>
                                        </td>

                                        <td class="px-4 py-3">
                                            {{ $asset->item_code }} — {{ $asset->item_name }}
                                        </td>

                                        <td class="px-4 py-3 font-semibold">
                                            {{ $assetStatusLabel($asset->status) }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>

        <div class="rounded-lg border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
            <div class="flex items-center justify-between border-b border-gray-200 p-4 dark:border-gray-800">
                <div>
                    <p class="font-semibold text-gray-800 dark:text-white">
                        Recent Movements
                    </p>

                    <p class="mt-1 text-xs text-gray-500">
                        10 pergerakan inventory terbaru.
                    </p>
                </div>

                <a
                    href="{{ route('admin.inventory.movements.index') }}"
                    class="text-sm font-medium text-brandColor hover:underline"
                >
                    View All Movements
                </a>
            </div>

            @if ($recentMovements->isEmpty())
                <div class="p-5 text-sm text-gray-500">
                    Belum ada movement inventory.
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="border-b border-gray-200 text-left text-xs uppercase text-gray-500 dark:border-gray-800">
                            <tr>
                                <th class="px-4 py-3">Date / Time</th>
                                <th class="px-4 py-3">Item</th>
                                <th class="px-4 py-3">Asset</th>
                                <th class="px-4 py-3">Movement</th>
                                <th class="px-4 py-3">Qty</th>
                                <th class="px-4 py-3">By</th>
                            </tr>
                        </thead>

                        <tbody>
                            @foreach ($recentMovements as $movement)
                                <tr class="border-b border-gray-100 last:border-b-0 dark:border-gray-800">
                                    <td class="whitespace-nowrap px-4 py-3">
                                        {{ \Carbon\Carbon::parse($movement->occurred_at)->format('d M Y H:i') }}
                                    </td>

                                    <td class="px-4 py-3">
                                        {{ $movement->item_code }} — {{ $movement->item_name }}
                                    </td>

                                    <td class="px-4 py-3">
                                        {{ $movement->asset_code ?: '-' }}
                                    </td>

                                    <td class="px-4 py-3 font-semibold">
                                        {{ $movementLabel($movement->movement_type) }}
                                    </td>

                                    <td class="whitespace-nowrap px-4 py-3">
                                        {{ $formatQty($movement->quantity) }} {{ $movement->unit }}
                                    </td>

                                    <td class="px-4 py-3">
                                        {{ $movement->performed_by_name ?: 'System / Migration' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</x-admin::layouts>
