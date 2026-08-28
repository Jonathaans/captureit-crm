<?php

namespace Webkul\Invoice\Services;

use Illuminate\Support\Facades\DB;
use Webkul\Invoice\Models\DeliveryOrder;
use Webkul\Invoice\Models\DeliveryOrderItem;
use Webkul\Invoice\Models\Invoice;
use Webkul\Product\Models\ProductEquipmentTemplate;

class DeliveryOrderService
{
    public function __construct(
        protected DeliveryOrderNumberService $numberService
    ) {
    }

    /**
     * Membuat Surat Jalan pertama dari Invoice.
     *
     * Untuk flow saat ini, tombol Generate hanya membuat satu Surat Jalan
     * pertama per Invoice. Database tetap mendukung lebih dari satu Surat Jalan
     * untuk pengembangan berikutnya.
     */
    public function createFromInvoice(
        Invoice $invoice,
        ?int $createdBy = null
    ): DeliveryOrder {
        /*
        |--------------------------------------------------------------------------
        | Cegah double generate
        |--------------------------------------------------------------------------
        */

        $existingDeliveryOrder = DeliveryOrder::query()
            ->where('invoice_id', $invoice->id)
            ->orderBy('id')
            ->first();

        if ($existingDeliveryOrder) {
            return $existingDeliveryOrder;
        }

        return DB::transaction(function () use ($invoice, $createdBy) {
            /*
            |--------------------------------------------------------------------------
            | Cek ulang di dalam transaction
            |--------------------------------------------------------------------------
            */

            $existingDeliveryOrder = DeliveryOrder::query()
                ->where('invoice_id', $invoice->id)
                ->orderBy('id')
                ->first();

            if ($existingDeliveryOrder) {
                return $existingDeliveryOrder;
            }

            /*
            |--------------------------------------------------------------------------
            | Load data yang dibutuhkan
            |--------------------------------------------------------------------------
            */

            $invoice->loadMissing([
                'quote',
                'person',
                'user',
                'items',
            ]);

            $customerName = $invoice->person?->name;
            $salesPersonName = $invoice->user?->name;

            $deliveryAddress = $this->formatAddress(
                $invoice->shipping_address ?: $invoice->billing_address
            );

            /*
            |--------------------------------------------------------------------------
            | Create Delivery Order Header
            |--------------------------------------------------------------------------
            */

            $deliveryOrder = DeliveryOrder::create([
                'delivery_order_number' => $this->numberService
                    ->generateForInvoice($invoice),

                'invoice_id' => $invoice->id,
                'quote_id' => $invoice->quote_id,

                'invoice_number' => $invoice->invoice_number,
                'quote_number' => $invoice->quote?->quote_number,

                'project_code' => $invoice->project_code,
                'business_unit' => $invoice->business_unit,
                'project_name' => $invoice->subject,

                'person_id' => $invoice->person_id,
                'customer_name' => $customerName,

                'user_id' => $invoice->user_id,
                'sales_person_name' => $salesPersonName,

                /*
                 * Recipient default dari customer.
                 * Tetap bisa diedit dari halaman Surat Jalan.
                 */
                'recipient_name' => $customerName,
                'recipient_phone' => null,

                'pic_name' => null,
                'pic_phone' => null,

                'event_date' => $invoice->event_date,
                'event_time' => null,
                'location' => $invoice->location,

                'delivery_address' => $deliveryAddress,
                'delivery_date' => $invoice->event_date,
                'delivery_time' => null,

                'status' => 'draft',
                'notes' => null,

                'created_by' => $createdBy,
            ]);

            /*
            |--------------------------------------------------------------------------
            | Auto Populate Equipment dari Product Template
            |--------------------------------------------------------------------------
            */

            $this->copyEquipmentFromInvoice(
                $invoice,
                $deliveryOrder
            );

            return $deliveryOrder;
        });
    }

