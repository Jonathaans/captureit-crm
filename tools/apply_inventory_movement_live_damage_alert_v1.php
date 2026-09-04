<?php

declare(strict_types=1);

/**
 * INVENTORY MOVEMENT LIVE + DAMAGE ALERT DETAIL V1
 *
 * Run from the CRM project root:
 * php tools/apply_inventory_movement_live_damage_alert_v1.php
 */

$root = dirname(__DIR__);

$paths = [
    'movement_controller' => $root.'/packages/Webkul/Admin/src/Http/Controllers/Inventory/InventoryMovementController.php',
    'movement_datagrid' => $root.'/packages/Webkul/Admin/src/DataGrids/Inventory/InventoryMovementDataGrid.php',
    'movement_view' => $root.'/packages/Webkul/Admin/src/Resources/views/inventory/movements/index.blade.php',
    'alert_controller' => $root.'/packages/Webkul/Admin/src/Http/Controllers/Inventory/InventoryAlertController.php',
    'alert_view' => $root.'/packages/Webkul/Admin/src/Resources/views/inventory/alerts/index.blade.php',
    'movement_test' => $root.'/tests/Unit/InventoryMovementLiveTest.php',
    'alert_test' => $root.'/tests/Unit/InventoryDamageAlertReasonTest.php',
];

function inventoryMovementLiveV1Fail(string $message): never
{
    fwrite(STDERR, $message.PHP_EOL);
    exit(1);
}

function inventoryMovementLiveV1Read(string $path): string
{
    $contents = file_get_contents($path);

    if ($contents === false) {
        throw new RuntimeException("Gagal membaca {$path}");
    }

    return str_replace(["\r\n", "\r"], "\n", $contents);
}

function inventoryMovementLiveV1Write(string $path, string $contents): void
{
    $temporary = $path.'.tmp-movement-live-'.bin2hex(random_bytes(4));

    if (file_put_contents($temporary, $contents) === false) {
        @unlink($temporary);
        throw new RuntimeException("Gagal menulis temporary file {$temporary}");
    }

    if (! rename($temporary, $path)) {
        @unlink($temporary);
        throw new RuntimeException("Gagal mengganti {$path}");
    }
}

function inventoryMovementLiveV1ReplaceOnce(
    string $source,
    string $search,
    string $replacement,
    string $label
): string {
    $count = substr_count($source, $search);

    if ($count !== 1) {
        throw new RuntimeException(
            "Preflight {$label} gagal: anchor ditemukan {$count} kali."
        );
    }

    return str_replace($search, $replacement, $source);
}

function inventoryMovementLiveV1Lint(string $path): array
{
    exec(
        escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($path).' 2>&1',
        $output,
        $code
    );

    return [$code, implode(PHP_EOL, $output)];
}

set_exception_handler(
    static function (Throwable $exception): void {
        inventoryMovementLiveV1Fail('PATCH GAGAL: '.$exception->getMessage());
    }
);

echo "INVENTORY MOVEMENT LIVE + DAMAGE ALERT DETAIL V1\n";
echo "=================================================\n\n";

foreach ($paths as $label => $path) {
    if (! is_file($path)) {
        inventoryMovementLiveV1Fail("File {$label} tidak ditemukan: {$path}");
    }
}

$sources = [];

foreach (
    [
        'movement_controller',
        'movement_datagrid',
        'movement_view',
        'alert_controller',
        'alert_view',
    ] as $key
) {
    $sources[$key] = inventoryMovementLiveV1Read($paths[$key]);
}

$installedChecks = [
    str_contains($sources['movement_controller'], 'INVENTORY MOVEMENT LIVE V1'),
    str_contains($sources['movement_datagrid'], 'INVENTORY MOVEMENT LIVE V1'),
    str_contains($sources['movement_view'], 'INVENTORY MOVEMENT LIVE V1'),
    str_contains($sources['alert_controller'], 'INVENTORY DAMAGE ALERT REASON V1'),
    str_contains($sources['alert_view'], 'INVENTORY DAMAGE ALERT REASON V1'),
];

$alreadyInstalled = ! in_array(false, $installedChecks, true);

if (in_array(true, $installedChecks, true) && ! $alreadyInstalled) {
    throw new RuntimeException(
        'Patch terdeteksi hanya terpasang sebagian. Pulihkan backup .bak-movement-live-damage-alert-v1 lalu jalankan ulang.'
    );
}

$backups = [];

