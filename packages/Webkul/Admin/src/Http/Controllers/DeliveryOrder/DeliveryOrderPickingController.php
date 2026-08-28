<?php

namespace Webkul\Admin\Http\Controllers\DeliveryOrder;

use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Invoice\Models\DeliveryOrder;
use Webkul\Invoice\Models\DeliveryOrderInventoryAllocation;
use Webkul\Invoice\Services\DeliveryOrderPickingService;

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
                'allOut'
            )
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

    private function findDeliveryOrder(
        int $id
    ): DeliveryOrder {
        return DeliveryOrder::with([
            'items.inventoryItem',
            'items.allocations.inventoryAsset',
        ])->findOrFail($id);
    }
}
