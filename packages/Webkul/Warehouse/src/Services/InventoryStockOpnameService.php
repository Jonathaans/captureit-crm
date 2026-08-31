<?php

namespace Webkul\Warehouse\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Webkul\Warehouse\Models\InventoryAsset;
use Webkul\Warehouse\Models\InventoryItem;
use Webkul\Warehouse\Models\InventoryStockMovement;
use Webkul\Warehouse\Models\InventoryStockOpnameEntry;
use Webkul\Warehouse\Models\InventoryStockOpnameSession;

class InventoryStockOpnameService
{
    /**
     * Assets in these statuses are expected to be physically present
     * in the warehouse during normal operation.
     *
     * OUT / RETURN_PENDING / MAINTENANCE / MISSING / RETIRED are not
     * expected physically in the active warehouse count. If scanned,
     * they become STATUS CONFLICT and are never silently corrected.
     */
    private const EXPECTED_PRESENT_STATUSES = [
        'available',
        'allocated',
        'picked',
        'damaged',
    ];

    public function createSession(
        int $warehouseId,
        ?string $notes,
        ?int $performedBy
    ): InventoryStockOpnameSession {
        /*
         * Defensive canonicalization for stale forms / direct requests.
         * A duplicate row with the same normalized warehouse name is
         * redirected to the row that actually owns inventory.
         */
        $warehouseId = $this->canonicalWarehouseId(
            $warehouseId
        );

        $existing = InventoryStockOpnameSession::query()
            ->where('warehouse_id', $warehouseId)
            ->whereIn(
                'status',
                InventoryStockOpnameSession::OPEN_STATUSES
            )
            ->orderByDesc('id')
            ->first();

        if ($existing) {
            throw ValidationException::withMessages([
                'warehouse_id' => sprintf(
                    'Warehouse ini masih mempunyai Stock Opname aktif %s (%s). Selesaikan session tersebut terlebih dahulu.',
                    $existing->reference_number ?: '#'.$existing->id,
                    strtoupper($existing->status)
                ),
            ]);
        }

        return DB::transaction(function () use (
            $warehouseId,
            $notes,
            $performedBy
        ) {
            $session = InventoryStockOpnameSession::create([
                'warehouse_id' => $warehouseId,
                'status' => 'draft',
                'notes' => $notes ? trim($notes) : null,
                'created_by' => $performedBy,
            ]);

            $session->update([
                'reference_number' => sprintf(
                    'SO-%s-%04d',
                    now()->format('ym'),
                    $session->id
                ),
            ]);

            return $session->fresh(['warehouse']);
        });
    }

    public function start(
        InventoryStockOpnameSession $session,
        ?int $performedBy
    ): InventoryStockOpnameSession {
        return DB::transaction(function () use (
            $session,
            $performedBy
        ) {
            $session = InventoryStockOpnameSession::query()
                ->lockForUpdate()
                ->findOrFail($session->id);

            if ($session->status !== 'draft') {
                throw ValidationException::withMessages([
                    'session' => 'Hanya Stock Opname DRAFT yang dapat dimulai.',
                ]);
            }

            /*
             * Snapshot every serialized asset assigned to this warehouse.
             * Even normally-absent statuses are snapshotted so scanning an
             * OUT / MISSING / RETIRED asset can produce a useful conflict.
             */
            InventoryAsset::query()
                ->with('item')
                ->where('warehouse_id', $session->warehouse_id)
                ->whereHas(
                    'item',
                    fn ($query) => $query->where(
                        'tracking_type',
                        'serialized'
                    )
                )
                ->orderBy('id')
                ->chunkById(200, function ($assets) use ($session) {
                    foreach ($assets as $asset) {
                        InventoryStockOpnameEntry::create([
                            'stock_opname_session_id' => $session->id,
                            'entry_type' => 'serialized',
                            'inventory_item_id' => $asset->inventory_item_id,
                            'inventory_asset_id' => $asset->id,
                            'expected_presence' => in_array(
                                $asset->status,
                                self::EXPECTED_PRESENT_STATUSES,
                                true
                            ),
                            'expected_status' => $asset->status,
                            'expected_condition' => $asset->condition,
                            'result' => 'pending',
                        ]);
                    }
                });

            /*
             * Snapshot active quantity-tracked items.
             */
            InventoryItem::query()
                ->where('warehouse_id', $session->warehouse_id)
                ->where('tracking_type', 'quantity')
                ->where('is_active', true)
                ->orderBy('id')
                ->chunkById(200, function ($items) use ($session) {
                    foreach ($items as $item) {
                        InventoryStockOpnameEntry::create([
                            'stock_opname_session_id' => $session->id,
                            'entry_type' => 'quantity',
                            'inventory_item_id' => $item->id,
                            'expected_presence' => true,
                            'system_quantity' => $item->quantity_on_hand,
                            'result' => 'pending',
                        ]);
                    }
                });

            $session->update([
                'status' => 'in_progress',
                'started_by' => $performedBy,
                'started_at' => now(),
            ]);

            return $session->fresh([
                'warehouse',
                'entries.item',
                'entries.asset',
            ]);
        });
    }

