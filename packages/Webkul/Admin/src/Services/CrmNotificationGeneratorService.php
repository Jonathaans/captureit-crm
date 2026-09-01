<?php

namespace Webkul\Admin\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Webkul\Admin\Models\CrmNotification;

class CrmNotificationGeneratorService
{
    public function generate(): array
    {
        $counts = [
            'calendar' => 0,
            'invoice' => 0,
            'po' => 0,
        ];

        $counts['calendar'] =
            $this->calendarNotifications();

        $counts['invoice'] =
            $this->invoiceNotifications();

        $counts['po'] =
            $this->purchaseOrderNotifications();

        return $counts;
    }

    private function calendarNotifications(): int
    {
        if (
            ! Schema::hasTable(
                'google_calendar_events'
            )
        ) {
            return 0;
        }

        $count = 0;

        $events =
            DB::table('google_calendar_events')
                ->whereNotNull('start_at')
                ->where(
                    'start_at',
                    '>=',
                    now()
                )
                ->where(
                    'start_at',
                    '<=',
                    now()->addDays(7)
                )
                ->get();

        foreach ($events as $event) {
            $start =
                \Carbon\Carbon::parse(
                    $event->start_at
                );

            $days =
                now()->startOfDay()
                    ->diffInDays(
                        $start->copy()->startOfDay(),
                        false
                    );

            if (
                ! in_array(
                    $days,
                    [
                        7,
                        3,
                        1,
                        0,
                    ],
                    true
                )
            ) {
                continue;
            }

            $label =
                $days === 0
                    ? 'TODAY'
                    : 'H-'.$days;

            $this->upsert([
                'user_id' =>
                    $event->sales_owner_id
                    ?: null,

                'type' =>
                    'event_reminder',

                'severity' =>
                    $days <= 1
                        ? 'warning'
                        : 'info',

                'title' =>
                    $label
                    .' Event: '
                    .$event->title,

                'message' =>
                    trim(
                        ($event->location ?: '')
                        .' · '
                        .$start->format(
                            'd M Y H:i'
                        ),
                        ' ·'
                    ),

                'action_url' =>
                    route(
                        'admin.google-calendar.leads.edit',
                        $event->lead_id
                    ),

                'source_type' =>
                    'google_calendar_event',

                'source_id' =>
                    (string) $event->id,

                'dedupe_key' =>
                    'event-reminder:'
                    .$event->id
                    .':'
                    .$start->format('Y-m-d')
                    .':'
                    .$label,

                'due_at' =>
                    $start,
            ]);

            $count++;
        }

        $errors =
            DB::table('google_calendar_events')
                ->where(
                    'sync_status',
                    'error'
                )
                ->get();

        foreach ($errors as $event) {
            $this->upsert([
                'user_id' =>
                    $event->sales_owner_id
                    ?: null,

                'type' =>
                    'google_sync_error',

                'severity' =>
                    'error',

                'title' =>
                    'Google Calendar Sync Failed',

                'message' =>
                    $event->title
                    .' · '
                    .(
                        $event->sync_error
                        ?: 'Unknown error'
                    ),

                'action_url' =>
                    route(
                        'admin.google-calendar.leads.edit',
                        $event->lead_id
                    ),

                'source_type' =>
                    'google_calendar_event',

                'source_id' =>
                    (string) $event->id,

                'dedupe_key' =>
                    'google-sync-error:'
                    .$event->id,

                'due_at' =>
                    now(),
            ]);

            $count++;
        }

        return $count;
    }

    private function invoiceNotifications(): int
    {
        if (
            ! Schema::hasTable(
                'invoices'
            )
            || ! Schema::hasColumn(
                'invoices',
                'due_at'
            )
            || ! Schema::hasColumn(
                'invoices',
                'balance_due'
            )
        ) {
            return 0;
        }

        $count = 0;

        $invoices =
            DB::table('invoices')
                ->whereNotNull('due_at')
                ->where(
                    'balance_due',
                    '>',
                    0
                )
                ->where(
                    'due_at',
                    '<=',
                    now()->addDays(3)
                )
                ->get();

        foreach ($invoices as $invoice) {
            $due =
                \Carbon\Carbon::parse(
                    $invoice->due_at
                );

            $overdue =
                $due->isPast();

            $invoiceNumber =
                $invoice->invoice_number
                ?? '#'.$invoice->id;

            $this->upsert([
                'user_id' =>
                    $invoice->user_id
                    ?? null,

                'type' =>
                    'invoice_due',

                'severity' =>
                    $overdue
                        ? 'error'
                        : 'warning',

                'title' =>
                    (
                        $overdue
                            ? 'OVERDUE: '
                            : 'Invoice Due Soon: '
                    )
                    .$invoiceNumber,

                'message' =>
                    'Balance Rp '
                    .number_format(
                        (float) $invoice->balance_due,
                        0,
                        ',',
                        '.'
                    )
                    .' · Due '
                    .$due->format(
                        'd M Y'
                    ),

                'action_url' =>
                    RouteSafe::invoiceUrl(
                        (int) $invoice->id
                    ),

                'source_type' =>
                    'invoice',

                'source_id' =>
                    (string) $invoice->id,

                'dedupe_key' =>
                    'invoice-due:'
                    .$invoice->id
                    .':'
                    .$due->format('Y-m-d'),

                'due_at' =>
                    $due,
            ]);

            $count++;
        }

        return $count;
    }

    private function purchaseOrderNotifications(): int
    {
        if (
            ! Schema::hasTable(
                'purchase_orders'
            )
        ) {
            return 0;
        }

        $count = 0;

        $orders =
            DB::table('purchase_orders')
                ->where(
                    'status',
                    'draft'
                )
                ->where(
                    'created_at',
                    '<=',
                    now()->subDays(2)
                )
                ->get();

        foreach ($orders as $po) {
            $this->upsert([
                'user_id' =>
                    $po->created_by
                    ?? null,

                'type' =>
                    'po_draft',

                'severity' =>
                    'warning',

                'title' =>
                    'PO masih DRAFT: '
                    .$po->po_number,

                'message' =>
                    (
                        $po->vendor_name
                        ?: 'Vendor'
                    )
                    .' · Rp '
                    .number_format(
                        (float) $po->grand_total,
                        0,
                        ',',
                        '.'
                    ),

                'action_url' =>
                    route(
                        'admin.purchase-orders.show',
                        $po->id
                    ),

                'source_type' =>
                    'purchase_order',

                'source_id' =>
                    (string) $po->id,

                'dedupe_key' =>
                    'po-draft:'
                    .$po->id,

                'due_at' =>
                    now(),
            ]);

            $count++;
        }

        return $count;
    }

    private function upsert(
        array $payload
    ): void {
        CrmNotification::query()
            ->updateOrCreate(
                [
                    'dedupe_key' =>
                        $payload['dedupe_key'],
                ],
                array_merge(
                    $payload,
                    [
                        'resolved_at' =>
                            null,
                    ]
                )
            );
    }
}

/**
 * Keep notification generation tolerant of route differences.
 */
class RouteSafe
{
    public static function invoiceUrl(
        int $id
    ): ?string {
        foreach (
            [
                'admin.invoices.view',
                'admin.invoices.show',
            ]
            as $routeName
        ) {
            if (
                \Illuminate\Support\Facades\Route::has(
                    $routeName
                )
            ) {
                try {
                    return route(
                        $routeName,
                        $id
                    );
                } catch (\Throwable) {
                    // Try next.
                }
            }
        }

        return null;
    }
}
