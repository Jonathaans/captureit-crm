<?php

namespace Webkul\Invoice\Models;

use Illuminate\Database\Eloquent\Model;

class WorkOrderItem extends Model
{
    protected $table = 'work_order_items';

    protected $guarded = [];

    public function workOrder()
    {
        return $this->belongsTo(
            WorkOrder::class,
            'work_order_id'
        );
    }
}
