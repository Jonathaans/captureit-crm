@php
    /*
     * Ambil data validation lama jika ada.
     * Kalau tidak ada, gunakan equipment yang sudah tersimpan.
     */
    $existingItems = old('items');

    if ($existingItems === null) {
        $existingItems = $deliveryOrder->items
            ->map(function ($item) {
                return [
                    'inventory_item_id' => $item->inventory_item_id,
                    'name'              => $item->name,
                    'description'       => $item->description,
                    'quantity'          => $item->quantity,
                    'unit'              => $item->unit,
                    'notes'             => $item->notes,
                ];
            })
            ->values()
            ->toArray();
    }

    $inventoryItems = \Webkul\Warehouse\Models\InventoryItem::query()
        ->where('is_active', true)
        ->orderBy('code')
        ->orderBy('name')
        ->get([
            'id',
            'code',
            'name',
            'tracking_type',
            'unit',
        ]);

    $equipmentRowCount = max(
        10,
        count($existingItems)
    );
@endphp

<div class="mt-4 rounded-lg border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
    <div class="mb-5">
        <p class="text-base font-semibold text-gray-800 dark:text-white">
            Equipment / Inventory Requirement
        </p>

        <p class="mt-1 text-xs text-gray-500">
            Equipment adalah snapshot kebutuhan Surat Jalan. Inventory Item
            menentukan master stok yang memenuhi kebutuhan tersebut.
            Actual asset code belum dipilih pada tahap ini.
        </p>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full min-w-[1280px]">
            <thead>
                <tr class="border-b border-gray-200 dark:border-gray-800">
                    <th class="w-[45px] px-2 py-3 text-left text-xs font-semibold text-gray-500">
                        #
                    </th>

                    <th class="min-w-[150px] px-2 py-3 text-left text-xs font-semibold text-gray-500">
                        Item
                    </th>

                    <th class="min-w-[190px] px-2 py-3 text-left text-xs font-semibold text-gray-500">
                        Description
                    </th>

                    <th class="min-w-[280px] px-2 py-3 text-left text-xs font-semibold text-gray-500">
                        Inventory Item
                    </th>

                    <th class="w-[100px] px-2 py-3 text-left text-xs font-semibold text-gray-500">
                        Qty
                    </th>

                    <th class="w-[120px] px-2 py-3 text-left text-xs font-semibold text-gray-500">
                        Unit
                    </th>

                    <th class="min-w-[180px] px-2 py-3 text-left text-xs font-semibold text-gray-500">
                        Notes
                    </th>
                </tr>
            </thead>

            <tbody>
                @for ($index = 0; $index < $equipmentRowCount; $index++)
                    @php
                        $item = $existingItems[$index] ?? [];
                        $selectedInventoryItemId = $item['inventory_item_id'] ?? null;
                    @endphp

                    <tr class="border-b border-gray-100 dark:border-gray-800">
                        <td class="px-2 py-2 text-sm text-gray-500">
                            {{ $index + 1 }}
                        </td>

                        <td class="px-2 py-2">
                            <input
                                type="text"
                                name="items[{{ $index }}][name]"
                                value="{{ $item['name'] ?? '' }}"
                                placeholder="Camera"
                                class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 outline-none transition-all focus:border-brandColor dark:border-gray-800 dark:bg-gray-950 dark:text-white"
                            >
                        </td>

                        <td class="px-2 py-2">
                            <input
                                type="text"
                                name="items[{{ $index }}][description]"
                                value="{{ $item['description'] ?? '' }}"
                                placeholder="Canon EOS 700D"
                                class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 outline-none transition-all focus:border-brandColor dark:border-gray-800 dark:bg-gray-950 dark:text-white"
                            >
                        </td>

                        <td class="px-2 py-2">
                            <select
                                name="items[{{ $index }}][inventory_item_id]"
                                class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 outline-none transition-all focus:border-brandColor dark:border-gray-800 dark:bg-gray-950 dark:text-white"
                            >
                                <option value="">
                                    -- Not linked --
                                </option>

                                @foreach ($inventoryItems as $inventoryItem)
                                    <option
                                        value="{{ $inventoryItem->id }}"
                                        @selected(
                                            (string) $selectedInventoryItemId
                                            === (string) $inventoryItem->id
                                        )
                                    >
                                        {{ $inventoryItem->code }}
                                        — {{ $inventoryItem->name }}
                                        ({{ ucfirst($inventoryItem->tracking_type) }})
                                    </option>
                                @endforeach
                            </select>

                            @error("items.{$index}.inventory_item_id")
                                <p class="mt-1 text-xs text-red-600">
                                    {{ $message }}
                                </p>
                            @enderror
                        </td>

                        <td class="px-2 py-2">
                            <input
                                type="number"
                                step="0.01"
                                min="0.01"
                                name="items[{{ $index }}][quantity]"
                                value="{{ $item['quantity'] ?? 1 }}"
                                class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 outline-none transition-all focus:border-brandColor dark:border-gray-800 dark:bg-gray-950 dark:text-white"
                            >
                        </td>

                        <td class="px-2 py-2">
                            <input
                                type="text"
                                name="items[{{ $index }}][unit]"
                                value="{{ $item['unit'] ?? 'unit' }}"
                                placeholder="unit"
                                class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 outline-none transition-all focus:border-brandColor dark:border-gray-800 dark:bg-gray-950 dark:text-white"
                            >
                        </td>

                        <td class="px-2 py-2">
                            <input
                                type="text"
                                name="items[{{ $index }}][notes]"
                                value="{{ $item['notes'] ?? '' }}"
                                placeholder="Catatan"
                                class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 dark:border-gray-800 dark:bg-gray-950 dark:text-white"
                            >
                        </td>
                    </tr>
                @endfor
            </tbody>
        </table>
    </div>

    <div class="mt-4 rounded-md border border-blue-200 bg-blue-50 px-4 py-3 text-xs text-blue-700 dark:border-blue-900 dark:bg-blue-900/20 dark:text-blue-300">
        <strong>Belum memilih actual asset.</strong>
        Contoh CAM-0001 atau CAM-002 baru dipilih di tahap Allocation / Picking.
    </div>

    <div class="mt-3 rounded-md border border-gray-200 bg-gray-50 px-4 py-3 text-xs text-gray-500 dark:border-gray-800 dark:bg-gray-950">
        Tidak perlu mengisi semua baris. Equipment dengan nama kosong
        otomatis diabaikan saat Surat Jalan disimpan.
    </div>

    @error('items')
        <p class="mt-2 text-xs text-red-600">
            {{ $message }}
        </p>
    @enderror
</div>
