<?php

namespace Webkul\Warehouse\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryItem extends Model
{
    protected $table = 'inventory_items';

    protected $fillable = [
        'code',
        'name',
        'category',
        'description',
        'tracking_type',
        'unit',
        'quantity_on_hand',
        'minimum_stock',
        'warehouse_id',
        'warehouse_location_id',
        'is_active',
    ];

    protected $casts = [
        'quantity_on_hand' => 'decimal:2',
        'minimum_stock' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(WarehouseProxy::modelClass(), 'warehouse_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(LocationProxy::modelClass(), 'warehouse_location_id');
    }

    public function assets(): HasMany
    {
        return $this->hasMany(InventoryAsset::class, 'inventory_item_id');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(InventoryStockMovement::class, 'inventory_item_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeSerialized($query)
    {
        return $query->where('tracking_type', 'serialized');
    }

    public function scopeQuantity($query)
    {
        return $query->where('tracking_type', 'quantity');
    }

    public function isSerialized(): bool
    {
        return $this->tracking_type === 'serialized';
    }

    public function isQuantityTracked(): bool
    {
        return $this->tracking_type === 'quantity';
    }
}
