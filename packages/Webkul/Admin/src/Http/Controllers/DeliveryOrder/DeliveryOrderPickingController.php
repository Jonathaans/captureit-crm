<?php

namespace Webkul\Admin\Http\Controllers\DeliveryOrder;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Invoice\Models\DeliveryOrder;
use Webkul\Invoice\Models\DeliveryOrderInventoryAllocation;
use Webkul\Invoice\Services\DeliveryOrderPickingService;
use Webkul\Warehouse\Models\InventoryAsset;

class DeliveryOrderPickingController extends Controller
{
    public function __construct(
        protected DeliveryOrderPickingService $pickingService
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
                'allocatedBy',
                'pickedBy',
                'outBy',
            ])
            ->where('delivery_order_id', $deliveryOrder->id)
            ->whereIn(
                'status',
                DeliveryOrderInventoryAllocation::ACTIVE_STATUSES
            )
            ->orderBy('delivery_order_item_id')
            ->orderBy('id')
            ->get();

        $summary = [
            'allocated' => $allocations
                ->where('status', 'allocated')
                ->count(),

            'picked' => $allocations
                ->where('status', 'picked')
                ->count(),

            'out' => $allocations
                ->where('status', 'out')
                ->count(),

            'return_pending' => $allocations
                ->where('status', 'return_pending')
                ->count(),
        ];

        $canOperate = strtolower(
            $deliveryOrder->status ?: 'draft'
        ) === 'issued';

        $allPicked = $allocations->isNotEmpty()
            && $allocations->every(
                fn ($allocation) => $allocation->status === 'picked'
            );

        /*
         * Serialized assets may already be OUT because they were
         * individually scanned. Confirm OUT then only finalizes the
         * remaining PICKED allocations, including quantity stock.
         */
        $readyForFinalOut = $allocations->isNotEmpty()
            && $allocations->every(
                fn ($allocation) => in_array(
                    $allocation->status,
                    [
                        'picked',
                        'out',
                    ],
                    true
                )
            );

        $allOut = $allocations->isNotEmpty()
            && $allocations->every(
                fn ($allocation) => $allocation->status === 'out'
            );

        return view(
            'admin::delivery-orders.picking',
            compact(
                'deliveryOrder',
                'allocations',
                'summary',
                'canOperate',
                'allPicked',
                'readyForFinalOut',
                'allOut'
            )
        );
    }

    /**
     * Barcode scanner -> ALLOCATED / PICKED.
     *
     * Most USB scanners behave like a keyboard and submit Enter.
     */
    public function scanPick(
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
        ]);

        $asset = $this->findAssetByBarcode(
            $validated['barcode']
        );

        $allocation = DeliveryOrderInventoryAllocation::query()
            ->where('delivery_order_id', $deliveryOrder->id)
            ->where('tracking_type', 'serialized')
            ->where('inventory_asset_id', $asset->id)
            ->whereIn('status', [
                'allocated',
                'picked',
            ])
            ->orderByDesc('id')
            ->first();

        if (! $allocation) {
            throw ValidationException::withMessages([
                'barcode' => sprintf(
                    'Asset %s tidak sedang dialokasikan untuk %s atau statusnya tidak dapat dipick.',
                    $asset->asset_code,
                    $deliveryOrder->delivery_order_number
                ),
            ]);
        }

        $this->pickingService->markPicked(
            $deliveryOrder,
            $allocation,
            auth()->guard('user')->id()
        );

        session()->flash(
            'success',
            'SCAN PICKED: '.$asset->asset_code
        );

        return redirect()->route(
            'admin.delivery-orders.picking.show',
            $deliveryOrder->id
        );
    }

    /**
     * Barcode scanner -> PICKED / OUT for one serialized asset.
     */
    public function scanOut(
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
        ]);

        $asset = $this->findAssetByBarcode(
            $validated['barcode']
        );

        $allocation = DeliveryOrderInventoryAllocation::query()
            ->where('delivery_order_id', $deliveryOrder->id)
            ->where('tracking_type', 'serialized')
            ->where('inventory_asset_id', $asset->id)
            ->whereIn('status', [
                'picked',
                'out',
            ])
            ->orderByDesc('id')
            ->first();

        if (! $allocation) {
            throw ValidationException::withMessages([
                'barcode' => sprintf(
                    'Asset %s tidak berada pada PICKED / OUT untuk %s.',
                    $asset->asset_code,
                    $deliveryOrder->delivery_order_number
                ),
            ]);
        }

        $this->pickingService->markOutSerialized(
            $deliveryOrder,
            $allocation,
            auth()->guard('user')->id()
        );

        session()->flash(
            'success',
            'SCAN OUT: '.$asset->asset_code
        );

        return redirect()->route(
            'admin.delivery-orders.picking.show',
            $deliveryOrder->id
        );
    }

    /**
     * Internal warehouse Picking List PDF.
     */
    public function print(
        int $id
    ): Response|StreamedResponse {
        $deliveryOrder = $this->findDeliveryOrder($id);

        $allocations = DeliveryOrderInventoryAllocation::query()
            ->with([
                'deliveryOrderItem',
                'inventoryItem',
                'inventoryAsset',
                'allocatedBy',
                'pickedBy',
                'outBy',
            ])
            ->where('delivery_order_id', $deliveryOrder->id)
            ->where('status', '!=', 'released')
            ->orderBy('delivery_order_item_id')
            ->orderBy('id')
            ->get();

        $fileName = 'Picking_List_'
            .str_replace(
                [
                    '/',
                    '\\',
                    ' ',
                ],
                [
                    '-',
                    '-',
                    '_',
                ],
                $deliveryOrder->delivery_order_number
            );

        return $this->downloadPDF(
            view(
                'admin::delivery-orders.picking-print',
                compact(
                    'deliveryOrder',
                    'allocations'
                )
            )->render(),
            $fileName
        );
    }


    public function markPicked(
        int $id,
        int $allocationId
    ): RedirectResponse {
        $deliveryOrder = $this->findDeliveryOrder($id);

        $allocation = DeliveryOrderInventoryAllocation::query()
            ->where('delivery_order_id', $deliveryOrder->id)
            ->findOrFail($allocationId);

        $this->pickingService->markPicked(
            $deliveryOrder,
            $allocation,
            auth()->guard('user')->id()
        );

        session()->flash(
            'success',
            'Inventory berhasil ditandai PICKED.'
        );

        return redirect()->route(
            'admin.delivery-orders.picking.show',
            $deliveryOrder->id
        );
    }

    public function markAllPicked(
        int $id
    ): RedirectResponse {
        $deliveryOrder = $this->findDeliveryOrder($id);

        $this->pickingService->markAllPicked(
            $deliveryOrder,
            auth()->guard('user')->id()
        );

        session()->flash(
            'success',
            'Seluruh inventory allocation berhasil ditandai PICKED.'
        );

        return redirect()->route(
            'admin.delivery-orders.picking.show',
            $deliveryOrder->id
        );
    }

    public function confirmOut(
        int $id
    ): RedirectResponse {
        $deliveryOrder = $this->findDeliveryOrder($id);

        $this->pickingService->confirmOut(
            $deliveryOrder,
            auth()->guard('user')->id()
        );

        session()->flash(
            'success',
            'Seluruh inventory berhasil dikonfirmasi OUT dari warehouse.'
        );

        return redirect()->route(
            'admin.delivery-orders.picking.show',
            $deliveryOrder->id
        );
    }

    private function findAssetByBarcode(
        string $barcode
    ): InventoryAsset {
        $barcode = trim($barcode);

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

        return $asset;
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
