<?php

namespace Webkul\Admin\Console\Commands;

use Illuminate\Console\Command;
use Webkul\Admin\Services\VendorSyncService;
use Webkul\Invoice\Models\PurchaseOrder;

class CrmVendorsBackfillCommand extends Command
{
    protected $signature =
        'crm:vendors-backfill';

    protected $description =
        'Build Vendor Master from existing Purchase Orders.';

    public function handle(
        VendorSyncService $sync
    ): int {
        $count = 0;

        PurchaseOrder::query()
            ->orderBy('id')
            ->chunkById(
                100,
                function ($orders) use ($sync, &$count) {
                    foreach ($orders as $po) {
                        $vendor =
                            $sync->findOrCreateFromPurchaseOrder(
                                $po
                            );

                        if (
                            $vendor
                            && (int) $po->vendor_id !== (int) $vendor->id
                        ) {
                            $po->vendor_id =
                                $vendor->id;

                            $po->saveQuietly();
                        }

                        $count++;
                    }
                }
            );

        $this->info(
            'Vendor backfill PASS: '
            .$count
            .' PO processed.'
        );

        return self::SUCCESS;
    }
}
