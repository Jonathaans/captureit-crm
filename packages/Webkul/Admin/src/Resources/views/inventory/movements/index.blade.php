<x-admin::layouts>
    <x-slot:title>
        Inventory Movements
    </x-slot>

    <div class="flex items-center justify-between gap-4 rounded-lg border border-gray-200 bg-white p-4 max-sm:flex-wrap dark:border-gray-800 dark:bg-gray-900">
        <div class="grid gap-1">
            <p class="text-xl font-bold leading-6 text-gray-800 dark:text-white">
                Inventory Movements
            </p>

            <p class="text-sm text-gray-500 dark:text-gray-400">
                Audit trail seluruh pergerakan inventory. Ledger ini read-only dan tidak memiliki tombol delete.
            </p>
        </div>

        @if (bouncer()->hasPermission('inventory.movements.adjust-stock'))
            <a
                href="{{ route('admin.inventory.movements.adjust-stock.create') }}"
                class="primary-button"
            >
                + Adjust Quantity Stock
            </a>
        @endif
    </div>

    <div class="mt-3.5">
        <x-admin::datagrid
            :src="route(
                'admin.inventory.movements.index',
                array_filter([
                    'inventory_item_id' => request('inventory_item_id'),
                    'inventory_asset_id' => request('inventory_asset_id'),
                ])
            )"
        />
    </div>
</x-admin::layouts>
