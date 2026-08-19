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
             */
            $existingInvoice = Invoice::where('quote_id', $quote->id)->first();

            if ($existingInvoice) {
                return $existingInvoice;
            }

            /*
             * Make sure quotation items are loaded.
             */
            $quote->loadMissing('items');

            /*
             * Create invoice header.
             *
             * Temporary number is used first because
             * invoice number will use the invoice ID.
             */
            $invoice = Invoice::create([
                'invoice_number' => 'TMP-' . Str::uuid(),

                'quote_id'  => $quote->id,
                'person_id' => $quote->person_id,
                'user_id'   => $quote->user_id,

                'subject'     => $quote->subject,
                'description' => $quote->description,

                'billing_address'  => $quote->billing_address,
                'shipping_address' => $quote->shipping_address,

                'discount_percent' => $quote->discount_percent ?? 0,
                'discount_amount'  => $quote->discount_amount ?? 0,
                'tax_amount'       => $quote->tax_amount ?? 0,
                'adjustment_amount'=> $quote->adjustment_amount ?? 0,

                'sub_total'   => $quote->sub_total ?? 0,
                'grand_total' => $quote->grand_total ?? 0,

                'paid_amount' => 0,
                'balance_due' => $quote->grand_total ?? 0,

                'status' => 'unpaid',

                'issued_at' => now(),
                'due_at'    => now()->addDays(7),
            ]);

            /*
             * Generate final invoice number.
             *
             * Example:
             * INV-2026-00001
             */
            $invoice->invoice_number = sprintf(
                'INV-%s-%05d',
                now()->format('Y'),
                $invoice->id
            );

            $invoice->save();

            /*
             * Copy quotation items into invoice items.
             *
             * These are snapshots. If quotation changes
             * later, invoice items remain unchanged.
             */
            foreach ($quote->items as $item) {
                $invoice->items()->create([
                    'product_id' => $item->product_id,

                    'sku'  => $item->sku,
                    'name' => $item->name,

                    'quantity' => $item->quantity,
                    'price'    => $item->price,

                    'coupon_code'      => $item->coupon_code,
                    'discount_percent' => $item->discount_percent ?? 0,
                    'discount_amount'  => $item->discount_amount ?? 0,

                    'tax_percent' => $item->tax_percent ?? 0,
                    'tax_amount'  => $item->tax_amount ?? 0,

                    'total' => $item->total,
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