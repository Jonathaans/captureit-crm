<?php

namespace Webkul\Admin\Models;

use Illuminate\Database\Eloquent\Model;

class WorkflowNotification extends Model
{
    protected $table =
        'crm_workflow_notifications';

    protected $guarded = [];

    protected $casts = [
        'meta' => 'array',
        'read_at' => 'datetime',
        'popup_at' => 'datetime',
    ];
}
