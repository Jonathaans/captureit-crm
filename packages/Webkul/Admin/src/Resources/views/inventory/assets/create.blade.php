<x-admin::layouts>
    <x-slot:title>
        Create Inventory Asset
    </x-slot>

    <form
        method="POST"
        action="{{ route('admin.inventory.assets.store') }}"
        class="grid gap-4"
    >
        @csrf

        <div class="flex items-center justify-between gap-4 max-sm:flex-wrap">
            <div>
                <p class="text-xl font-bold text-gray-800 dark:text-white">
                    Create Inventory Asset
                </p>

                <p class="mt-1 text-sm text-gray-500">
                    Daftarkan satu unit fisik serialized ke Gudang Utama.
                </p>
            </div>

            <div class="flex gap-2">
                <a
                    href="{{ route('admin.inventory.assets.index') }}"
                    class="secondary-button"
                >
                    Cancel
                </a>

                <button type="submit" class="primary-button">
                    Save Asset
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

        @if ($items->isEmpty())
            <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
                Belum ada Inventory Item aktif dengan Tracking Type Serialized.
                Buat Inventory Item serialized terlebih dahulu.
            </div>
        @else
            <div class="grid gap-4 rounded-lg border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
                <div class="grid grid-cols-2 gap-4 max-md:grid-cols-1">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium">
                            Inventory Item *
                        </label>

                        <select
                            name="inventory_item_id"
                            class="w-full rounded-md border px-3 py-2 dark:border-gray-800 dark:bg-gray-900"
                            required
                        >
                            <option value="">Select Inventory Item</option>

                            @foreach ($items as $item)
                                <option
                                    value="{{ $item->id }}"
                                    @selected(
                                        (string) old('inventory_item_id', $selectedItemId)
                                        === (string) $item->id
                                    )
                                >
                                    {{ $item->code }} — {{ $item->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium">
                            Asset Code *
                        </label>

                        <input
                            id="asset-code"
                            type="text"
                            name="asset_code"
                            value="{{ old('asset_code') }}"
                            class="w-full rounded-md border px-3 py-2 uppercase dark:border-gray-800 dark:bg-gray-900"
                            placeholder="CAM-0002"
                            required
                        >

                        <p class="mt-1 text-xs text-gray-500">
                            Kode unik internal untuk unit fisik ini.
                        </p>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium">
                            Barcode
                        </label>

                        <input
                            id="barcode-value"
                            type="text"
                            name="barcode_value"
                            value="{{ old('barcode_value') }}"
                            class="w-full rounded-md border px-3 py-2 dark:border-gray-800 dark:bg-gray-900"
                            placeholder="Kosong = otomatis sama dengan Asset Code"
                        >
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium">
                            Serial Number
                        </label>

                        <input
                            type="text"
                            name="serial_number"
                            value="{{ old('serial_number') }}"
                            class="w-full rounded-md border px-3 py-2 dark:border-gray-800 dark:bg-gray-900"
                            placeholder="Serial number manufacturer"
                        >
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium">
                            Initial Condition *
                        </label>

                        <select
                            name="condition"
                            class="w-full rounded-md border px-3 py-2 dark:border-gray-800 dark:bg-gray-900"
                            required
                        >
                            <option value="good" @selected(old('condition', 'good') === 'good')>
                                Good
                            </option>

                            <option value="fair" @selected(old('condition') === 'fair')>
                                Fair
                            </option>

                            <option value="damaged" @selected(old('condition') === 'damaged')>
                                Damaged
                            </option>
                        </select>

                        <p class="mt-1 text-xs text-gray-500">
                            Good/Fair membuat status awal Available. Damaged membuat status awal Damaged.
                        </p>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium">
                            Warehouse
                        </label>

                        <input
                            type="text"
                            value="Gudang Utama"
                            class="w-full rounded-md border bg-gray-50 px-3 py-2 text-gray-500 dark:border-gray-800 dark:bg-gray-950"
                            readonly
                        >
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium">
                            Purchase Date
                        </label>

                        <input
                            type="date"
                            name="purchase_date"
                            value="{{ old('purchase_date') }}"
                            class="w-full rounded-md border px-3 py-2 dark:border-gray-800 dark:bg-gray-900"
                        >
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium">
                            Purchase Price
                        </label>

                        <input
                            type="number"
                            step="0.01"
                            min="0"
                            name="purchase_price"
                            value="{{ old('purchase_price') }}"
                            class="w-full rounded-md border px-3 py-2 dark:border-gray-800 dark:bg-gray-900"
                            placeholder="0"
                        >
                    </div>
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium">
                        Notes
                    </label>

                    <textarea
                        name="notes"
                        rows="4"
                        class="w-full rounded-md border px-3 py-2 dark:border-gray-800 dark:bg-gray-900"
                    >{{ old('notes') }}</textarea>
                </div>
            </div>
        @endif
    </form>
</x-admin::layouts>
