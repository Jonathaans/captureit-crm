<?php

namespace Webkul\Admin\Http\Controllers\Inventory;

use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Webkul\Admin\Http\Controllers\Controller;

class InventoryDashboardController extends Controller
{
    public function index(): View
    {
        $assetStatus = DB::table('inventory_assets')
            ->select(
                'status',
                DB::raw('COUNT(*) as total')
            )
            ->groupBy('status')
            ->pluck(
                'total',
                'status'
            );

        $summary = [
            'total_assets' => (int) $assetStatus->sum(),
            'available' => (int) (
                $assetStatus['available']
                ?? 0
            ),
            'allocated' => (int) (
                $assetStatus['allocated']
                ?? 0
            ),
            'picked' => (int) (
                $assetStatus['picked']
                ?? 0
            ),
            'out' => (int) (
                $assetStatus['out']
                ?? 0
            ),
            'return_pending' => (int) (
                $assetStatus['return_pending']
                ?? 0
            ),
            'maintenance' => (int) (
                $assetStatus['maintenance']
                ?? 0
            ),
            'damaged' => (int) (
                $assetStatus['damaged']
                ?? 0
            ),
            'missing' => (int) (
                $assetStatus['missing']
                ?? 0
            ),
            'retired' => (int) (
                $assetStatus['retired']
                ?? 0
            ),
        ];

        $summary['problem_assets'] =
            $summary['maintenance']
            + $summary['damaged']
            + $summary['missing'];

        $summary['available_percent'] =
            $summary['total_assets'] > 0
                ? round(
                    (
                        $summary['available']
                        / $summary['total_assets']
                    ) * 100
                )
                : 0;

        /*
         * Quantity stock health.
         */
        $quantityBase = DB::table('inventory_items')
            ->where(
                'tracking_type',
                'quantity'
            )
            ->where(
                'is_active',
                true
            );

        $quantityItems = (clone $quantityBase)
            ->count();

        $outOfStockItems = (clone $quantityBase)
            ->where(
                'quantity_on_hand',
                '<=',
                0
            )
            ->count();

        $lowStockItemsCount = (clone $quantityBase)
            ->where(
                'minimum_stock',
                '>',
                0
            )
            ->where(
                'quantity_on_hand',
                '>',
                0
            )
            ->whereColumn(
                'quantity_on_hand',
                '<=',
                'minimum_stock'
            )
            ->count();

        $healthyQuantityItems = max(
            $quantityItems
            - $lowStockItemsCount
            - $outOfStockItems,
            0
        );

        $stockHealth = [
            'total' => $quantityItems,
            'healthy' => $healthyQuantityItems,
            'low' => $lowStockItemsCount,
            'out' => $outOfStockItems,
            'healthy_percent' => $quantityItems > 0
                ? round(
                    (
                        $healthyQuantityItems
                        / $quantityItems
                    ) * 100
                )
                : 100,
            'low_percent' => $quantityItems > 0
                ? round(
                    (
                        $lowStockItemsCount
                        / $quantityItems
                    ) * 100
                )
                : 0,
            'out_percent' => $quantityItems > 0
                ? round(
                    (
                        $outOfStockItems
                        / $quantityItems
                    ) * 100
                )
                : 0,
        ];

        /*
         * Low stock details retained from the existing dashboard.
         */
        $lowStockItems = DB::table('inventory_items')
            ->where(
                'tracking_type',
                'quantity'
            )
            ->where(
                'is_active',
                true
            )
            ->where(
                'minimum_stock',
                '>',
                0
            )
            ->whereColumn(
                'quantity_on_hand',
                '<=',
                'minimum_stock'
            )
            ->orderByRaw(
                '(quantity_on_hand / NULLIF(minimum_stock, 0)) asc'
            )
            ->orderBy('name')
            ->limit(10)
            ->get();

        /*
         * Asset attention preview.
         */
        $attentionAssets = DB::table('inventory_assets')
            ->join(
                'inventory_items',
                'inventory_assets.inventory_item_id',
                '=',
                'inventory_items.id'
            )
            ->select(
                'inventory_assets.id',
                'inventory_assets.asset_code',
                'inventory_assets.status',
                'inventory_assets.condition',
                'inventory_items.code as item_code',
                'inventory_items.name as item_name'
            )
            ->whereIn(
                'inventory_assets.status',
                [
                    'out',
                    'return_pending',
                    'maintenance',
                    'damaged',
                    'missing',
                ]
            )
            ->orderByRaw("
                CASE inventory_assets.status
                    WHEN 'missing' THEN 1
                    WHEN 'damaged' THEN 2
                    WHEN 'return_pending' THEN 3
                    WHEN 'maintenance' THEN 4
                    WHEN 'out' THEN 5
                    ELSE 6
                END
            ")
            ->orderBy(
                'inventory_assets.updated_at',
                'desc'
            )
            ->limit(10)
            ->get();

        $assetAttention = [
            'missing' => $summary['missing'],
            'maintenance' => $summary['maintenance'],
            'return_pending' => $summary['return_pending'],
            'damaged' => $summary['damaged'],
        ];

        /*
         * Lightweight alert preview.
         * Uses the same core rules as Phase 4E without duplicating the full page.
         */
        $dashboardAlerts = collect();

        foreach ($lowStockItems as $item) {
            $current = (float) $item->quantity_on_hand;
            $minimum = (float) $item->minimum_stock;

            $dashboardAlerts->push([
                'type' => $current <= 0
                    ? 'critical'
                    : 'warning',
                'title' => $item->name,
                'subtitle' => $current <= 0
                    ? 'Stock habis'
                    : 'Stock di bawah minimum',
                'value' => $this->formatQuantity(
                    $current
                ).' '.$item->unit,
                'detail' => 'Min: '
                    .$this->formatQuantity($minimum)
                    .' '.$item->unit,
                'item_id' => (int) $item->id,
            ]);
        }

        $problemPreview = DB::table('inventory_assets')
            ->join(
                'inventory_items',
                'inventory_assets.inventory_item_id',
                '=',
                'inventory_items.id'
            )
            ->whereIn(
                'inventory_assets.status',
                [
                    'missing',
                    'maintenance',
                    'damaged',
                    'return_pending',
                ]
            )
            ->select([
                'inventory_assets.id',
                'inventory_assets.asset_code',
                'inventory_assets.status',
                'inventory_items.name as item_name',
            ])
            ->orderByRaw("
                CASE inventory_assets.status
                    WHEN 'missing' THEN 1
                    WHEN 'damaged' THEN 2
                    WHEN 'return_pending' THEN 3
                    WHEN 'maintenance' THEN 4
                    ELSE 5
                END
            ")
            ->orderByDesc(
                'inventory_assets.updated_at'
            )
            ->limit(8)
            ->get();

        foreach ($problemPreview as $asset) {
            $status = strtolower(
                (string) $asset->status
            );

            [$type, $subtitle, $detail] = match ($status) {
                'missing' => [
                    'critical',
                    'Asset tidak ditemukan',
                    'MISSING',
                ],
                'damaged' => [
                    'warning',
                    'Perlu perbaikan',
                    'DAMAGED',
                ],
                'maintenance' => [
                    'info',
                    'Sedang maintenance',
                    'MAINTENANCE',
                ],
                'return_pending' => [
                    'info',
                    'Menunggu finalize return',
                    'RETURN PENDING',
                ],
                default => [
                    'info',
                    'Perlu perhatian',
                    strtoupper($status),
                ],
            };

            $dashboardAlerts->push([
                'type' => $type,
                'title' => $asset->item_name,
                'subtitle' => $subtitle,
                'value' => $asset->asset_code,
                'detail' => $detail,
                'asset_id' => (int) $asset->id,
            ]);
        }

        $severityWeight = [
            'critical' => 1,
            'warning' => 2,
            'info' => 3,
        ];

        $dashboardAlerts = $dashboardAlerts
            ->sortBy(
                fn ($alert) => $severityWeight[
                    $alert['type']
                ] ?? 99
            )
            ->take(5)
            ->values();

        /*
         * Recent movements retained and slightly extended for richer UI.
         */
        $recentMovements = DB::table(
            'inventory_stock_movements'
        )
            ->join(
                'inventory_items',
                'inventory_stock_movements.inventory_item_id',
                '=',
                'inventory_items.id'
            )
            ->leftJoin(
                'inventory_assets',
                'inventory_stock_movements.inventory_asset_id',
                '=',
                'inventory_assets.id'
            )
            ->leftJoin(
                'warehouses',
                'inventory_stock_movements.warehouse_id',
                '=',
                'warehouses.id'
            )
            ->leftJoin(
                'users',
                'inventory_stock_movements.performed_by',
                '=',
                'users.id'
            )
            ->select(
                'inventory_stock_movements.id',
                'inventory_stock_movements.movement_type',
                'inventory_stock_movements.quantity',
                'inventory_stock_movements.from_status',
                'inventory_stock_movements.to_status',
                'inventory_stock_movements.reference_number',
                'inventory_stock_movements.occurred_at',
                'inventory_items.code as item_code',
                'inventory_items.name as item_name',
                'inventory_items.unit',
                'inventory_assets.asset_code',
                'warehouses.name as warehouse_name',
                'users.name as performed_by_name'
            )
            ->orderByDesc(
                'inventory_stock_movements.occurred_at'
            )
            ->orderByDesc(
                'inventory_stock_movements.id'
            )
            ->limit(10)
            ->get();

        return view(
            'admin::inventory.dashboard.index',
            compact(
                'summary',
                'stockHealth',
                'assetAttention',
                'lowStockItems',
                'attentionAssets',
                'dashboardAlerts',
                'recentMovements'
            )
        );
    }

    private function formatQuantity(
        float $value
    ): string {
        return rtrim(
            rtrim(
                number_format(
                    $value,
                    2,
                    '.',
                    ''
                ),
                '0'
            ),
            '.'
        );
    }
}
