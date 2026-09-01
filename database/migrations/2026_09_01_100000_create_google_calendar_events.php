<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            Schema::hasTable('users')
            && ! Schema::hasColumn(
                'users',
                'google_calendar_color_id'
            )
        ) {
            Schema::table(
                'users',
                function (Blueprint $table) {
                    $table->string(
                        'google_calendar_color_id',
                        2
                    )
                        ->nullable();
                }
            );
        }

        if (! Schema::hasTable('google_calendar_events')) {
            Schema::create(
                'google_calendar_events',
                function (Blueprint $table) {
                    $table->increments('id');

                    $table->unsignedInteger(
                        'lead_id'
                    )->unique();

                    $table->unsignedInteger(
                        'activity_id'
                    )->nullable();

                    $table->unsignedInteger(
                        'sales_owner_id'
                    )->nullable();

                    $table->string(
                        'title',
                        255
                    );

                    $table->string(
                        'location',
                        500
                    )->nullable();

                    $table->text(
                        'notes'
                    )->nullable();

                    $table->dateTime(
                        'start_at'
                    )->nullable();

                    $table->dateTime(
                        'end_at'
                    )->nullable();

                    $table->string(
                        'event_status',
                        30
                    )->default(
                        'needs_schedule'
                    );

                    $table->string(
                        'google_calendar_id',
                        255
                    )->nullable();

                    $table->string(
                        'google_event_id',
                        255
                    )->nullable();

                    $table->string(
                        'sync_status',
                        30
                    )->default(
                        'needs_schedule'
                    );

                    $table->text(
                        'sync_error'
                    )->nullable();

                    $table->text(
                        'activity_sync_error'
                    )->nullable();

                    $table->timestamp(
                        'synced_at'
                    )->nullable();

                    $table->timestamps();

                    $table->index(
                        'sales_owner_id'
                    );

                    $table->index(
                        'sync_status'
                    );
                }
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'google_calendar_events'
        );

        if (
            Schema::hasTable('users')
            && Schema::hasColumn(
                'users',
                'google_calendar_color_id'
            )
        ) {
            Schema::table(
                'users',
                function (Blueprint $table) {
                    $table->dropColumn(
                        'google_calendar_color_id'
                    );
                }
            );
        }
    }
};
