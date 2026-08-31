<?php

namespace Webkul\Warehouse\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Webkul\User\Models\UserProxy;

class InventoryStockOpnameEntry extends Model
{
    protected $table = 'inventory_stock_opname_entries';

    protected $fillable = [
        'stock_opname_session_id',
        'entry_type',
        'inventory_item_id',
        'inventory_asset_id',
        'scan_value',
        'expected_presence',
        'expected_status',
        'observed_status',
        'expected_condition',
        'system_quantity',
        'actual_quantity',
        'variance',
        'result',
        'scanned_by',
        'scanned_at',
        'counted_by',
        'counted_at',
        'notes',
    ];

    protected $casts = [
        'expected_presence' => 'boolean',
        'system_quantity' => 'decimal:2',
        'actual_quantity' => 'decimal:2',
        'variance' => 'decimal:2',
        'scanned_at' => 'datetime',
        'counted_at' => 'datetime',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(
            InventoryStockOpnameSession::class,
            'stock_opname_session_id'
        );
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(
            InventoryItem::class,
            'inventory_item_id'
        );
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(
            InventoryAsset::class,
            'inventory_asset_id'
        );
    }

    public function scannedBy(): BelongsTo
    {
        return $this->belongsTo(
            UserProxy::modelClass(),
            'scanned_by'
        );
    }

    public function countedBy(): BelongsTo
    {
        return $this->belongsTo(
            UserProxy::modelClass(),
            'counted_by'
        );
    }
}
