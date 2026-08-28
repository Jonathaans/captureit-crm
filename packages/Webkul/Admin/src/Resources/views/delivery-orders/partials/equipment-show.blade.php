<div class="rounded-lg border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
    @php
        $formatQty = static function ($value) {
            if ($value === null) {
                return '-';
            }

            return rtrim(
                rtrim(
                    number_format((float) $value, 2, '.', ''),
                    '0'
                ),
                '.'
            );
        };

        $deliveryStatus = strtolower(
            $deliveryOrder->status ?: 'draft'
        );
    @endphp

    <div class="mb-5 flex items-start justify-between gap-4 max-sm:flex-wrap">
        <div>
            <p class="text-base font-semibold text-gray-800 dark:text-white">
                Equipment / Inventory Requirement
            </p>

            <p class="mt-1 text-xs text-gray-500">
                Need adalah snapshot kebutuhan SJ. Allocated menunjukkan barang
                yang sudah dicadangkan khusus untuk Surat Jalan ini.
            </p>
        </div>

        @if (
            bouncer()->hasPermission('delivery-orders.inventory-allocation')
        )
            <a
                href="{{ route(
                    'admin.delivery-orders.inventory-allocation.edit',
                    $deliveryOrder->id
                ) }}"
                class="{{ $deliveryStatus === 'draft' ? 'primary-button' : 'secondary-button' }}"
            >
                {{ $deliveryStatus === 'draft' ? 'Manage Inventory Allocation' : 'View Inventory Allocation' }}
            </a>
        @endif
    </div>

    @if ($deliveryOrder->items->isEmpty())
        <div class="rounded-lg border border-dashed border-gray-300 p-8 text-center dark:border-gray-700">
            <p class="text-sm font-medium text-gray-600 dark:text-gray-300">
                Belum ada equipment.
            </p>
        </div>
    @else
        <div class="overflow-x-auto">
            <table class="w-full min-w-[1250px]">
                <thead>
                    <tr class="border-b border-gray-200 dark:border-gray-800">
                        <th class="px-3 py-3 text-left text-xs font-semibold text-gray-500">#</th>
                        <th class="px-3 py-3 text-left text-xs font-semibold text-gray-500">Equipment</th>
                        <th class="px-3 py-3 text-left text-xs font-semibold text-gray-500">Inventory Mapping</th>
                        <th class="px-3 py-3 text-right text-xs font-semibold text-gray-500">Need</th>
                        <th class="px-3 py-3 text-left text-xs font-semibold text-gray-500">Actual Allocation</th>
                        <th class="px-3 py-3 text-right text-xs font-semibold text-gray-500">Free Available</th>
                        <th class="px-3 py-3 text-right text-xs font-semibold text-gray-500">Shortage</th>
                        <th class="px-3 py-3 text-left text-xs font-semibold text-gray-500">Notes</th>
                    </tr>
                </thead>

                <tbody>
                    @foreach ($deliveryOrder->items as $item)
                        @php
                            $inventoryItem = $item->inventoryItem;
                            $need = (float) $item->quantity;

                            $activeAllocations = $item->allocations
                                ->whereIn(
                                    'status',
                                    \Webkul\Invoice\Models\DeliveryOrderInventoryAllocation::ACTIVE_STATUSES
                                );

                            $allocated = 0;
                            $freeAvailable = null;
                            $capacity = null;

                            if ($inventoryItem) {
                                if ($inventoryItem->isSerialized()) {
                                    $allocated = $activeAllocations->count();

                                    $freeAvailable = (float) $inventoryItem
                                        ->assets()
                                        ->where('status', 'available')
                                        ->count();

                                    $capacity = $allocated + $freeAvailable;
                                } else {
                                    $allocated = (float) $activeAllocations
                                        ->sum('quantity');

                                    $totalReserved = (float) \Webkul\Invoice\Models\DeliveryOrderInventoryAllocation::query()
                                        ->where('inventory_item_id', $inventoryItem->id)
                                        ->where('tracking_type', 'quantity')
                                        ->whereIn(
                                            'status',
                                            \Webkul\Invoice\Models\DeliveryOrderInventoryAllocation::ACTIVE_STATUSES
                                        )
                                        ->sum('quantity');

                                    $freeAvailable = max(
                                        (float) $inventoryItem->quantity_on_hand
                                        - $totalReserved,
                                        0
                                    );

                                    $capacity = $allocated + $freeAvailable;
                                }
                            }

                            $shortage = $capacity === null
                                ? null
                                : max($need - $capacity, 0);

                            $assetCodes = $activeAllocations
                                ->where('tracking_type', 'serialized')
                                ->map(fn ($allocation) => $allocation->inventoryAsset?->asset_code)
                                ->filter()
                                ->values();
                        @endphp

                        <tr class="border-b border-gray-100 dark:border-gray-800">
                            <td class="px-3 py-4 text-sm text-gray-500">
                                {{ $loop->iteration }}
                            </td>

                            <td class="px-3 py-4">
                                <p class="text-sm font-medium text-gray-800 dark:text-white">
                                    {{ $item->name }}
                                </p>

                                @if ($item->description)
                                    <p class="mt-1 text-xs text-gray-500">
                                        {{ $item->description }}
                                    </p>
                                @endif
                            </td>

                            <td class="px-3 py-4">
                                @if ($inventoryItem)
                                    <p class="text-sm font-semibold text-gray-800 dark:text-white">
                                        {{ $inventoryItem->code }}
                                    </p>

                                    <p class="mt-1 text-xs text-gray-500">
                                        {{ $inventoryItem->name }}
                                        · {{ ucfirst($inventoryItem->tracking_type) }}
                                    </p>
                                @else
                                    <span class="rounded-full bg-yellow-100 px-2.5 py-1 text-xs font-semibold text-yellow-700">
                                        NOT LINKED
                                    </span>
                                @endif
                            </td>

                            <td class="px-3 py-4 text-right text-sm font-semibold text-gray-800 dark:text-white">
                                {{ $formatQty($need) }} {{ $item->unit }}
                            </td>

                            <td class="px-3 py-4">
                                @if (! $inventoryItem)
                                    <span class="text-sm text-gray-400">-</span>
                                @elseif ($inventoryItem->isSerialized())
                                    @if ($assetCodes->isEmpty())
                                        <span class="rounded-full bg-yellow-100 px-2.5 py-1 text-xs font-bold text-yellow-700">
                                            NOT ALLOCATED
                                        </span>
                                    @else
                                        <div class="flex max-w-[320px] flex-wrap gap-1.5">
                                            @foreach ($assetCodes as $assetCode)
                                                <span class="rounded-full bg-blue-100 px-2.5 py-1 text-xs font-bold text-blue-700">
                                                    {{ $assetCode }}
                                                </span>
                                            @endforeach
                                        </div>
                                    @endif
                                @else
                                    @if ($allocated > 0)
                                        <span class="rounded-full bg-blue-100 px-2.5 py-1 text-xs font-bold text-blue-700">
                                            {{ $formatQty($allocated) }} {{ $inventoryItem->unit }} RESERVED
                                        </span>
                                    @else
                                        <span class="rounded-full bg-yellow-100 px-2.5 py-1 text-xs font-bold text-yellow-700">
                                            NOT ALLOCATED
                                        </span>
                                    @endif
                                @endif
                            </td>

                            <td class="px-3 py-4 text-right">
                                @if ($inventoryItem)
                                    <span class="text-sm font-semibold text-green-700">
                                        {{ $formatQty($freeAvailable) }}
                                        {{ $inventoryItem->unit }}
                                    </span>
                                @else
                                    <span class="text-sm text-gray-400">-</span>
                                @endif
                            </td>

                            <td class="px-3 py-4 text-right">
                                @if (! $inventoryItem)
                                    <span class="text-sm text-gray-400">-</span>
                                @elseif ($shortage > 0)
                                    <span class="rounded-full bg-red-100 px-2.5 py-1 text-xs font-bold text-red-700">
                                        {{ $formatQty($shortage) }} {{ $inventoryItem->unit }}
                                    </span>
                                @else
                                    <span class="rounded-full bg-green-100 px-2.5 py-1 text-xs font-bold text-green-700">
                                        OK
                                    </span>
                                @endif
                            </td>

                            <td class="px-3 py-4 text-sm text-gray-600 dark:text-gray-300">
                                {{ $item->notes ?: '-' }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-4 rounded-md border border-gray-200 bg-gray-50 px-4 py-3 text-xs text-gray-500 dark:border-gray-800 dark:bg-gray-950">
            Serialized menampilkan Asset Code aktual, misalnya CAM-002.
            Quantity menampilkan jumlah yang di-reserve. Allocation belum berarti barang OUT.
        </div>
    @endif
</div>
