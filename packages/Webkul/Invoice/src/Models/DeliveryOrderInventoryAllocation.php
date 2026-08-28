<?php

namespace Webkul\Invoice\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Webkul\User\Models\UserProxy;
use Webkul\Warehouse\Models\InventoryAsset;
use Webkul\Warehouse\Models\InventoryItem;

class DeliveryOrderInventoryAllocation extends Model
{
    public const ACTIVE_STATUSES = [
        'allocated',
        'picked',
        'out',
        'return_pending',
    ];

    protected $table = 'delivery_order_inventory_allocations';

    protected $fillable = [
        'delivery_order_id',
        'delivery_order_item_id',
        'inventory_item_id',
        'inventory_asset_id',
        'tracking_type',
        'quantity',
        'status',
        'allocated_by',
        'allocated_at',
        'released_by',
        'released_at',
        'notes',
    ];

    protected $casts = [
        'delivery_order_id'      => 'integer',
        'delivery_order_item_id' => 'integer',
        'inventory_item_id'      => 'integer',
        'inventory_asset_id'     => 'integer',
        'quantity'               => 'decimal:2',
        'allocated_by'           => 'integer',
        'released_by'            => 'integer',
        'allocated_at'           => 'datetime',
        'released_at'            => 'datetime',
    ];

    public function deliveryOrder(): BelongsTo
    {
        return $this->belongsTo(
            DeliveryOrder::class,
            'delivery_order_id'
        );
    }

    public function deliveryOrderItem(): BelongsTo
    {
        return $this->belongsTo(
            DeliveryOrderItem::class,
            'delivery_order_item_id'
        );
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(
            InventoryItem::class,
            'inventory_item_id'
        );
    }

    public function inventoryAsset(): BelongsTo
    {
        return $this->belongsTo(
            InventoryAsset::class,
            'inventory_asset_id'
        );
    }

    public function allocatedBy(): BelongsTo
    {
        return $this->belongsTo(
            UserProxy::modelClass(),
            'allocated_by'
        );
    }

    public function releasedBy(): BelongsTo
    {
        return $this->belongsTo(
            UserProxy::modelClass(),
            'released_by'
        );
    }

    public function scopeActive($query)
    {
        return $query->whereIn(
            'status',
            self::ACTIVE_STATUSES
        );
    }
}