    /**
     * Global scanner entry point.
     *
     * Scan is observation only. It does NOT change inventory asset status.
     */
    public function scan(
        InventoryStockOpnameSession $session,
        string $code,
        ?int $performedBy
    ): array {
        $this->assertInProgress($session);

        $code = trim($code);

        if ($code === '') {
            throw ValidationException::withMessages([
                'barcode' => 'QR / Barcode tidak boleh kosong.',
            ]);
        }

        return DB::transaction(function () use (
            $session,
            $code,
            $performedBy
        ) {
            $session = InventoryStockOpnameSession::query()
                ->lockForUpdate()
                ->findOrFail($session->id);

            if ($session->status !== 'in_progress') {
                throw ValidationException::withMessages([
                    'barcode' => 'Scanner hanya aktif saat Stock Opname IN PROGRESS.',
                ]);
            }

            $asset = InventoryAsset::query()
                ->with('item')
                ->where(function ($query) use ($code) {
                    $query
                        ->where('barcode_value', $code)
                        ->orWhere('asset_code', $code);
                })
                ->first();

            if (! $asset) {
                $entry = InventoryStockOpnameEntry::query()
                    ->where('stock_opname_session_id', $session->id)
                    ->where('entry_type', 'unknown')
                    ->where('scan_value', $code)
                    ->lockForUpdate()
                    ->first();

                $duplicate = (bool) $entry;

                if (! $entry) {
                    $entry = InventoryStockOpnameEntry::create([
                        'stock_opname_session_id' => $session->id,
                        'entry_type' => 'unknown',
                        'scan_value' => $code,
                        'expected_presence' => false,
                        'result' => 'unknown',
                        'scanned_by' => $performedBy,
                        'scanned_at' => now(),
                        'notes' => 'QR / Barcode tidak terdaftar di Inventory Assets.',
                    ]);
                }

                return [
                    'entry' => $entry,
                    'duplicate' => $duplicate,
                    'message' => sprintf(
                        'UNKNOWN ASSET: %s tidak terdaftar di Inventory Assets.',
                        $code
                    ),
                    'type' => 'error',
                    'summary' => $this->summary($session->id),
                ];
            }

            $entry = InventoryStockOpnameEntry::query()
                ->where('stock_opname_session_id', $session->id)
                ->where('inventory_asset_id', $asset->id)
                ->lockForUpdate()
                ->first();

            if (! $entry) {
                /*
                 * Asset may have been moved into this warehouse after the
                 * session snapshot. Keep it as an unexpected observation.
                 */
                $entry = InventoryStockOpnameEntry::create([
                    'stock_opname_session_id' => $session->id,
                    'entry_type' => 'serialized',
                    'inventory_item_id' => $asset->inventory_item_id,
                    'inventory_asset_id' => $asset->id,
                    'expected_presence' => false,
                    'expected_status' => $asset->status,
                    'expected_condition' => $asset->condition,
                    'result' => 'pending',
                    'notes' => 'Asset tidak ada pada snapshot awal session.',
                ]);
            }

            if ($entry->scanned_at) {
                return [
                    'entry' => $entry->fresh(['asset.item']),
                    'duplicate' => true,
                    'message' => sprintf(
                        '%s sudah pernah discan pada session ini. Hasil: %s.',
                        $asset->asset_code,
                        strtoupper(
                            str_replace('_', ' ', $entry->result)
                        )
                    ),
                    'type' => 'warning',
                    'summary' => $this->summary($session->id),
                ];
            }

            $result = 'found';
            $message = sprintf(
                '%s FOUND.',
                $asset->asset_code
            );
            $type = 'success';

            if ((int) $asset->warehouse_id !== (int) $session->warehouse_id) {
                $result = 'unexpected';
                $message = sprintf(
                    'UNEXPECTED: %s terdaftar di warehouse lain tetapi ditemukan pada Stock Opname ini.',
                    $asset->asset_code
                );
                $type = 'error';
            } elseif (
                ! $entry->expected_presence
                || ! in_array(
                    $asset->status,
                    self::EXPECTED_PRESENT_STATUSES,
                    true
                )
                || (
                    $entry->expected_status
                    && $asset->status !== $entry->expected_status
                )
            ) {
                $result = 'status_conflict';
                $message = sprintf(
                    'STATUS CONFLICT: %s ditemukan fisik, snapshot %s, status sistem saat scan %s.',
                    $asset->asset_code,
                    strtoupper($entry->expected_status ?: '-'),
                    strtoupper($asset->status ?: '-')
                );
                $type = 'error';
            }

            $entry->update([
                'scan_value' => $code,
                'observed_status' => $asset->status,
                'result' => $result,
                'scanned_by' => $performedBy,
                'scanned_at' => now(),
            ]);

            return [
                'entry' => $entry->fresh(['asset.item']),
                'duplicate' => false,
                'message' => $message,
                'type' => $type,
                'summary' => $this->summary($session->id),
            ];
        });
    }

