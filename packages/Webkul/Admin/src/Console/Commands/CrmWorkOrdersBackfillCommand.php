<?php

namespace Webkul\Admin\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;
use Webkul\Invoice\Models\Invoice;
use Webkul\Invoice\Services\WorkOrderService;

class CrmWorkOrdersBackfillCommand extends Command
{
    protected $signature =
        'crm:work-orders-backfill';

    protected $description =
        'Create SPK for historical invoices that already have Surat Jalan and link those SJ to the SPK.';

    public function handle(
        WorkOrderService $service
    ): int {
        $invoiceIds =
            DB::table(
                'delivery_orders'
            )
                ->whereNotNull(
                    'invoice_id'
                )
                ->distinct()
                ->orderBy(
                    'invoice_id'
                )
                ->pluck(
                    'invoice_id'
                );

        if ($invoiceIds->isEmpty()) {
            $this->info(
                'Tidak ada historical Surat Jalan yang perlu di-backfill.'
            );

            return self::SUCCESS;
        }

        $failed = 0;

        foreach ($invoiceIds as $invoiceId) {
            try {
                $invoice =
                    Invoice::query()
                        ->find(
                            (int) $invoiceId
                        );

                if (! $invoice) {
                    $this->warn(
                        'Invoice #'
                        .$invoiceId
                        .' tidak ditemukan, skip.'
                    );

                    continue;
                }

                $workOrder =
                    $service->createFromInvoice(
                        $invoice,
                        null
                    );

                $linked =
                    DB::table(
                        'delivery_orders'
                    )
                        ->where(
                            'invoice_id',
                            $invoice->id
                        )
                        ->whereNull(
                            'work_order_id'
                        )
                        ->update([
                            'work_order_id' =>
                                $workOrder->id,
                        ]);

                $this->info(
                    $invoice->invoice_number
                    .' -> '
                    .$workOrder->work_order_number
                    .' | linked SJ: '
                    .$linked
                );
            } catch (Throwable $exception) {
                $failed++;

                $this->error(
                    'Invoice #'
                    .$invoiceId
                    .' FAIL: '
                    .$exception->getMessage()
                );
            }
        }

        return $failed > 0
            ? self::FAILURE
            : self::SUCCESS;
    }
}
