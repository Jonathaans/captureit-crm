<?php

namespace Webkul\Admin\DataGrids\Inventory;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Webkul\DataGrid\DataGrid;

class InventoryConsumableDataGrid extends DataGrid
{
    public function prepareQueryBuilder(): Builder
    {
        $queryBuilder = DB::table('inventory_items')
            ->leftJoin(
                'warehouses',
                'inventory_items.warehouse_id',
                '=',
                'warehouses.id'
            )
            ->where(
                'inventory_items.tracking_type',
                'quantity'
            )
            ->select([
                'inventory_items.id',
                'inventory_items.code',
                'inventory_items.name',
                'inventory_items.category',
                'inventory_items.unit',
                'inventory_items.quantity_on_hand',
                'inventory_items.minimum_stock',
                'inventory_items.is_active',
                'inventory_items.updated_at',
                'warehouses.name as warehouse_name',
            ]);

        $this->addFilter('code', 'inventory_items.code');
        $this->addFilter('name', 'inventory_items.name');
        $this->addFilter('category', 'inventory_items.category');
        $this->addFilter('is_active', 'inventory_items.is_active');

        return $queryBuilder;
    }

    public function prepareColumns(): void
    {
        $this->addColumn([
            'index' => 'code',
            'label' => 'Code',
            'type' => 'string',
            'searchable' => true,
            'sortable' => true,
            'filterable' => true,
            'closure' => function ($row) {
                return sprintf(
                    '<a class="text-brandColor font-medium transition-all hover:underline" href="%s">%s</a>',
                    route(
                        'admin.inventory.consumables.edit',
                        $row->id
                    ),
                    e($row->code)
                );
            },
        ]);

        $this->addColumn([
            'index' => 'name',
            'label' => 'Consumable',
            'type' => 'string',
            'searchable' => true,
            'sortable' => true,
            'filterable' => true,
        ]);

        $this->addColumn([
            'index' => 'category',
            'label' => 'Category',
            'type' => 'string',
            'searchable' => true,
            'sortable' => true,
            'filterable' => true,
            'closure' => fn ($row) => $row->category ?: '-',
        ]);

        $this->addColumn([
            'index' => 'current_stock',
            'label' => 'Current Stock',
            'type' => 'string',
            'searchable' => false,
            'sortable' => false,
            'filterable' => false,
            'closure' => function ($row) {
                return sprintf(
                    '<strong>%s</strong> <span style="color:#6b7280;">%s</span>',
                    e($this->formatQuantity(
                        (float) $row->quantity_on_hand
                    )),
                    e($row->unit)
                );
            },
        ]);

        $this->addColumn([
            'index' => 'minimum_stock',
            'label' => 'Minimum',
            'type' => 'string',
            'searchable' => false,
            'sortable' => true,
            'filterable' => false,
            'closure' => fn ($row) =>
                $this->formatQuantity(
                    (float) $row->minimum_stock
                ).' '.e($row->unit),
        ]);

        $this->addColumn([
            'index' => 'stock_status',
            'label' => 'Stock Status',
            'type' => 'string',
            'searchable' => false,
            'sortable' => false,
            'filterable' => false,
            'closure' => function ($row) {
                $current = (float) $row->quantity_on_hand;
                $minimum = (float) $row->minimum_stock;

                if ($current <= 0) {
                    return '<span style="display:inline-flex;padding:4px 9px;border-radius:9999px;background:#fee2e2;color:#b91c1c;font-weight:700;font-size:10px;">OUT OF STOCK</span>';
                }

                if ($minimum > 0 && $current <= $minimum) {
                    return '<span style="display:inline-flex;padding:4px 9px;border-radius:9999px;background:#fef3c7;color:#b45309;font-weight:700;font-size:10px;">LOW STOCK</span>';
                }

                return '<span style="display:inline-flex;padding:4px 9px;border-radius:9999px;background:#dcfce7;color:#15803d;font-weight:700;font-size:10px;">HEALTHY</span>';
            },
        ]);

        $this->addColumn([
            'index' => 'warehouse_name',
            'label' => 'Warehouse',
            'type' => 'string',
            'searchable' => false,
            'sortable' => false,
            'filterable' => false,
            'closure' => fn ($row) => $row->warehouse_name ?: '-',
        ]);

        $this->addColumn([
            'index' => 'is_active',
            'label' => 'Status',
            'type' => 'boolean',
            'searchable' => false,
            'sortable' => true,
            'filterable' => true,
            'filterable_type' => 'dropdown',
            'filterable_options' => [
                ['label' => 'Active', 'value' => 1],
                ['label' => 'Inactive', 'value' => 0],
            ],
            'closure' => function ($row) {
                return $row->is_active
                    ? '<span style="display:inline-flex;padding:4px 9px;border-radius:9999px;background:#dcfce7;color:#15803d;font-weight:700;font-size:10px;">ACTIVE</span>'
                    : '<span style="display:inline-flex;padding:4px 9px;border-radius:9999px;background:#f3f4f6;color:#6b7280;font-weight:700;font-size:10px;">INACTIVE</span>';
            },
        ]);
    }

    public function prepareActions(): void
    {
        if (bouncer()->hasPermission('inventory.consumables.edit')) {
            $this->addAction([
                'index' => 'edit',
                'icon' => 'icon-edit',
                'title' => 'Edit Consumable',
                'method' => 'GET',
                'url' => fn ($row) => route(
                    'admin.inventory.consumables.edit',
                    $row->id
                ),
            ]);
        }

        if (bouncer()->hasPermission('inventory.movements.adjust-stock')) {
            $this->addAction([
                'index' => 'adjust-stock',
                'icon' => 'icon-setting',
                'title' => 'Adjust Stock',
                'method' => 'GET',
                'url' => fn ($row) => route(
                    'admin.inventory.movements.adjust-stock.create',
                    ['inventory_item_id' => $row->id]
                ),
            ]);
        }
    }

    private function formatQuantity(float $value): string
    {
        return rtrim(
            rtrim(
                number_format($value, 2, '.', ''),
                '0'
            ),
            '.'
        );
    }
}
