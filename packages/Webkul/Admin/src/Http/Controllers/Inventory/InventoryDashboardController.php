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
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $summary = [
            'total_assets'    => (int) $assetStatus->sum(),
            'available'       => (int) ($assetStatus['available'] ?? 0),
            'allocated'       => (int) ($assetStatus['allocated'] ?? 0),
            'picked'          => (int) ($assetStatus['picked'] ?? 0),
            'out'             => (int) ($assetStatus['out'] ?? 0),
            'return_pending'  => (int) ($assetStatus['return_pending'] ?? 0),
            'maintenance'     => (int) ($assetStatus['maintenance'] ?? 0),
            'damaged'         => (int) ($assetStatus['damaged'] ?? 0),
            'missing'         => (int) ($assetStatus['missing'] ?? 0),
            'retired'         => (int) ($assetStatus['retired'] ?? 0),
            'quantity_items'  => DB::table('inventory_items')
                ->where('tracking_type', 'quantity')
                ->where('is_active', true)
                ->count(),
            'low_stock_items' => DB::table('inventory_items')
                ->where('tracking_type', 'quantity')
                ->where('is_active', true)
                ->where('minimum_stock', '>', 0)
                ->whereColumn('quantity_on_hand', '<=', 'minimum_stock')
                ->count(),
        ];

        $lowStockItems = DB::table('inventory_items')
            ->where('tracking_type', 'quantity')
            ->where('is_active', true)
            ->where('minimum_stock', '>', 0)
            ->whereColumn('quantity_on_hand', '<=', 'minimum_stock')
            ->orderByRaw('(quantity_on_hand / NULLIF(minimum_stock, 0)) asc')
            ->orderBy('name')
            ->limit(10)
            ->get();

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
            ->whereIn('inventory_assets.status', [
                'out',
                'return_pending',
                'maintenance',
                'damaged',
                'missing',
            ])
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
            ->orderBy('inventory_assets.updated_at', 'desc')
            ->limit(10)
            ->get();

        $recentMovements = DB::table('inventory_stock_movements')
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
                'users.name as performed_by_name'
            )
            ->orderByDesc('inventory_stock_movements.occurred_at')
            ->orderByDesc('inventory_stock_movements.id')
            ->limit(10)
            ->get();

        return view(
            'admin::inventory.dashboard.index',
            compact(
                'summary',
                'lowStockItems',
                'attentionAssets',
                'recentMovements'
            )
        );
    }
}
