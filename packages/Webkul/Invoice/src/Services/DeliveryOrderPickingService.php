<?php

namespace Webkul\Invoice\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Webkul\Invoice\Models\DeliveryOrder;
use Webkul\Invoice\Models\DeliveryOrderInventoryAllocation;
use Webkul\Warehouse\Models\InventoryAsset;
use Webkul\Warehouse\Models\InventoryItem;
use Webkul\Warehouse\Models\InventoryStockMovement;

class DeliveryOrderPickingService
{
    /**
     * Mark satu allocation sebagai PICKED.
     */
    public function markPicked(
        DeliveryOrder $deliveryOrder,
        DeliveryOrderInventoryAllocation $allocation,
        ?int $performedBy = null
    ): void {
        $this->assertIssued($deliveryOrder);

        if ($allocation->delivery_order_id !== $deliveryOrder->id) {
            throw ValidationException::withMessages([
                'allocation' => 'Allocation tidak termasuk Surat Jalan ini.',
            ]);
        }

        DB::transaction(function () use (
            $deliveryOrder,
            $allocation,
            $performedBy
        ) {
            $lockedAllocation = DeliveryOrderInventoryAllocation::query()
                ->lockForUpdate()
                ->findOrFail($allocation->id);

            if ($lockedAllocation->status === 'picked') {
                return;
            }

            if ($lockedAllocation->status !== 'allocated') {
                throw ValidationException::withMessages([
                    'allocation' => sprintf(
                        'Allocation hanya dapat dipick dari status ALLOCATED. Status saat ini: %s.',
                        strtoupper($lockedAllocation->status)
                    ),
                ]);
            }

            $inventoryItem = InventoryItem::query()
                ->findOrFail($lockedAllocation->inventory_item_id);

            $inventoryAsset = null;

            if ($lockedAllocation->tracking_type === 'serialized') {
                $inventoryAsset = InventoryAsset::query()
                    ->lockForUpdate()
                    ->findOrFail($lockedAllocation->inventory_asset_id);

                if ($inventoryAsset->status !== 'allocated') {
                    throw ValidationException::withMessages([
                        'allocation' => sprintf(
                            'Asset %s harus berstatus ALLOCATED sebelum dipick. Status saat ini: %s.',
                            $inventoryAsset->asset_code,
                            strtoupper($inventoryAsset->status)
                        ),
                    ]);
                }

                $inventoryAsset->update([
                    'status' => 'picked',
                ]);
            }

            $lockedAllocation->update([
                'status'    => 'picked',
                'picked_by' => $performedBy,
                'picked_at' => now(),
            ]);

            $this->recordMovement(
                $deliveryOrder,
                $inventoryItem,
                $inventoryAsset,
                'picked',
                (float) $lockedAllocation->quantity,
                'allocated',
                'picked',
                $performedBy,
                sprintf(
                    'Picked for %s.',
                    $deliveryOrder->delivery_order_number
                )
            );
        });
    }

    /**
     * Mark semua allocation ALLOCATED menjadi PICKED.
     */
    public function markAllPicked(
        DeliveryOrder $deliveryOrder,
        ?int $performedBy = null
    ): void {
        $this->assertIssued($deliveryOrder);

        $allocationIds = DeliveryOrderInventoryAllocation::query()
            ->where('delivery_order_id', $deliveryOrder->id)
            ->where('status', 'allocated')
            ->orderBy('id')
            ->pluck('id');

        if ($allocationIds->isEmpty()) {
            return;
        }

        foreach ($allocationIds as $allocationId) {
            $allocation = DeliveryOrderInventoryAllocation::findOrFail(
                $allocationId
            );

            $this->markPicked(
                $deliveryOrder,
                $allocation,
                $performedBy
            );
        }
    }