    public function countQuantity(
        InventoryStockOpnameSession $session,
        InventoryStockOpnameEntry $entry,
        float $actualQuantity,
        ?int $performedBy
    ): InventoryStockOpnameEntry {
        $this->assertInProgress($session);

        if (
            (int) $entry->stock_opname_session_id
            !== (int) $session->id
            || $entry->entry_type !== 'quantity'
        ) {
            throw ValidationException::withMessages([
                'actual_quantity' => 'Quantity entry tidak valid untuk session ini.',
            ]);
        }

        if ($actualQuantity < 0) {
            throw ValidationException::withMessages([
                'actual_quantity' => 'Actual quantity tidak boleh negatif.',
            ]);
        }

        return DB::transaction(function () use (
            $session,
            $entry,
            $actualQuantity,
            $performedBy
        ) {
            $session = InventoryStockOpnameSession::query()
                ->lockForUpdate()
                ->findOrFail($session->id);

            if ($session->status !== 'in_progress') {
                throw ValidationException::withMessages([
                    'actual_quantity' => 'Quantity count hanya dapat diubah saat IN PROGRESS.',
                ]);
            }

            $entry = InventoryStockOpnameEntry::query()
                ->lockForUpdate()
                ->findOrFail($entry->id);

            $actualQuantity = round($actualQuantity, 2);
            $systemQuantity = (float) $entry->system_quantity;
            $variance = round(
                $actualQuantity - $systemQuantity,
                2
            );

            $entry->update([
                'actual_quantity' => $actualQuantity,
                'variance' => $variance,
                'result' => abs($variance) < 0.005
                    ? 'matched'
                    : 'variance',
                'counted_by' => $performedBy,
                'counted_at' => now(),
            ]);

            return $entry->fresh(['item']);
        });
    }

