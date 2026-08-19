<?php

namespace Webkul\Invoice\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Webkul\Invoice\Models\Invoice;
use Webkul\Invoice\Models\Payment;

class PaymentService
{
    /**
     * Add payment to an invoice and recalculate
     * paid amount, balance, and payment status.
     */
    public function addPayment(Invoice $invoice, array $data): Payment
    {
        return DB::transaction(function () use ($invoice, $data) {
            /**
             * Reload and lock invoice so two payments
             * cannot update the balance simultaneously.
             */
            $invoice = Invoice::query()
                ->lockForUpdate()
                ->findOrFail($invoice->id);

            $amount = (float) ($data['amount'] ?? 0);

            if ($amount <= 0) {
                throw new InvalidArgumentException(
                    'Payment amount must be greater than zero.'
                );
            }

            if ($amount > (float) $invoice->balance_due) {
                throw new InvalidArgumentException(
                    'Payment amount cannot exceed invoice balance.'
                );
            }

            /**
             * Create payment history.
             */
            $payment = Payment::create([
                'invoice_id'       => $invoice->id,
                'amount'           => $amount,
                'payment_method'   => $data['payment_method'] ?? null,
                'reference_number' => $data['reference_number'] ?? null,
                'notes'            => $data['notes'] ?? null,
                'paid_at'          => $data['paid_at'] ?? now(),
                'created_by'       => $data['created_by'] ?? null,
            ]);

            /**
             * Calculate total payments from history.
             */
            $paidAmount = (float) Payment::where(
                'invoice_id',
                $invoice->id
            )->sum('amount');

            $grandTotal = (float) $invoice->grand_total;

            $balanceDue = max(
                0,
                $grandTotal - $paidAmount
            );

            /**
             * Automatic payment status.
             */
            if ($paidAmount <= 0) {
                $status = 'unpaid';
            } elseif ($paidAmount < $grandTotal) {
                $status = 'partial';
            } else {
                $status = 'paid';
            }

            $invoice->update([
                'paid_amount' => $paidAmount,
                'balance_due' => $balanceDue,
                'status'      => $status,
            ]);

            return $payment->fresh([
                'invoice',
                'creator',
            ]);
        });
    }
}