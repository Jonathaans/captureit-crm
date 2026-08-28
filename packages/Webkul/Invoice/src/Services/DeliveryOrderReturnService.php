<?php

namespace Webkul\Invoice\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Webkul\Invoice\Models\DeliveryOrder;
use Webkul\Invoice\Models\DeliveryOrderInventoryAllocation;
use Webkul\Warehouse\Models\InventoryAsset;
use Webkul\Warehouse\Models\InventoryItem;
use Webkul\Warehouse\Models\InventoryStockMovement;

class DeliveryOrderReturnService
{
    /**
     * Memulai proses return untuk seluruh allocation yang masih OUT.
     *
     * Serialized asset:
     * OUT -> RETURN_PENDING
     *
     * Quantity:
     * allocation OUT -> RETURN_PENDING
     * quantity_on_hand belum berubah.
     */
    public function startReturn(
        DeliveryOrder $deliveryOrder,
        ?int $performedBy = null
    ): void {
        $this->assertDelivered($deliveryOrder);

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
                if ($this->allReturned($deliveryOrder)) {
                    return;
                }

                throw ValidationException::withMessages([
                    'inventory' => 'Tidak ada inventory aktif yang dapat diproses return.',
                ]);
            }

            $invalid = $allocations
                ->whereIn('status', [
                    'allocated',
                    'picked',
                ]);

