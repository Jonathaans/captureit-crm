<?php

namespace Webkul\Invoice\Services;

use Illuminate\Support\Facades\DB;
use LogicException;
use Webkul\Invoice\Models\DeliveryOrder;
use Webkul\Invoice\Models\Invoice;
use Webkul\Invoice\Models\WorkOrder;

class WorkOrderDeliveryOrderService
{
    public function __construct(
        protected DeliveryOrderService $deliveryOrderService
    ) {
    }

    /**
     * SPK -> many Surat Jalan.
     *
     * The existing DeliveryOrderService remains the source of truth for:
     * - SJ numbering
     * - first-SJ equipment template copy
     * - additional SJ being empty
     * - current inventory workflow
     *
     * V1 SPK only supplies the required work_order_id parent.
     */
    public function create(
        WorkOrder $workOrder,
        ?int $createdBy = null
    ): DeliveryOrder {
        return DB::transaction(
            function () use (
                $workOrder,
                $createdBy
            ) {
                $workOrder =
                    WorkOrder::query()
                        ->whereKey(
                            $workOrder->id
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                if (
                    strtolower(
                        (string) $workOrder->status
                    ) === 'cancelled'
                ) {
                    throw new LogicException(
                        'SPK cancelled tidak dapat membuat Surat Jalan.'
                    );
                }

                $invoice =
                    Invoice::query()
                        ->findOrFail(
                            $workOrder->invoice_id
                        );

                return $this->deliveryOrderService
                    ->createFromInvoice(
                        $invoice,
                        $createdBy,
                        $workOrder->id
                    );
            }
        );
    }
}
