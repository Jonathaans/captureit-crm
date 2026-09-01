<?php

namespace Webkul\Admin\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;
use Webkul\Admin\Models\GoogleCalendarEvent;
use Webkul\Admin\Services\GoogleCalendarService;

class SyncGoogleCalendarEventJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 5;

    public int $timeout = 90;

    public function __construct(
        public int $calendarEventId
    ) {
        $this->onQueue(
            'integrations'
        );
    }

    public function backoff(): array
    {
        return [
            60,
            300,
            900,
            3600,
        ];
    }

    public function handle(
        GoogleCalendarService $googleCalendar
    ): void {
        $event =
            GoogleCalendarEvent::query()
                ->find(
                    $this->calendarEventId
                );

        if (! $event) {
            return;
        }

        if (
            $event->sync_status === 'synced'
            && $event->google_event_id
        ) {
            return;
        }

        $event->update([
            'sync_status' => 'syncing',
            'sync_error' => null,
        ]);

        $googleCalendar->sync($event);
    }

    public function failed(
        ?Throwable $exception
    ): void {
        $event =
            GoogleCalendarEvent::query()
                ->find(
                    $this->calendarEventId
                );

        if (! $event) {
            return;
        }

        $event->update([
            'sync_status' => 'error',
            'sync_error' => $exception?->getMessage()
                ?: 'Queue job failed.',
        ]);
    }
}
