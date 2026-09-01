<?php

namespace Webkul\Admin\Models;

use Illuminate\Database\Eloquent\Model;

class CrmSystemIncident extends Model
{
    protected $table = 'crm_system_incidents';

    protected $guarded = [];

    protected $casts = [
        'context' => 'array',
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];
}
