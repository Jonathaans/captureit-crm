<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

require __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';

$app->make(
    Kernel::class
)->bootstrap();

echo "CRM GOOGLE CALENDAR SCHEMA CHECK\n";
echo "================================\n\n";

$errors = [];

if (
    ! Schema::hasTable(
        'google_calendar_events'
    )
) {
    $errors[] =
        'google_calendar_events belum ada.';
}

if (
    ! Schema::hasColumn(
        'users',
        'google_calendar_color_id'
    )
) {
    $errors[] =
        'users.google_calendar_color_id belum ada.';
}

foreach (
    [
        'admin.google-calendar.leads.edit',
        'admin.google-calendar.leads.update',
        'admin.google-calendar.leads.sync',
    ]
    as $route
) {
    if (! Route::has($route)) {
        $errors[] =
            'Route belum ada: '
            .$route;
    }
}

echo "Activity Bridge\n";
echo "---------------\n";

if (! Schema::hasTable('activities')) {
    echo "WARN: table activities tidak ditemukan.\n";
} else {
    $columns =
        Schema::getColumnListing(
            'activities'
        );

    echo "activities columns:\n";
    echo implode(
        ', ',
        $columns
    );
    echo "\n\n";

    $database =
        DB::getDatabaseName();

    $pivots =
        DB::table(
            'information_schema.COLUMNS'
        )
            ->select(
                'TABLE_NAME'
            )
            ->where(
                'TABLE_SCHEMA',
                $database
            )
            ->whereIn(
                'COLUMN_NAME',
                [
                    'activity_id',
                    'lead_id',
                ]
            )
            ->groupBy(
                'TABLE_NAME'
            )
            ->havingRaw(
                'COUNT(DISTINCT COLUMN_NAME) = 2'
            )
            ->pluck(
                'TABLE_NAME'
            );

    if ($pivots->isEmpty()) {
        echo "Lead-Activity pivot: not detected\n";
    } else {
        echo "Lead-Activity pivot candidate: "
            .$pivots->implode(', ')
            ."\n";
    }
}

echo "\n";

if ($errors) {
    echo "FAIL\n";

    foreach ($errors as $error) {
        echo " - {$error}\n";
    }

    exit(1);
}

echo "PASS\n";
echo "Database + routes Google Calendar ready.\n";
