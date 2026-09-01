<?php

namespace Webkul\Admin\Console\Commands;

use Illuminate\Console\Command;
use Throwable;
use Webkul\Admin\Models\GoogleCalendarEvent;
use Webkul\Admin\Services\GoogleCalendarService;

class GoogleCalendarSyncCommand extends Command
{
    protected $signature =
        'crm:google-calendar-sync {--lead=}';

    protected $description =
        'Sync pending/error confirmed CRM events to Google Calendar.';

    public function handle(
        GoogleCalendarService $googleCalendar
    ): int {
        if (! $googleCalendar->enabled()) {
            $this->warn(
                'Google Calendar integration belum diaktifkan.'
            );

            return self::SUCCESS;
        }

        $query =
            GoogleCalendarEvent::query()
                ->whereNotNull(
                    'start_at'
                )
                ->whereNotNull(
                    'end_at'
                )
                ->whereIn(
                    'sync_status',
                    [
                        'pending',
                        'pending_config',
                        'error',
                    ]
                );

        if ($this->option('lead')) {
            $query->where(
                'lead_id',
                (int) $this->option(
                    'lead'
                )
            );
        }

        $events =
            $query->get();

        foreach ($events as $event) {
            try {
                $googleCalendar->sync(
                    $event
                );

                $this->info(
                    'PASS Lead #'
                    .$event->lead_id
                );
            } catch (Throwable $exception) {
                $event->update([
                    'sync_status' =>
                        'error',

                    'sync_error' =>
                        $exception->getMessage(),
                ]);

                $this->error(
                    'FAIL Lead #'
                    .$event->lead_id
                    .': '
                    .$exception->getMessage()
                );
            }
        }

        return self::SUCCESS;
    }
}
