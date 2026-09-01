<?php

namespace Webkul\Admin\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Throwable;
use Webkul\Admin\Services\CalendarSalesOwnerService;
use Webkul\Admin\Services\GoogleCalendarService;
use Webkul\Admin\Services\SalesCalendarColorService;

class GoogleCalendarCheckCommand extends Command
{
    protected $signature =
        'crm:google-calendar-check';

    protected $description =
        'Check CRM Google Calendar integration and Sales color assignments.';

    public function handle(
        GoogleCalendarService $googleCalendar,
        CalendarSalesOwnerService $owners,
        SalesCalendarColorService $colors
    ): int {
        $this->info(
            'CRM GOOGLE CALENDAR CHECK'
        );

        $this->line(
            str_repeat(
                '=',
                25
            )
        );

        if (
            ! Schema::hasTable(
                'google_calendar_events'
            )
        ) {
            $this->error(
                'google_calendar_events table belum ada.'
            );

            return self::FAILURE;
        }

        if (
            ! Schema::hasColumn(
                'users',
                'google_calendar_color_id'
            )
        ) {
            $this->error(
                'users.google_calendar_color_id belum ada.'
            );

            return self::FAILURE;
        }

        $this->newLine();
        $this->info(
            'Eligible Sales Owners'
        );

        foreach (
            $owners->options()
            as $owner
        ) {
            $color =
                $colors->assignIfEligible(
                    (int) $owner->id
                );

            $this->line(
                sprintf(
                    '#%d %s [%s] colorId=%s',
                    $owner->id,
                    $owner->name,
                    $owner->role?->name
                        ?? '-',
                    $color
                        ?: '-'
                )
            );
        }

        $this->newLine();

        if (! $googleCalendar->enabled()) {
            $this->warn(
                'GOOGLE_CALENDAR_ENABLED=false. CRM foundation siap, tetapi API sync belum aktif.'
            );

            return self::SUCCESS;
        }

        try {
            $calendar =
                $googleCalendar
                    ->checkConnection();

            $this->info(
                'Google Calendar connection: PASS'
            );

            $this->line(
                'Calendar: '
                .(
                    $calendar['summary']
                    ?? $calendar['id']
                    ?? '-'
                )
            );

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error(
                'Google Calendar connection: FAIL'
            );

            $this->line(
                $exception->getMessage()
            );

            return self::FAILURE;
        }
    }
}
