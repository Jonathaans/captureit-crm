<?php

namespace Webkul\Admin\Http\Controllers\Inventory;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Webkul\Admin\Http\Controllers\Controller;

class InventoryQaController extends Controller
{
    public function index(): View
    {
        $report = $this->buildReport();

        return view(
            'admin::inventory.qa.index',
            $report
        );
    }

    public function exportCsv(): StreamedResponse
    {
        $report = $this->buildReport();

        return response()->streamDownload(
            function () use ($report) {
                $handle = fopen('php://output', 'w');

                if ($handle === false) {
                    return;
                }

                fwrite($handle, "\xEF\xBB\xBF");

                fputcsv(
                    $handle,
                    [
                        'Check',
                        'Category',
                        'Status',
                        'Summary',
                        'Count',
                        'Recommendation',
                    ],
                    ',',
                    '"',
                    ''
                );

                foreach ($report['checks'] as $check) {
                    fputcsv(
                        $handle,
                        [
                            $check['name'],
                            $check['category'],
                            strtoupper($check['status']),
                            $check['summary'],
                            $check['count'],
                            $check['recommendation'],
                        ],
                        ',',
                        '"',
                        ''
                    );
                }

                fclose($handle);
            },
            'warehouse-qa_'.now()->format('Ymd_His').'.csv',
            [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Cache-Control' => 'no-store, no-cache, must-revalidate',
            ]
        );
    }

