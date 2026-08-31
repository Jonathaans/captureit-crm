<?php

namespace Webkul\Warehouse\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Webkul\User\Models\UserProxy;

class InventoryAssetMaintenance extends Model
{
    protected $table = 'inventory_asset_maintenances';

    protected $fillable = [
        'inventory_asset_id',
        'reference_number',
        'status',
        'problem',
        'technician_name',
        'repair_notes',
        'repair_cost',
        'result_condition',
        'retirement_reason',
        'started_by',
        'started_at',
        'completed_by',
        'completed_at',
        'retired_by',
        'retired_at',
    ];

    protected $casts = [
        'repair_cost' => 'decimal:2',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'retired_at' => 'datetime',
    ];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(InventoryAsset::class, 'inventory_asset_id');
    }

    public function startedBy(): BelongsTo
    {
        return $this->belongsTo(UserProxy::modelClass(), 'started_by');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(UserProxy::modelClass(), 'completed_by');
    }

    public function retiredBy(): BelongsTo
    {
        return $this->belongsTo(UserProxy::modelClass(), 'retired_by');
    }

    public function isInProgress(): bool
    {
        return $this->status === 'in_progress';
    }
}
