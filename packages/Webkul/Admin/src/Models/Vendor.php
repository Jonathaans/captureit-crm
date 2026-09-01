<?php

namespace Webkul\Admin\Models;

use Illuminate\Database\Eloquent\Model;

class Vendor extends Model
{
    protected $table = 'vendors';

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
