<?php

namespace Webkul\Admin\Http\Controllers\GoogleCalendar;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Admin\Models\GoogleCalendarEvent;
use Webkul\Admin\Services\CalendarActivityBridgeService;
use Webkul\Admin\Services\CalendarSalesOwnerService;
use Webkul\Admin\Services\GoogleCalendarService;
use Webkul\Admin\Services\LeadWonCalendarService;
use Webkul\Admin\Services\SalesCalendarColorService;
use Webkul\Lead\Models\Lead;

class LeadCalendarController extends Controller
{
    public function edit(
        int $leadId,
        LeadWonCalendarService $leadService,
        CalendarSalesOwnerService $ownerService
    ): View {
        $lead =
            Lead::query()
                ->findOrFail(
                    $leadId
                );

        abort_unless(
            $leadService->isWon(
                $lead
            ),
            422,
            'Lead harus berstatus WON sebelum dibuat menjadi Confirmed Event.'
        );

        $event =
            $leadService->ensureDraft(
                $lead
            );

        return view(
            'admin::google-calendar.leads.edit',
            [
                'lead' =>
                    $lead,

                'event' =>
                    $event,

                'salesOwners' =>
                    $ownerService->options(),

                'googleEnabled' =>
                    (bool) config(
                        'google-calendar.enabled'
                    ),
            ]
        );
    }

    public function update(
        Request $request,
        int $leadId,
        LeadWonCalendarService $leadService,
        CalendarSalesOwnerService $ownerService,
        SalesCalendarColorService $colorService,
        CalendarActivityBridgeService $activityBridge,
        GoogleCalendarService $googleCalendar
    ): RedirectResponse {
        $lead =
            Lead::query()
                ->findOrFail(
                    $leadId
                );

        abort_unless(
            $leadService->isWon(
                $lead
            ),
            422,
            'Lead harus berstatus WON.'
        );

        $validated =
            $request->validate([
                'title' => [
                    'required',
                    'string',
                    'max:255',
                ],

                'sales_owner_id' => [
                    'required',
                    'integer',
                ],

                'location' => [
                    'nullable',
                    'string',
                    'max:500',
                ],

                'notes' => [
                    'nullable',
                    'string',
                ],

                'start_at' => [
                    'required',
                    'date',
                ],

                'end_at' => [
                    'required',
                    'date',
                    'after:start_at',
                ],
            ]);

        $salesOwnerId =
            (int) $validated[
                'sales_owner_id'
            ];

        abort_unless(
            $ownerService->isEligible(
                $salesOwnerId
            ),
            422,
            'Sales Owner harus Administrator, Sales Admin, atau Sales User.'
        );

        $colorService->assignIfEligible(
            $salesOwnerId
        );

        $event =
            GoogleCalendarEvent::query()
                ->updateOrCreate(
                    [
                        'lead_id' =>
                            $leadId,
                    ],
                    [
                        'sales_owner_id' =>
                            $salesOwnerId,

                        'title' =>
                            $validated['title'],

                        'location' =>
                            $validated['location']
                            ?? null,

                        'notes' =>
                            $validated['notes']
                            ?? null,

                        'start_at' =>
                            $validated['start_at'],

                        'end_at' =>
                            $validated['end_at'],

                        'event_status' =>
                            'confirmed',

                        'sync_status' =>
                            config(
                                'google-calendar.enabled'
                            )
                                ? 'pending'
                                : 'pending_config',
                    ]
                );

        /*
         * Activity is best-effort and does not block Google Calendar.
         */
        $activityBridge->sync(
            $event
        );

        if (! $googleCalendar->enabled()) {
            session()->flash(
                'warning',
                'Event tersimpan. Google Calendar belum diaktifkan, jadi event menunggu konfigurasi.'
            );

            return redirect()->route(
                'admin.google-calendar.leads.edit',
                $leadId
            );
        }

        try {
            $googleCalendar->sync(
                $event
            );

            session()->flash(
                'success',
                'Confirmed Event tersimpan dan Google Calendar berhasil disinkronkan.'
            );
        } catch (Throwable $exception) {
            $event->update([
                'sync_status' =>
                    'error',

                'sync_error' =>
                    $exception->getMessage(),
            ]);

            session()->flash(
                'warning',
                'Event tersimpan, tetapi Google Calendar sync gagal: '
                .$exception->getMessage()
            );
        }

        return redirect()->route(
            'admin.google-calendar.leads.edit',
            $leadId
        );
    }

    public function sync(
        int $leadId,
        GoogleCalendarService $googleCalendar
    ): RedirectResponse {
        $event =
            GoogleCalendarEvent::query()
                ->where(
                    'lead_id',
                    $leadId
                )
                ->firstOrFail();

        try {
            $googleCalendar->sync(
                $event
            );

            session()->flash(
                'success',
                'Google Calendar berhasil disinkronkan.'
            );
        } catch (Throwable $exception) {
            $event->update([
                'sync_status' =>
                    'error',

                'sync_error' =>
                    $exception->getMessage(),
            ]);

            session()->flash(
                'warning',
                'Google Calendar sync gagal: '
                .$exception->getMessage()
            );
        }

        return redirect()->route(
            'admin.google-calendar.leads.edit',
            $leadId
        );
    }
}
