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
     * One-scan serialized return.
     *
     * If allocation is still OUT, the service internally records:
     * OUT -> RETURN_PENDING
     * then immediately completes physical Check-In:
     * RETURN_PENDING -> AVAILABLE / DAMAGED
     *
     * The operator only scans once.
     */
    /**
     * Scan serialized asset as physically RECEIVED.
     *
     * Important:
     * Scan DOES NOT decide the final condition.
     *
     * OUT -> RETURN_PENDING
     *
     * RETURN_PENDING means:
     * barang fisik sudah diterima warehouse dan menunggu inspection.
     */
    public function scanReturnSerialized(
        DeliveryOrder $deliveryOrder,
        DeliveryOrderInventoryAllocation $allocation,
        ?int $performedBy = null
    ): DeliveryOrderInventoryAllocation {
        $this->assertDelivered($deliveryOrder);

        return DB::transaction(function () use (
            $deliveryOrder,
            $allocation,
            $performedBy
        ) {
            $lockedAllocation = DeliveryOrderInventoryAllocation::query()
                ->where('delivery_order_id', $deliveryOrder->id)
                ->lockForUpdate()
                ->findOrFail($allocation->id);

            if ($lockedAllocation->tracking_type !== 'serialized') {
                throw ValidationException::withMessages([
                    'barcode' => 'Asset scan bukan serialized allocation.',
                ]);
            }

            /*
             * Idempotent:
             * double scan does not create duplicate movement.
             */
            if (
                in_array(
                    $lockedAllocation->status,
                    [
                        'return_pending',
                        'returned',
                    ],
                    true
                )
            ) {
                return $lockedAllocation->fresh([
                    'inventoryAsset',
                ]);
            }

            if ($lockedAllocation->status !== 'out') {
                throw ValidationException::withMessages([
                    'barcode' => sprintf(
                        'Asset tidak dapat diterima dari status %s.',
                        strtoupper($lockedAllocation->status)
                    ),
                ]);
            }

            $this->moveOneToReturnPending(
                $deliveryOrder,
                $lockedAllocation,
                $performedBy
            );

            return $lockedAllocation->fresh([
                'inventoryAsset',
            ]);
        });
    }

    /**
     * One-submit quantity return.
     *
     * Quantity OUT can be checked in directly. The service still records
     * the internal OUT -> RETURN_PENDING movement first for audit.
     */
    public function returnQuantityDirect(
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

            if ($lockedAllocation->status === 'returned') {
                return;
            }

            if ($lockedAllocation->status === 'out') {
                $this->moveOneToReturnPending(
                    $deliveryOrder,
                    $lockedAllocation,
                    $performedBy
                );

                $lockedAllocation->refresh();
            }

            $this->finalizeQuantityCheckIn(
                $deliveryOrder,
                $lockedAllocation,
                $returnedQuantity,
                $notes,
                $performedBy
            );
        });
    }

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
     * RETURN DAMAGED NOTE V1
     *
     * Finalize all received serialized assets and all quantity returns in one
     * transaction. Damage notes are mandatory and are persisted on the
     * allocation and its inventory movement.
     *
     * @param array<int, string> $conditions
     * @param array<int, float|int|string> $quantities
     * @param array<int, string> $returnNotes
     */
    public function finalizeReturnBatch(
        DeliveryOrder $deliveryOrder,
        array $conditions,
        array $quantities,
        ?int $performedBy = null,
        array $returnNotes = []
    ): void {
        $this->assertDelivered($deliveryOrder);

        DB::transaction(function () use (
            $deliveryOrder,
            $conditions,
            $quantities,
            $performedBy,
            $returnNotes
        ) {
            $notReceived = DeliveryOrderInventoryAllocation::query()
                ->where('delivery_order_id', $deliveryOrder->id)
                ->where('tracking_type', 'serialized')
                ->where('status', 'out')
                ->with('inventoryAsset')
                ->get();

            if ($notReceived->isNotEmpty()) {
                $codes = $notReceived
                    ->pluck('inventoryAsset.asset_code')
                    ->filter()
                    ->values()
                    ->all();

                throw ValidationException::withMessages([
                    'return' => 'Masih ada serialized asset yang belum discan kembali: '
                        .implode(', ', $codes)
                        .'. Scan barangnya atau tandai Missing.',
                ]);
            }

            $pendingSerialized = DeliveryOrderInventoryAllocation::query()
                ->where('delivery_order_id', $deliveryOrder->id)
                ->where('tracking_type', 'serialized')
                ->where('status', 'return_pending')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($pendingSerialized as $allocation) {
                $key = (string) $allocation->id;

                if (! array_key_exists($key, $conditions)) {
                    throw ValidationException::withMessages([
                        'conditions.'.$allocation->id => 'Pilih condition untuk seluruh asset yang sudah diterima.',
                    ]);
                }

                $condition = strtolower(
                    trim(
                        (string) $conditions[$key]
                    )
                );

                if (
                    ! in_array(
                        $condition,
                        [
                            'good',
                            'fair',
                            'damaged',
                        ],
                        true
                    )
                ) {
                    throw ValidationException::withMessages([
                        'conditions.'.$allocation->id => 'Condition hanya boleh Good, Fair, atau Damaged.',
                    ]);
                }

                $damageNote = null;

                if ($condition === 'damaged') {
                    $damageNote = trim((string) ($returnNotes[$key] ?? ''));

                    if ($damageNote === '') {
                        throw ValidationException::withMessages([
                            'return_notes.'.$allocation->id =>
                                'Alasan kerusakan wajib diisi untuk barang DAMAGED.',
                        ]);
                    }

                    if (mb_strlen($damageNote) > 2000) {
                        throw ValidationException::withMessages([
                            'return_notes.'.$allocation->id =>
                                'Alasan kerusakan maksimal 2000 karakter.',
                        ]);
                    }
                }

                $this->finalizeSerializedCheckIn(
                    $deliveryOrder,
                    $allocation,
                    $condition,
                    $damageNote,
                    $performedBy
                );
            }

            $activeQuantity = DeliveryOrderInventoryAllocation::query()
                ->where('delivery_order_id', $deliveryOrder->id)
                ->where('tracking_type', 'quantity')
                ->whereIn(
                    'status',
                    [
                        'out',
                        'return_pending',
                    ]
                )
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($activeQuantity as $allocation) {
                $key = (string) $allocation->id;

                if (! array_key_exists($key, $quantities)) {
                    throw ValidationException::withMessages([
                        'quantities.'.$allocation->id => 'Isi Returned Quantity untuk seluruh quantity item.',
                    ]);
                }

                $returnedQuantity = round(
                    (float) $quantities[$key],
                    2
                );

                if (
                    $returnedQuantity < 0
                    || $returnedQuantity > (float) $allocation->quantity + 0.0001
                ) {
                    throw ValidationException::withMessages([
                        'quantities.'.$allocation->id => sprintf(
                            'Returned Quantity harus antara 0 dan %s.',
                            $this->formatQuantity(
                                (float) $allocation->quantity
                            )
                        ),
                    ]);
                }

                if ($allocation->status === 'out') {
                    $this->moveOneToReturnPending(
                        $deliveryOrder,
                        $allocation,
                        $performedBy
                    );

                    $allocation->refresh();
                }

                $this->finalizeQuantityCheckIn(
                    $deliveryOrder,
                    $allocation,
                    $returnedQuantity,
                    null,
                    $performedBy
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

        if (
            ! in_array(
                $condition,
                [
                    'good',
                    'fair',
                    'damaged',
                    'missing',
                ],
                true
            )
        ) {
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

            if ($lockedAllocation->status === 'out') {
                $this->moveOneToReturnPending(
                    $deliveryOrder,
                    $lockedAllocation,
                    $performedBy
                );

                $lockedAllocation->refresh();
            }

            $this->finalizeSerializedCheckIn(
                $deliveryOrder,
                $lockedAllocation,
                $condition,
                $notes,
                $performedBy
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

            if ($lockedAllocation->status === 'out') {
                $this->moveOneToReturnPending(
                    $deliveryOrder,
                    $lockedAllocation,
                    $performedBy
                );

                $lockedAllocation->refresh();
            }

            $this->finalizeQuantityCheckIn(
                $deliveryOrder,
                $lockedAllocation,
                $returnedQuantity,
                $notes,
                $performedBy
            );
        });
    }

    private function moveOneToReturnPending(
        DeliveryOrder $deliveryOrder,
        DeliveryOrderInventoryAllocation $allocation,
        ?int $performedBy
    ): void {
        if ($allocation->status === 'return_pending') {
            return;
        }

        if ($allocation->status !== 'out') {
            throw ValidationException::withMessages([
                'inventory' => sprintf(
                    'Inventory hanya dapat masuk Return Pending dari OUT. Status saat ini: %s.',
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
                        'Asset %s harus OUT sebelum Check-In.',
                        $inventoryAsset->asset_code
                    ),
                ]);
            }

            $inventoryAsset->update([
                'status' => 'return_pending',
            ]);
        }

        $allocation->update([
            'status'            => 'return_pending',
            'return_pending_by' => $performedBy,
            'return_pending_at' => now(),
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
                'Physical return received for %s; entering inspection.',
                $deliveryOrder->delivery_order_number
            )
        );
    }

    private function finalizeSerializedCheckIn(
        DeliveryOrder $deliveryOrder,
        DeliveryOrderInventoryAllocation $allocation,
        string $condition,
        ?string $notes,
        ?int $performedBy
    ): void {
        // RETURN DAMAGED NOTE V1: enforce the rule at the service boundary too.
        $notes = $notes !== null ? trim($notes) : null;

        if ($condition === 'damaged' && ($notes === null || $notes === '')) {
            throw ValidationException::withMessages([
                'notes' => 'Alasan kerusakan wajib diisi untuk barang DAMAGED.',
            ]);
        }

        if ($notes !== null && mb_strlen($notes) > 2000) {
            throw ValidationException::withMessages([
                'notes' => 'Alasan kerusakan maksimal 2000 karakter.',
            ]);
        }

        if ($allocation->status !== 'return_pending') {
            throw ValidationException::withMessages([
                'condition' => sprintf(
                    'Serialized asset hanya dapat Check-In dari RETURN PENDING. Status saat ini: %s.',
                    strtoupper($allocation->status)
                ),
            ]);
        }

        $inventoryItem = InventoryItem::query()
            ->findOrFail($allocation->inventory_item_id);

        $inventoryAsset = InventoryAsset::query()
            ->lockForUpdate()
            ->findOrFail($allocation->inventory_asset_id);

        if ($inventoryAsset->status !== 'return_pending') {
            throw ValidationException::withMessages([
                'condition' => sprintf(
                    'Asset %s harus RETURN PENDING. Status saat ini: %s.',
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

        $allocation->update([
            'status'            => 'returned',
            'checked_in_by'     => $performedBy,
            'checked_in_at'     => now(),
            'return_condition'  => $condition,
            'returned_quantity' => $condition === 'missing'
                ? 0
                : 1,
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
    }

    private function finalizeQuantityCheckIn(
        DeliveryOrder $deliveryOrder,
        DeliveryOrderInventoryAllocation $allocation,
        float $returnedQuantity,
        ?string $notes,
        ?int $performedBy
    ): void {
        if ($allocation->status !== 'return_pending') {
            throw ValidationException::withMessages([
                'returned_quantity' => sprintf(
                    'Quantity hanya dapat Check-In dari RETURN PENDING. Status saat ini: %s.',
                    strtoupper($allocation->status)
                ),
            ]);
        }

        $outQuantity = (float) $allocation->quantity;
        $returnedQuantity = round(
            $returnedQuantity,
            2
        );

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
            ->findOrFail($allocation->inventory_item_id);

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

        $allocation->update([
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
