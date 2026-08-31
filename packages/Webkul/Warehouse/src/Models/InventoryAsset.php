<?php

namespace Webkul\Warehouse\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryAsset extends Model
{
    protected $table = 'inventory_assets';

    protected $fillable = [
        'inventory_item_id',
        'asset_code',
        'barcode_value',
        'serial_number',
        'warehouse_id',
        'warehouse_location_id',
        'status',
        'condition',
        'purchase_date',
        'purchase_price',
        'notes',
    ];

    protected $casts = [
        'purchase_date' => 'date',
        'purchase_price' => 'decimal:2',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(WarehouseProxy::modelClass(), 'warehouse_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(LocationProxy::modelClass(), 'warehouse_location_id');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(InventoryStockMovement::class, 'inventory_asset_id');
    }

    public function maintenances(): HasMany
    {
        return $this->hasMany(
            InventoryAssetMaintenance::class,
            'inventory_asset_id'
        );
    }

    public function scopeAvailable($query)
    {
        return $query->where('status', 'available');
    }

    public function scopeAllocated($query)
    {
        return $query->where('status', 'allocated');
    }

    public function scopeOut($query)
    {
        return $query->where('status', 'out');
    }

    public function isAvailable(): bool
    {
        return $this->status === 'available';
    }
}
