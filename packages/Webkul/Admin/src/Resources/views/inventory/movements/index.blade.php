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
                Audit trail seluruh pergerakan inventory. Data terbaru dimuat otomatis setiap 10 detik.
            </p>
        </div>

        <div class="flex items-center gap-2 max-sm:flex-wrap">
            <!-- INVENTORY MOVEMENT LIVE V1 -->
            <v-inventory-movement-live></v-inventory-movement-live>

            @if (bouncer()->hasPermission('inventory.movements.adjust-stock'))
                <a
                    href="{{ route('admin.inventory.movements.adjust-stock.create') }}"
                    class="primary-button"
                >
                    + Adjust Quantity Stock
                </a>
            @endif
        </div>
    </div>

    <div class="mt-3.5">
        <x-admin::datagrid
            :src="route(
                'admin.inventory.movements.index',
                array_filter([
                    'inventory_item_id' => request('inventory_item_id'),
                    'inventory_asset_id' => request('inventory_asset_id'),
                    '_movement_live_v' => '1',
                ])
            )"
        />
    </div>

    @pushOnce('scripts')
        <script
            type="text/x-template"
            id="v-inventory-movement-live-template"
        >
            <div class="flex items-center gap-2 max-sm:flex-wrap">
                <span
                    class="inline-flex items-center gap-2 rounded-md px-3 py-2 text-xs font-bold ring-1 ring-inset"
                    :class="hasCustomView
                        ? 'bg-amber-50 text-amber-700 ring-amber-200'
                        : 'bg-green-50 text-green-700 ring-green-200'"
                >
                    <span
                        class="h-2 w-2 rounded-full"
                        :class="isRefreshing ? 'animate-pulse bg-blue-500' : (hasCustomView ? 'bg-amber-500' : 'bg-green-500')"
                    ></span>

                    @{{ statusLabel }}
                </span>

                <button
                    type="button"
                    class="secondary-button"
                    :disabled="isRefreshing"
                    @click="refreshNow"
                >
                    @{{ isRefreshing ? 'Refreshing...' : 'Refresh Now' }}
                </button>

                <button
                    v-if="hasCustomView"
                    type="button"
                    class="secondary-button"
                    @click="resetView"
                >
                    Reset View
                </button>
            </div>
        </script>

        <script type="module">
            app.component('v-inventory-movement-live', {
                template: '#v-inventory-movement-live-template',

                data() {
                    return {
                        timer: null,
                        isRefreshing: false,
                        hasCustomView: false,
                        lastUpdatedAt: null,
                    };
                },

                computed: {
                    statusLabel() {
                        if (this.hasCustomView) {
                            return 'Filter/sort aktif - hasil dibatasi';
                        }

                        if (! this.lastUpdatedAt) {
                            return 'Auto-refresh 10 detik';
                        }

                        return `Live - updated ${this.lastUpdatedAt.toLocaleTimeString('id-ID')}`;
                    },
                },

                mounted() {
                    this.$emitter.on(
                        'change-datagrid',
                        this.onDatagridChanged
                    );

                    this.timer = window.setInterval(
                        () => {
                            if (! document.hidden) {
                                this.refreshNow();
                            }
                        },
                        10000
                    );
                },

                beforeUnmount() {
                    window.clearInterval(this.timer);

                    this.$emitter.off(
                        'change-datagrid',
                        this.onDatagridChanged
                    );
                },

                methods: {
                    onDatagridChanged(payload = {}) {
                        const applied = payload.applied ?? {};
                        const columns = applied.filters?.columns ?? [];
                        const hasFilter = columns.some((column) => {
                            const value = column?.value;

                            if (Array.isArray(value)) {
                                return value.some(
                                    (entry) => String(entry).trim() !== ''
                                );
                            }

                            return value !== null
                                && value !== undefined
                                && String(value).trim() !== '';
                        });
                        const sort = applied.sort ?? {};
                        const hasSort = Boolean(
                            sort.column
                            && sort.order
                        );

                        this.hasCustomView = hasFilter || hasSort;
                        this.lastUpdatedAt = new Date();
                        this.isRefreshing = false;
                    },

                    refreshNow() {
                        if (this.isRefreshing) {
                            return;
                        }

                        this.isRefreshing = true;
                        this.$emitter.emit('reload-datagrids');

                        window.setTimeout(
                            () => this.isRefreshing = false,
                            5000
                        );
                    },

                    resetView() {
                        try {
                            const stored = JSON.parse(
                                localStorage.getItem('datagrids') || '[]'
                            );
                            const retained = Array.isArray(stored)
                                ? stored.filter(
                                    (datagrid) => ! String(datagrid?.src ?? '')
                                        .includes('/admin/inventory/movements')
                                )
                                : [];

                            localStorage.setItem(
                                'datagrids',
                                JSON.stringify(retained)
                            );
                        } catch (error) {
                            localStorage.removeItem('datagrids');
                        }

                        window.location.assign(
                            @json(route('admin.inventory.movements.index'))
                        );
                    },
                },
            });
        </script>
    @endPushOnce
</x-admin::layouts>
