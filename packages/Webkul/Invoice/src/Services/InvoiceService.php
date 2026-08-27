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

            /*
             * Pastikan quotation items sudah diload.
             */
            $quote->loadMissing('items');

            /*
             * Create invoice header.
             *
             * Gunakan temporary invoice number terlebih dahulu.
             * Nomor final akan dibuat setelah Invoice mendapatkan ID.
             */
            $invoice = Invoice::create([
                'invoice_number' => 'TMP-'.Str::uuid(),

                /*
                 * Project Code diwariskan langsung dari Quote.
                 *
                 * Contoh:
                 * Quote   = PRJ-2026-00001
                 * Invoice = PRJ-2026-00001
                 */
                'project_code' => $quote->project_code,
                'event_date'   => $quote->event_date,
                'location'     => $quote->location,
                'payment_term' => $quote->payment_term,

                'quote_id'  => $quote->id,
                'person_id' => $quote->person_id,
                'user_id'   => $quote->user_id,

                'subject'     => $quote->subject,
                'description' => $quote->description,

                'billing_address'  => $quote->billing_address,
                'shipping_address' => $quote->shipping_address,

                'discount_percent'  => $quote->discount_percent ?? 0,
                'discount_amount'   => $quote->discount_amount ?? 0,
                'tax_amount'        => $quote->tax_amount ?? 0,
                'adjustment_amount' => $quote->adjustment_amount ?? 0,

                'sub_total'   => $quote->sub_total ?? 0,
                'grand_total' => $quote->grand_total ?? 0,

                'paid_amount' => 0,
                'balance_due' => $quote->grand_total ?? 0,

                /*
                 * Payment Status.
                 */
                'status' => 'unpaid',

                /*
                 * Event Status.
                 *
                 * Invoice dibuat dari Quote yang sudah disetujui,
                 * sehingga default = confirm.
                 */
                'event_status' => 'confirm',

                'issued_at' => now(),
                'due_at'    => now()->addDays(7),
            ]);

            /*
             * =====================================================
             * GENERATE FINAL INVOICE NUMBER
             * =====================================================
             *
             * Format:
             *
             * INV 2608-0001
             *
             * 26   = Tahun
             * 08   = Bulan
             * 0001 = Sequence invoice pada bulan tersebut
             *
             * Bulan berikutnya sequence dimulai kembali dari 0001.
             *
             * Contoh:
             *
             * Agustus 2026:
             * INV 2608-0001
             * INV 2608-0002
             * INV 2608-0003
             *
             * September 2026:
             * INV 2609-0001
             */

            $yearMonth = now()->format('ym');

            $prefix = 'INV '.$yearMonth.'-';

            /*
             * Cari invoice terakhir pada bulan yang sama.
             *
             * Karena sequence selalu 4 digit,
             * sorting invoice_number tetap aman.
             */
            $lastInvoice = Invoice::query()
                ->where(
                    'invoice_number',
                    'like',
                    $prefix.'%'
                )
                ->orderByDesc('invoice_number')
                ->first();

            /*
             * Default invoice pertama pada bulan tersebut.
             */
            $nextNumber = 1;

            if ($lastInvoice?->invoice_number) {
                /*
                 * Ambil 4 digit terakhir.
                 *
                 * Contoh:
                 *
                 * INV 2608-0025
                 *
                 * menjadi:
                 *
                 * 0025
                 */
                $lastSequence = (int) substr(
                    $lastInvoice->invoice_number,
                    -4
                );

                $nextNumber = $lastSequence + 1;
            }

            /*
             * Generate nomor final.
             */
            $invoice->invoice_number =
                $prefix.str_pad(
                    $nextNumber,
                    4,
                    '0',
                    STR_PAD_LEFT
                );

            $invoice->save();

/*
 * =====================================================
 * COPY QUOTE ITEMS → INVOICE ITEMS
 * =====================================================
 *
 * Invoice items adalah snapshot.
 *
 * Jika Quote diubah setelah Invoice dibuat,
 * Invoice Item tidak ikut berubah.
 */
foreach ($quote->items as $item) {

    $invoice->items()->create([

        'product_id' => $item->product_id,

        'sku'  => $item->sku,

        /*
         * Package
         */
        'name' => $item->name,


        /*
         * Tambahan dari Quote Item
         */
        'description' => $item->description ?? null,

        'day' => $item->day ?? 1,


        /*
         * Pricing
         */
        'quantity' => $item->quantity,

        'price' => $item->price,


        /*
         * Discount
         */
        'coupon_code' => $item->coupon_code,

        'discount_percent' => $item->discount_percent ?? 0,

        'discount_amount' => $item->discount_amount ?? 0,


        /*
         * Tax
         */
        'tax_percent' => $item->tax_percent ?? 0,

        'tax_amount' => $item->tax_amount ?? 0,


        /*
         * Final total
         */
        'total' => $item->total,

    ]);
}

            /*
             * Return invoice lengkap.
             */
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