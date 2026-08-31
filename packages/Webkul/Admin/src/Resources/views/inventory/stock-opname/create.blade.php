<x-admin::layouts>
    <x-slot:title>
        New Stock Opname
    </x-slot>

    <div class="mx-auto grid max-w-3xl gap-5">
        <div class="rounded-lg border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
            <a
                href="{{ route('admin.inventory.stock-opname.index') }}"
                class="text-sm text-gray-600 hover:text-brandColor"
            >
                &larr; Back to Stock Opname
            </a>

            <p class="mt-3 text-xl font-bold text-gray-800 dark:text-white">
                New Stock Opname
            </p>

            <p class="mt-1 text-sm text-gray-500">
                Buat session terlebih dahulu. Snapshot inventory baru diambil saat Start Counting. Jika hanya ada satu gudang fisik, warehouse dipilih otomatis.
            </p>
        </div>

        <form
            method="POST"
            action="{{ route('admin.inventory.stock-opname.store') }}"
            class="rounded-lg border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900"
        >
            @csrf

            <div class="grid gap-5">
                <div>
                    <label class="mb-2 block text-sm font-semibold text-gray-700 dark:text-gray-300">
                        Warehouse
                    </label>

                    @if ($singleWarehouse)
                        <input
                            type="hidden"
                            name="warehouse_id"
                            value="{{ $singleWarehouse->id }}"
                        >

                        <div class="rounded-md border border-gray-300 bg-gray-50 px-4 py-3 dark:border-gray-700 dark:bg-gray-950">
                            <p class="font-bold text-gray-800 dark:text-white">
                                {{ $singleWarehouse->name }}
                            </p>

                            <p class="mt-1 text-xs text-gray-500">
                                Single warehouse mode
                                &middot;
                                {{ $singleWarehouse->asset_count }} asset
                                &middot;
                                {{ $singleWarehouse->item_count }} inventory item
                            </p>
                        </div>
                    @else
                        <select
                            name="warehouse_id"
                            required
                            class="w-full rounded-md border border-gray-300 bg-white px-3 py-2.5 text-sm dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                        >
                            <option value="">Select Warehouse</option>

                            @foreach ($warehouses as $warehouse)
                                <option
                                    value="{{ $warehouse->id }}"
                                    @selected((string) old('warehouse_id') === (string) $warehouse->id)
                                >
                                    {{ $warehouse->name }}
                                </option>
                            @endforeach
                        </select>
                    @endif

                    @if ($ignoredDuplicateWarehouses > 0)
                        <div class="mt-2 rounded-md border border-yellow-200 bg-yellow-50 px-3 py-2 text-xs text-yellow-800">
                            {{ $ignoredDuplicateWarehouses }} duplicate warehouse database record diabaikan.
                            Stock Opname otomatis memakai warehouse yang memiliki Inventory Assets / Items.
                        </div>
                    @endif

                    @error('warehouse_id')
                        <p class="mt-1 text-xs font-semibold text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="mb-2 block text-sm font-semibold text-gray-700 dark:text-gray-300">
                        Notes
                    </label>

                    <textarea
                        name="notes"
                        rows="4"
                        maxlength="5000"
                        class="w-full rounded-md border border-gray-300 bg-white px-3 py-2.5 text-sm dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                        placeholder="Contoh: Stock opname bulanan Gudang Utama."
                    >{{ old('notes') }}</textarea>
                </div>

                <div class="rounded-md border border-yellow-200 bg-yellow-50 p-4 text-sm text-yellow-800">
                    <strong>Operational note:</strong>
                    idealnya hindari Issue, Return, Maintenance update, atau stock movement selama sesi counting berjalan agar snapshot dan kondisi fisik tetap sinkron.
                </div>

                <div class="flex justify-end gap-3">
                    <a
                        href="{{ route('admin.inventory.stock-opname.index') }}"
                        class="secondary-button"
                    >
                        Cancel
                    </a>

                    <button type="submit" class="primary-button">
                        Create Session
                    </button>
                </div>
            </div>
        </form>
    </div>
</x-admin::layouts>
