<x-admin::layouts>
    <x-slot:title>
        Inventory Allocation — {{ $deliveryOrder->delivery_order_number }}
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

        $status = strtolower(
            $deliveryOrder->status ?: 'draft'
        );
    @endphp

    <div class="flex items-center justify-between gap-4 rounded-lg border border-gray-200 bg-white p-4 max-sm:flex-wrap dark:border-gray-800 dark:bg-gray-900">
        <div>
            <a
                href="{{ route('admin.delivery-orders.show', $deliveryOrder->id) }}"
                class="text-sm text-gray-600 hover:text-brandColor dark:text-gray-300"
            >
                ← Back to Surat Jalan
            </a>

            <div class="mt-2 flex items-center gap-3">
                <p class="text-xl font-bold text-gray-800 dark:text-white">
                    Inventory Allocation
                </p>

                <span class="rounded-full bg-gray-100 px-3 py-1 text-xs font-bold uppercase text-gray-700 dark:bg-gray-800 dark:text-gray-300">
                    {{ $status }}
                </span>
            </div>

            <p class="mt-1 text-sm text-gray-500">
                {{ $deliveryOrder->delivery_order_number }}
                · {{ $deliveryOrder->project_code ?: '-' }}
                @if ($deliveryOrder->project_name)
                    · {{ $deliveryOrder->project_name }}
                @endif
            </p>
        </div>

        @if (! $editable)
            <div class="rounded-md bg-yellow-50 px-4 py-2 text-sm font-medium text-yellow-700">
                Read only. Allocation hanya dapat diubah saat Surat Jalan masih DRAFT.
            </div>
        @endif
    </div>

    <div class="mt-4 rounded-lg border border-blue-200 bg-blue-50 p-4 text-sm text-blue-800 dark:border-blue-900 dark:bg-blue-900/20 dark:text-blue-200">
        <strong>Allocation = reservation.</strong>
        Serialized memilih asset fisik tertentu. Quantity hanya mencadangkan jumlah.
        Barang belum dianggap keluar gudang pada tahap ini.
    </div>

    <div class="mt-4 grid gap-4">
        @foreach ($deliveryOrder->items as $item)
            @php
                $inventoryItem = $item->inventoryItem;
                $summary = $summaries[$item->id] ?? null;

                $activeAllocations = $item->allocations
                    ->whereIn(
                        'status',
                        \Webkul\Invoice\Models\DeliveryOrderInventoryAllocation::ACTIVE_STATUSES
                    );

                $selectedAssetIds = $activeAllocations
                    ->pluck('inventory_asset_id')
                    ->filter()
                    ->map(fn ($value) => (int) $value)
                    ->all();
            @endphp

            <div class="rounded-lg border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
                <div class="flex items-start justify-between gap-4 max-sm:flex-wrap">
                    <div>
                        <p class="text-base font-semibold text-gray-800 dark:text-white">
                            {{ $loop->iteration }}. {{ $item->name }}
                        </p>

                        @if ($item->description)
                            <p class="mt-1 text-sm text-gray-500">
                                {{ $item->description }}
                            </p>
                        @endif
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <span class="rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold text-gray-700">
                            NEED {{ $formatQty($item->quantity) }} {{ $item->unit }}
                        </span>

                        @if ($summary && $summary['complete'])
                            <span class="rounded-full bg-green-100 px-3 py-1 text-xs font-bold text-green-700">
                                COMPLETE
                            </span>
                        @elseif ($inventoryItem)
                            <span class="rounded-full bg-yellow-100 px-3 py-1 text-xs font-bold text-yellow-700">
                                INCOMPLETE
                            </span>
                        @endif
                    </div>
                </div>

                @if (! $inventoryItem)
                    <div class="mt-4 rounded-md border border-yellow-200 bg-yellow-50 p-4 text-sm text-yellow-700">
                        Item ini belum terhubung ke Inventory Item. Mapping dahulu melalui Edit Surat Jalan / Equipment Template.
                    </div>
                @elseif ($inventoryItem->isSerialized())
                    <div class="mt-4 grid grid-cols-4 gap-3 max-lg:grid-cols-2 max-sm:grid-cols-1">
                        <div class="rounded-md border border-gray-200 p-3 dark:border-gray-800">
                            <p class="text-xs uppercase text-gray-500">Inventory Item</p>
                            <p class="mt-1 font-semibold text-gray-800 dark:text-white">
                                {{ $inventoryItem->code }}
                            </p>
                            <p class="text-xs text-gray-500">{{ $inventoryItem->name }}</p>
                        </div>

                        <div class="rounded-md border border-gray-200 p-3 dark:border-gray-800">
                            <p class="text-xs uppercase text-gray-500">Allocated</p>
                            <p class="mt-1 text-lg font-bold text-gray-800 dark:text-white">
                                {{ $summary['allocated'] }} asset
                            </p>
                        </div>

                        <div class="rounded-md border border-gray-200 p-3 dark:border-gray-800">
                            <p class="text-xs uppercase text-gray-500">Free Available</p>
                            <p class="mt-1 text-lg font-bold text-green-700">
                                {{ $summary['free_available'] }} asset
                            </p>
                        </div>

                        <div class="rounded-md border border-gray-200 p-3 dark:border-gray-800">
                            <p class="text-xs uppercase text-gray-500">Capacity for this SJ</p>
                            <p class="mt-1 text-lg font-bold text-gray-800 dark:text-white">
                                {{ $summary['max_for_this'] }} asset
                            </p>
                        </div>
                    </div>

                    <form
                        method="POST"
                        action="{{ route(
                            'admin.delivery-orders.inventory-allocation.update',
                            [$deliveryOrder->id, $item->id]
                        ) }}"
                        class="mt-4"
                    >
                        @csrf
                        @method('PUT')

                        <div class="overflow-x-auto rounded-md border border-gray-200 dark:border-gray-800">
                            <table class="w-full min-w-[760px]">
                                <thead>
                                    <tr class="border-b border-gray-200 bg-gray-50 text-left text-xs uppercase text-gray-500 dark:border-gray-800 dark:bg-gray-950">
                                        <th class="w-[70px] px-3 py-3">Select</th>
                                        <th class="px-3 py-3">Asset Code</th>
                                        <th class="px-3 py-3">Serial Number</th>
                                        <th class="px-3 py-3">Condition</th>
                                        <th class="px-3 py-3">Status</th>
                                    </tr>
                                </thead>

                                <tbody>
                                    @forelse (($assetOptions[$item->id] ?? collect()) as $asset)
                                        @php
                                            $isSelected = in_array(
                                                (int) $asset->id,
                                                $selectedAssetIds,
                                                true
                                            );
                                        @endphp

                                        <tr class="border-b border-gray-100 last:border-b-0 dark:border-gray-800">
                                            <td class="px-3 py-3">
                                                <input
                                                    type="checkbox"
                                                    name="asset_ids[]"
                                                    value="{{ $asset->id }}"
                                                    @checked($isSelected)
                                                    @disabled(! $editable)
                                                    class="h-4 w-4"
                                                >
                                            </td>

                                            <td class="px-3 py-3 font-semibold text-gray-800 dark:text-white">
                                                {{ $asset->asset_code }}
                                            </td>

                                            <td class="px-3 py-3 text-sm text-gray-600 dark:text-gray-300">
                                                {{ $asset->serial_number ?: '-' }}
                                            </td>

                                            <td class="px-3 py-3 text-sm">
                                                {{ ucfirst($asset->condition ?: '-') }}
                                            </td>

                                            <td class="px-3 py-3">
                                                <span class="rounded-full px-2.5 py-1 text-xs font-bold {{ $isSelected ? 'bg-blue-100 text-blue-700' : 'bg-green-100 text-green-700' }}">
                                                    {{ $isSelected ? 'ALLOCATED TO THIS SJ' : strtoupper($asset->status) }}
                                                </span>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="px-4 py-8 text-center text-sm text-gray-500">
                                                Tidak ada asset AVAILABLE untuk Inventory Item ini.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        @error('asset_ids')
                            <p class="mt-2 text-xs text-red-600">{{ $message }}</p>
                        @enderror

                        @error('asset_ids.*')
                            <p class="mt-2 text-xs text-red-600">{{ $message }}</p>
                        @enderror

                        @if ($editable)
                            <div class="mt-4 flex justify-end gap-2">
                                @if ($summary['allocated'] > 0)
                                    <button
                                        type="submit"
                                        form="release-allocation-{{ $item->id }}"
                                        class="secondary-button"
                                        onclick="return confirm('Release seluruh asset allocation untuk item ini?')"
                                    >
                                        Release All
                                    </button>
                                @endif

                                <button type="submit" class="primary-button">
                                    Save Asset Allocation
                                </button>
                            </div>
                        @endif
                    </form>

                    @if ($editable && $summary['allocated'] > 0)
                        <form
                            id="release-allocation-{{ $item->id }}"
                            method="POST"
                            action="{{ route(
                                'admin.delivery-orders.inventory-allocation.release',
                                [$deliveryOrder->id, $item->id]
                            ) }}"
                        >
                            @csrf
                            @method('DELETE')
                        </form>
                    @endif
                @else
                    <div class="mt-4 grid grid-cols-5 gap-3 max-xl:grid-cols-3 max-md:grid-cols-2 max-sm:grid-cols-1">
                        <div class="rounded-md border border-gray-200 p-3 dark:border-gray-800">
                            <p class="text-xs uppercase text-gray-500">Inventory Item</p>
                            <p class="mt-1 font-semibold text-gray-800 dark:text-white">
                                {{ $inventoryItem->code }}
                            </p>
                            <p class="text-xs text-gray-500">{{ $inventoryItem->name }}</p>
                        </div>

                        <div class="rounded-md border border-gray-200 p-3 dark:border-gray-800">
                            <p class="text-xs uppercase text-gray-500">Physical Stock</p>
                            <p class="mt-1 text-lg font-bold text-gray-800 dark:text-white">
                                {{ $formatQty($inventoryItem->quantity_on_hand) }} {{ $inventoryItem->unit }}
                            </p>
                        </div>

                        <div class="rounded-md border border-gray-200 p-3 dark:border-gray-800">
                            <p class="text-xs uppercase text-gray-500">Allocated to this SJ</p>
                            <p class="mt-1 text-lg font-bold text-blue-700">
                                {{ $formatQty($summary['allocated']) }} {{ $inventoryItem->unit }}
                            </p>
                        </div>

                        <div class="rounded-md border border-gray-200 p-3 dark:border-gray-800">
                            <p class="text-xs uppercase text-gray-500">Free Available</p>
                            <p class="mt-1 text-lg font-bold text-green-700">
                                {{ $formatQty($summary['free_available']) }} {{ $inventoryItem->unit }}
                            </p>
                        </div>

                        <div class="rounded-md border border-gray-200 p-3 dark:border-gray-800">
                            <p class="text-xs uppercase text-gray-500">Max for this SJ</p>
                            <p class="mt-1 text-lg font-bold text-gray-800 dark:text-white">
                                {{ $formatQty($summary['max_for_this']) }} {{ $inventoryItem->unit }}
                            </p>
                        </div>
                    </div>

                    <form
                        method="POST"
                        action="{{ route(
                            'admin.delivery-orders.inventory-allocation.update',
                            [$deliveryOrder->id, $item->id]
                        ) }}"
                        class="mt-4 flex items-end gap-3 max-sm:flex-col max-sm:items-stretch"
                    >
                        @csrf
                        @method('PUT')

                        <div class="w-full max-w-xs">
                            <label class="mb-1.5 block text-sm font-medium text-gray-800 dark:text-white">
                                Reserved Quantity
                            </label>

                            <input
                                type="number"
                                name="quantity"
                                value="{{ old('quantity', $formatQty($summary['allocated'])) }}"
                                min="0"
                                step="0.01"
                                max="{{ $formatQty(min($summary['need'], $summary['max_for_this'])) }}"
                                @disabled(! $editable)
                                class="w-full rounded-md border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-800 outline-none focus:border-brandColor dark:border-gray-800 dark:bg-gray-950 dark:text-white"
                            >

                            @error('quantity')
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        @if ($editable)
                            <button type="submit" class="primary-button">
                                Save Quantity Allocation
                            </button>

                            @if ($summary['allocated'] > 0)
                                <button
                                    type="submit"
                                    form="release-allocation-{{ $item->id }}"
                                    class="secondary-button"
                                    onclick="return confirm('Release quantity allocation untuk item ini?')"
                                >
                                    Release
                                </button>
                            @endif
                        @endif
                    </form>

                    @if ($editable && $summary['allocated'] > 0)
                        <form
                            id="release-allocation-{{ $item->id }}"
                            method="POST"
                            action="{{ route(
                                'admin.delivery-orders.inventory-allocation.release',
                                [$deliveryOrder->id, $item->id]
                            ) }}"
                        >
                            @csrf
                            @method('DELETE')
                        </form>
                    @endif
                @endif
            </div>
        @endforeach
    </div>
</x-admin::layouts>
