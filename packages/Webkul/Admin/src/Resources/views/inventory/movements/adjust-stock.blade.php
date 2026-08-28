<x-admin::layouts>
    <x-slot:title>
        Adjust Quantity Stock
    </x-slot>

    <form
        method="POST"
        action="{{ route('admin.inventory.movements.adjust-stock.store') }}"
        class="grid gap-4"
    >
        @csrf

        <div class="flex items-center justify-between gap-4 max-sm:flex-wrap">
            <div>
                <p class="text-xl font-bold text-gray-800 dark:text-white">
                    Adjust Quantity Stock
                </p>

                <p class="mt-1 text-sm text-gray-500">
                    Catat Stock In, Stock Out, atau koreksi stock untuk Inventory Item bertipe Quantity.
                </p>
            </div>

            <div class="flex gap-2">
                <a
                    href="{{ route('admin.inventory.movements.index') }}"
                    class="secondary-button"
                >
                    Cancel
                </a>

                @if (! $items->isEmpty())
                    <button type="submit" class="primary-button">
                        Record Movement
                    </button>
                @endif
            </div>
        </div>

        @if ($errors->any())
            <div class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                <ul class="list-disc pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($items->isEmpty())
            <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
                Belum ada Inventory Item aktif dengan Tracking Type Quantity.
                Buat item seperti Paper Roll atau consumable terlebih dahulu.
            </div>
        @else
            <div class="grid gap-4 rounded-lg border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
                <div class="rounded-lg border border-blue-200 bg-blue-50 p-3 text-sm text-blue-800">
                    Stock tidak dapat menjadi negatif. Semua perubahan disimpan ke Inventory Movements sebagai audit trail.
                </div>

                <div class="grid grid-cols-2 gap-4 max-md:grid-cols-1">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium">
                            Inventory Item *
                        </label>

                        <select
                            id="inventory-item"
                            name="inventory_item_id"
                            class="w-full rounded-md border px-3 py-2 dark:border-gray-800 dark:bg-gray-900"
                            required
                        >
                            <option value="">Select Quantity Item</option>

                            @foreach ($items as $item)
                                <option
                                    value="{{ $item->id }}"
                                    data-stock="{{ $item->quantity_on_hand }}"
                                    data-unit="{{ $item->unit }}"
                                    @selected(
                                        (string) old('inventory_item_id', $selectedItemId)
                                        === (string) $item->id
                                    )
                                >
                                    {{ $item->code }} — {{ $item->name }}
                                </option>
                            @endforeach
                        </select>

                        <p
                            id="current-stock"
                            class="mt-1 text-xs font-medium text-gray-500"
                        >
                            Select an item to see current stock.
                        </p>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium">
                            Movement Type *
                        </label>

                        <select
                            name="movement_type"
                            class="w-full rounded-md border px-3 py-2 dark:border-gray-800 dark:bg-gray-900"
                            required
                        >
                            <option value="stock_in" @selected(old('movement_type') === 'stock_in')>
                                Stock In — barang masuk normal
                            </option>

                            <option value="stock_out" @selected(old('movement_type') === 'stock_out')>
                                Stock Out — barang keluar normal
                            </option>

                            <option value="adjustment_in" @selected(old('movement_type') === 'adjustment_in')>
                                Adjustment In — koreksi tambah
                            </option>

                            <option value="adjustment_out" @selected(old('movement_type') === 'adjustment_out')>
                                Adjustment Out — koreksi kurang
                            </option>
                        </select>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium">
                            Quantity *
                        </label>

                        <input
                            type="number"
                            step="0.01"
                            min="0.01"
                            name="quantity"
                            value="{{ old('quantity') }}"
                            class="w-full rounded-md border px-3 py-2 dark:border-gray-800 dark:bg-gray-900"
                            required
                        >
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium">
                            Reference Number
                        </label>

                        <input
                            type="text"
                            name="reference_number"
                            value="{{ old('reference_number') }}"
                            class="w-full rounded-md border px-3 py-2 dark:border-gray-800 dark:bg-gray-900"
                            placeholder="PO / Receipt / Adjustment reference (optional)"
                        >
                    </div>
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium">
                        Notes / Reason *
                    </label>

                    <textarea
                        name="notes"
                        rows="4"
                        class="w-full rounded-md border px-3 py-2 dark:border-gray-800 dark:bg-gray-900"
                        placeholder="Jelaskan alasan stock berubah."
                        required
                    >{{ old('notes') }}</textarea>
                </div>
            </div>
        @endif
    </form>

    <script>
        function syncCurrentStock() {
            const select = document.getElementById('inventory-item');
            const label = document.getElementById('current-stock');

            if (! select || ! label) {
                return;
            }

            const option = select.options[select.selectedIndex];

            if (! option || ! option.value) {
                label.textContent = 'Select an item to see current stock.';
                return;
            }

            const stock = parseFloat(option.dataset.stock || '0');
            const unit = option.dataset.unit || 'unit';

            label.textContent = `Current stock: ${stock} ${unit}`;
        }

        document.addEventListener('DOMContentLoaded', function () {
            const select = document.getElementById('inventory-item');

            select?.addEventListener('change', syncCurrentStock);

            syncCurrentStock();
        });
    </script>
</x-admin::layouts>
