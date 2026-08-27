<?php

namespace Webkul\Invoice\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Webkul\Invoice\Models\Invoice;
use Webkul\Quote\Models\Quote;

class InvoiceService
{
    /**
     * Create an invoice from an existing quotation.
     */
    public function createFromQuote(Quote $quote): Invoice
    {
        return DB::transaction(function () use ($quote) {
            /*
             * Prevent duplicate invoice.
             *
             * Satu Quote hanya boleh memiliki satu Invoice.
             */
            $existingInvoice = Invoice::query()
                ->where('quote_id', $quote->id)
                ->first();

            if ($existingInvoice) {
                return $existingInvoice;
            }

            $quote->loadMissing('items');

            $invoice = Invoice::create([
                'invoice_number' => 'TMP-'.Str::uuid(),

                /*
                 * Project identity diwariskan langsung dari Quote.
                 */
                'project_code' => $quote->project_code,
                'business_unit' => $quote->business_unit,

                'event_date' => $quote->event_date,
                'location' => $quote->location,
                'payment_term' => $quote->payment_term,

                'quote_id' => $quote->id,
                'person_id' => $quote->person_id,
                'user_id' => $quote->user_id,

                'subject' => $quote->subject,
                'description' => $quote->description,

                'billing_address' => $quote->billing_address,
                'shipping_address' => $quote->shipping_address,

                'discount_percent' => $quote->discount_percent ?? 0,
                'discount_amount' => $quote->discount_amount ?? 0,
                'tax_amount' => $quote->tax_amount ?? 0,
                'adjustment_amount' => $quote->adjustment_amount ?? 0,

                'sub_total' => $quote->sub_total ?? 0,
                'grand_total' => $quote->grand_total ?? 0,

                'paid_amount' => 0,
                'balance_due' => $quote->grand_total ?? 0,

                'status' => 'unpaid',

                /*
                 * Invoice dibuat dari Quote yang disetujui.
                 */
                'event_status' => 'confirm',

                'issued_at' => now(),
                'due_at' => now()->addDays(7),
            ]);

            /*
            |--------------------------------------------------------------------------
            | Generate Final Invoice Number
            |--------------------------------------------------------------------------
            */

            $yearMonth = now()->format('ym');

            $prefix = 'INV '.$yearMonth.'-';

            $lastInvoice = Invoice::query()
                ->where(
                    'invoice_number',
                    'like',
                    $prefix.'%'
                )
                ->orderByDesc('invoice_number')
                ->first();

            $nextNumber = 1;

            if ($lastInvoice?->invoice_number) {
                $lastSequence = (int) substr(
                    $lastInvoice->invoice_number,
                    -4
                );

                $nextNumber =
                    $lastSequence + 1;
            }

            $invoice->invoice_number =
                $prefix.str_pad(
                    $nextNumber,
                    4,
                    '0',
                    STR_PAD_LEFT
                );

            $invoice->save();

            /*
            |--------------------------------------------------------------------------
            | Copy Quote Items -> Invoice Items
            |--------------------------------------------------------------------------
            */

            foreach ($quote->items as $item) {
                $invoice->items()->create([
                    'product_id' =>
                        $item->product_id,

                    'sku' =>
                        $item->sku,

                    'name' =>
                        $item->name,

                    'description' =>
                        $item->description ?? null,

                    'day' =>
                        $item->day ?? 1,

                    'quantity' =>
                        $item->quantity,

                    'price' =>
                        $item->price,

                    'coupon_code' =>
                        $item->coupon_code,

                    'discount_percent' =>
                        $item->discount_percent ?? 0,

                    'discount_amount' =>
                        $item->discount_amount ?? 0,

                    'tax_percent' =>
                        $item->tax_percent ?? 0,

                    'tax_amount' =>
                        $item->tax_amount ?? 0,

                    'total' =>
                        $item->total,
                ]);
            }

            return $invoice->fresh([
                'items',
                'payments',
                'quote',
                'person',
                'user',
            ]);
        });
    }
}
