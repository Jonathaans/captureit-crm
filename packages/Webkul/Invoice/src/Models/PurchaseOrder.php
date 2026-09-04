<?php

namespace Webkul\Invoice\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrder extends Model
{
    protected $table = 'purchase_orders';

    protected $fillable = [
        'po_number',
        'invoice_id',
        'invoice_number',
        'project_code',
        'project_name',
        'business_unit',
        'vendor_name',
        'vendor_phone',
        'vendor_email',
        'vendor_address',
        'order_date',
        'payment_terms',
        'status',
        'sub_total',
        'tax_amount',
        'adjustment_amount',
        'grand_total',
        'notes',
        'expense_id',
        'created_by',
        'created_by_name',
        'released_by',
        'released_by_name',
        'released_at',
        // PURCHASE ORDER PAID WORKFLOW V1
        'payment_proof_path',
        'paid_by',
        'paid_by_name',
        'paid_at',
        'completed_by',
        'completed_by_name',
        'completed_at',
        'cancelled_by',
        'cancelled_by_name',
        'cancelled_at',
    ];

    protected $casts = [
        'order_date' => 'date',
        'released_at' => 'datetime',
        'paid_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'sub_total' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'adjustment_amount' => 'decimal:2',
        'grand_total' => 'decimal:2',
    ];

    /**
     * Selectable Terms of Payment for vendor Purchase Orders.
     */
    public static function paymentTermsOptions(): array
    {
        return [
            '7_days' => '7 Days',
            '14_days' => '14 Days',
            '21_days' => '21 Days',
            '30_days' => '30 Days',
            'full_payment_before_event' => 'Full Payment Before Event',
        ];
    }

    public function getPaymentTermsLabelAttribute(): string
    {
        return static::paymentTermsOptions()[
            $this->payment_terms
        ] ?? ucwords(
            str_replace(
                '_',
                ' ',
                (string) $this->payment_terms
            )
        );
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class, 'purchase_order_id')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isReleased(): bool
    {
        return $this->status === 'released';
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }
}
