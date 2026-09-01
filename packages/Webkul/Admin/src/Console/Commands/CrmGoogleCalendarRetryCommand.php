<?php

namespace Webkul\Admin\Console\Commands;

use Illuminate\Console\Command;
use Webkul\Admin\Jobs\SyncGoogleCalendarEventJob;
use Webkul\Admin\Models\GoogleCalendarEvent;

class CrmGoogleCalendarRetryCommand extends Command
{
    protected $signature =
        'crm:google-calendar-retry {--lead=}';

    protected $description =
        'Queue pending/error Google Calendar events with automatic retries.';

    public function handle(): int
    {
        $query =
            GoogleCalendarEvent::query()
                ->whereNotNull('start_at')
                ->whereNotNull('end_at')
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
                (int) $this->option('lead')
            );
        }

        $events = $query->get();

        foreach ($events as $event) {
            $event->update([
                'sync_status' => 'pending',
                'sync_error' => null,
            ]);

            SyncGoogleCalendarEventJob::dispatch(
                (int) $event->id
            );
        }

        $this->info(
            $events->count()
            .' Google Calendar event(s) queued.'
        );

        return self::SUCCESS;
    }
}
