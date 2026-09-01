<?php

namespace Webkul\Invoice\Models;

use Illuminate\Database\Eloquent\Model;

class WorkOrder extends Model
{
    protected $table = 'work_orders';

    protected $guarded = [];

    protected $casts = [
        'event_date' => 'date',
        'released_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function invoice()
    {
        return $this->belongsTo(
            Invoice::class,
            'invoice_id'
        );
    }

    public function items()
    {
        return $this->hasMany(
            WorkOrderItem::class,
            'work_order_id'
        )->orderBy('sort_order');
    }

    public function deliveryOrders()
    {
        return $this->hasMany(
            DeliveryOrder::class,
            'work_order_id'
        )->orderBy('id');
    }
}
