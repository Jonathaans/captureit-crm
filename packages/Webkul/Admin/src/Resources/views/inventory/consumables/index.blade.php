<x-admin::layouts>
    <x-slot:title>
        Consumables
    </x-slot>

    <div style="width:100%;display:flex;flex-direction:column;gap:14px;">
        <div
            class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900"
            style="padding:18px;display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-wrap:wrap;"
        >
            <div style="display:flex;align-items:center;gap:10px;">
                <span style="width:42px;height:42px;border-radius:12px;display:flex;align-items:center;justify-content:center;background:#fff7ed;color:#c2410c;font-size:20px;">
                    ◫
                </span>

                <div>
                    <p class="text-xl font-bold text-gray-900 dark:text-white">
                        Consumables
                    </p>

                    <p class="mt-1 text-sm text-gray-500">
                        Quantity stock seperti Ribbon, Paper Roll, Frame, dan material habis pakai.
                    </p>
                </div>
            </div>

            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                @if (bouncer()->hasPermission('inventory.movements.adjust-stock'))
                    <a
                        href="{{ route('admin.inventory.movements.adjust-stock.create') }}"
                        class="secondary-button"
                    >
                        Adjust Stock
                    </a>
                @endif

                @if (bouncer()->hasPermission('inventory.consumables.create'))
                    <a
                        href="{{ route('admin.inventory.consumables.create') }}"
                        class="primary-button"
                    >
                        + Consumable
                    </a>
                @endif
            </div>
        </div>

        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;">
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <p class="text-[11px] font-bold uppercase tracking-wide text-gray-500">
                    Total Consumables
                </p>
                <p class="mt-2 text-2xl font-bold text-gray-900 dark:text-white">
                    {{ $summary['total'] }}
                </p>
            </div>

            <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 shadow-sm">
                <p class="text-[11px] font-bold uppercase tracking-wide text-emerald-700">
                    Healthy
                </p>
                <p class="mt-2 text-2xl font-bold text-emerald-800">
                    {{ $summary['healthy'] }}
                </p>
            </div>

            <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 shadow-sm">
                <p class="text-[11px] font-bold uppercase tracking-wide text-amber-700">
                    Low Stock
                </p>
                <p class="mt-2 text-2xl font-bold text-amber-800">
                    {{ $summary['low'] }}
                </p>
            </div>

            <div class="rounded-xl border border-red-200 bg-red-50 p-4 shadow-sm">
                <p class="text-[11px] font-bold uppercase tracking-wide text-red-700">
                    Out of Stock
                </p>
                <p class="mt-2 text-2xl font-bold text-red-800">
                    {{ $summary['out'] }}
                </p>
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <x-admin::datagrid
                :src="route('admin.inventory.consumables.index')"
            />
        </div>
    </div>
</x-admin::layouts>