    /**
     * Freeze current count results into REVIEW.
     *
     * Unscanned expected serialized assets become MISSING.
     * Unscanned assets that were not expected remain EXPECTED ABSENT.
     */
    public function review(
        InventoryStockOpnameSession $session,
        ?int $performedBy
    ): InventoryStockOpnameSession {
        $this->assertInProgress($session);

        return DB::transaction(function () use (
            $session,
            $performedBy
        ) {
            $session = InventoryStockOpnameSession::query()
                ->lockForUpdate()
                ->findOrFail($session->id);

            if ($session->status !== 'in_progress') {
                throw ValidationException::withMessages([
                    'session' => 'Session harus IN PROGRESS sebelum Review.',
                ]);
            }

            $uncountedQuantity = InventoryStockOpnameEntry::query()
                ->where('stock_opname_session_id', $session->id)
                ->where('entry_type', 'quantity')
                ->whereNull('actual_quantity')
                ->with('item')
                ->get();

            if ($uncountedQuantity->isNotEmpty()) {
                $names = $uncountedQuantity
                    ->pluck('item.name')
                    ->filter()
                    ->take(8)
                    ->implode(', ');

                throw ValidationException::withMessages([
                    'session' => sprintf(
                        'Quantity count belum lengkap: %s%s',
                        $names ?: $uncountedQuantity->count().' item',
                        $uncountedQuantity->count() > 8 ? ', ...' : ''
                    ),
                ]);
            }

            InventoryStockOpnameEntry::query()
                ->where('stock_opname_session_id', $session->id)
                ->where('entry_type', 'serialized')
                ->whereNull('scanned_at')
                ->where('expected_presence', true)
                ->update([
                    'result' => 'missing',
                ]);

            InventoryStockOpnameEntry::query()
                ->where('stock_opname_session_id', $session->id)
                ->where('entry_type', 'serialized')
                ->whereNull('scanned_at')
                ->where('expected_presence', false)
                ->update([
                    'result' => 'expected_absent',
                ]);

            $session->update([
                'status' => 'review',
                'reviewed_by' => $performedBy,
                'reviewed_at' => now(),
            ]);

            return $session->fresh([
                'warehouse',
                'entries.item',
                'entries.asset',
            ]);
        });
    }

    public function resume(
        InventoryStockOpnameSession $session
    ): InventoryStockOpnameSession {
        return DB::transaction(function () use ($session) {
            $session = InventoryStockOpnameSession::query()
                ->lockForUpdate()
                ->findOrFail($session->id);

            if ($session->status !== 'review') {
                throw ValidationException::withMessages([
                    'session' => 'Hanya session REVIEW yang dapat dikembalikan ke counting.',
                ]);
            }

            InventoryStockOpnameEntry::query()
                ->where('stock_opname_session_id', $session->id)
                ->where('entry_type', 'serialized')
                ->whereNull('scanned_at')
                ->whereIn('result', [
                    'missing',
                    'expected_absent',
                ])
                ->update([
                    'result' => 'pending',
                ]);

            $session->update([
                'status' => 'in_progress',
                'reviewed_by' => null,
                'reviewed_at' => null,
            ]);

            return $session->fresh();
        });
    }

