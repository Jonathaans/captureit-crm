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

    /**
     * Status yang masih mencadangkan stok fisik di warehouse.
     *
     * OUT dan RETURN_PENDING tidak termasuk karena quantity_on_hand
     * sudah berkurang ketika barang benar-benar keluar.
     */
    public const RESERVATION_STATUSES = [
        'allocated',
        'picked',
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
        'picked_by',
        'picked_at',
        'out_by',
        'out_at',
        'return_pending_by',
        'return_pending_at',
        'checked_in_by',
        'checked_in_at',
        'return_condition',
        'returned_quantity',
        'return_notes',
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
        'picked_by'              => 'integer',
        'out_by'                 => 'integer',
        'return_pending_by'      => 'integer',
        'checked_in_by'          => 'integer',
        'released_by'            => 'integer',
        'returned_quantity'      => 'decimal:2',
        'allocated_at'           => 'datetime',
        'picked_at'              => 'datetime',
        'out_at'                 => 'datetime',
        'return_pending_at'      => 'datetime',
        'checked_in_at'          => 'datetime',
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

    public function pickedBy(): BelongsTo
    {
        return $this->belongsTo(
            UserProxy::modelClass(),
            'picked_by'
        );
    }

    public function outBy(): BelongsTo
    {
        return $this->belongsTo(
            UserProxy::modelClass(),
            'out_by'
        );
    }

    public function returnPendingBy(): BelongsTo
    {
        return $this->belongsTo(
            UserProxy::modelClass(),
            'return_pending_by'
        );
    }

    public function checkedInBy(): BelongsTo
    {
        return $this->belongsTo(
            UserProxy::modelClass(),
            'checked_in_by'
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
