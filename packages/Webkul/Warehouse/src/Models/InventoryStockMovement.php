<?php

namespace Webkul\Warehouse\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Webkul\User\Models\UserProxy;

class InventoryStockMovement extends Model
{
    protected $table = 'inventory_stock_movements';

    protected $fillable = [
        'inventory_item_id',
        'inventory_asset_id',
        'warehouse_id',
        'warehouse_location_id',
        'movement_type',
        'quantity',
        'from_status',
        'to_status',
        'reference_type',
        'reference_id',
        'reference_number',
        'performed_by',
        'notes',
        'occurred_at',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'occurred_at' => 'datetime',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(InventoryAsset::class, 'inventory_asset_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(WarehouseProxy::modelClass(), 'warehouse_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(LocationProxy::modelClass(), 'warehouse_location_id');
    }

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(UserProxy::modelClass(), 'performed_by');
    }
}
