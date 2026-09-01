<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Master switch
    |--------------------------------------------------------------------------
    */
    'enabled' =>
        env(
            'GOOGLE_CALENDAR_ENABLED',
            false
        ),

    /*
    |--------------------------------------------------------------------------
    | Shared calendar ID
    |--------------------------------------------------------------------------
    |
    | Example:
    | abc123@group.calendar.google.com
    |
    */
    'calendar_id' =>
        env(
            'GOOGLE_CALENDAR_ID'
        ),

    /*
    |--------------------------------------------------------------------------
    | Service Account JSON
    |--------------------------------------------------------------------------
    |
    | Keep this file PRIVATE.
    | Default target:
    |
    | storage/app/private/google-calendar/service-account.json
    |
    */
    'credentials_path' =>
        env(
            'GOOGLE_CALENDAR_CREDENTIALS_PATH',
            storage_path(
                'app/private/google-calendar/service-account.json'
            )
        ),

    'timezone' =>
        env(
            'GOOGLE_CALENDAR_TIMEZONE',
            config(
                'app.timezone',
                'Asia/Jakarta'
            )
        ),

    /*
    |--------------------------------------------------------------------------
    | Cancelled event color
    |--------------------------------------------------------------------------
    |
    | Google Calendar event color ID 8 = Graphite.
    |
    */
    'cancelled_color_id' =>
        '8',
];
