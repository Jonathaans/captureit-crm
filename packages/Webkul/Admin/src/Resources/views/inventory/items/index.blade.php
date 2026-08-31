<x-admin::layouts>
    <x-slot:title>
        Inventory Items
    </x-slot>

    {!! view_render_event('admin.inventory.items.index.before') !!}

    <div
        class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900"
        style="
            display:flex;
            align-items:flex-start;
            justify-content:space-between;
            gap:16px;
            flex-wrap:wrap;
        "
    >
        <div>
            <p class="text-xl font-bold leading-6 text-gray-800 dark:text-white">
                Inventory Items
            </p>

            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Master equipment serialized. Item quantity dipisahkan ke menu Consumables.
            </p>

            <div class="mt-3 flex flex-wrap items-center gap-2">
                <span class="rounded-full bg-blue-100 px-2.5 py-1 text-[10px] font-bold text-blue-700">
                    SERIALIZED ONLY
                </span>

                <a
                    href="{{ route('admin.inventory.consumables.index') }}"
                    class="text-xs font-bold text-brandColor hover:underline"
                >
                    Open Consumables →
                </a>
            </div>
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
        <x-admin::datagrid
            :src="route('admin.inventory.items.index')"
        />
    </div>

    {!! view_render_event('admin.inventory.items.index.after') !!}
</x-admin::layouts>