    /**
     * Scan / mark satu serialized asset sebagai OUT.
     *
     * Quantity stock tetap diproses oleh Confirm OUT.
     */
    public function markOutSerialized(
        DeliveryOrder $deliveryOrder,
        DeliveryOrderInventoryAllocation $allocation,
        ?int $performedBy = null
    ): void {
        $this->assertIssued($deliveryOrder);

        if ($allocation->delivery_order_id !== $deliveryOrder->id) {
            throw ValidationException::withMessages([
                'barcode' => 'Allocation tidak termasuk Surat Jalan ini.',
            ]);
        }

        DB::transaction(function () use (
            $deliveryOrder,
            $allocation,
            $performedBy
        ) {
            $lockedAllocation = DeliveryOrderInventoryAllocation::query()
                ->lockForUpdate()
                ->findOrFail($allocation->id);

            if ($lockedAllocation->tracking_type !== 'serialized') {
                throw ValidationException::withMessages([
                    'barcode' => 'Scan OUT hanya digunakan untuk serialized asset.',
                ]);
            }

            if ($lockedAllocation->status === 'out') {
                return;
            }

            if ($lockedAllocation->status !== 'picked') {
                throw ValidationException::withMessages([
                    'barcode' => sprintf(
                        'Asset hanya dapat OUT dari status PICKED. Status allocation saat ini: %s.',
                        strtoupper($lockedAllocation->status)
                    ),
                ]);
            }

            $inventoryItem = InventoryItem::query()
                ->findOrFail($lockedAllocation->inventory_item_id);

            $inventoryAsset = InventoryAsset::query()
                ->lockForUpdate()
                ->findOrFail($lockedAllocation->inventory_asset_id);

            if ($inventoryAsset->status !== 'picked') {
                throw ValidationException::withMessages([
                    'barcode' => sprintf(
                        'Asset %s harus berstatus PICKED sebelum OUT. Status saat ini: %s.',
                        $inventoryAsset->asset_code,
                        strtoupper($inventoryAsset->status)
                    ),
                ]);
            }

            $inventoryAsset->update([
                'status' => 'out',
            ]);

            $lockedAllocation->update([
                'status' => 'out',
                'out_by' => $performedBy,
                'out_at' => now(),
            ]);

            $this->recordMovement(
                $deliveryOrder,
                $inventoryItem,
                $inventoryAsset,
                'out',
                1,
                'picked',
                'out',
                $performedBy,
                sprintf(
                    'Serialized asset %s scanned OUT for %s.',
                    $inventoryAsset->asset_code,
                    $deliveryOrder->delivery_order_number
                )
            );
        });
    }

    /**
     * Confirm seluruh PICKED inventory benar-benar OUT dari warehouse.
     *
     * Serialized:
     * PICKED -> OUT
     *
     * Quantity:
     * PICKED -> OUT dan quantity_on_hand berkurang.
     */
    public function confirmOut(
        DeliveryOrder $deliveryOrder,
        ?int $performedBy = null
    ): void {
        $this->assertIssued($deliveryOrder);

        DB::transaction(function () use (
            $deliveryOrder,
            $performedBy
        ) {
            $allocations = DeliveryOrderInventoryAllocation::query()
                ->where('delivery_order_id', $deliveryOrder->id)
                ->whereIn(
                    'status',
                    DeliveryOrderInventoryAllocation::ACTIVE_STATUSES
                )
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($allocations->isEmpty()) {
                throw ValidationException::withMessages([
                    'inventory' => 'Surat Jalan tidak memiliki inventory allocation aktif.',
                ]);
            }

            $invalid = $allocations
                ->whereNotIn(
                    'status',
                    [
                        'picked',
                        'out',
                    ]
                );

            if ($invalid->isNotEmpty()) {
                $statuses = $invalid
                    ->pluck('status')
                    ->map(fn ($status) => strtoupper($status))
                    ->unique()
                    ->implode(', ');

                throw ValidationException::withMessages([
                    'inventory' => 'Confirm OUT membutuhkan seluruh inventory minimal berstatus PICKED. Status yang masih ditemukan: '
                        .$statuses.'.',
                ]);
            }

            foreach ($allocations as $allocation) {
                /*
                 * Serialized asset yang sudah di-scan OUT tidak boleh
                 * diproses dua kali.
                 */
                if ($allocation->status === 'out') {
                    continue;
                }

                $inventoryItem = InventoryItem::query()
                    ->lockForUpdate()
                    ->findOrFail($allocation->inventory_item_id);

                $inventoryAsset = null;
                $notes = sprintf(
                    'Confirmed OUT for %s.',
                    $deliveryOrder->delivery_order_number
                );

                if ($allocation->tracking_type === 'serialized') {
                    $inventoryAsset = InventoryAsset::query()
                        ->lockForUpdate()
                        ->findOrFail($allocation->inventory_asset_id);

                    if ($inventoryAsset->status !== 'picked') {
                        throw ValidationException::withMessages([
                            'inventory' => sprintf(
                                'Asset %s belum berstatus PICKED.',
                                $inventoryAsset->asset_code
                            ),
                        ]);
                    }

                    $inventoryAsset->update([
                        'status' => 'out',
                    ]);
                } else {
                    $quantity = (float) $allocation->quantity;
                    $before = (float) $inventoryItem->quantity_on_hand;
                    $after = $before - $quantity;

                    if ($after < -0.0001) {
                        throw ValidationException::withMessages([
                            'inventory' => sprintf(
                                'Stock %s tidak cukup untuk OUT %s %s. Stock saat ini %s %s.',
                                $inventoryItem->name,
                                $this->formatQuantity($quantity),
                                $inventoryItem->unit,
                                $this->formatQuantity($before),
                                $inventoryItem->unit
                            ),
                        ]);
                    }

                    $after = max($after, 0);

                    $inventoryItem->update([
                        'quantity_on_hand' => $after,
                    ]);

                    $notes = sprintf(
                        'Confirmed OUT for %s. Stock %s %s -> %s %s.',
                        $deliveryOrder->delivery_order_number,
                        $this->formatQuantity($before),
                        $inventoryItem->unit,
                        $this->formatQuantity($after),
                        $inventoryItem->unit
                    );
                }

                $allocation->update([
                    'status' => 'out',
                    'out_by' => $performedBy,
                    'out_at' => now(),
                ]);

                $this->recordMovement(
                    $deliveryOrder,
                    $inventoryItem,
                    $inventoryAsset,
                    'out',
                    (float) $allocation->quantity,
                    'picked',
                    'out',
                    $performedBy,
                    $notes
                );
            }
        });
    }

