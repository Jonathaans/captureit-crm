<?php

declare(strict_types=1);

/**
 * Read-only checker for Inventory Movement Live + Damage Alert Detail V1.
 *
 * Run from the CRM project root:
 * php tools/check_inventory_movement_live_damage_alert_v1.php
 */

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$root = dirname(__DIR__);
$errors = [];

function inventoryMovementLiveV1Check(
    bool $condition,
    string $success,
    string $failure
): void {
    global $errors;

    if ($condition) {
        echo "[OK]   {$success}\n";

        return;
    }

    echo "[FAIL] {$failure}\n";
    $errors[] = $failure;
}

echo "CHECK INVENTORY MOVEMENT LIVE + DAMAGE ALERT DETAIL V1\n";
echo "=======================================================\n\n";

foreach ([$root.'/vendor/autoload.php', $root.'/bootstrap/app.php'] as $path) {
    if (! is_file($path)) {
        fwrite(STDERR, "File tidak ditemukan: {$path}\n");
        exit(1);
    }
}

require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$paths = [
    'Movement Controller' => $root.'/packages/Webkul/Admin/src/Http/Controllers/Inventory/InventoryMovementController.php',
    'Movement DataGrid' => $root.'/packages/Webkul/Admin/src/DataGrids/Inventory/InventoryMovementDataGrid.php',
    'Movement View' => $root.'/packages/Webkul/Admin/src/Resources/views/inventory/movements/index.blade.php',
    'Alert Controller' => $root.'/packages/Webkul/Admin/src/Http/Controllers/Inventory/InventoryAlertController.php',
    'Alert View' => $root.'/packages/Webkul/Admin/src/Resources/views/inventory/alerts/index.blade.php',
    'Movement Test' => $root.'/tests/Unit/InventoryMovementLiveTest.php',
    'Alert Test' => $root.'/tests/Unit/InventoryDamageAlertReasonTest.php',
];

$contents = [];

foreach ($paths as $label => $path) {
    $exists = is_file($path);

    inventoryMovementLiveV1Check(
        $exists,
        "{$label} tersedia.",
        "{$label} tidak ditemukan: {$path}"
    );

    $contents[$label] = $exists
        ? (string) file_get_contents($path)
        : '';
}

$checks = [
    'Movement AJAX memakai no-store' =>
        str_contains($contents['Movement Controller'], 'INVENTORY MOVEMENT LIVE V1')
        && str_contains(
            $contents['Movement Controller'],
            'no-store, no-cache, must-revalidate'
        ),

    'Movement default terbaru lebih dahulu' =>
        str_contains($contents['Movement DataGrid'], 'INVENTORY MOVEMENT LIVE V1')
        && str_contains(
            $contents['Movement DataGrid'],
            "protected \$sortColumn = 'occurred_at'"
        )
        && str_contains(
            $contents['Movement DataGrid'],
            "protected \$sortOrder = 'desc'"
        ),

    'Movement auto-refresh setiap 10 detik' =>
        str_contains($contents['Movement View'], 'INVENTORY MOVEMENT LIVE V1')
        && str_contains($contents['Movement View'], 'reload-datagrids')
        && str_contains($contents['Movement View'], '10000')
        && str_contains($contents['Movement View'], 'Refresh Now'),

    'Movement memiliki Reset View dan state key baru' =>
        str_contains($contents['Movement View'], '_movement_live_v')
        && str_contains($contents['Movement View'], 'resetView')
        && str_contains($contents['Movement View'], "localStorage.getItem('datagrids')"),

    'Alert mengambil alasan DAMAGED terakhir' =>
        str_contains($contents['Alert Controller'], 'INVENTORY DAMAGE ALERT REASON V1')
        && str_contains($contents['Alert Controller'], 'latest_damage_notes')
        && str_contains($contents['Alert Controller'], 'damage_allocations.return_notes as damage_reason')
        && str_contains($contents['Alert Controller'], 'damage_reference'),

    'Alert menampilkan alasan dan sumber Return' =>
        str_contains($contents['Alert View'], 'INVENTORY DAMAGE ALERT REASON V1')
        && str_contains($contents['Alert View'], 'Alasan rusak:')
        && str_contains($contents['Alert View'], 'Sumber Return:')
        && str_contains($contents['Alert View'], "\$alert['damage_reason']"),

    'Search dan CSV alert membawa alasan rusak' =>
        substr_count($contents['Alert Controller'], "\$alert['damage_reason'] ?? ''") >= 2
        && str_contains($contents['Alert Controller'], "'Damage Reason'")
        && str_contains($contents['Alert Controller'], "'Damage Reference'"),
];

foreach ($checks as $label => $ok) {
    inventoryMovementLiveV1Check(
        $ok,
        $label,
        $label.' belum terpasang dengan benar.'
    );
}

$hasMovements = Schema::hasTable('inventory_stock_movements');
inventoryMovementLiveV1Check(
    $hasMovements,
    'Tabel inventory_stock_movements tersedia.',
    'Tabel inventory_stock_movements tidak ditemukan.'
);

$hasAllocations = Schema::hasTable('delivery_order_inventory_allocations');
inventoryMovementLiveV1Check(
    $hasAllocations,
    'Tabel delivery_order_inventory_allocations tersedia.',
    'Tabel delivery_order_inventory_allocations tidak ditemukan.'
);

if ($hasAllocations) {
    inventoryMovementLiveV1Check(
        Schema::hasColumn(
            'delivery_order_inventory_allocations',
            'return_notes'
        ),
        'Kolom return_notes tersedia.',
        'Kolom return_notes tidak ditemukan.'
    );
}

echo "\nDATA DIAGNOSTIC (read-only)\n";

if ($hasMovements) {
    $movementCount = DB::table('inventory_stock_movements')->count();
    $latestMovement = DB::table('inventory_stock_movements')
        ->orderByDesc('occurred_at')
        ->orderByDesc('id')
        ->first([
            'id',
            'movement_type',
            'reference_number',
            'occurred_at',
        ]);

    echo "[INFO] Total movement: {$movementCount}\n";
    echo '[INFO] Movement terbaru: '.(
        $latestMovement
            ? sprintf(
                '#%s | %s | %s | %s',
                $latestMovement->id,
                strtoupper((string) $latestMovement->movement_type),
                $latestMovement->reference_number ?: '-',
                $latestMovement->occurred_at ?: '-'
            )
            : '-'
    )."\n";
}

if (
    $hasAllocations
    && Schema::hasColumn(
        'delivery_order_inventory_allocations',
        'return_notes'
    )
) {
    $damageNotes = DB::table('delivery_order_inventory_allocations')
        ->where('tracking_type', 'serialized')
        ->where('status', 'returned')
        ->where('return_condition', 'damaged')
        ->whereNotNull('return_notes')
        ->where('return_notes', '<>', '')
        ->count();

    echo "[INFO] Return DAMAGED dengan alasan: {$damageNotes}\n";
}

echo PHP_EOL;

if ($errors !== []) {
    echo '[FAIL] Checker menemukan '.count($errors)." masalah.\n";
    exit(1);
}

echo "[PASS] Movement live dan detail Damage Alert siap digunakan.\n";
echo "Browser test: buka Movement, Reset View, lalu cek auto-refresh maksimal 10 detik.\n";
