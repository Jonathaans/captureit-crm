<?php

namespace Webkul\Admin\Models;

use Illuminate\Database\Eloquent\Model;

class GoogleCalendarEvent extends Model
{
    protected $table =
        'google_calendar_events';

    protected $fillable = [
        'lead_id',
        'activity_id',
        'sales_owner_id',
        'title',
        'location',
        'notes',
        'start_at',
        'end_at',
        'event_status',
        'google_calendar_id',
        'google_event_id',
        'sync_status',
        'sync_error',
        'activity_sync_error',
        'synced_at',
    ];

    protected $casts = [
        'start_at' =>
            'datetime',

        'end_at' =>
            'datetime',

        'synced_at' =>
            'datetime',
    ];
}
