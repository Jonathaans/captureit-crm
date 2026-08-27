<?php

namespace Webkul\Product\Models;

use Illuminate\Database\Eloquent\Model;

class ProductEquipmentTemplate extends Model
{
    protected $table = 'product_equipment_templates';

    protected $fillable = [
        'product_id',
        'name',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Product pemilik template.
     */
    public function product()
    {
        return $this->belongsTo(
            ProductProxy::modelClass(),
            'product_id'
        );
    }

    /**
     * Equipment dalam template.
     */
    public function items()
    {
        return $this->hasMany(
            ProductEquipmentTemplateItem::class,
            'template_id'
        )->orderBy('sort_order');
    }
}