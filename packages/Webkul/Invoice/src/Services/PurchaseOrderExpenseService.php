<?php

namespace Webkul\Invoice\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Webkul\Invoice\Models\Expense;
use Webkul\Invoice\Models\PurchaseOrder;

class PurchaseOrderExpenseService
{
        /**
     * PURCHASE ORDER PAID WORKFLOW V1
     *
     * PAID PO -> exactly one Expense on the related Invoice.
     */
    public function createForPaidPurchaseOrder(
        PurchaseOrder $purchaseOrder,
        ?int $userId,
        ?string $userName
    ): int {
        if (! $purchaseOrder->isPaid()) {
            throw new RuntimeException('Expense PO hanya dapat dibuat ketika status PAID.');
        }

        $expenseTable = (new Expense())->getTable();
        $columns = Schema::getColumnListing($expenseTable);

        foreach (['invoice_id', 'category', 'amount', 'expense_date'] as $requiredColumn) {
            if (! in_array($requiredColumn, $columns, true)) {
                throw new RuntimeException(
                    sprintf(
                        'Expense table %s tidak memiliki kolom wajib %s.',
                        $expenseTable,
                        $requiredColumn
                    )
                );
            }
        }

        /* PURCHASE ORDER PAID PDF RECEIPT V1: keep a host-independent route. */
        $receiptUrl = route(
            'admin.purchase-orders.payment-proof',
            $purchaseOrder->id,
            absolute: false
        );

        if (
            $purchaseOrder->expense_id
            && DB::table($expenseTable)
                ->where('id', $purchaseOrder->expense_id)
                ->exists()
        ) {
            $updates = [];

            if (in_array('receipt_path', $columns, true)) {
                $updates['receipt_path'] = $receiptUrl;
            }

            if (in_array('updated_at', $columns, true)) {
                $updates['updated_at'] = now();
            }

            if ($updates !== []) {
                DB::table($expenseTable)
                    ->where('id', $purchaseOrder->expense_id)
                    ->update($updates);
            }

            return (int) $purchaseOrder->expense_id;
        }

        $description = sprintf(
            '%s - Vendor %s - %s',
            $purchaseOrder->po_number,
            $purchaseOrder->vendor_name,
            $purchaseOrder->project_name ?: $purchaseOrder->invoice_number
        );

        $payload = [
            'invoice_id' => $purchaseOrder->invoice_id,
            'category' => $this->resolveCategory($expenseTable),
            'amount' => (float) $purchaseOrder->grand_total,
            'expense_date' => ($purchaseOrder->paid_at ?? now())->toDateString(),
        ];

        $optionalValues = [
            'description' => $description,
            'notes' => $description,
            'receipt_path' => $receiptUrl,
            'reference_type' => 'purchase_order',
            'reference_id' => $purchaseOrder->id,
            'reference_number' => $purchaseOrder->po_number,
            'purchase_order_id' => $purchaseOrder->id,
            'user_id' => $userId,
            'created_by' => $userId,
            'created_by_name' => $userName,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        foreach ($optionalValues as $column => $value) {
            if (in_array($column, $columns, true)) {
                $payload[$column] = $value;
            }
        }

        return (int) DB::table($expenseTable)->insertGetId($payload);
    }

    /**
     * Old release posting entry point is intentionally blocked.
     */
    public function createForReleasedPurchaseOrder(
        PurchaseOrder $purchaseOrder,
        ?int $userId,
        ?string $userName
    ): int {
        throw new RuntimeException(
            'Posting Expense saat RELEASED sudah dinonaktifkan. Gunakan workflow PAID.'
        );
    }

    public function removeForCancelledPurchaseOrder(PurchaseOrder $purchaseOrder): void
    {
        if (! $purchaseOrder->expense_id) {
            return;
        }

        $expenseTable = (new Expense())->getTable();

        DB::table($expenseTable)
            ->where('id', $purchaseOrder->expense_id)
            ->delete();
    }

    /**
     * Supports customized ENUM category columns.
     */
    private function resolveCategory(string $expenseTable): string
    {
        $column = DB::selectOne(
            sprintf(
                "SHOW COLUMNS FROM `%s` LIKE 'category'",
                str_replace('`', '``', $expenseTable)
            )
        );

        $type = strtolower((string) ($column->Type ?? ''));

        if (! str_starts_with($type, 'enum(')) {
            return 'vendor_outsource';
        }

        preg_match_all(
            "/'((?:[^'\\\\]|\\\\.)*)'/",
            $type,
            $matches
        );

        $allowed = collect($matches[1] ?? [])
            ->map(fn ($value) => stripslashes($value))
            ->values();

        foreach (
            [
                'vendor_outsource',
                'vendor',
                'outsourcing',
                'operational',
                'other',
                'others',
                'miscellaneous',
            ]
            as $preferred
        ) {
            if ($allowed->contains($preferred)) {
                return $preferred;
            }
        }

        return $allowed->isNotEmpty()
            ? (string) $allowed->first()
            : 'vendor_outsource';
    }
}
