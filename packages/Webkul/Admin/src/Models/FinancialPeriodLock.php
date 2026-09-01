<?php

namespace Webkul\Admin\Models;

use Illuminate\Database\Eloquent\Model;

class FinancialPeriodLock extends Model
{
    protected $table =
        'financial_period_locks';

    protected $guarded = [];

    protected $casts = [
        'starts_at' => 'date',
        'ends_at' => 'date',
        'locked_at' => 'datetime',
    ];
}
