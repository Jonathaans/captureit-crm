<?php

namespace Webkul\Admin\DataGrids\Inventory;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Webkul\DataGrid\DataGrid;

class InventoryAssetDataGrid extends DataGrid
{
    public function prepareQueryBuilder(): Builder
    {
        $queryBuilder = DB::table('inventory_assets')
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
            ->leftJoin(
                'warehouse_locations',
                'inventory_assets.warehouse_location_id',
                '=',
                'warehouse_locations.id'
            )
            ->select(
                'inventory_assets.id',
                'inventory_assets.asset_code',
                'inventory_assets.barcode_value',
                'inventory_assets.serial_number',
                'inventory_assets.status',
                'inventory_assets.condition',
                'inventory_assets.created_at',
                'inventory_items.id as inventory_item_id',
                'inventory_items.code as item_code',
                'inventory_items.name as item_name',
                'inventory_items.category',
                'warehouses.name as warehouse_name',
                'warehouse_locations.name as location_name'
            );

        if (request()->filled('inventory_item_id')) {
            $queryBuilder->where(
                'inventory_assets.inventory_item_id',
                request()->integer('inventory_item_id')
            );
        }

        $this->addFilter(
            'asset_code',
            'inventory_assets.asset_code'
        );

        $this->addFilter(
            'barcode_value',
            'inventory_assets.barcode_value'
        );

        $this->addFilter(
            'serial_number',
            'inventory_assets.serial_number'
        );

        $this->addFilter(
            'item_name',
            'inventory_items.name'
        );

        $this->addFilter(
            'category',
            'inventory_items.category'
        );

        $this->addFilter(
            'status',
            'inventory_assets.status'
        );

        $this->addFilter(
            'condition',
            'inventory_assets.condition'
        );

        return $queryBuilder;
    }

    public function prepareColumns(): void
    {
        $this->addColumn([
            'index'      => 'asset_code',
            'label'      => 'Asset Code',
            'type'       => 'string',
            'searchable' => true,
            'sortable'   => true,
            'filterable' => true,
            'closure'    => function ($row) {
                $url = route(
                    'admin.inventory.assets.edit',
                    $row->id
                );

                return sprintf(
                    '<a class="text-brandColor font-medium transition-all hover:underline" href="%s">%s</a>',
                    $url,
                    e($row->asset_code)
                );
            },
        ]);

        $this->addColumn([
            'index'      => 'item_name',
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
            'index'      => 'barcode_value',
            'label'      => 'Barcode',
            'type'       => 'string',
            'searchable' => true,
            'sortable'   => true,
            'filterable' => true,
            'closure'    => fn ($row) => $row->barcode_value ?: '-',
        ]);

        $this->addColumn([
            'index'      => 'serial_number',
            'label'      => 'Serial Number',
            'type'       => 'string',
            'searchable' => true,
            'sortable'   => true,
            'filterable' => true,
            'closure'    => fn ($row) => $row->serial_number ?: '-',
        ]);

        $this->addColumn([
            'index'              => 'status',
            'label'              => 'Status',
            'type'               => 'string',
            'searchable'         => false,
            'sortable'           => true,
            'filterable'         => true,
            'filterable_type'    => 'dropdown',
            'filterable_options' => [
                ['label' => 'Available', 'value' => 'available'],
                ['label' => 'Allocated', 'value' => 'allocated'],
                ['label' => 'Picked', 'value' => 'picked'],
                ['label' => 'Out', 'value' => 'out'],
                ['label' => 'Return Pending', 'value' => 'return_pending'],
                ['label' => 'Maintenance', 'value' => 'maintenance'],
                ['label' => 'Damaged', 'value' => 'damaged'],
                ['label' => 'Missing', 'value' => 'missing'],
                ['label' => 'Retired', 'value' => 'retired'],
            ],
            'closure' => fn ($row) => $this->statusBadge(
                (string) $row->status
            ),
        ]);

        $this->addColumn([
            'index'              => 'condition',
            'label'              => 'Condition',
            'type'               => 'string',
            'searchable'         => false,
            'sortable'           => true,
            'filterable'         => true,
            'filterable_type'    => 'dropdown',
            'filterable_options' => [
                ['label' => 'Good', 'value' => 'good'],
                ['label' => 'Fair', 'value' => 'fair'],
                ['label' => 'Damaged', 'value' => 'damaged'],
            ],
            'closure' => fn ($row) => ucfirst(
                str_replace('_', ' ', (string) $row->condition)
            ),
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
    }

    public function prepareActions(): void
    {
        if (! bouncer()->hasPermission('inventory.assets.edit')) {
            return;
        }

        $this->addAction([
            'index'  => 'edit',
            'icon'   => 'icon-edit',
            'title'  => 'Edit Asset',
            'method' => 'GET',
            'url'    => fn ($row) => route(
                'admin.inventory.assets.edit',
                $row->id
            ),
        ]);
    }

    private function statusBadge(string $status): string
    {
        $labels = [
            'available'      => ['AVAILABLE', '#dcfce7', '#15803d'],
            'allocated'      => ['ALLOCATED', '#fef3c7', '#a16207'],
            'picked'         => ['PICKED', '#dbeafe', '#1d4ed8'],
            'out'            => ['OUT', '#e0e7ff', '#4338ca'],
            'return_pending' => ['RETURN PENDING', '#ffedd5', '#c2410c'],
            'maintenance'    => ['MAINTENANCE', '#f3e8ff', '#7e22ce'],
            'damaged'        => ['DAMAGED', '#fee2e2', '#b91c1c'],
            'missing'        => ['MISSING', '#fee2e2', '#991b1b'],
            'retired'        => ['RETIRED', '#f3f4f6', '#4b5563'],
        ];

        [$label, $background, $color] = $labels[$status]
            ?? [strtoupper($status), '#f3f4f6', '#4b5563'];

        return sprintf(
            '<span style="padding:4px 9px;border-radius:9999px;background:%s;color:%s;font-weight:700;font-size:11px;">%s</span>',
            $background,
            $color,
            e($label)
        );
    }
}
