<?php

namespace Webkul\Product\Models;

use Illuminate\Database\Eloquent\Model;

class ProductEquipmentTemplateItem extends Model
{
    protected $table =
        'product_equipment_template_items';

    protected $fillable = [
        'template_id',

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
     * Template pemilik equipment.
     */
    public function template()
    {
        return $this->belongsTo(
            ProductEquipmentTemplate::class,
            'template_id'
        );
    }
}