<?php

namespace Webkul\Invoice\Models;

use Illuminate\Database\Eloquent\Model;
use Webkul\Invoice\Contracts\Expense as ExpenseContract;
use Webkul\User\Models\UserProxy;

class Expense extends Model implements ExpenseContract
{
    protected $table = 'expenses';

    protected $fillable = [
        'invoice_id',
        'category',
        'description',
        'amount',
        'expense_date',
        'vendor_name',
        'reference_number',
        'receipt_path',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'amount'       => 'decimal:4',
        'expense_date' => 'date',
    ];

    public function invoice()
    {
        return $this->belongsTo(InvoiceProxy::modelClass());
    }

    public function creator()
    {
        return $this->belongsTo(
            UserProxy::modelClass(),
            'created_by'
        );
    }
}