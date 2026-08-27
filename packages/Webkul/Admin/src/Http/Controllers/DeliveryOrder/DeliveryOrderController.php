<?php

namespace Webkul\Admin\Http\Controllers\DeliveryOrder;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Webkul\Admin\DataGrids\DeliveryOrder\DeliveryOrderDataGrid;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Invoice\Models\DeliveryOrder;
use Webkul\Invoice\Models\DeliveryOrderItem;

class DeliveryOrderController extends Controller
{
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
        $deliveryOrder = DeliveryOrder::with([
            'invoice',
            'quote',
            'person',
            'user',
            'creator',
            'items',
        ])->findOrFail($id);

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
        $deliveryOrder = DeliveryOrder::with([
            'invoice',
            'quote',
            'person',
            'user',
            'creator',
            'items',
        ])->findOrFail($id);

        return view(
            'admin::delivery-orders.edit',
            compact('deliveryOrder')
        );
    }

    /**
     * Update Delivery Order.
     */
    public function update(
        Request $request,
        int $id
    ): RedirectResponse {
        $deliveryOrder = DeliveryOrder::findOrFail($id);

        /*
        |--------------------------------------------------------------------------
        | Validation
        |--------------------------------------------------------------------------
        */

        $validated = $request->validate([
            'recipient_name'  => ['nullable', 'string', 'max:255'],
            'recipient_phone' => ['nullable', 'string', 'max:50'],

            'pic_name'  => ['nullable', 'string', 'max:255'],
            'pic_phone' => ['nullable', 'string', 'max:50'],

            'event_date' => ['nullable', 'date'],
            'event_time' => ['nullable', 'date_format:H:i'],

            'location' => ['nullable', 'string', 'max:255'],

            'delivery_address' => ['nullable', 'string'],

            'delivery_date' => ['nullable', 'date'],
            'delivery_time' => ['nullable', 'date_format:H:i'],

            'notes' => ['nullable', 'string'],

            /*
            |--------------------------------------------------------------------------
            | Equipment Items
            |--------------------------------------------------------------------------
            */

            'items' => ['nullable', 'array'],

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
                /*
                |--------------------------------------------------------------------------
                | Update Header
                |--------------------------------------------------------------------------
                */

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
                | Rebuild Equipment
                |--------------------------------------------------------------------------
                |
                | Untuk versi awal ini:
                | hapus equipment lama lalu recreate dari form.
                |
                | Ini sederhana, aman, dan cukup untuk Delivery Order.
                |
                */

                $deliveryOrder
                    ->items()
                    ->delete();

                $items = $validated['items'] ?? [];

                $sortOrder = 0;

                foreach ($items as $item) {
                    /*
                     * Abaikan row kosong.
                     */
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
}