    /**
     * True bila seluruh allocation aktif sudah OUT.
     */
    public function allOut(
        DeliveryOrder $deliveryOrder
    ): bool {
        $active = DeliveryOrderInventoryAllocation::query()
            ->where('delivery_order_id', $deliveryOrder->id)
            ->whereIn(
                'status',
                DeliveryOrderInventoryAllocation::ACTIVE_STATUSES
            );

        if (! (clone $active)->exists()) {
            return false;
        }

        return ! (clone $active)
            ->where('status', '!=', 'out')
            ->exists();
    }

    private function assertIssued(
        DeliveryOrder $deliveryOrder
    ): void {
        if (
            strtolower($deliveryOrder->status ?: 'draft')
            !== 'issued'
        ) {
            throw ValidationException::withMessages([
                'delivery_order' => 'Picking / OUT hanya dapat diproses ketika Surat Jalan berstatus ISSUED.',
            ]);
        }
    }

    private function recordMovement(
        DeliveryOrder $deliveryOrder,
        InventoryItem $inventoryItem,
        ?InventoryAsset $inventoryAsset,
        string $movementType,
        float $quantity,
        ?string $fromStatus,
        ?string $toStatus,
        ?int $performedBy,
        string $notes
    ): InventoryStockMovement {
        return InventoryStockMovement::create([
            'inventory_item_id'     => $inventoryItem->id,
            'inventory_asset_id'    => $inventoryAsset?->id,
            'warehouse_id'          => $inventoryAsset?->warehouse_id
                ?? $inventoryItem->warehouse_id,
            'warehouse_location_id' => $inventoryAsset?->warehouse_location_id
                ?? $inventoryItem->warehouse_location_id,
            'movement_type'         => $movementType,
            'quantity'              => $quantity,
            'from_status'           => $fromStatus,
            'to_status'             => $toStatus,
            'reference_type'        => 'delivery_order',
            'reference_id'          => $deliveryOrder->id,
            'reference_number'      => $deliveryOrder->delivery_order_number,
            'performed_by'          => $performedBy,
            'notes'                 => $notes,
            'occurred_at'           => now(),
        ]);
    }

    private function formatQuantity(float $value): string
    {
        return rtrim(
            rtrim(
                number_format($value, 2, '.', ''),
                '0'
            ),
            '.'
        );
    }
}