    private function buildReport(): array
    {
        $checks = collect();

        $requiredTables = [
            'warehouses',
            'inventory_items',
            'inventory_assets',
            'inventory_stock_movements',
            'delivery_orders',
            'delivery_order_items',
            'delivery_order_inventory_allocations',
            'inventory_asset_maintenances',
            'inventory_stock_opname_sessions',
            'inventory_stock_opname_entries',
        ];

        $missingTables = collect($requiredTables)
            ->filter(
                fn ($table) => ! Schema::hasTable($table)
            )
            ->values();

        $checks->push(
            $this->check(
                'Core Inventory Schema',
                'Foundation',
                $missingTables->isEmpty() ? 'pass' : 'fail',
                $missingTables->isEmpty()
                    ? 'Semua tabel inti Inventory / SJ tersedia.'
                    : 'Ada tabel inti yang belum tersedia: '.$missingTables->implode(', '),
                $missingTables->count(),
                $missingTables->isEmpty()
                    ? 'Tidak ada tindakan.'
                    : 'Periksa migration status sebelum melakukan QA operasional.'
            )
        );

        if (Schema::hasTable('warehouses')) {
            $warehouseGroups = DB::table('warehouses')
                ->selectRaw('LOWER(TRIM(name)) as normalized_name, COUNT(*) as total')
                ->groupByRaw('LOWER(TRIM(name))')
                ->havingRaw('COUNT(*) > 1')
                ->get();

            $duplicateWarehouseRows = (int) $warehouseGroups->sum('total');

            $checks->push(
                $this->check(
                    'Duplicate Physical Warehouse',
                    'Foundation',
                    $warehouseGroups->isEmpty() ? 'pass' : 'warn',
                    $warehouseGroups->isEmpty()
                        ? 'Tidak ada nama warehouse duplikat.'
                        : $warehouseGroups->count().' nama warehouse memiliki row database duplikat.',
                    $duplicateWarehouseRows,
                    $warehouseGroups->isEmpty()
                        ? 'Tidak ada tindakan.'
                        : 'Hotfix single-warehouse melindungi Stock Opname, tetapi cleanup DB terkontrol tetap disarankan sebelum production.'
                )
            );
        }

        if (Schema::hasTable('inventory_assets')) {
            $duplicateAssetCodes = DB::table('inventory_assets')
                ->whereNotNull('asset_code')
                ->where('asset_code', '<>', '')
                ->select('asset_code')
                ->groupBy('asset_code')
                ->havingRaw('COUNT(*) > 1')
                ->count();

            $checks->push(
                $this->check(
                    'Unique Asset Code',
                    'Serialized Assets',
                    $duplicateAssetCodes === 0 ? 'pass' : 'fail',
                    $duplicateAssetCodes === 0
                        ? 'Semua asset_code unik.'
                        : $duplicateAssetCodes.' asset_code memiliki duplikasi.',
                    $duplicateAssetCodes,
                    $duplicateAssetCodes === 0
                        ? 'Tidak ada tindakan.'
                        : 'Jangan operasikan scanner sebelum duplicate asset code diperbaiki.'
                )
            );

            $duplicateBarcodes = DB::table('inventory_assets')
                ->whereNotNull('barcode_value')
                ->where('barcode_value', '<>', '')
                ->select('barcode_value')
                ->groupBy('barcode_value')
                ->havingRaw('COUNT(*) > 1')
                ->count();

            $checks->push(
                $this->check(
                    'Unique QR / Barcode',
                    'Serialized Assets',
                    $duplicateBarcodes === 0 ? 'pass' : 'fail',
                    $duplicateBarcodes === 0
                        ? 'Semua barcode_value unik.'
                        : $duplicateBarcodes.' QR / barcode memiliki duplikasi.',
                    $duplicateBarcodes,
                    $duplicateBarcodes === 0
                        ? 'Scanner aman dari collision barcode.'
                        : 'Reprint QR setelah barcode duplicate diperbaiki.'
                )
            );

            $validStatuses = [
                'available',
                'allocated',
                'picked',
                'out',
                'return_pending',
                'maintenance',
                'damaged',
                'missing',
                'retired',
            ];

            $invalidAssetStatuses = DB::table('inventory_assets')
                ->whereNotIn('status', $validStatuses)
                ->count();

            $checks->push(
                $this->check(
                    'Serialized Status Integrity',
                    'Serialized Assets',
                    $invalidAssetStatuses === 0 ? 'pass' : 'fail',
                    $invalidAssetStatuses === 0
                        ? 'Semua serialized asset memakai status lifecycle yang valid.'
                        : $invalidAssetStatuses.' asset memakai status di luar lifecycle yang dikenal.',
                    $invalidAssetStatuses,
                    $invalidAssetStatuses === 0
                        ? 'Tidak ada tindakan.'
                        : 'Audit asset status sebelum Allocation / Return dijalankan.'
                )
            );
        }

        if (Schema::hasTable('inventory_items')) {
            $negativeQuantity = DB::table('inventory_items')
                ->where('tracking_type', 'quantity')
                ->where('quantity_on_hand', '<', 0)
                ->count();

            $checks->push(
                $this->check(
                    'Quantity Stock Non-Negative',
                    'Quantity Stock',
                    $negativeQuantity === 0 ? 'pass' : 'fail',
                    $negativeQuantity === 0
                        ? 'Tidak ada quantity_on_hand negatif.'
                        : $negativeQuantity.' quantity item memiliki stock negatif.',
                    $negativeQuantity,
                    $negativeQuantity === 0
                        ? 'Tidak ada tindakan.'
                        : 'Lakukan rekonsiliasi movement / Stock Opname sebelum event berikutnya.'
                )
            );

            /*
             * Do not use GROUP BY + count() on SELECT * from this join.
             * MySQL wraps grouped count queries in a subquery, and because
             * both tables have an "id" column it can fail with:
             * Duplicate column name 'id'.
             *
             * whereNotExists() expresses the rule directly:
             * active serialized Inventory Item with no physical asset.
             */
            $serializedWithoutAssets = DB::table('inventory_items')
                ->where('inventory_items.tracking_type', 'serialized')
                ->where('inventory_items.is_active', true)
                ->whereNotExists(function ($query) {
                    $query
                        ->selectRaw('1')
                        ->from('inventory_assets')
                        ->whereColumn(
                            'inventory_assets.inventory_item_id',
                            'inventory_items.id'
                        );
                })
                ->count();

            $checks->push(
                $this->check(
                    'Serialized Master Has Physical Asset',
                    'Serialized Assets',
                    $serializedWithoutAssets === 0 ? 'pass' : 'warn',
                    $serializedWithoutAssets === 0
                        ? 'Semua serialized Inventory Item aktif memiliki physical asset.'
                        : $serializedWithoutAssets.' serialized item aktif belum memiliki asset fisik.',
                    $serializedWithoutAssets,
                    $serializedWithoutAssets === 0
                        ? 'Tidak ada tindakan.'
                        : 'Register physical asset / Bulk Create sebelum item dipakai pada SJ.'
                )
            );
        }

        if (
            Schema::hasTable('delivery_order_inventory_allocations')
            && Schema::hasTable('inventory_assets')
        ) {
            $activeStatuses = [
                'allocated',
                'picked',
                'out',
                'return_pending',
            ];

            $duplicateActiveAssets = DB::table('delivery_order_inventory_allocations')
                ->whereNotNull('inventory_asset_id')
                ->whereIn('status', $activeStatuses)
                ->select('inventory_asset_id')
                ->groupBy('inventory_asset_id')
                ->havingRaw('COUNT(DISTINCT delivery_order_id) > 1')
                ->count();

            $checks->push(
                $this->check(
                    'Double Event Asset Protection',
                    'Delivery Order',
                    $duplicateActiveAssets === 0 ? 'pass' : 'fail',
                    $duplicateActiveAssets === 0
                        ? 'Tidak ada serialized asset aktif pada lebih dari satu SJ.'
                        : $duplicateActiveAssets.' serialized asset terikat aktif pada lebih dari satu SJ.',
                    $duplicateActiveAssets,
                    $duplicateActiveAssets === 0
                        ? 'Double-event protection konsisten.'
                        : 'Jangan Issue SJ terkait sampai duplicate active allocation direkonsiliasi.'
                )
            );

            $stateMismatch = DB::table('delivery_order_inventory_allocations as a')
                ->join(
                    'inventory_assets as ia',
                    'ia.id',
                    '=',
                    'a.inventory_asset_id'
                )
                ->whereNotNull('a.inventory_asset_id')
                ->where(function ($query) {
                    $query
                        ->where(function ($q) {
                            $q->where('a.status', 'allocated')
                                ->where('ia.status', '<>', 'allocated');
                        })
                        ->orWhere(function ($q) {
                            $q->where('a.status', 'out')
                                ->where('ia.status', '<>', 'out');
                        })
                        ->orWhere(function ($q) {
                            $q->where('a.status', 'return_pending')
                                ->where('ia.status', '<>', 'return_pending');
                        });
                })
                ->count();

            $checks->push(
                $this->check(
                    'Allocation ↔ Asset Status Sync',
                    'Delivery Order',
                    $stateMismatch === 0 ? 'pass' : 'fail',
                    $stateMismatch === 0
                        ? 'Status allocation aktif sinkron dengan status physical asset.'
                        : $stateMismatch.' allocation tidak sinkron dengan asset status.',
                    $stateMismatch,
                    $stateMismatch === 0
                        ? 'Tidak ada tindakan.'
                        : 'Audit SJ terkait dan Inventory Movements sebelum melakukan perubahan manual.'
                )
            );
        }

        if (Schema::hasTable('inventory_stock_movements')) {
            $missingSjReference = DB::table('inventory_stock_movements')
                ->where('reference_type', 'delivery_order')
                ->where(function ($query) {
                    $query
                        ->whereNull('reference_id')
                        ->orWhereNull('reference_number')
                        ->orWhere('reference_number', '');
                })
                ->count();

            $checks->push(
                $this->check(
                    'Movement Has SJ Reference',
                    'Audit Trail',
                    $missingSjReference === 0 ? 'pass' : 'fail',
                    $missingSjReference === 0
                        ? 'Semua movement bertipe delivery_order memiliki ID dan nomor SJ.'
                        : $missingSjReference.' movement delivery_order kehilangan reference SJ.',
                    $missingSjReference,
                    $missingSjReference === 0
                        ? 'Audit trail event memiliki referensi SJ.'
                        : 'Perbaiki source movement, jangan hanya edit tampilan Reference.'
                )
            );

            if (Schema::hasTable('delivery_orders')) {
                $orphanSjReferences = DB::table('inventory_stock_movements as m')
                    ->leftJoin(
                        'delivery_orders as d',
                        'd.id',
                        '=',
                        'm.reference_id'
                    )
                    ->where('m.reference_type', 'delivery_order')
                    ->whereNotNull('m.reference_id')
                    ->whereNull('d.id')
                    ->count();

                $checks->push(
                    $this->check(
                        'Movement SJ Reference Exists',
                        'Audit Trail',
                        $orphanSjReferences === 0 ? 'pass' : 'fail',
                        $orphanSjReferences === 0
                            ? 'Tidak ada movement yang menunjuk ke SJ yang hilang.'
                            : $orphanSjReferences.' movement menunjuk ke delivery_order yang tidak ditemukan.',
                        $orphanSjReferences,
                        $orphanSjReferences === 0
                            ? 'Tidak ada tindakan.'
                            : 'Periksa data delete/import sebelum production.'
                    )
                );
            }
        }

        if (
            Schema::hasTable('inventory_asset_maintenances')
            && Schema::hasTable('inventory_assets')
        ) {
            $maintenanceWithoutJob = DB::table('inventory_assets as ia')
                ->leftJoin(
                    'inventory_asset_maintenances as m',
                    function ($join) {
                        $join
                            ->on(
                                'm.inventory_asset_id',
                                '=',
                                'ia.id'
                            )
                            ->where(
                                'm.status',
                                '=',
                                'in_progress'
                            );
                    }
                )
                ->where('ia.status', 'maintenance')
                ->whereNull('m.id')
                ->count();

            $activeJobWrongAssetStatus = DB::table('inventory_asset_maintenances as m')
                ->join(
                    'inventory_assets as ia',
                    'ia.id',
                    '=',
                    'm.inventory_asset_id'
                )
                ->where('m.status', 'in_progress')
                ->where('ia.status', '<>', 'maintenance')
                ->count();

            $maintenanceMismatch =
                $maintenanceWithoutJob
                + $activeJobWrongAssetStatus;

            $checks->push(
                $this->check(
                    'Maintenance Lifecycle Sync',
                    'Maintenance',
                    $maintenanceMismatch === 0 ? 'pass' : 'fail',
                    $maintenanceMismatch === 0
                        ? 'Asset MAINTENANCE dan maintenance job aktif saling sinkron.'
                        : $maintenanceMismatch.' maintenance record / asset status tidak sinkron.',
                    $maintenanceMismatch,
                    $maintenanceMismatch === 0
                        ? 'Tidak ada tindakan.'
                        : 'Selesaikan mismatch melalui workflow Maintenance, bukan edit status asset langsung.'
                )
            );
        }

        if (
            Schema::hasTable('inventory_stock_opname_sessions')
            && Schema::hasTable('inventory_stock_opname_entries')
        ) {
            $finalizedPending = DB::table('inventory_stock_opname_sessions as s')
                ->join(
                    'inventory_stock_opname_entries as e',
                    'e.stock_opname_session_id',
                    '=',
                    's.id'
                )
                ->where('s.status', 'finalized')
                ->where('e.result', 'pending')
                ->count();

            $checks->push(
                $this->check(
                    'Finalized Stock Opname Has No Pending Entry',
                    'Stock Opname',
                    $finalizedPending === 0 ? 'pass' : 'fail',
                    $finalizedPending === 0
                        ? 'Tidak ada finalized Stock Opname dengan entry PENDING.'
                        : $finalizedPending.' entry masih PENDING pada session finalized.',
                    $finalizedPending,
                    $finalizedPending === 0
                        ? 'Stock Opname audit state konsisten.'
                        : 'Audit session terkait sebelum digunakan sebagai bukti stocktake.'
                )
            );

            $duplicateScannedAssets = DB::table('inventory_stock_opname_entries')
                ->whereNotNull('inventory_asset_id')
                ->select(
                    'stock_opname_session_id',
                    'inventory_asset_id'
                )
                ->groupBy(
                    'stock_opname_session_id',
                    'inventory_asset_id'
                )
                ->havingRaw('COUNT(*) > 1')
                ->count();

            $checks->push(
                $this->check(
                    'Stock Opname No Double Asset Entry',
                    'Stock Opname',
                    $duplicateScannedAssets === 0 ? 'pass' : 'fail',
                    $duplicateScannedAssets === 0
                        ? 'Satu asset hanya memiliki satu entry per Stock Opname session.'
                        : $duplicateScannedAssets.' duplicate asset entry ditemukan.',
                    $duplicateScannedAssets,
                    $duplicateScannedAssets === 0
                        ? 'Duplicate scan protection konsisten.'
                        : 'Periksa unique constraint dan histori session.'
                )
            );
        }

        $checks = $checks->values();

        $summary = [
            'total' => $checks->count(),
            'pass' => $checks->where('status', 'pass')->count(),
            'warn' => $checks->where('status', 'warn')->count(),
            'fail' => $checks->where('status', 'fail')->count(),
        ];

        $summary['health_percent'] = $summary['total'] > 0
            ? (int) round(
                (
                    (
                        $summary['pass']
                        + ($summary['warn'] * 0.5)
                    )
                    / $summary['total']
                ) * 100
            )
            : 100;

        $manualFlow = collect([
            [
                'step' => 1,
                'title' => 'Product Equipment Template',
                'action' => 'Pastikan equipment template sudah dipetakan ke Inventory Item yang benar.',
                'expected' => 'Serialized/quantity requirement terbentuk sesuai master.',
            ],
            [
                'step' => 2,
                'title' => 'Generate Surat Jalan',
                'action' => 'Generate SJ dari Invoice/Quote flow yang valid.',
                'expected' => 'Requirement snapshot membawa inventory_item_id dan tidak berubah jika template diedit kemudian.',
            ],
            [
                'step' => 3,
                'title' => 'Scan Allocation',
                'action' => 'Scan satu serialized asset AVAILABLE ke SJ.',
                'expected' => 'Asset AVAILABLE → ALLOCATED, movement ALLOCATED memiliki reference nomor SJ.',
            ],
            [
                'step' => 4,
                'title' => 'Double Event Protection',
                'action' => 'Coba scan asset yang sama ke SJ kedua yang masih aktif.',
                'expected' => 'Sistem menolak dan menampilkan SJ yang sedang memakai asset tersebut.',
            ],
            [
                'step' => 5,
                'title' => 'Prepare Quantity',
                'action' => 'Isi prepared quantity untuk Paper Roll / Frame.',
                'expected' => 'Requirement hanya complete saat prepared quantity sama dengan request.',
            ],
            [
                'step' => 6,
                'title' => 'Issue / Release',
                'action' => 'Issue SJ setelah semua requirement lengkap.',
                'expected' => 'Serialized ALLOCATED → OUT; quantity physical stock berkurang; movement OUT memakai nomor SJ.',
            ],
            [
                'step' => 7,
                'title' => 'Return Scan',
                'action' => 'Setelah Delivered, scan asset yang kembali.',
                'expected' => 'OUT → RETURN_PENDING; scan belum mengubah condition atau AVAILABLE.',
            ],
            [
                'step' => 8,
                'title' => 'Finalize Return',
                'action' => 'Pilih GOOD/FAIR/DAMAGED dan finalize return.',
                'expected' => 'GOOD/FAIR → AVAILABLE; DAMAGED → DAMAGED; movement tetap mereferensikan SJ.',
            ],
            [
                'step' => 9,
                'title' => 'Maintenance',
                'action' => 'Kirim satu DAMAGED asset ke Maintenance lalu complete GOOD/FAIR.',
                'expected' => 'DAMAGED → MAINTENANCE → AVAILABLE dan MNT history tercatat.',
            ],
            [
                'step' => 10,
                'title' => 'Stock Opname',
                'action' => 'Buat session baru, scan serialized asset, count quantity, Review, lalu Finalize.',
                'expected' => 'Tidak ada double count; Missing tetap boleh finalize; movement adjustment tercatat.',
            ],
            [
                'step' => 11,
                'title' => 'Alerts & Reorder',
                'action' => 'Turunkan satu quantity item sampai minimum/0 atau gunakan test asset problem.',
                'expected' => 'Low/Critical/Missing/Maintenance/Return Pending alert muncul sesuai state.',
            ],
            [
                'step' => 12,
                'title' => 'Movement Audit',
                'action' => 'Buka Inventory Movements dan filter Reference Type SURAT JALAN.',
                'expected' => 'ALLOCATED/OUT/RETURN movements dapat ditelusuri kembali ke detail SJ.',
            ],
        ]);

        return compact(
            'checks',
            'summary',
            'manualFlow'
        );
    }

    private function check(
        string $name,
        string $category,
        string $status,
        string $summary,
        int $count,
        string $recommendation
    ): array {
        return compact(
            'name',
            'category',
            'status',
            'summary',
            'count',
            'recommendation'
        );
    }
}
