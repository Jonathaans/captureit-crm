<?php

namespace Webkul\Admin\Models;

use Illuminate\Database\Eloquent\Model;

class CrmAuditLog extends Model
{
    public $timestamps = false;

    protected $table = 'crm_audit_logs';

    protected $guarded = [];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'created_at' => 'datetime',
    ];
}
