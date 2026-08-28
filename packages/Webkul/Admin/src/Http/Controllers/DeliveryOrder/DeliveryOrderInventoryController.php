<?php

namespace Webkul\Admin\Http\Controllers\DeliveryOrder;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Invoice\Models\DeliveryOrder;
use Webkul\Invoice\Models\DeliveryOrderInventoryAllocation;
use Webkul\Invoice\Services\DeliveryOrderInventoryAllocationService;
use Webkul\Warehouse\Models\InventoryAsset;

class DeliveryOrderInventoryController extends Controller
{
    public function __construct(
        protected DeliveryOrderInventoryAllocationService $allocationService
    ) {
    }

    public function edit(int $id): View
    {
        $deliveryOrder = $this->findDeliveryOrder($id);

        $assetOptions = [];
        $summaries = [];

        foreach ($deliveryOrder->items as $item) {
            $inventoryItem = $item->inventoryItem;

            if (! $inventoryItem) {
                $summaries[$item->id] = [
                    'tracking_type'  => null,
                    'need'           => (float) $item->quantity,
                    'allocated'      => 0,
                    'free_available' => 0,
                    'max_for_this'   => 0,
                    'complete'       => false,
                ];

                continue;
            }

            $currentAllocations = $item->allocations
                ->whereIn(
                    'status',
                    DeliveryOrderInventoryAllocation::ACTIVE_STATUSES
                );

            if ($inventoryItem->isSerialized()) {
                $currentAssetIds = $currentAllocations
                    ->pluck('inventory_asset_id')
                    ->filter()
                    ->map(fn ($value) => (int) $value)
                    ->values();

                $assetOptions[$item->id] = InventoryAsset::query()
                    ->where('inventory_item_id', $inventoryItem->id)
                    ->where(function ($query) use ($currentAssetIds) {
                        $query->where('status', 'available');

                        if ($currentAssetIds->isNotEmpty()) {
                            $query->orWhereIn(
                                'id',
                                $currentAssetIds->all()
                            );
                        }
                    })
                    ->orderBy('asset_code')
                    ->get();

                $freeAvailable = InventoryAsset::query()
                    ->where('inventory_item_id', $inventoryItem->id)
                    ->where('status', 'available')
                    ->count();

                $allocated = $currentAllocations->count();
                $need = (float) $item->quantity;

                $summaries[$item->id] = [
                    'tracking_type'  => 'serialized',
                    'need'           => $need,
                    'allocated'      => $allocated,
                    'free_available' => $freeAvailable,
                    'max_for_this'   => $allocated + $freeAvailable,
                    'complete'       => floor($need) === $need
                        && $allocated >= (int) $need,
                ];

                continue;
            }

            $currentQuantity = (float) $currentAllocations->sum('quantity');

            $totalReserved = (float) DeliveryOrderInventoryAllocation::query()
                ->where('inventory_item_id', $inventoryItem->id)
                ->where('tracking_type', 'quantity')
                ->whereIn(
                    'status',
                    DeliveryOrderInventoryAllocation::ACTIVE_STATUSES
                )
                ->sum('quantity');

            $freeAvailable = max(
                (float) $inventoryItem->quantity_on_hand - $totalReserved,
                0
            );

            $maxForThis = $currentQuantity + $freeAvailable;
            $need = (float) $item->quantity;

            $summaries[$item->id] = [
                'tracking_type'  => 'quantity',
                'need'           => $need,
                'allocated'      => $currentQuantity,
                'free_available' => $freeAvailable,
                'max_for_this'   => $maxForThis,
                'complete'       => $currentQuantity + 0.0001 >= $need,
            ];
        }

        $editable = strtolower(
            $deliveryOrder->status ?: 'draft'
        ) === 'draft';

        return view(
            'admin::delivery-orders.inventory-allocation',
            compact(
                'deliveryOrder',
                'assetOptions',
                'summaries',
                'editable'
            )
        );
    }

    public function update(
        Request $request,
        int $id,
        int $itemId
    ): RedirectResponse {
        $deliveryOrder = $this->findDeliveryOrder($id);

        $deliveryOrderItem = $deliveryOrder->items
            ->firstWhere('id', $itemId);

        if (! $deliveryOrderItem) {
            abort(404);
        }

        $inventoryItem = $deliveryOrderItem->inventoryItem;

        if (! $inventoryItem) {
            throw ValidationException::withMessages([
                'inventory_item_id' => 'Item Surat Jalan belum terhubung ke Inventory Item.',
            ]);
        }

        if ($inventoryItem->isSerialized()) {
            $validated = $request->validate([
                'asset_ids'   => ['nullable', 'array'],
                'asset_ids.*' => [
                    'integer',
                    'distinct',
                    'exists:inventory_assets,id',
                ],
            ]);

            $this->allocationService->syncSerialized(
                $deliveryOrder,
                $deliveryOrderItem,
                $validated['asset_ids'] ?? [],
                auth()->guard('user')->id()
            );
        } else {
            $validated = $request->validate([
                'quantity' => [
                    'required',
                    'numeric',
                    'min:0',
                ],
            ]);

            $this->allocationService->syncQuantity(
                $deliveryOrder,
                $deliveryOrderItem,
                (float) $validated['quantity'],
                auth()->guard('user')->id()
            );
        }

        session()->flash(
            'success',
            "Allocation {$deliveryOrderItem->name} berhasil diperbarui."
        );

        return redirect()->route(
            'admin.delivery-orders.inventory-allocation.edit',
            $deliveryOrder->id
        );
    }

    public function release(
        int $id,
        int $itemId
    ): RedirectResponse {
        $deliveryOrder = $this->findDeliveryOrder($id);

        $deliveryOrderItem = $deliveryOrder->items
            ->firstWhere('id', $itemId);

        if (! $deliveryOrderItem) {
            abort(404);
        }

        $this->allocationService->releaseItem(
            $deliveryOrder,
            $deliveryOrderItem,
            auth()->guard('user')->id()
        );

        session()->flash(
            'success',
            "Allocation {$deliveryOrderItem->name} berhasil dilepas."
        );

        return redirect()->route(
            'admin.delivery-orders.inventory-allocation.edit',
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
