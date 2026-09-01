<?php

namespace Webkul\Admin\Models;

use Illuminate\Database\Eloquent\Model;

class CrmNotification extends Model
{
    protected $table = 'crm_notifications';

    protected $guarded = [];

    protected $casts = [
        'due_at' => 'datetime',
        'read_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];
}