if (! $alreadyInstalled) {
    $oldMovementIndex = <<<'PHP'
    public function index()
    {
        if (request()->ajax() || request()->expectsJson()) {
            return app(InventoryMovementDataGrid::class)->toJson();
        }

        return view('admin::inventory.movements.index');
    }
PHP;
    $newMovementIndex = <<<'PHP'
    /**
     * INVENTORY MOVEMENT LIVE V1
     *
     * Always return a fresh ledger response. The client refreshes this JSON
     * endpoint every 10 seconds while the Movement page is visible.
     */
    public function index()
    {
        if (request()->ajax() || request()->expectsJson()) {
            $response = app(InventoryMovementDataGrid::class)->toJson();

            $response->headers->set(
                'Cache-Control',
                'no-store, no-cache, must-revalidate, max-age=0'
            );
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('Expires', '0');

            return $response;
        }

        return view('admin::inventory.movements.index');
    }
PHP;
    $sources['movement_controller'] = inventoryMovementLiveV1ReplaceOnce(
        $sources['movement_controller'],
        $oldMovementIndex,
        $newMovementIndex,
        'movement controller index'
    );

    $sources['movement_datagrid'] = inventoryMovementLiveV1ReplaceOnce(
        $sources['movement_datagrid'],
        "class InventoryMovementDataGrid extends DataGrid\n{\n",
        "class InventoryMovementDataGrid extends DataGrid\n{\n"
            ."    /** INVENTORY MOVEMENT LIVE V1: newest movement first. */\n"
            ."    protected \$sortColumn = 'occurred_at';\n\n"
            ."    protected \$sortOrder = 'desc';\n\n",
        'movement datagrid default sort'
    );

    $oldMovementOrder = <<<'PHP'
                'users.name as performed_by_name'
            )
            ->orderByDesc('inventory_stock_movements.occurred_at')
            ->orderByDesc('inventory_stock_movements.id');
PHP;
    $newMovementOrder = <<<'PHP'
                'users.name as performed_by_name'
            );
PHP;
    $sources['movement_datagrid'] = inventoryMovementLiveV1ReplaceOnce(
        $sources['movement_datagrid'],
        $oldMovementOrder,
        $newMovementOrder,
        'movement datagrid duplicate query order'
    );

    $sources['movement_view'] = <<<'BLADE'
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
BLADE;

    $sources['alert_controller'] = inventoryMovementLiveV1ReplaceOnce(
        $sources['alert_controller'],
        "                                        \$alert['recommended_action'],\n",
        "                                        \$alert['recommended_action'],\n"
            ."                                        \$alert['damage_reason'] ?? '',\n"
            ."                                        \$alert['damage_reference'] ?? '',\n",
        'alert search damage reason'
    );

    $oldCsvHeader = <<<'PHP'
                        'Recommended Action',
                        'Last Updated',
PHP;
    $newCsvHeader = <<<'PHP'
                        'Recommended Action',
                        'Damage Reason',
                        'Damage Reference',
                        'Last Updated',
PHP;
    $sources['alert_controller'] = inventoryMovementLiveV1ReplaceOnce(
        $sources['alert_controller'],
        $oldCsvHeader,
        $newCsvHeader,
        'alert CSV damage headers'
    );

    $oldCsvRow = <<<'PHP'
                            $alert['recommended_action'],
                            $alert['updated_at']
PHP;
    $newCsvRow = <<<'PHP'
                            $alert['recommended_action'],
                            $alert['damage_reason'] ?? '',
                            $alert['damage_reference'] ?? '',
                            $alert['updated_at']
PHP;
    $sources['alert_controller'] = inventoryMovementLiveV1ReplaceOnce(
        $sources['alert_controller'],
        $oldCsvRow,
        $newCsvRow,
        'alert CSV damage values'
    );

    $oldProblemAssets = <<<'PHP'
        $problemAssets = DB::table('inventory_assets')
            ->join(
                'inventory_items',
                'inventory_assets.inventory_item_id',
                '=',
                'inventory_items.id'
            )
            ->leftJoin(
                'warehouses',
                'inventory_assets.warehouse_id',
                '=',
                'warehouses.id'
            )
            ->whereIn(
                'inventory_assets.status',
                [
                    'missing',
                    'damaged',
                    'maintenance',
                    'return_pending',
                ]
            )
            ->select([
                'inventory_assets.id',
                'inventory_assets.asset_code',
                'inventory_assets.status',
                'inventory_assets.condition',
                'inventory_assets.updated_at',
                'inventory_items.name as item_name',
                'warehouses.name as warehouse_name',
            ])
            ->orderByDesc(
                'inventory_assets.updated_at'
            )
            ->get();
