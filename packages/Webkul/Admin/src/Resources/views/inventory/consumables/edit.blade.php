<x-admin::layouts>
    <x-slot:title>
        Edit Consumable
    </x-slot>

    @php
        $stock = (float) $item->quantity_on_hand;
        $minimum = (float) $item->minimum_stock;

        $stockLabel = $stock <= 0
            ? 'OUT OF STOCK'
            : (
                $minimum > 0 && $stock <= $minimum
                    ? 'LOW STOCK'
                    : 'HEALTHY'
            );

        $stockClass = $stock <= 0
            ? 'bg-red-100 text-red-700'
            : (
                $minimum > 0 && $stock <= $minimum
                    ? 'bg-amber-100 text-amber-700'
                    : 'bg-emerald-100 text-emerald-700'
            );

        $formatQty = static function ($value) {
            return rtrim(
                rtrim(
                    number_format((float) $value, 2, '.', ''),
                    '0'
                ),
                '.'
            );
        };
    @endphp

    <form
        method="POST"
        action="{{ route('admin.inventory.consumables.update', $item->id) }}"
        style="width:100%;max-width:1100px;margin:0 auto;display:flex;flex-direction:column;gap:14px;"
    >
        @csrf
        @method('PUT')

        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900"
             style="display:flex;justify-content:space-between;gap:16px;flex-wrap:wrap;">
            <div>
                <p class="text-xl font-bold text-gray-900 dark:text-white">
                    {{ $item->name }}
                </p>

                <div class="mt-2 flex flex-wrap items-center gap-2">
                    <span class="rounded-full bg-blue-100 px-2.5 py-1 text-[10px] font-bold text-blue-700">
                        QUANTITY
                    </span>
                    <span class="rounded-full px-2.5 py-1 text-[10px] font-bold {{ $stockClass }}">
                        {{ $stockLabel }}
                    </span>
                </div>
            </div>

            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                @if (bouncer()->hasPermission('inventory.movements.adjust-stock'))
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

                <a
                    href="{{ route(
                        'admin.inventory.movements.index',
                        ['inventory_item_id' => $item->id]
                    ) }}"
                    class="secondary-button"
                >
                    Movements
                </a>

                <a href="{{ route('admin.inventory.consumables.index') }}" class="secondary-button">
                    Back
                </a>

                <button type="submit" class="primary-button">
                    Save Changes
                </button>
            </div>
        </div>

        @if ($errors->any())
            <div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                <ul class="list-disc pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:12px;">
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <p class="text-[11px] font-bold uppercase tracking-wide text-gray-500">Current Stock</p>
                <p class="mt-2 text-2xl font-bold text-gray-900 dark:text-white">
                    {{ $formatQty($item->quantity_on_hand) }} {{ $item->unit }}
                </p>
                <p class="mt-1 text-xs text-gray-500">
                    Read-only. Gunakan Adjust Stock.
                </p>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <p class="text-[11px] font-bold uppercase tracking-wide text-gray-500">Minimum Stock</p>
                <p class="mt-2 text-2xl font-bold text-gray-900 dark:text-white">
                    {{ $formatQty($item->minimum_stock) }} {{ $item->unit }}
                </p>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <p class="text-[11px] font-bold uppercase tracking-wide text-gray-500">Warehouse</p>
                <p class="mt-2 text-lg font-bold text-gray-900 dark:text-white">
                    {{ $item->warehouse?->name ?: '-' }}
                </p>
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:16px;">
                <div>
                    <label class="mb-1.5 block text-sm font-medium">Code *</label>
                    <input type="text" name="code" value="{{ old('code', $item->code) }}" class="w-full rounded-md border px-3 py-2 dark:border-gray-800 dark:bg-gray-900" required>
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium">Name *</label>
                    <input type="text" name="name" value="{{ old('name', $item->name) }}" class="w-full rounded-md border px-3 py-2 dark:border-gray-800 dark:bg-gray-900" required>
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium">Category</label>
                    <input type="text" name="category" value="{{ old('category', $item->category) }}" class="w-full rounded-md border px-3 py-2 dark:border-gray-800 dark:bg-gray-900">
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium">Unit *</label>
                    <input type="text" name="unit" value="{{ old('unit', $item->unit) }}" class="w-full rounded-md border px-3 py-2 dark:border-gray-800 dark:bg-gray-900" required>
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium">Minimum Stock</label>
                    <input type="number" step="0.01" min="0" name="minimum_stock" value="{{ old('minimum_stock', $item->minimum_stock) }}" class="w-full rounded-md border px-3 py-2 dark:border-gray-800 dark:bg-gray-900">
                </div>
            </div>

            <div class="mt-4">
                <label class="mb-1.5 block text-sm font-medium">Description</label>
                <textarea name="description" rows="4" class="w-full rounded-md border px-3 py-2 dark:border-gray-800 dark:bg-gray-900">{{ old('description', $item->description) }}</textarea>
            </div>

            <label class="mt-4 inline-flex items-center gap-2 text-sm font-medium">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $item->is_active))>
                Active Consumable
            </label>
        </div>
    </form>
</x-admin::layouts>
