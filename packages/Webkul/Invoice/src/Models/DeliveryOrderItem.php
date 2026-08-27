<?php

namespace Webkul\Invoice\Models;

use Illuminate\Database\Eloquent\Model;
use Webkul\Invoice\Contracts\DeliveryOrderItem as DeliveryOrderItemContract;

class DeliveryOrderItem extends Model implements DeliveryOrderItemContract
{
    protected $table = 'delivery_order_items';

    protected $fillable = [
        'delivery_order_id',

        'product_id',
        'sku',

        'name',
        'description',

        'quantity',
        'unit',

        'notes',

        'sort_order',
    ];

    protected $casts = [
        'quantity'   => 'decimal:2',
        'sort_order' => 'integer',
    ];

    /**
     * Surat Jalan pemilik item.
     */
    public function deliveryOrder()
    {
        return $this->belongsTo(
            DeliveryOrderProxy::modelClass(),
            'delivery_order_id'
        );
    }
}