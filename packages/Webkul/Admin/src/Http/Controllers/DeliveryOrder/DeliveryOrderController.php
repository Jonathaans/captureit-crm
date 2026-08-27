<?php

namespace Webkul\Admin\Http\Controllers\DeliveryOrder;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Webkul\Admin\DataGrids\DeliveryOrder\DeliveryOrderDataGrid;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Core\Traits\PDFHandler;
use Webkul\Invoice\Models\DeliveryOrder;
use Webkul\Invoice\Models\DeliveryOrderItem;

class DeliveryOrderController extends Controller
{
    use PDFHandler;

    /**
     * Delivery Order listing.
     */
    public function index()
    {
        if (
            request()->ajax()
            || request()->expectsJson()
        ) {
            return app(
                DeliveryOrderDataGrid::class
            )->toJson();
        }

        return view(
            'admin::delivery-orders.index'
        );
    }

    /**
     * Delivery Order detail.
     */
    public function show(int $id): View
    {
        $deliveryOrder = $this->findDeliveryOrder($id);

        return view(
            'admin::delivery-orders.show',
            compact('deliveryOrder')
        );
    }

    /**
     * Edit Delivery Order.
     */
    public function edit(int $id): View
    {
        $deliveryOrder = $this->findDeliveryOrder($id);

        return view(
            'admin::delivery-orders.edit',
            compact('deliveryOrder')
        );
    }

    /**
     * Download / Print Surat Jalan as A4 PDF.
     */
    public function print(
        int $id
    ): Response|StreamedResponse {
        $deliveryOrder = $this->findDeliveryOrder($id);

        $fileName = 'Surat_Jalan_'
            .str_replace(
                ['/', '\\', ' '],
                ['-', '-', '_'],
                $deliveryOrder->delivery_order_number
            );

        return $this->downloadPDF(
            view(
                'admin::delivery-orders.print',
                compact('deliveryOrder')
            )->render(),
            $fileName
        );
    }

    /**
     * Update Delivery Order status.
     *
     * Flow:
     * draft -> issued -> delivered -> returned
     *
     * Cancel:
     * draft / issued -> cancelled
     */
    public function updateStatus(
        Request $request,
        int $id
    ): RedirectResponse {
        $validated = $request->validate([
            'status' => [
                'required',
                Rule::in([
                    'issued',
                    'delivered',
                    'returned',
                    'cancelled',
                ]),
            ],
        ]);

        $deliveryOrder = DeliveryOrder::findOrFail($id);

        $currentStatus = strtolower(
            $deliveryOrder->status ?: 'draft'
        );

        $nextStatus = strtolower(
            $validated['status']
        );

        /*
        |--------------------------------------------------------------------------
        | Allowed Status Transitions
        |--------------------------------------------------------------------------
        */

        $allowedTransitions = [
            'draft' => [
                'issued',
                'cancelled',
            ],

            'issued' => [
                'delivered',
                'cancelled',
            ],

            'delivered' => [
                'returned',
            ],

            'returned' => [],

            'cancelled' => [],
        ];

        if (
            ! in_array(
                $nextStatus,
                $allowedTransitions[$currentStatus] ?? [],
                true
            )
        ) {
            return redirect()
                ->route(
                    'admin.delivery-orders.show',
                    $deliveryOrder->id
                )
                ->with(
                    'error',
                    "Status tidak dapat diubah dari {$currentStatus} ke {$nextStatus}."
                );
        }

        DB::transaction(
            function () use (
                $deliveryOrder,
                $nextStatus
            ) {
                $data = [
                    'status' => $nextStatus,
                ];

                /*
                |--------------------------------------------------------------------------
                | Automatic Timestamps
                |--------------------------------------------------------------------------
                */

                if (
                    $nextStatus === 'issued'
                    && ! $deliveryOrder->issued_at
                ) {
                    $data['issued_at'] = now();
                }

                if (
                    $nextStatus === 'delivered'
                    && ! $deliveryOrder->delivered_at
                ) {
                    $data['delivered_at'] = now();
                }

                if (
                    $nextStatus === 'returned'
                    && ! $deliveryOrder->returned_at
                ) {
                    $data['returned_at'] = now();
                }

                $deliveryOrder->update($data);
            }
        );

        session()->flash(
            'success',
            match ($nextStatus) {
                'issued' =>
                    'Surat Jalan berhasil di-issue.',

                'delivered' =>
                    'Surat Jalan ditandai sebagai delivered.',

                'returned' =>
                    'Barang ditandai sudah returned.',

                'cancelled' =>
                    'Surat Jalan dibatalkan.',

                default =>
                    'Status Surat Jalan berhasil diperbarui.',
            }
        );

        return redirect()->route(
            'admin.delivery-orders.show',
            $deliveryOrder->id
        );
    }

