<?php

namespace Webkul\Warehouse\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Webkul\User\Models\UserProxy;

class InventoryStockOpnameSession extends Model
{
    public const OPEN_STATUSES = [
        'draft',
        'in_progress',
        'review',
    ];

    protected $table = 'inventory_stock_opname_sessions';

    protected $fillable = [
        'reference_number',
        'warehouse_id',
        'status',
        'notes',
        'created_by',
        'started_by',
        'started_at',
        'reviewed_by',
        'reviewed_at',
        'finalized_by',
        'finalized_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'finalized_at' => 'datetime',
    ];

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(
            WarehouseProxy::modelClass(),
            'warehouse_id'
        );
    }

    public function entries(): HasMany
    {
        return $this->hasMany(
            InventoryStockOpnameEntry::class,
            'stock_opname_session_id'
        );
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(
            UserProxy::modelClass(),
            'created_by'
        );
    }

    public function startedBy(): BelongsTo
    {
        return $this->belongsTo(
            UserProxy::modelClass(),
            'started_by'
        );
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(
            UserProxy::modelClass(),
            'reviewed_by'
        );
    }

    public function finalizedBy(): BelongsTo
    {
        return $this->belongsTo(
            UserProxy::modelClass(),
            'finalized_by'
        );
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isInProgress(): bool
    {
        return $this->status === 'in_progress';
    }

    public function isReview(): bool
    {
        return $this->status === 'review';
    }

    public function isFinalized(): bool
    {
        return $this->status === 'finalized';
    }
}
