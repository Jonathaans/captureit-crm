<?php

namespace Webkul\Invoice\Services;

use Illuminate\Support\Facades\DB;
use Webkul\Invoice\Models\DeliveryOrder;
use Webkul\Invoice\Models\Invoice;

class DeliveryOrderService
{
    public function __construct(
        protected DeliveryOrderNumberService $numberService
    ) {
    }

    /**
     * Membuat Surat Jalan pertama dari Invoice.
     *
     * Untuk sementara:
     * 1 Invoice = 1 Surat Jalan melalui tombol Generate.
     *
     * Database tetap mendukung banyak Surat Jalan per Invoice
     * untuk pengembangan berikutnya.
     */
    public function createFromInvoice(
        Invoice $invoice,
        ?int $createdBy = null
    ): DeliveryOrder {
        /*
         * Cegah double generate karena tombol diklik dua kali.
         */
        $existingDeliveryOrder = DeliveryOrder::query()
            ->where('invoice_id', $invoice->id)
            ->orderBy('id')
            ->first();

        if ($existingDeliveryOrder) {
            return $existingDeliveryOrder;
        }

        return DB::transaction(
            function () use ($invoice, $createdBy) {
                /*
                 * Cek sekali lagi di dalam transaction.
                 */
                $existingDeliveryOrder = DeliveryOrder::query()
                    ->where('invoice_id', $invoice->id)
                    ->orderBy('id')
                    ->first();

                if ($existingDeliveryOrder) {
                    return $existingDeliveryOrder;
                }

                /*
                 * Pastikan relasi tersedia.
                 */
                $invoice->loadMissing([
                    'quote',
                    'person',
                    'user',
                ]);

                $customerName =
                    $invoice->person?->name;

                $salesPersonName =
                    $invoice->user?->name;

                $deliveryAddress =
                    $this->formatAddress(
                        $invoice->shipping_address
                        ?: $invoice->billing_address
                    );

                return DeliveryOrder::create([
                    /*
                     * Document Identity
                     */
                    'delivery_order_number' =>
                        $this->numberService
                            ->generateForInvoice($invoice),

                    /*
                     * References
                     */
                    'invoice_id' =>
                        $invoice->id,

                    'quote_id' =>
                        $invoice->quote_id,

                    'invoice_number' =>
                        $invoice->invoice_number,

                    'quote_number' =>
                        $invoice->quote?->quote_number,

                    /*
                     * Project
                     */
                    'project_code' =>
                        $invoice->project_code,

                    /*
                     * Saat ini project name memakai subject Invoice.
                     */
                    'project_name' =>
                        $invoice->subject,

                    /*
                     * Customer
                     */
                    'person_id' =>
                        $invoice->person_id,

                    'customer_name' =>
                        $customerName,

                    /*
                     * Sales
                     */
                    'user_id' =>
                        $invoice->user_id,

                    'sales_person_name' =>
                        $salesPersonName,

                    /*
                     * Recipient default dari customer.
                     *
                     * Nanti masih bisa diedit dari halaman
                     * Delivery Order.
                     */
                    'recipient_name' =>
                        $customerName,

                    'recipient_phone' =>
                        null,

                    /*
                     * PIC sengaja kosong dulu.
                     */
                    'pic_name' =>
                        null,

                    'pic_phone' =>
                        null,

                    /*
                     * Event
                     */
                    'event_date' =>
                        $invoice->event_date,

                    'event_time' =>
                        null,

                    'location' =>
                        $invoice->location,

                    /*
                     * Delivery
                     */
                    'delivery_address' =>
                        $deliveryAddress,

                    'delivery_date' =>
                        $invoice->event_date,

                    'delivery_time' =>
                        null,

                    /*
                     * Status awal
                     */
                    'status' =>
                        'draft',

                    /*
                     * Notes
                     */
                    'notes' =>
                        null,

                    /*
                     * Audit
                     */
                    'created_by' =>
                        $createdBy,
                ]);
            }
        );
    }

    /**
     * Ubah address Invoice menjadi text untuk Surat Jalan.
     */
    protected function formatAddress(
        mixed $address
    ): ?string {
        if (empty($address)) {
            return null;
        }

        if (is_string($address)) {
            $address = trim($address);

            return $address !== ''
                ? $address
                : null;
        }

        if (! is_array($address)) {
            return null;
        }

        /*
         * Beberapa kemungkinan key address dari CRM.
         */
        $preferredKeys = [
            'address',
            'street',
            'city',
            'state',
            'postcode',
            'postal_code',
            'zip',
            'country',
        ];

        $parts = [];

        foreach ($preferredKeys as $key) {
            if (
                isset($address[$key])
                && is_scalar($address[$key])
                && trim((string) $address[$key]) !== ''
            ) {
                $parts[] = trim(
                    (string) $address[$key]
                );
            }
        }

        /*
         * Kalau struktur address berbeda,
         * ambil scalar values sebagai fallback.
         */
        if (empty($parts)) {
            foreach ($address as $value) {
                if (
                    is_scalar($value)
                    && trim((string) $value) !== ''
                ) {
                    $parts[] = trim(
                        (string) $value
                    );
                }
            }
        }

        $parts = array_values(
            array_unique($parts)
        );

        return empty($parts)
            ? null
            : implode(', ', $parts);
    }
}