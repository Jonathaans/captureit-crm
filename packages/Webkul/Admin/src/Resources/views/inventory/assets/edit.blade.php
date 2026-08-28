<x-admin::layouts>
    <x-slot:title>
        Edit Inventory Asset
    </x-slot>

    <form
        method="POST"
        action="{{ route('admin.inventory.assets.update', $asset->id) }}"
        class="grid gap-4"
    >
        @csrf
        @method('PUT')

        <div class="flex items-center justify-between gap-4 max-sm:flex-wrap">
            <div>
                <p class="text-xl font-bold text-gray-800 dark:text-white">
                    {{ $asset->asset_code }}
                </p>

                <p class="mt-1 text-sm text-gray-500">
                    {{ $asset->item?->code }} — {{ $asset->item?->name }}
                </p>
            </div>

            <div class="flex flex-wrap gap-2">
                <a
                    href="{{ route(
                        'admin.inventory.assets.index',
                        ['inventory_item_id' => $asset->inventory_item_id]
                    ) }}"
                    class="secondary-button"
                >
                    Back to Assets
                </a>

                @if (bouncer()->hasPermission('inventory.assets.edit'))
                    <button type="submit" class="primary-button">
                        Save Changes
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

        <div class="grid grid-cols-4 gap-3 max-lg:grid-cols-2 max-sm:grid-cols-1">
            <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                <p class="text-xs uppercase text-gray-500">Inventory Item</p>
                <p class="mt-1 font-semibold">
                    {{ $asset->item?->name ?? '-' }}
                </p>
            </div>

            <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                <p class="text-xs uppercase text-gray-500">Status</p>
                <p class="mt-1 font-semibold">
                    {{ ucwords(str_replace('_', ' ', $asset->status)) }}
                </p>
            </div>

            <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                <p class="text-xs uppercase text-gray-500">Condition</p>
                <p class="mt-1 font-semibold">
                    {{ ucfirst($asset->condition) }}
                </p>
            </div>

            <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                <p class="text-xs uppercase text-gray-500">Warehouse</p>
                <p class="mt-1 font-semibold">
                    {{ $asset->warehouse?->name ?? '-' }}
                </p>
            </div>
        </div>

        <div class="grid gap-4 rounded-lg border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
            <div class="rounded-lg border border-blue-200 bg-blue-50 p-3 text-sm text-blue-800">
                Status dan Condition tidak diedit langsung dari halaman ini.
                Perubahannya nanti melalui Stock Movement / Return workflow agar audit trail tetap terjaga.
            </div>

            <div class="grid grid-cols-2 gap-4 max-md:grid-cols-1">
                <div>
                    <label class="mb-1.5 block text-sm font-medium">
                        Asset Code *
                    </label>

                    <input
                        type="text"
                        name="asset_code"
                        value="{{ old('asset_code', $asset->asset_code) }}"
                        class="w-full rounded-md border px-3 py-2 uppercase dark:border-gray-800 dark:bg-gray-900"
                        required
                    >
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium">
                        Barcode
                    </label>

                    <input
                        type="text"
                        name="barcode_value"
                        value="{{ old('barcode_value', $asset->barcode_value) }}"
                        class="w-full rounded-md border px-3 py-2 dark:border-gray-800 dark:bg-gray-900"
                    >
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium">
                        Serial Number
                    </label>

                    <input
                        type="text"
                        name="serial_number"
                        value="{{ old('serial_number', $asset->serial_number) }}"
                        class="w-full rounded-md border px-3 py-2 dark:border-gray-800 dark:bg-gray-900"
                    >
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium">
                        Warehouse Location
                    </label>

                    <input
                        type="text"
                        value="{{ $asset->location?->name ?? 'Not used' }}"
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
                        value="{{ old(
                            'purchase_date',
                            $asset->purchase_date?->format('Y-m-d')
                        ) }}"
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
                        value="{{ old('purchase_price', $asset->purchase_price) }}"
                        class="w-full rounded-md border px-3 py-2 dark:border-gray-800 dark:bg-gray-900"
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
                >{{ old('notes', $asset->notes) }}</textarea>
            </div>
        </div>
    </form>
</x-admin::layouts>