            if ($invalid->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'inventory' => 'Return hanya dapat dimulai setelah seluruh inventory berstatus OUT.',
                ]);
            }

            foreach ($allocations as $allocation) {
                if ($allocation->status === 'return_pending') {
                    continue;
                }

                if ($allocation->status !== 'out') {
                    throw ValidationException::withMessages([
                        'inventory' => sprintf(
                            'Allocation ID %d tidak dapat masuk RETURN PENDING dari status %s.',
                            $allocation->id,
                            strtoupper($allocation->status)
                        ),
                    ]);
                }

                $inventoryItem = InventoryItem::query()
                    ->findOrFail($allocation->inventory_item_id);

                $inventoryAsset = null;

                if ($allocation->tracking_type === 'serialized') {
                    $inventoryAsset = InventoryAsset::query()
                        ->lockForUpdate()
                        ->findOrFail($allocation->inventory_asset_id);

                    if ($inventoryAsset->status !== 'out') {
                        throw ValidationException::withMessages([
                            'inventory' => sprintf(
                                'Asset %s harus berstatus OUT sebelum Return Pending. Status saat ini: %s.',
                                $inventoryAsset->asset_code,
                                strtoupper($inventoryAsset->status)
                            ),
                        ]);
                    }

                    $inventoryAsset->update([
                        'status' => 'return_pending',
                    ]);
                }

                $allocation->update([
                    'status'             => 'return_pending',
                    'return_pending_by'  => $performedBy,
                    'return_pending_at'  => now(),
                ]);

                $this->recordMovement(
                    $deliveryOrder,
                    $inventoryItem,
                    $inventoryAsset,
                    'return_pending',
                    (float) $allocation->quantity,
                    'out',
                    'return_pending',
                    $performedBy,
                    sprintf(
                        'Return process started for %s.',
                        $deliveryOrder->delivery_order_number
                    )
                );
            }
        });
    }

    /**
     * Check-In satu serialized asset.
     */
    public function checkInSerialized(
        DeliveryOrder $deliveryOrder,
        DeliveryOrderInventoryAllocation $allocation,
        string $condition,
        ?string $notes = null,
        ?int $performedBy = null
    ): void {
        $this->assertDelivered($deliveryOrder);

        $allowedConditions = [
            'good',
            'fair',
            'damaged',
            'missing',
        ];

        if (! in_array($condition, $allowedConditions, true)) {
            throw ValidationException::withMessages([
                'condition' => 'Return condition tidak valid.',
            ]);
        }

        DB::transaction(function () use (
            $deliveryOrder,
            $allocation,
            $condition,
            $notes,
            $performedBy
        ) {
            $lockedAllocation = DeliveryOrderInventoryAllocation::query()
                ->where('delivery_order_id', $deliveryOrder->id)
                ->lockForUpdate()
                ->findOrFail($allocation->id);

            if ($lockedAllocation->tracking_type !== 'serialized') {
                throw ValidationException::withMessages([
                    'condition' => 'Allocation ini bukan serialized asset.',
                ]);
            }

            if ($lockedAllocation->status !== 'return_pending') {
                throw ValidationException::withMessages([
                    'condition' => sprintf(
                        'Serialized asset hanya dapat di-Check-In dari RETURN PENDING. Status saat ini: %s.',
                        strtoupper($lockedAllocation->status)
                    ),
                ]);
            }

            $inventoryItem = InventoryItem::query()
                ->findOrFail($lockedAllocation->inventory_item_id);

            $inventoryAsset = InventoryAsset::query()
                ->lockForUpdate()
                ->findOrFail($lockedAllocation->inventory_asset_id);

            if ($inventoryAsset->status !== 'return_pending') {
                throw ValidationException::withMessages([
                    'condition' => sprintf(
                        'Asset %s harus berstatus RETURN PENDING. Status saat ini: %s.',
                        $inventoryAsset->asset_code,
                        strtoupper($inventoryAsset->status)
                    ),
                ]);
            }

            $targetStatus = match ($condition) {
                'damaged' => 'damaged',
                'missing' => 'missing',
                default   => 'available',
            };

            $assetData = [
                'status' => $targetStatus,
            ];

            if ($condition !== 'missing') {
                $assetData['condition'] = $condition;
            }

            $inventoryAsset->update($assetData);

            $returnedQuantity = $condition === 'missing'
                ? 0
                : 1;

            $lockedAllocation->update([
                'status'            => 'returned',
                'checked_in_by'     => $performedBy,
                'checked_in_at'     => now(),
                'return_condition'  => $condition,
                'returned_quantity' => $returnedQuantity,
                'return_notes'      => $notes,
            ]);

            $movementType = match ($condition) {
                'damaged' => 'damaged',
                'missing' => 'missing',
                default   => 'returned',
            };

            $this->recordMovement(
                $deliveryOrder,
                $inventoryItem,
                $inventoryAsset,
                $movementType,
                1,
                'return_pending',
                $targetStatus,
                $performedBy,
                sprintf(
                    'Check-In %s from %s. Condition: %s.%s',
                    $inventoryAsset->asset_code,
                    $deliveryOrder->delivery_order_number,
                    strtoupper($condition),
                    $notes ? ' '.$notes : ''
                )
            );
        });
    }

    /**
     * Check-In quantity stock.
     *
     * returnedQuantity = jumlah fisik yang benar-benar kembali.
     * Sisa quantity dianggap terpakai / consumed dan tidak ditambahkan
     * kembali ke quantity_on_hand.
     */
    public function checkInQuantity(
        DeliveryOrder $deliveryOrder,
        DeliveryOrderInventoryAllocation $allocation,
        float $returnedQuantity,
        ?string $notes = null,
        ?int $performedBy = null
    ): void {
        $this->assertDelivered($deliveryOrder);

        DB::transaction(function () use (
            $deliveryOrder,
            $allocation,
            $returnedQuantity,
            $notes,
            $performedBy
        ) {
            $lockedAllocation = DeliveryOrderInventoryAllocation::query()
                ->where('delivery_order_id', $deliveryOrder->id)
                ->lockForUpdate()
                ->findOrFail($allocation->id);

            if ($lockedAllocation->tracking_type !== 'quantity') {
                throw ValidationException::withMessages([
                    'returned_quantity' => 'Allocation ini bukan quantity stock.',
                ]);
            }

            if ($lockedAllocation->status !== 'return_pending') {
                throw ValidationException::withMessages([
                    'returned_quantity' => sprintf(
                        'Quantity hanya dapat di-Check-In dari RETURN PENDING. Status saat ini: %s.',
                        strtoupper($lockedAllocation->status)
                    ),
                ]);
            }

            $outQuantity = (float) $lockedAllocation->quantity;
            $returnedQuantity = round($returnedQuantity, 2);

            if (
                $returnedQuantity < 0
                || $returnedQuantity > $outQuantity + 0.0001
            ) {
                throw ValidationException::withMessages([
                    'returned_quantity' => sprintf(
                        'Jumlah kembali harus antara 0 dan %s.',
                        $this->formatQuantity($outQuantity)
                    ),
                ]);
            }

            $returnedQuantity = min(
                $returnedQuantity,
                $outQuantity
            );

            $inventoryItem = InventoryItem::query()
                ->lockForUpdate()
                ->findOrFail($lockedAllocation->inventory_item_id);

            $before = (float) $inventoryItem->quantity_on_hand;
            $after = $before + $returnedQuantity;
            $consumedQuantity = max(
                $outQuantity - $returnedQuantity,
                0
            );

            if ($returnedQuantity > 0) {
                $inventoryItem->update([
                    'quantity_on_hand' => $after,
                ]);
            }

            $returnCondition = match (true) {
                $returnedQuantity <= 0.0001 => 'consumed',
                $consumedQuantity <= 0.0001 => 'full_return',
                default                    => 'partial_return',
            };

            $lockedAllocation->update([
                'status'            => 'returned',
                'checked_in_by'     => $performedBy,
                'checked_in_at'     => now(),
                'return_condition'  => $returnCondition,
                'returned_quantity' => $returnedQuantity,
                'return_notes'      => $notes,
            ]);

            $this->recordMovement(
                $deliveryOrder,
                $inventoryItem,
                null,
                'returned',
                $returnedQuantity,
                'return_pending',
                'available',
                $performedBy,
                sprintf(
                    'Quantity Check-In for %s. Returned %s %s, consumed %s %s. Stock %s %s -> %s %s.%s',
                    $deliveryOrder->delivery_order_number,
                    $this->formatQuantity($returnedQuantity),
                    $inventoryItem->unit,
                    $this->formatQuantity($consumedQuantity),
                    $inventoryItem->unit,
                    $this->formatQuantity($before),
                    $inventoryItem->unit,
                    $this->formatQuantity($after),
                    $inventoryItem->unit,
                    $notes ? ' '.$notes : ''
                )
            );
        });
    }

    /**
     * Return dianggap complete bila ada allocation RETURNED dan
     * sudah tidak ada allocation operational yang aktif.
     *
     * History RELEASED dari proses re-allocation lama diabaikan.
     */
    public function allReturned(
        DeliveryOrder $deliveryOrder
    ): bool {
        $hasReturned = DeliveryOrderInventoryAllocation::query()
            ->where('delivery_order_id', $deliveryOrder->id)
            ->where('status', 'returned')
            ->exists();

        if (! $hasReturned) {
            return false;
        }

        return ! DeliveryOrderInventoryAllocation::query()
            ->where('delivery_order_id', $deliveryOrder->id)
            ->whereIn(
                'status',
                DeliveryOrderInventoryAllocation::ACTIVE_STATUSES
            )
            ->exists();
    }

    private function assertDelivered(
        DeliveryOrder $deliveryOrder
    ): void {
        if (
            strtolower($deliveryOrder->status ?: 'draft')
            !== 'delivered'
        ) {
            throw ValidationException::withMessages([
                'delivery_order' => 'Return / Check-In hanya dapat diproses ketika Surat Jalan berstatus DELIVERED.',
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
