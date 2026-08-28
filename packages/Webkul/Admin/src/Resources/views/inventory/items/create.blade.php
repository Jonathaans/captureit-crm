<x-admin::layouts>
    <x-slot:title>
        Create Inventory Item
    </x-slot>

    <form
        method="POST"
        action="{{ route('admin.inventory.items.store') }}"
        class="grid gap-4"
    >
        @csrf

        <div class="flex items-center justify-between gap-4">
            <div>
                <p class="text-xl font-bold text-gray-800 dark:text-white">
                    Create Inventory Item
                </p>

                <p class="mt-1 text-sm text-gray-500">
                    Buat master equipment serialized atau barang quantity.
                </p>
            </div>

            <div class="flex gap-2">
                <a href="{{ route('admin.inventory.items.index') }}" class="secondary-button">
                    Cancel
                </a>

                <button type="submit" class="primary-button">
                    Save Item
                </button>
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

        <div class="grid gap-4 rounded-lg border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
            <div class="grid grid-cols-2 gap-4 max-md:grid-cols-1">
                <div>
                    <label class="mb-1.5 block text-sm font-medium">Code *</label>
                    <input
                        type="text"
                        name="code"
                        value="{{ old('code') }}"
                        class="w-full rounded-md border px-3 py-2 dark:border-gray-800 dark:bg-gray-900"
                        placeholder="CAM-700D"
                        required
                    >
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium">Name *</label>
                    <input
                        type="text"
                        name="name"
                        value="{{ old('name') }}"
                        class="w-full rounded-md border px-3 py-2 dark:border-gray-800 dark:bg-gray-900"
                        placeholder="Canon EOS 700D"
                        required
                    >
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium">Category</label>
                    <input
                        type="text"
                        name="category"
                        value="{{ old('category') }}"
                        class="w-full rounded-md border px-3 py-2 dark:border-gray-800 dark:bg-gray-900"
                        placeholder="Camera"
                    >
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium">Unit *</label>
                    <input
                        type="text"
                        name="unit"
                        value="{{ old('unit', 'unit') }}"
                        class="w-full rounded-md border px-3 py-2 dark:border-gray-800 dark:bg-gray-900"
                        placeholder="unit / roll / set"
                        required
                    >
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium">Tracking Type *</label>
                    <select
                        id="tracking-type"
                        name="tracking_type"
                        class="w-full rounded-md border px-3 py-2 dark:border-gray-800 dark:bg-gray-900"
                        onchange="window.syncInventoryTrackingFields?.(this.value)"
                        required
                    >
                        <option value="serialized" @selected(old('tracking_type', 'serialized') === 'serialized')>
                            Serialized — setiap unit punya Asset Code
                        </option>
                        <option value="quantity" @selected(old('tracking_type') === 'quantity')>
                            Quantity — stok dihitung jumlah
                        </option>
                    </select>
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium">Warehouse</label>
                    <input
                        type="text"
                        value="{{ $warehouse->name }}"
                        class="w-full rounded-md border bg-gray-50 px-3 py-2 text-gray-500 dark:border-gray-800 dark:bg-gray-950"
                        readonly
                    >
                    <p class="mt-1 text-xs text-gray-500">
                        Otomatis menggunakan gudang utama. Warehouse Location tidak diwajibkan.
                    </p>
                </div>

                <div
                    id="opening-stock-wrap"
                    style="{{ old('tracking_type', 'serialized') === 'quantity' ? '' : 'display:none;' }}"
                >
                    <label class="mb-1.5 block text-sm font-medium">Opening Stock</label>
                    <input
                        type="number"
                        step="0.01"
                        min="0"
                        name="opening_stock"
                        value="{{ old('opening_stock', 0) }}"
                        class="w-full rounded-md border px-3 py-2 dark:border-gray-800 dark:bg-gray-900"
                    >
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium">Minimum Stock</label>
                    <input
                        type="number"
                        step="0.01"
                        min="0"
                        name="minimum_stock"
                        value="{{ old('minimum_stock', 0) }}"
                        class="w-full rounded-md border px-3 py-2 dark:border-gray-800 dark:bg-gray-900"
                    >
                </div>
            </div>

            <div>
                <label class="mb-1.5 block text-sm font-medium">Description</label>
                <textarea
                    name="description"
                    rows="4"
                    class="w-full rounded-md border px-3 py-2 dark:border-gray-800 dark:bg-gray-900"
                >{{ old('description') }}</textarea>
            </div>

            <label class="flex items-center gap-2">
                <input
                    type="checkbox"
                    name="is_active"
                    value="1"
                    @checked(old('is_active', 1))
                >
                <span class="text-sm font-medium">Active</span>
            </label>
        </div>
    </form>

    <script>
        window.syncInventoryTrackingFields = function (trackingType) {
            const openingStock = document.getElementById('opening-stock-wrap');

            if (! openingStock) {
                return;
            }

            openingStock.style.display = trackingType === 'quantity'
                ? 'block'
                : 'none';
        };

        /**
         * Jalankan langsung karena pada layout admin Krayin script Blade
         * dapat dieksekusi ketika DOMContentLoaded sudah terlewati.
         */
        (function () {
            const tracking = document.getElementById('tracking-type');

            if (! tracking) {
                return;
            }

            window.syncInventoryTrackingFields(tracking.value);
        })();
    </script>
</x-admin::layouts>
