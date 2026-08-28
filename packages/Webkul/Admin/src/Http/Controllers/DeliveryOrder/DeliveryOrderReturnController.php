<?php

namespace Webkul\Admin\Http\Controllers\DeliveryOrder;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Invoice\Models\DeliveryOrder;
use Webkul\Invoice\Models\DeliveryOrderInventoryAllocation;
use Webkul\Invoice\Services\DeliveryOrderReturnService;
use Webkul\Warehouse\Models\InventoryAsset;

class DeliveryOrderReturnController extends Controller
{
    public function __construct(
        protected DeliveryOrderReturnService $returnService
    ) {
    }

    public function show(int $id): View
    {
        $deliveryOrder = $this->findDeliveryOrder($id);

        $allocations = DeliveryOrderInventoryAllocation::query()
            ->with([
                'deliveryOrderItem',
                'inventoryItem',
                'inventoryAsset',
                'returnPendingBy',
                'checkedInBy',
            ])
            ->where('delivery_order_id', $deliveryOrder->id)
            ->whereIn('status', [
                'out',
                'return_pending',
                'returned',
            ])
            ->orderBy('delivery_order_item_id')
            ->orderBy('id')
            ->get();

        $summary = [
            'out' => $allocations
                ->where('status', 'out')
                ->count(),

            'return_pending' => $allocations
                ->where('status', 'return_pending')
                ->count(),

            'returned' => $allocations
                ->where('status', 'returned')
                ->count(),
        ];

        $canOperate = strtolower(
            $deliveryOrder->status ?: 'draft'
        ) === 'delivered';

        $allReturned = $this->returnService
            ->allReturned($deliveryOrder);

        return view(
            'admin::delivery-orders.return',
            compact(
                'deliveryOrder',
                'allocations',
                'summary',
                'canOperate',
                'allReturned'
            )
        );
    }

    public function start(int $id): RedirectResponse
    {
        $deliveryOrder = $this->findDeliveryOrder($id);

        $this->returnService->startReturn(
            $deliveryOrder,
            auth()->guard('user')->id()
        );

        session()->flash(
            'success',
            'Proses return dimulai. Inventory sekarang berstatus RETURN PENDING.'
        );

        return redirect()->route(
            'admin.delivery-orders.return.show',
            $deliveryOrder->id
        );
    }

    /**
     * Scanner Check-In for serialized assets.
     *
     * Default condition from UI is GOOD. For damaged/fair, choose the
     * condition before scanning. Missing assets still use the row form
     * because naturally there is nothing physical to scan.
     */
    public function scanCheckIn(
        Request $request,
        int $id
    ): RedirectResponse {
        $deliveryOrder = $this->findDeliveryOrder($id);

        $validated = $request->validate([
            'barcode' => [
                'required',
                'string',
                'max:100',
            ],
            'condition' => [
                'required',
                Rule::in([
                    'good',
                    'fair',
                    'damaged',
                ]),
            ],
            'notes' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ]);

        $barcode = trim($validated['barcode']);

        $asset = InventoryAsset::query()
            ->where(function ($query) use ($barcode) {
                $query
                    ->where('barcode_value', $barcode)
                    ->orWhere('asset_code', $barcode);
            })
            ->first();

        if (! $asset) {
            throw ValidationException::withMessages([
                'barcode' => 'Barcode / Asset Code tidak ditemukan: '.$barcode,
            ]);
        }

        $allocation = DeliveryOrderInventoryAllocation::query()
            ->where('delivery_order_id', $deliveryOrder->id)
            ->where('tracking_type', 'serialized')
            ->where('inventory_asset_id', $asset->id)
            ->whereIn('status', [
                'return_pending',
                'returned',
            ])
            ->orderByDesc('id')
            ->first();

        if (! $allocation) {
            throw ValidationException::withMessages([
                'barcode' => sprintf(
                    'Asset %s tidak berada pada RETURN PENDING untuk %s.',
                    $asset->asset_code,
                    $deliveryOrder->delivery_order_number
                ),
            ]);
        }

        if ($allocation->status === 'returned') {
            session()->flash(
                'success',
                'Asset '.$asset->asset_code.' sudah selesai Check-In.'
            );

            return redirect()->route(
                'admin.delivery-orders.return.show',
                $deliveryOrder->id
            );
        }

        $this->returnService->checkInSerialized(
            $deliveryOrder,
            $allocation,
            $validated['condition'],
            $validated['notes'] ?? null,
            auth()->guard('user')->id()
        );

        session()->flash(
            'success',
            'SCAN CHECK-IN: '.$asset->asset_code
            .' / '.strtoupper($validated['condition'])
        );

        return redirect()->route(
            'admin.delivery-orders.return.show',
            $deliveryOrder->id
        );
    }


    public function checkIn(
        Request $request,
        int $id,
        int $allocationId
    ): RedirectResponse {
        $deliveryOrder = $this->findDeliveryOrder($id);

        $allocation = DeliveryOrderInventoryAllocation::query()
            ->where('delivery_order_id', $deliveryOrder->id)
            ->findOrFail($allocationId);

        if ($allocation->tracking_type === 'serialized') {
            $validated = $request->validate([
                'condition' => [
                    'required',
                    Rule::in([
                        'good',
                        'fair',
                        'damaged',
                        'missing',
                    ]),
                ],
                'notes' => [
                    'nullable',
                    'string',
                    'max:2000',
                ],
            ]);

            $this->returnService->checkInSerialized(
                $deliveryOrder,
                $allocation,
                $validated['condition'],
                $validated['notes'] ?? null,
                auth()->guard('user')->id()
            );
        } elseif ($allocation->tracking_type === 'quantity') {
            $validated = $request->validate([
                'returned_quantity' => [
                    'required',
                    'numeric',
                    'min:0',
                    'max:'.(float) $allocation->quantity,
                ],
                'notes' => [
                    'nullable',
                    'string',
                    'max:2000',
                ],
            ]);

            $this->returnService->checkInQuantity(
                $deliveryOrder,
                $allocation,
                (float) $validated['returned_quantity'],
                $validated['notes'] ?? null,
                auth()->guard('user')->id()
            );
        } else {
            throw ValidationException::withMessages([
                'inventory' => 'Tracking type allocation tidak dikenali.',
            ]);
        }

        session()->flash(
            'success',
            'Inventory berhasil di-Check-In.'
        );

        return redirect()->route(
            'admin.delivery-orders.return.show',
            $deliveryOrder->id
        );
    }

    private function findDeliveryOrder(
        int $id
    ): DeliveryOrder {
        return DeliveryOrder::with([
            'items.inventoryItem',
            'items.allocations.inventoryAsset',
        ])->findOrFail($id);
    }
}