PHP;
    $newProblemAssets = <<<'PHP'
        /* INVENTORY DAMAGE ALERT REASON V1 */
        $latestDamageNotes = DB::table(
            'delivery_order_inventory_allocations'
        )
            ->select(
                'inventory_asset_id',
                DB::raw('MAX(id) as allocation_id')
            )
            ->where('tracking_type', 'serialized')
            ->where('status', 'returned')
            ->where('return_condition', 'damaged')
            ->whereNotNull('return_notes')
            ->where('return_notes', '<>', '')
            ->groupBy('inventory_asset_id');

        $problemAssets = DB::table('inventory_assets')
            ->join(
                'inventory_items',
                'inventory_assets.inventory_item_id',
                '=',
                'inventory_items.id'
            )
            ->leftJoin(
                'warehouses',
                'inventory_assets.warehouse_id',
                '=',
                'warehouses.id'
            )
            ->leftJoinSub(
                $latestDamageNotes,
                'latest_damage_notes',
                fn ($join) => $join->on(
                    'latest_damage_notes.inventory_asset_id',
                    '=',
                    'inventory_assets.id'
                )
            )
            ->leftJoin(
                'delivery_order_inventory_allocations as damage_allocations',
                'damage_allocations.id',
                '=',
                'latest_damage_notes.allocation_id'
            )
            ->leftJoin(
                'delivery_orders as damage_delivery_orders',
                'damage_delivery_orders.id',
                '=',
                'damage_allocations.delivery_order_id'
            )
            ->whereIn(
                'inventory_assets.status',
                [
                    'missing',
                    'damaged',
                    'maintenance',
                    'return_pending',
                ]
            )
            ->select([
                'inventory_assets.id',
                'inventory_assets.asset_code',
                'inventory_assets.status',
                'inventory_assets.condition',
                'inventory_assets.updated_at',
                'inventory_items.name as item_name',
                'warehouses.name as warehouse_name',
                'damage_allocations.return_notes as damage_reason',
                'damage_allocations.checked_in_at as damage_recorded_at',
                'damage_delivery_orders.delivery_order_number as damage_reference',
            ])
            ->orderByDesc(
                'inventory_assets.updated_at'
            )
            ->get();
PHP;
    $sources['alert_controller'] = inventoryMovementLiveV1ReplaceOnce(
        $sources['alert_controller'],
        $oldProblemAssets,
        $newProblemAssets,
        'alert latest damage reason query'
    );

    $sources['alert_controller'] = inventoryMovementLiveV1ReplaceOnce(
        $sources['alert_controller'],
        "                'recommended_action' => \$action,\n",
        "                'damage_reason' => trim((string) (\$asset->damage_reason ?? '')) ?: null,\n"
            ."                'damage_reference' => trim((string) (\$asset->damage_reference ?? '')) ?: null,\n"
            ."                'damage_recorded_at' => \$this->carbon(\$asset->damage_recorded_at ?? null),\n"
            ."                'recommended_action' => \$action,\n",
        'alert damage reason payload'
    );

    $oldAlertCurrent = <<<'BLADE'
                                                <p class="mt-0.5 text-xs text-gray-500">
                                                    {{ $alert['detail'] }}
                                                </p>
BLADE;
    $newAlertCurrent = <<<'BLADE'
                                                <p class="mt-0.5 text-xs text-gray-500">
                                                    {{ $alert['detail'] }}
                                                </p>

                                                @if (($alert['type'] ?? null) === 'damaged_asset')
                                                    <!-- INVENTORY DAMAGE ALERT REASON V1 -->
                                                    <div class="mt-2 max-w-sm rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs leading-5 text-red-800 dark:border-red-900 dark:bg-red-950/30 dark:text-red-200">
                                                        <p>
                                                            <strong>Alasan rusak:</strong>
                                                            {{ $alert['damage_reason'] ?? 'Belum ada alasan kerusakan yang tercatat.' }}
                                                        </p>

                                                        @if ($alert['damage_reference'] ?? null)
                                                            <p class="mt-1 text-[11px] text-red-600 dark:text-red-300">
                                                                Sumber Return: {{ $alert['damage_reference'] }}
                                                            </p>
                                                        @endif
                                                    </div>
                                                @endif
BLADE;
    $sources['alert_view'] = inventoryMovementLiveV1ReplaceOnce(
        $sources['alert_view'],
        $oldAlertCurrent,
        $newAlertCurrent,
        'alert table damage reason'
    );

    $oldAttentionDetail = <<<'BLADE'
                                    <p class="mt-1 text-xs leading-5 text-gray-500">
                                        {{ $alert['recommended_action'] }}
                                    </p>
