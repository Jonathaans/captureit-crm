<?php

namespace Webkul\Invoice\Services;

use Webkul\Invoice\Models\DeliveryOrder;
use Webkul\Invoice\Models\Invoice;

class DeliveryOrderNumberService
{
    /**
     * Generate nomor Surat Jalan berdasarkan Invoice.
     *
     * Contoh:
     *
     * Invoice:
     * INV 2608-0006
     *
     * Surat Jalan pertama:
     * SJ 2608-0006
     *
     * Jika suatu Invoice memiliki Surat Jalan kedua:
     * SJ 2608-0006-02
     */
    public function generateForInvoice(Invoice $invoice): string
    {
        $invoiceNumber = trim((string) $invoice->invoice_number);

        /*
         * Coba mengambil YYMM dan sequence
         * dari nomor invoice.
         *
         * INV 2608-0006
         */
        if (
            preg_match(
                '/^INV\s+(\d{4})-(\d{4})$/i',
                $invoiceNumber,
                $matches
            )
        ) {
            $yearMonth = $matches[1];
            $sequence = $matches[2];

            $baseNumber = "SJ {$yearMonth}-{$sequence}";

            /*
             * Surat Jalan pertama untuk Invoice.
             */
            if (! $this->exists($baseNumber)) {
                return $baseNumber;
            }

            /*
             * Kalau sudah ada, buat:
             *
             * SJ 2608-0006-02
             * SJ 2608-0006-03
             * dst.
             */
            $deliverySequence = 2;

            do {
                $candidate = $baseNumber.'-'.str_pad(
                    $deliverySequence,
                    2,
                    '0',
                    STR_PAD_LEFT
                );

                $deliverySequence++;
            } while ($this->exists($candidate));

            return $candidate;
        }

        /*
         * Fallback apabila invoice_number tidak
         * mengikuti format INV YYMM-XXXX.
         */
        return $this->generateFallback();
    }

    /**
     * Cek apakah nomor Surat Jalan sudah dipakai.
     */
    protected function exists(string $number): bool
    {
        return DeliveryOrder::query()
            ->where('delivery_order_number', $number)
            ->exists();
    }

    /**
     * Generator fallback.
     *
     * Format:
     * SJ YYMM-XXXX
     */
    protected function generateFallback(): string
    {
        $yearMonth = now()->format('ym');

        $prefix = 'SJ '.$yearMonth.'-';

        $numbers = DeliveryOrder::query()
            ->where(
                'delivery_order_number',
                'like',
                $prefix.'%'
            )
            ->pluck('delivery_order_number');

        $highestSequence = 0;

        foreach ($numbers as $number) {
            if (
                preg_match(
                    '/^'.preg_quote($prefix, '/').'(\d{4})$/',
                    $number,
                    $matches
                )
            ) {
                $sequence = (int) $matches[1];

                if ($sequence > $highestSequence) {
                    $highestSequence = $sequence;
                }
            }
        }

        $nextSequence = $highestSequence + 1;

        return $prefix.str_pad(
            $nextSequence,
            4,
            '0',
            STR_PAD_LEFT
        );
    }
}