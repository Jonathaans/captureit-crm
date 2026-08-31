<?php

namespace Webkul\Warehouse\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Webkul\Warehouse\Models\InventoryAsset;
use Webkul\Warehouse\Models\InventoryAssetMaintenance;
use Webkul\Warehouse\Models\InventoryStockMovement;

class InventoryMaintenanceService
{
    public function start(
        InventoryAsset $asset,
        string $problem,
        ?string $technicianName,
        ?int $performedBy
    ): InventoryAssetMaintenance {
        return DB::transaction(function () use ($asset, $problem, $technicianName, $performedBy) {
            $asset = InventoryAsset::query()
                ->lockForUpdate()
                ->findOrFail($asset->id);

            if ($asset->status !== 'damaged') {
                throw ValidationException::withMessages([
                    'asset' => sprintf(
                        '%s hanya dapat masuk Maintenance dari status DAMAGED. Status saat ini: %s.',
                        $asset->asset_code,
                        strtoupper($asset->status)
                    ),
                ]);
            }

            $active = InventoryAssetMaintenance::query()
                ->where('inventory_asset_id', $asset->id)
                ->where('status', 'in_progress')
                ->lockForUpdate()
                ->first();

            if ($active) {
                throw ValidationException::withMessages([
                    'asset' => sprintf(
                        '%s sudah mempunyai Maintenance aktif %s.',
                        $asset->asset_code,
                        $active->reference_number ?: '#'.$active->id
                    ),
                ]);
            }

            $maintenance = InventoryAssetMaintenance::create([
                'inventory_asset_id' => $asset->id,
                'status' => 'in_progress',
                'problem' => trim($problem),
                'technician_name' => $technicianName ? trim($technicianName) : null,
                'repair_cost' => 0,
                'started_by' => $performedBy,
                'started_at' => now(),
            ]);

            $maintenance->update([
                'reference_number' => sprintf('MNT-%06d', $maintenance->id),
            ]);

            $asset->update([
                'status' => 'maintenance',
            ]);

            $this->recordMovement(
                $asset,
                $maintenance,
                'maintenance_started',
                'damaged',
                'maintenance',
                $performedBy,
                sprintf(
                    'Maintenance started. Problem: %s%s',
                    trim($problem),
                    $technicianName ? ' Technician: '.trim($technicianName).'.' : ''
                )
            );

            return $maintenance->fresh(['asset.item']);
        });
    }

    public function complete(
        InventoryAssetMaintenance $maintenance,
        string $resultCondition,
        ?string $technicianName,
        ?string $repairNotes,
        float $repairCost,
        ?int $performedBy
    ): InventoryAssetMaintenance {
        return DB::transaction(function () use (
            $maintenance,
            $resultCondition,
            $technicianName,
            $repairNotes,
            $repairCost,
            $performedBy
        ) {
            $maintenance = InventoryAssetMaintenance::query()
                ->lockForUpdate()
                ->findOrFail($maintenance->id);

            if ($maintenance->status !== 'in_progress') {
                throw ValidationException::withMessages([
                    'maintenance' => 'Maintenance ini sudah tidak IN PROGRESS.',
                ]);
            }

            if (! in_array($resultCondition, ['good', 'fair'], true)) {
                throw ValidationException::withMessages([
                    'result_condition' => 'Hasil repair hanya boleh GOOD atau FAIR.',
                ]);
            }

            $asset = InventoryAsset::query()
                ->lockForUpdate()
                ->findOrFail($maintenance->inventory_asset_id);

            if ($asset->status !== 'maintenance') {
                throw ValidationException::withMessages([
                    'asset' => sprintf(
                        '%s harus berstatus MAINTENANCE. Status saat ini: %s.',
                        $asset->asset_code,
                        strtoupper($asset->status)
                    ),
                ]);
            }

            $repairCost = max(round($repairCost, 2), 0);

            $asset->update([
                'status' => 'available',
                'condition' => $resultCondition,
            ]);

            $maintenance->update([
                'status' => 'completed',
                'technician_name' => $technicianName
                    ? trim($technicianName)
                    : $maintenance->technician_name,
                'repair_notes' => $repairNotes ? trim($repairNotes) : null,
                'repair_cost' => $repairCost,
                'result_condition' => $resultCondition,
                'completed_by' => $performedBy,
                'completed_at' => now(),
            ]);

            $this->recordMovement(
                $asset,
                $maintenance,
                'maintenance_completed',
                'maintenance',
                'available',
                $performedBy,
                sprintf(
                    'Maintenance completed. Result: %s. Repair cost: %s.%s',
                    strtoupper($resultCondition),
                    number_format($repairCost, 2, '.', ''),
                    $repairNotes ? ' Notes: '.trim($repairNotes) : ''
                )
            );

            return $maintenance->fresh([
                'asset.item',
                'startedBy',
                'completedBy',
            ]);
        });
    }

    public function retire(
        InventoryAssetMaintenance $maintenance,
        string $retirementReason,
        ?int $performedBy
    ): InventoryAssetMaintenance {
        return DB::transaction(function () use ($maintenance, $retirementReason, $performedBy) {
            $maintenance = InventoryAssetMaintenance::query()
                ->lockForUpdate()
                ->findOrFail($maintenance->id);

            if ($maintenance->status !== 'in_progress') {
                throw ValidationException::withMessages([
                    'maintenance' => 'Maintenance ini sudah tidak IN PROGRESS.',
                ]);
            }

            $asset = InventoryAsset::query()
                ->lockForUpdate()
                ->findOrFail($maintenance->inventory_asset_id);

            if ($asset->status !== 'maintenance') {
                throw ValidationException::withMessages([
                    'asset' => sprintf(
                        '%s harus berstatus MAINTENANCE sebelum RETIRED.',
                        $asset->asset_code
                    ),
                ]);
            }

            $asset->update([
                'status' => 'retired',
                'condition' => 'damaged',
            ]);

            $maintenance->update([
                'status' => 'retired',
                'retirement_reason' => trim($retirementReason),
                'retired_by' => $performedBy,
                'retired_at' => now(),
            ]);

            $this->recordMovement(
                $asset,
                $maintenance,
                'asset_retired',
                'maintenance',
                'retired',
                $performedBy,
                'Asset retired after maintenance inspection. Reason: '.trim($retirementReason)
            );

            return $maintenance->fresh([
                'asset.item',
                'startedBy',
                'retiredBy',
            ]);
        });
    }

    private function recordMovement(
        InventoryAsset $asset,
        InventoryAssetMaintenance $maintenance,
        string $movementType,
        string $fromStatus,
        string $toStatus,
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
            'reference_type' => 'inventory_maintenance',
            'reference_id' => $maintenance->id,
            'reference_number' => $maintenance->reference_number,
            'performed_by' => $performedBy,
            'notes' => $notes,
            'occurred_at' => now(),
        ]);
    }
}