BLADE;
    $newAttentionDetail = <<<'BLADE'
                                    <p class="mt-1 text-xs leading-5 text-gray-500">
                                        {{ $alert['recommended_action'] }}
                                    </p>

                                    @if (($alert['type'] ?? null) === 'damaged_asset')
                                        <p class="mt-1 line-clamp-2 text-xs leading-5 text-red-600 dark:text-red-300">
                                            <strong>Alasan rusak:</strong>
                                            {{ $alert['damage_reason'] ?? 'Belum tercatat' }}
                                        </p>
                                    @endif
BLADE;
    $sources['alert_view'] = inventoryMovementLiveV1ReplaceOnce(
        $sources['alert_view'],
        $oldAttentionDetail,
        $newAttentionDetail,
        'alert attention damage reason'
    );

    $requiredMarkers = [
        'movement_controller' => [
            'INVENTORY MOVEMENT LIVE V1',
            'no-store, no-cache, must-revalidate',
        ],
        'movement_datagrid' => [
            'INVENTORY MOVEMENT LIVE V1',
            "protected \$sortColumn = 'occurred_at'",
            "protected \$sortOrder = 'desc'",
        ],
        'movement_view' => [
            'INVENTORY MOVEMENT LIVE V1',
            '_movement_live_v',
            'reload-datagrids',
            '10000',
            'resetView',
        ],
        'alert_controller' => [
            'INVENTORY DAMAGE ALERT REASON V1',
            'latest_damage_notes',
            'damage_reason',
            'damage_reference',
        ],
        'alert_view' => [
            'INVENTORY DAMAGE ALERT REASON V1',
            'Alasan rusak:',
            "\$alert['damage_reason']",
        ],
    ];

    foreach ($requiredMarkers as $key => $markers) {
        foreach ($markers as $marker) {
            if (! str_contains($sources[$key], $marker)) {
                throw new RuntimeException("Validation {$key} gagal: {$marker}");
            }
        }
    }

    $stamp = date('Ymd-His');
    $sourceKeys = [
        'movement_controller',
        'movement_datagrid',
        'movement_view',
        'alert_controller',
        'alert_view',
    ];

    foreach ($sourceKeys as $key) {
        $backup = $paths[$key].'.bak-movement-live-damage-alert-v1-'.$stamp;

        if (! copy($paths[$key], $backup)) {
            throw new RuntimeException("Gagal membuat backup {$backup}");
        }

        $backups[$key] = $backup;
    }

    try {
        foreach ($sourceKeys as $key) {
            inventoryMovementLiveV1Write(
                $paths[$key],
                rtrim($sources[$key]).PHP_EOL
            );
        }

        foreach (
            [
                'movement_controller',
                'movement_datagrid',
                'alert_controller',
                'movement_test',
                'alert_test',
            ] as $key
        ) {
            [$lintCode, $lintOutput] = inventoryMovementLiveV1Lint($paths[$key]);

            if ($lintCode !== 0) {
                throw new RuntimeException(
                    "PHP lint gagal {$paths[$key]}:\n{$lintOutput}"
                );
            }
        }
    } catch (Throwable $exception) {
        foreach ($backups as $key => $backup) {
            @copy($backup, $paths[$key]);
        }

        throw $exception;
    }

    echo "[OK] Inventory Movement live refresh terpasang.\n";
    echo "[OK] Default urutan movement terbaru terpasang.\n";
    echo "[OK] Detail alasan Damaged Asset terpasang.\n";
    echo "[OK] Backup source: .bak-movement-live-damage-alert-v1-{$stamp}\n";
} else {
    echo "[SKIP] Patch sudah terpasang.\n";
}

$php = escapeshellarg(PHP_BINARY);
$artisan = escapeshellarg($root.'/artisan');
passthru($php.' '.$artisan.' optimize:clear', $cacheCode);

if ($cacheCode !== 0) {
    echo "[WARN] Patch berhasil, tetapi optimize:clear gagal. Jalankan manual.\n";
}

echo "\n[PASS] Inventory Movement Live + Damage Alert Detail V1 siap.\n";
echo "- Movement refresh otomatis setiap 10 detik dan respons tidak dicache.\n";
echo "- State lama direset oleh versi src baru; Reset View tersedia.\n";
echo "- Alasan rusak dan nomor Surat Jalan tampil di Damaged Asset alert.\n\n";
echo "Jalankan checker:\n";
echo "php tools/check_inventory_movement_live_damage_alert_v1.php\n";
