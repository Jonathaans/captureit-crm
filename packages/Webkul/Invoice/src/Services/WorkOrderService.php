<?php

namespace Webkul\Invoice\Services;

use Illuminate\Support\Facades\DB;
use Webkul\Invoice\Models\Invoice;
use Webkul\Invoice\Models\WorkOrder;

class WorkOrderService
{
    public function __construct(
        protected WorkOrderNumberService $numberService
    ) {
    }

    /**
     * One Invoice -> One SPK.
     *
     * Idempotent:
     * if SPK already exists, the existing SPK is returned.
     */
    public function createFromInvoice(
        Invoice $invoice,
        ?int $createdBy = null
    ): WorkOrder {
        return DB::transaction(
            function () use (
                $invoice,
                $createdBy
            ) {
                $invoice =
                    Invoice::query()
                        ->whereKey(
                            $invoice->id
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                $existing =
                    WorkOrder::query()
                        ->where(
                            'invoice_id',
                            $invoice->id
                        )
                        ->first();

                if ($existing) {
                    return $existing;
                }

                $invoice->loadMissing([
                    'quote',
                    'person',
                    'user',
                    'items',
                ]);

                $workOrder =
                    WorkOrder::query()
                        ->create([
                            'work_order_number' =>
                                $this->numberService
                                    ->generate(
                                        $invoice->event_date
                                        ?: now()
                                    ),

                            'invoice_id' =>
                                $invoice->id,

                            'invoice_number' =>
                                $invoice->invoice_number,

                            'quote_id' =>
                                $invoice->quote_id,

                            'quote_number' =>
                                $invoice->quote?->quote_number,

                            'project_code' =>
                                $invoice->project_code,

                            'business_unit' =>
                                $invoice->business_unit,

                            'project_name' =>
                                $invoice->subject,

                            'person_id' =>
                                $invoice->person_id,

                            'customer_name' =>
                                $invoice->person?->name,

                            'user_id' =>
                                $invoice->user_id,

                            'sales_person_name' =>
                                $invoice->user?->name,

                            'event_date' =>
                                $invoice->event_date,

                            'location' =>
                                $invoice->location,

                            'notes' =>
                                null,

                            'status' =>
                                'draft',

                            /*
                             * Sales signature name is prefilled from Invoice.
                             * Admin Sales + Operational remain editable.
                             */
                            'admin_sales_name' =>
                                null,

                            'sales_name' =>
                                $invoice->user?->name,

                            'operational_name' =>
                                null,

                            'created_by' =>
                                $createdBy,
                        ]);

                $sortOrder = 0;

                foreach ($invoice->items as $invoiceItem) {
                    $name =
                        trim(
                            (string) (
                                $invoiceItem->name
                                ?? ''
                            )
                        );

                    if ($name === '') {
                        continue;
                    }

                    $workOrder->items()
                        ->create([
                            'product_id' =>
                                $invoiceItem->product_id,

                            'name' =>
                                $name,

                            'notes' =>
                                null,

                            'sort_order' =>
                                $sortOrder++,
                        ]);
                }

                return $workOrder->fresh([
                    'items',
                ]);
            }
        );
    }
}
