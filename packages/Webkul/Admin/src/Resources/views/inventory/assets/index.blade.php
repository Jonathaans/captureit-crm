<x-admin::layouts>
    <x-slot:title>
        Inventory Assets
    </x-slot>

    <div class="flex items-center justify-between gap-4 rounded-lg border border-gray-200 bg-white p-4 max-sm:flex-wrap dark:border-gray-800 dark:bg-gray-900">
        <div class="grid gap-1">
            <p class="text-xl font-bold leading-6 text-gray-800 dark:text-white">
                Inventory Assets
            </p>

            <p class="text-sm text-gray-500 dark:text-gray-400">
                @if ($selectedItem)
                    Unit fisik untuk {{ $selectedItem->name }} ({{ $selectedItem->code }}).
                @else
                    Monitoring unit fisik serialized di Gudang Utama.
                @endif
            </p>
        </div>

        <div class="flex flex-wrap gap-2">
            @if ($selectedItem)
                <a
                    href="{{ route('admin.inventory.items.edit', $selectedItem->id) }}"
                    class="secondary-button"
                >
                    Back to Item
                </a>
            @endif

            @if (bouncer()->hasPermission('inventory.assets.barcodes'))
                <a
                    href="{{ route(
                        'admin.inventory.assets.qr-labels.index',
                        $selectedItem
                            ? ['inventory_item_id' => $selectedItem->id]
                            : []
                    ) }}"
                    class="secondary-button"
                >
                    QR Labels
                </a>
            @endif

            @if (bouncer()->hasPermission('inventory.assets.create'))
                <a
                    href="{{ route('admin.inventory.assets.create', $selectedItem ? ['inventory_item_id' => $selectedItem->id] : []) }}"
                    class="primary-button"
                >
                    + Create Asset
                </a>
            @endif
        </div>
    </div>

    <div class="mt-3.5">
        <x-admin::datagrid
            :src="route(
                'admin.inventory.assets.index',
                $selectedItem ? ['inventory_item_id' => $selectedItem->id] : []
            )"
        />
    </div>
</x-admin::layouts>
