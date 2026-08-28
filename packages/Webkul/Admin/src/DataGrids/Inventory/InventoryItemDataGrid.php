<?php

namespace Webkul\Admin\DataGrids\Inventory;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Webkul\DataGrid\DataGrid;

class InventoryItemDataGrid extends DataGrid
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
            ->leftJoin(
                'inventory_assets',
                'inventory_items.id',
                '=',
                'inventory_assets.inventory_item_id'
            )
            ->select(
                'inventory_items.id',
                'inventory_items.code',
                'inventory_items.name',
                'inventory_items.category',
                'inventory_items.tracking_type',
                'inventory_items.unit',
                'inventory_items.quantity_on_hand',
                'inventory_items.minimum_stock',
                'inventory_items.is_active',
                'inventory_items.created_at',
                'warehouses.name as warehouse_name',
                DB::raw('COUNT(inventory_assets.id) as asset_count'),
                DB::raw(
                    "SUM(
                        CASE
                            WHEN inventory_assets.status = 'available' THEN 1
                            ELSE 0
                        END
                    ) as available_asset_count"
                )
            )
            ->groupBy(
                'inventory_items.id',
                'inventory_items.code',
                'inventory_items.name',
                'inventory_items.category',
                'inventory_items.tracking_type',
                'inventory_items.unit',
                'inventory_items.quantity_on_hand',
                'inventory_items.minimum_stock',
                'inventory_items.is_active',
                'inventory_items.created_at',
                'warehouses.name'
            );

        $this->addFilter('code', 'inventory_items.code');
        $this->addFilter('name', 'inventory_items.name');
        $this->addFilter('category', 'inventory_items.category');
        $this->addFilter('tracking_type', 'inventory_items.tracking_type');
        $this->addFilter('is_active', 'inventory_items.is_active');

        return $queryBuilder;
    }

    public function prepareColumns(): void
    {
        $this->addColumn([
            'index'      => 'code',
            'label'      => 'Code',
            'type'       => 'string',
            'searchable' => true,
            'sortable'   => true,
            'filterable' => true,
            'closure'    => function ($row) {
                $url = route('admin.inventory.items.edit', $row->id);

                return sprintf(
                    '<a class="text-brandColor font-medium transition-all hover:underline" href="%s">%s</a>',
                    $url,
                    e($row->code)
                );
            },
        ]);

        $this->addColumn([
            'index'      => 'name',
            'label'      => 'Inventory Item',
            'type'       => 'string',
            'searchable' => true,
            'sortable'   => true,
            'filterable' => true,
        ]);

        $this->addColumn([
            'index'      => 'category',
            'label'      => 'Category',
            'type'       => 'string',
            'searchable' => true,
            'sortable'   => true,
            'filterable' => true,
            'closure'    => fn ($row) => $row->category ?: '-',
        ]);

        $this->addColumn([
            'index'              => 'tracking_type',
            'label'              => 'Tracking',
            'type'               => 'string',
            'searchable'         => false,
            'sortable'           => true,
            'filterable'         => true,
            'filterable_type'    => 'dropdown',
            'filterable_options' => [
                ['label' => 'Serialized', 'value' => 'serialized'],
                ['label' => 'Quantity', 'value' => 'quantity'],
            ],
            'closure' => fn ($row) => strtolower(trim((string) $row->tracking_type)) === 'serialized'
                ? 'Serialized'
                : 'Quantity',
        ]);

        $this->addColumn([
            'index'      => 'stock',
            'label'      => 'Stock / Assets',
            'type'       => 'string',
            'searchable' => false,
            'sortable'   => false,
            'filterable' => false,
            'closure'    => function ($row) {
                $trackingType = strtolower(trim((string) $row->tracking_type));

                if ($trackingType === 'serialized') {
                    return sprintf(
                        '%d total / %d available',
                        (int) ($row->asset_count ?? 0),
                        (int) ($row->available_asset_count ?? 0)
                    );
                }

                $quantity = rtrim(
                    rtrim(
                        number_format(
                            (float) ($row->quantity_on_hand ?? 0),
                            2,
                            '.',
                            ''
                        ),
                        '0'
                    ),
                    '.'
                );

                return $quantity.' '.e((string) ($row->unit ?: 'unit'));
            },
        ]);

        $this->addColumn([
            'index'      => 'warehouse_name',
            'label'      => 'Warehouse',
            'type'       => 'string',
            'searchable' => false,
            'sortable'   => false,
            'filterable' => false,
            'closure'    => fn ($row) => $row->warehouse_name ?: '-',
        ]);

        $this->addColumn([
            'index'              => 'is_active',
            'label'              => 'Status',
            'type'               => 'boolean',
            'searchable'         => false,
            'sortable'           => true,
            'filterable'         => true,
            'filterable_type'    => 'dropdown',
            'filterable_options' => [
                ['label' => 'Active', 'value' => 1],
                ['label' => 'Inactive', 'value' => 0],
            ],
            'closure' => function ($row) {
                if ($row->is_active) {
                    return '<span style="padding:4px 9px;border-radius:9999px;background:#dcfce7;color:#15803d;font-weight:700;font-size:11px;">ACTIVE</span>';
                }

                return '<span style="padding:4px 9px;border-radius:9999px;background:#f3f4f6;color:#6b7280;font-weight:700;font-size:11px;">INACTIVE</span>';
            },
        ]);
    }

    public function prepareActions(): void
    {
        if (! bouncer()->hasPermission('inventory.items.edit')) {
            return;
        }

        $this->addAction([
            'index'  => 'edit',
            'icon'   => 'icon-edit',
            'title'  => 'Edit Inventory Item',
            'method' => 'GET',
            'url'    => fn ($row) => route(
                'admin.inventory.items.edit',
                $row->id
            ),
        ]);
    }
}
