<?php

namespace Webkul\Invoice\Models;

use Illuminate\Database\Eloquent\Model;
use Webkul\Contact\Models\PersonProxy;
use Webkul\Invoice\Contracts\Invoice as InvoiceContract;
use Webkul\Quote\Models\QuoteProxy;
use Webkul\User\Models\UserProxy;

class Invoice extends Model implements InvoiceContract
{
    protected $table = 'invoices';

    protected $fillable = [
        'invoice_number',
        'quote_id',
        'person_id',
        'user_id',
        'subject',
        'description',
        'billing_address',
        'shipping_address',
        'discount_percent',
        'discount_amount',
        'tax_amount',
        'adjustment_amount',
        'sub_total',
        'grand_total',
        'paid_amount',
        'balance_due',
        'status',
        'issued_at',
        'due_at',
    ];

    protected $casts = [
        'billing_address'  => 'array',
        'shipping_address' => 'array',
        'issued_at'        => 'datetime',
        'due_at'           => 'datetime',
    ];

    public function quote()
    {
        return $this->belongsTo(QuoteProxy::modelClass());
    }

    public function person()
    {
        return $this->belongsTo(PersonProxy::modelClass());
    }

    public function user()
    {
        return $this->belongsTo(UserProxy::modelClass());
    }

    public function items()
    {
        return $this->hasMany(InvoiceItemProxy::modelClass());
    }

    public function payments()
    {
        return $this->hasMany(PaymentProxy::modelClass());
    }
}