<x-admin::layouts>
    <x-slot:title>
        Edit Inventory Item
    </x-slot>

    <form
        method="POST"
        action="{{ route('admin.inventory.items.update', $item->id) }}"
        class="grid gap-4"
    >
        @csrf
        @method('PUT')

        <div class="flex items-center justify-between gap-4">
            <div>
                <p class="text-xl font-bold text-gray-800 dark:text-white">
                    {{ $item->name }}
                </p>

                <p class="mt-1 text-sm text-gray-500">
                    {{ $item->code }} · {{ ucfirst($item->tracking_type) }}
                </p>
            </div>

            <div class="flex flex-wrap gap-2">
                @if (
                    $item->tracking_type === 'serialized'
                    && bouncer()->hasPermission('inventory.assets')
                )
                    <a
                        href="{{ route('admin.inventory.assets.index', ['inventory_item_id' => $item->id]) }}"
                        class="secondary-button"
                    >
                        Manage Assets
                    </a>
                @endif

                @if (
                    $item->tracking_type === 'quantity'
                    && bouncer()->hasPermission('inventory.movements.adjust-stock')
                )
                    <a
                        href="{{ route(
                            'admin.inventory.movements.adjust-stock.create',
                            ['inventory_item_id' => $item->id]
                        ) }}"
                        class="secondary-button"
                    >
                        Adjust Stock
                    </a>
                @endif

                <a href="{{ route('admin.inventory.items.index') }}" class="secondary-button">
                    Back
                </a>

                @if (bouncer()->hasPermission('inventory.items.edit'))
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
                <p class="text-xs uppercase text-gray-500">Tracking</p>
                <p class="mt-1 font-semibold">{{ ucfirst($item->tracking_type) }}</p>
            </div>

            <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                <p class="text-xs uppercase text-gray-500">Warehouse</p>
                <p class="mt-1 font-semibold">{{ $item->warehouse?->name ?? '-' }}</p>
            </div>

            <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                <p class="text-xs uppercase text-gray-500">
                    {{ $item->tracking_type === 'serialized' ? 'Total Assets' : 'Current Stock' }}
                </p>
                <p class="mt-1 font-semibold">
                    @if ($item->tracking_type === 'serialized')
                        {{ $item->assets()->count() }}
                    @else
                        {{ rtrim(rtrim(number_format((float) $item->quantity_on_hand, 2, '.', ''), '0'), '.') }}
                        {{ $item->unit }}
                    @endif
                </p>
            </div>

            <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                <p class="text-xs uppercase text-gray-500">Status</p>
                <p class="mt-1 font-semibold">{{ $item->is_active ? 'Active' : 'Inactive' }}</p>
            </div>
        </div>

        <div class="grid gap-4 rounded-lg border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
            <div class="grid grid-cols-2 gap-4 max-md:grid-cols-1">
                <div>
                    <label class="mb-1.5 block text-sm font-medium">Code *</label>
                    <input
                        type="text"
                        name="code"
                        value="{{ old('code', $item->code) }}"
                        class="w-full rounded-md border px-3 py-2 dark:border-gray-800 dark:bg-gray-900"
                        required
                    >
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium">Name *</label>
                    <input
                        type="text"
                        name="name"
                        value="{{ old('name', $item->name) }}"
                        class="w-full rounded-md border px-3 py-2 dark:border-gray-800 dark:bg-gray-900"
                        required
                    >
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium">Category</label>
                    <input
                        type="text"
                        name="category"
                        value="{{ old('category', $item->category) }}"
                        class="w-full rounded-md border px-3 py-2 dark:border-gray-800 dark:bg-gray-900"
                    >
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium">Unit *</label>
                    <input
                        type="text"
                        name="unit"
                        value="{{ old('unit', $item->unit) }}"
                        class="w-full rounded-md border px-3 py-2 dark:border-gray-800 dark:bg-gray-900"
                        required
                    >
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium">Minimum Stock</label>
                    <input
                        type="number"
                        step="0.01"
                        min="0"
                        name="minimum_stock"
                        value="{{ old('minimum_stock', $item->minimum_stock) }}"
                        class="w-full rounded-md border px-3 py-2 dark:border-gray-800 dark:bg-gray-900"
                    >
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium">Warehouse</label>
                    <input
                        type="text"
                        value="{{ $item->warehouse?->name ?? '-' }}"
                        class="w-full rounded-md border bg-gray-50 px-3 py-2 text-gray-500 dark:border-gray-800 dark:bg-gray-950"
                        readonly
                    >
                </div>
            </div>

            <div>
                <label class="mb-1.5 block text-sm font-medium">Description</label>
                <textarea
                    name="description"
                    rows="4"
                    class="w-full rounded-md border px-3 py-2 dark:border-gray-800 dark:bg-gray-900"
                >{{ old('description', $item->description) }}</textarea>
            </div>

            <label class="flex items-center gap-2">
                <input
                    type="checkbox"
                    name="is_active"
                    value="1"
                    @checked(old('is_active', $item->is_active))
                >
                <span class="text-sm font-medium">Active</span>
            </label>

            @if ($item->tracking_type === 'quantity')
                <div class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
                    Current Stock tidak diubah dari halaman Edit Item. Stock adjustment akan menggunakan
                    Stock Movement supaya seluruh perubahan memiliki audit trail.
                </div>
            @endif
        </div>
    </form>
</x-admin::layouts>
