<?php

namespace Webkul\Invoice\Services;

use Illuminate\Support\Facades\DB;

class PurchaseOrderNumberService
{
    /**
     * Generates:
     * PO 2608-0001
     * PO 2608-0002
     * PO 2609-0001
     *
     * Call from a DB transaction.
     */
    public function generate(): string
    {
        $period = now()->format('ym');

        DB::table('purchase_order_sequences')->insertOrIgnore([
            'period' => $period,
            'last_number' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $sequence = DB::table('purchase_order_sequences')
            ->where('period', $period)
            ->lockForUpdate()
            ->first();

        $nextNumber = ((int) ($sequence->last_number ?? 0)) + 1;

        DB::table('purchase_order_sequences')
            ->where('period', $period)
            ->update([
                'last_number' => $nextNumber,
                'updated_at' => now(),
            ]);

        return sprintf('PO %s-%04d', $period, $nextNumber);
    }
}