    /**
     * Copy Equipment Template dari Product yang terdapat pada Invoice
     * ke delivery_order_items.
     *
     * Template hanya dicopy saat Surat Jalan dibuat. Setelah itu,
     * perubahan pada Product Equipment Template tidak mengubah Surat Jalan lama.
     */
    protected function copyEquipmentFromInvoice(
        Invoice $invoice,
        DeliveryOrder $deliveryOrder
    ): void {
        /*
        |--------------------------------------------------------------------------
        | Ambil Invoice Item yang mempunyai Product
        |--------------------------------------------------------------------------
        */

        $invoiceItems = $invoice->items
            ->filter(function ($invoiceItem) {
                return ! empty($invoiceItem->product_id);
            });

        if ($invoiceItems->isEmpty()) {
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Ambil semua Product ID pada Invoice
        |--------------------------------------------------------------------------
        */

        $productIds = $invoiceItems
            ->pluck('product_id')
            ->filter()
            ->unique()
            ->values();

        if ($productIds->isEmpty()) {
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Ambil Equipment Template yang aktif
        |--------------------------------------------------------------------------
        */

        $templates = ProductEquipmentTemplate::query()
            ->with('items')
            ->whereIn('product_id', $productIds)
            ->where('is_active', true)
            ->get()
            ->keyBy('product_id');

        if ($templates->isEmpty()) {
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Temporary Equipment List
        |--------------------------------------------------------------------------
        |
        | Equipment dengan Name + Description + Unit + Notes yang sama
        | akan digabung quantity-nya.
        |
        | Contoh:
        | Classic Photobooth -> Printer 1
        | Additional Printer -> Printer 1
        |
        | Hasil Surat Jalan -> Printer 2
        |
        */

        $equipment = [];

        foreach ($invoiceItems as $invoiceItem) {
            $template = $templates->get($invoiceItem->product_id);

            /*
             * Product tidak mempunyai template aktif.
             */
            if (! $template) {
                continue;
            }

            /*
            |--------------------------------------------------------------------------
            | Quantity Product
            |--------------------------------------------------------------------------
            |
            | Jika Classic Photobooth Qty 2 dan template Lighting Qty 2,
            | maka hasil Surat Jalan Lighting Qty 4.
            |
            */

            $productQuantity = is_numeric($invoiceItem->quantity)
                ? (float) $invoiceItem->quantity
                : 1.0;

            if ($productQuantity <= 0) {
                $productQuantity = 1.0;
            }

            foreach ($template->items as $templateItem) {
                $name = trim((string) $templateItem->name);

                if ($name === '') {
                    continue;
                }

                $description = trim(
                    (string) ($templateItem->description ?? '')
                );

                $unit = trim(
                    (string) ($templateItem->unit ?: 'unit')
                );

                $notes = trim(
                    (string) ($templateItem->notes ?? '')
                );

                $templateQuantity = is_numeric($templateItem->quantity)
                    ? (float) $templateItem->quantity
                    : 1.0;

                if ($templateQuantity <= 0) {
                    $templateQuantity = 1.0;
                }

                $quantity = $templateQuantity * $productQuantity;

                /*
                 * Inventory master yang dipetakan dari Equipment Template.
                 *
                 * Null berarti requirement masih berupa text dan belum
                 * terhubung ke Inventory Master.
                 */
                $inventoryItemId = $templateItem->inventory_item_id
                    ? (int) $templateItem->inventory_item_id
                    : null;

                /*
                |--------------------------------------------------------------------------
                | Merge Key
                |--------------------------------------------------------------------------
                |
                | inventory_item_id ikut menjadi bagian merge key.
                | Dua baris text yang sama tetapi menunjuk master inventory
                | berbeda tidak boleh digabung menjadi satu requirement.
                |
                */

                $mergeKey = mb_strtolower(
                    $name
                    .'|'
                    .$description
                    .'|'
                    .$unit
                    .'|'
                    .$notes
                    .'|inventory:'
                    .($inventoryItemId ?? 'none')
                );

                if (isset($equipment[$mergeKey])) {
                    $equipment[$mergeKey]['quantity'] += $quantity;

                    /*
                     * Jika item hasil merge berasal dari product yang berbeda,
                     * kosongkan referensi product agar tidak misleading.
                     */
                    if (
                        $equipment[$mergeKey]['product_id']
                        !== (int) $invoiceItem->product_id
                    ) {
                        $equipment[$mergeKey]['product_id'] = null;
                        $equipment[$mergeKey]['sku'] = null;
                    }

                    continue;
                }

                $sku = trim(
                    (string) ($invoiceItem->sku ?? '')
                );

                $equipment[$mergeKey] = [
                    'product_id' => (int) $invoiceItem->product_id,
                    'inventory_item_id' => $inventoryItemId,
                    'sku' => $sku !== '' ? $sku : null,

                    'name' => $name,

                    'description' => $description !== ''
                        ? $description
                        : null,

                    'quantity' => $quantity,

                    'unit' => $unit !== ''
                        ? $unit
                        : 'unit',

                    'notes' => $notes !== ''
                        ? $notes
                        : null,
                ];
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Simpan ke delivery_order_items
        |--------------------------------------------------------------------------
        */

        $sortOrder = 0;

        foreach ($equipment as $item) {
            DeliveryOrderItem::create([
                'delivery_order_id' => $deliveryOrder->id,

                'product_id' => $item['product_id'],
                'inventory_item_id' => $item['inventory_item_id'],
                'sku' => $item['sku'],

                'name' => $item['name'],
                'description' => $item['description'],

                'quantity' => $item['quantity'],
                'unit' => $item['unit'],

                'notes' => $item['notes'],

                'sort_order' => $sortOrder++,
            ]);
        }
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
                $parts[] = trim((string) $address[$key]);
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
                    $parts[] = trim((string) $value);
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