    /**
     * Finalize physical count.
     *
     * Automatic corrections are deliberately conservative:
     *
     * 1. Quantity: current stock is adjusted to the physically counted qty.
     * 2. Serialized AVAILABLE/DAMAGED expected but not found -> MISSING,
     *    only if its current status still matches the snapshot.
     * 3. Previously MISSING serialized asset that is physically found ->
     *    AVAILABLE or DAMAGED based on its stored condition.
     * 4. OUT / RETURN_PENDING / MAINTENANCE / ALLOCATED conflicts are NOT
     *    silently corrected. They stay visible for workflow reconciliation.
     */
    public function finalize(
        InventoryStockOpnameSession $session,
        ?int $performedBy
    ): InventoryStockOpnameSession {
        return DB::transaction(function () use (
            $session,
            $performedBy
        ) {
            $session = InventoryStockOpnameSession::query()
                ->lockForUpdate()
                ->findOrFail($session->id);

            if ($session->status !== 'review') {
                throw ValidationException::withMessages([
                    'session' => 'Stock Opname harus berada pada status REVIEW sebelum Finalize.',
                ]);
            }

            $entries = InventoryStockOpnameEntry::query()
                ->where('stock_opname_session_id', $session->id)
                ->with(['item', 'asset'])
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach (
                $entries->where('entry_type', 'quantity')
                as $entry
            ) {
                if ($entry->actual_quantity === null) {
                    throw ValidationException::withMessages([
                        'session' => 'Masih ada quantity item yang belum dihitung.',
                    ]);
                }

                $item = InventoryItem::query()
                    ->lockForUpdate()
                    ->findOrFail($entry->inventory_item_id);

                $before = (float) $item->quantity_on_hand;
                $after = (float) $entry->actual_quantity;
                $difference = round($after - $before, 2);

                if (abs($difference) < 0.005) {
                    continue;
                }

                $item->update([
                    'quantity_on_hand' => $after,
                ]);

                $movementType = $difference > 0
                    ? 'stock_opname_adjustment_in'
                    : 'stock_opname_adjustment_out';

                $snapshotChanged = abs(
                    $before - (float) $entry->system_quantity
                ) >= 0.005;

                InventoryStockMovement::create([
                    'inventory_item_id' => $item->id,
                    'inventory_asset_id' => null,
                    'warehouse_id' => $item->warehouse_id,
                    'warehouse_location_id' => $item->warehouse_location_id,
                    'movement_type' => $movementType,
                    'quantity' => abs($difference),
                    'from_status' => null,
                    'to_status' => null,
                    'reference_type' => 'stock_opname',
                    'reference_id' => $session->id,
                    'reference_number' => $session->reference_number,
                    'performed_by' => $performedBy,
                    'notes' => sprintf(
                        'Stock Opname %s: stock %s %s -> actual count %s %s.%s',
                        $session->reference_number,
                        $this->formatQuantity($before),
                        $item->unit,
                        $this->formatQuantity($after),
                        $item->unit,
                        $snapshotChanged
                            ? sprintf(
                                ' Note: system stock changed after session snapshot (%s %s).',
                                $this->formatQuantity(
                                    (float) $entry->system_quantity
                                ),
                                $item->unit
                            )
                            : ''
                    ),
                    'occurred_at' => now(),
                ]);
            }

            foreach (
                $entries->where('entry_type', 'serialized')
                as $entry
            ) {
                if (! $entry->inventory_asset_id) {
                    continue;
                }

                $asset = InventoryAsset::query()
                    ->lockForUpdate()
                    ->find($entry->inventory_asset_id);

                if (! $asset) {
                    continue;
                }

                /*
                 * Expected physically, not scanned.
                 *
                 * AVAILABLE / DAMAGED can safely move to MISSING.
                 * ALLOCATED / PICKED are intentionally not touched because
                 * an active Delivery Order may own that lifecycle.
                 */
                if (
                    $entry->result === 'missing'
                    && in_array(
                        $entry->expected_status,
                        ['available', 'damaged'],
                        true
                    )
                    && $asset->status === $entry->expected_status
                    && (int) $asset->warehouse_id
                        === (int) $session->warehouse_id
                ) {
                    $before = $asset->status;

                    $asset->update([
                        'status' => 'missing',
                    ]);

                    $this->recordAssetMovement(
                        $session,
                        $asset,
                        'stock_opname_missing',
                        $before,
                        'missing',
                        $performedBy,
                        sprintf(
                            '%s tidak ditemukan pada Stock Opname %s.',
                            $asset->asset_code,
                            $session->reference_number
                        )
                    );

                    $this->appendEntryNote(
                        $entry,
                        'Finalize: asset diubah menjadi MISSING.'
                    );

                    continue;
                }

                /*
                 * Previously MISSING but physically found.
                 * Restore to AVAILABLE, or DAMAGED if the stored condition
                 * already says damaged.
                 */
                if (
                    $entry->scanned_at
                    && $entry->result === 'status_conflict'
                    && $asset->status === 'missing'
                    && (int) $asset->warehouse_id
                        === (int) $session->warehouse_id
                ) {
                    $target = $asset->condition === 'damaged'
                        ? 'damaged'
                        : 'available';

                    $asset->update([
                        'status' => $target,
                    ]);

                    $this->recordAssetMovement(
                        $session,
                        $asset,
                        'stock_opname_found',
                        'missing',
                        $target,
                        $performedBy,
                        sprintf(
                            '%s ditemukan kembali secara fisik pada Stock Opname %s.',
                            $asset->asset_code,
                            $session->reference_number
                        )
                    );

                    $this->appendEntryNote(
                        $entry,
                        'Finalize: asset MISSING ditemukan kembali dan dipulihkan ke '.strtoupper($target).'.'
                    );

                    continue;
                }

                /*
                 * Explicit audit note for unresolved workflow conflicts.
                 */
                if (
                    in_array(
                        $entry->result,
                        [
                            'status_conflict',
                            'unexpected',
                            'missing',
                        ],
                        true
                    )
                ) {
                    $this->appendEntryNote(
                        $entry,
                        sprintf(
                            'Finalize: tidak ada auto-correction. Current asset status %s. Perlu reconciliation workflow bila masih konflik.',
                            strtoupper($asset->status)
                        )
                    );
                }
            }

            $session->update([
                'status' => 'finalized',
                'finalized_by' => $performedBy,
                'finalized_at' => now(),
            ]);

            return $session->fresh([
                'warehouse',
                'entries.item',
                'entries.asset',
                'finalizedBy',
            ]);
        });
    }

