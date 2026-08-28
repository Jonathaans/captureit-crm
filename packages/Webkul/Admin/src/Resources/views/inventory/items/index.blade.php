<x-admin::layouts>
    <x-slot:title>
        Inventory Items
    </x-slot>

    {!! view_render_event('admin.inventory.items.index.before') !!}

    <div class="flex items-center justify-between gap-4 rounded-lg border border-gray-200 bg-white p-4 max-sm:flex-wrap dark:border-gray-800 dark:bg-gray-900">
        <div class="grid gap-1">
            <p class="text-xl font-bold leading-6 text-gray-800 dark:text-white">
                Inventory Items
            </p>

            <p class="text-sm text-gray-500 dark:text-gray-400">
                Master equipment dan consumable yang berada di Gudang Utama.
            </p>
        </div>

        @if (bouncer()->hasPermission('inventory.items.create'))
            <a
                href="{{ route('admin.inventory.items.create') }}"
                class="primary-button"
            >
                + Create Inventory Item
            </a>
        @endif
    </div>

    <div class="mt-3.5">
        <x-admin::datagrid :src="route('admin.inventory.items.index')" />
    </div>

    {!! view_render_event('admin.inventory.items.index.after') !!}
</x-admin::layouts>
