<x-admin::layouts>
    <x-slot:title>
        Delivery Orders
    </x-slot>

    {!! view_render_event('admin.delivery_orders.index.before') !!}

    <div
        class="flex items-center justify-between gap-4 rounded-lg border border-gray-200 bg-white p-4 max-sm:flex-wrap dark:border-gray-800 dark:bg-gray-900"
    >
        <div class="grid gap-1">
            <p class="text-xl font-bold leading-6 text-gray-800 dark:text-white">
                Delivery Orders
            </p>

            <p class="text-sm text-gray-500 dark:text-gray-400">
                Manage Surat Jalan, project delivery, equipment, and delivery status.
            </p>
        </div>
    </div>

    <div class="mt-3.5">
        <x-admin::datagrid
            :src="route('admin.delivery-orders.index')"
        />
    </div>

    {!! view_render_event('admin.delivery_orders.index.after') !!}
</x-admin::layouts>