    public function summary(int $sessionId): array
    {
        $base = InventoryStockOpnameEntry::query()
            ->where(
                'stock_opname_session_id',
                $sessionId
            );

        $serialized = (clone $base)
            ->where('entry_type', 'serialized');

        $quantity = (clone $base)
            ->where('entry_type', 'quantity');

        return [
            'expected' => (clone $serialized)
                ->where('expected_presence', true)
                ->count(),

            'scanned' => (clone $serialized)
                ->whereNotNull('scanned_at')
                ->count(),

            'found' => (clone $serialized)
                ->where('result', 'found')
                ->count(),

            'missing' => (clone $serialized)
                ->where('result', 'missing')
                ->count(),

            'conflicts' => (clone $serialized)
                ->whereIn(
                    'result',
                    ['status_conflict', 'unexpected']
                )
                ->count(),

            'unknown' => (clone $base)
                ->where('entry_type', 'unknown')
                ->count(),

            'quantity_total' => (clone $quantity)
                ->count(),

            'quantity_counted' => (clone $quantity)
                ->whereNotNull('actual_quantity')
                ->count(),

            'quantity_variance' => (clone $quantity)
                ->where('result', 'variance')
                ->count(),
        ];
    }

    private function canonicalWarehouseId(
        int $warehouseId
    ): int {
        $warehouse = DB::table('warehouses')
            ->where('id', $warehouseId)
            ->first([
                'id',
                'name',
            ]);

        if (! $warehouse) {
            return $warehouseId;
        }

        $normalizedName = mb_strtolower(
            trim((string) $warehouse->name)
        );

        $candidates = DB::table('warehouses')
            ->orderBy('id')
            ->get([
                'id',
                'name',
            ])
            ->filter(
                fn ($candidate) => mb_strtolower(
                    trim((string) $candidate->name)
                ) === $normalizedName
            )
            ->map(function ($candidate) {
                $candidate->asset_count = DB::table(
                    'inventory_assets'
                )
                    ->where(
                        'warehouse_id',
                        $candidate->id
                    )
                    ->count();

                $candidate->item_count = DB::table(
                    'inventory_items'
                )
                    ->where(
                        'warehouse_id',
                        $candidate->id
                    )
                    ->count();

                return $candidate;
            })
            ->sort(function ($left, $right) {
                if (
                    (int) $left->asset_count
                    !== (int) $right->asset_count
                ) {
                    return (int) $right->asset_count
                        <=> (int) $left->asset_count;
                }

                if (
                    (int) $left->item_count
                    !== (int) $right->item_count
                ) {
                    return (int) $right->item_count
                        <=> (int) $left->item_count;
                }

                return (int) $left->id
                    <=> (int) $right->id;
            })
            ->values();

        return (int) (
            $candidates->first()?->id
            ?: $warehouseId
        );
    }

    private function assertInProgress(
        InventoryStockOpnameSession $session
    ): void {
        if ($session->status !== 'in_progress') {
            throw ValidationException::withMessages([
                'session' => 'Stock Opname harus IN PROGRESS untuk melakukan counting.',
            ]);
        }
    }

    private function recordAssetMovement(
        InventoryStockOpnameSession $session,
        InventoryAsset $asset,
        string $movementType,
        ?string $fromStatus,
        ?string $toStatus,
        ?int $performedBy,
        string $notes
    ): InventoryStockMovement {
        return InventoryStockMovement::create([
            'inventory_item_id' => $asset->inventory_item_id,
            'inventory_asset_id' => $asset->id,
            'warehouse_id' => $asset->warehouse_id,
            'warehouse_location_id' => $asset->warehouse_location_id,
            'movement_type' => $movementType,
            'quantity' => 1,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'reference_type' => 'stock_opname',
            'reference_id' => $session->id,
            'reference_number' => $session->reference_number,
            'performed_by' => $performedBy,
            'notes' => $notes,
            'occurred_at' => now(),
        ]);
    }

    private function appendEntryNote(
        InventoryStockOpnameEntry $entry,
        string $note
    ): void {
        $existing = trim((string) $entry->notes);

        $entry->update([
            'notes' => $existing !== ''
                ? $existing."\n".$note
                : $note,
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