    /**
     * Update Delivery Order data.
     */
    public function update(
        Request $request,
        int $id
    ): RedirectResponse {
        $deliveryOrder = DeliveryOrder::findOrFail($id);

        $validated = $request->validate([
            'recipient_name' => [
                'nullable',
                'string',
                'max:255',
            ],

            'recipient_phone' => [
                'nullable',
                'string',
                'max:50',
            ],

            'pic_name' => [
                'nullable',
                'string',
                'max:255',
            ],

            'pic_phone' => [
                'nullable',
                'string',
                'max:50',
            ],

            'event_date' => [
                'nullable',
                'date',
            ],

            'event_time' => [
                'nullable',
                'date_format:H:i',
            ],

            'location' => [
                'nullable',
                'string',
                'max:255',
            ],

            'delivery_address' => [
                'nullable',
                'string',
            ],

            'delivery_date' => [
                'nullable',
                'date',
            ],

            'delivery_time' => [
                'nullable',
                'date_format:H:i',
            ],

            'notes' => [
                'nullable',
                'string',
            ],

            'items' => [
                'nullable',
                'array',
            ],

            'items.*.name' => [
                'nullable',
                'string',
                'max:255',
            ],

            'items.*.description' => [
                'nullable',
                'string',
            ],

            'items.*.quantity' => [
                'nullable',
                'numeric',
                'min:0.01',
            ],

            'items.*.unit' => [
                'nullable',
                'string',
                'max:30',
            ],

            'items.*.notes' => [
                'nullable',
                'string',
            ],
        ]);

        DB::transaction(
            function () use (
                $deliveryOrder,
                $validated
            ) {
                $deliveryOrder->update([
                    'recipient_name' =>
                        $validated['recipient_name'] ?? null,

                    'recipient_phone' =>
                        $validated['recipient_phone'] ?? null,

                    'pic_name' =>
                        $validated['pic_name'] ?? null,

                    'pic_phone' =>
                        $validated['pic_phone'] ?? null,

                    'event_date' =>
                        $validated['event_date'] ?? null,

                    'event_time' =>
                        $validated['event_time'] ?? null,

                    'location' =>
                        $validated['location'] ?? null,

                    'delivery_address' =>
                        $validated['delivery_address'] ?? null,

                    'delivery_date' =>
                        $validated['delivery_date'] ?? null,

                    'delivery_time' =>
                        $validated['delivery_time'] ?? null,

                    'notes' =>
                        $validated['notes'] ?? null,
                ]);

                /*
                |--------------------------------------------------------------------------
                | Rebuild Equipment Items
                |--------------------------------------------------------------------------
                */

                $deliveryOrder
                    ->items()
                    ->delete();

                $items = $validated['items'] ?? [];

                $sortOrder = 0;

                foreach ($items as $item) {
                    $name = trim(
                        (string) ($item['name'] ?? '')
                    );

                    if ($name === '') {
                        continue;
                    }

                    DeliveryOrderItem::create([
                        'delivery_order_id' =>
                            $deliveryOrder->id,

                        'name' =>
                            $name,

                        'description' =>
                            $item['description'] ?? null,

                        'quantity' =>
                            $item['quantity'] ?? 1,

                        'unit' =>
                            ! empty($item['unit'])
                                ? $item['unit']
                                : 'unit',

                        'notes' =>
                            $item['notes'] ?? null,

                        'sort_order' =>
                            $sortOrder++,
                    ]);
                }
            }
        );

        session()->flash(
            'success',
            'Surat Jalan berhasil diperbarui.'
        );

        return redirect()->route(
            'admin.delivery-orders.show',
            $deliveryOrder->id
        );
    }

    /**
     * Shared Delivery Order loader.
     */
    private function findDeliveryOrder(
        int $id
    ): DeliveryOrder {
        return DeliveryOrder::with([
            'invoice',
            'quote',
            'person',
            'user',
            'creator',
            'items',
        ])->findOrFail($id);
    }
}
