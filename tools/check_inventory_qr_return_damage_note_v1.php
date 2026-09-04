<?php

declare(strict_types=1);

/**
 * Read-only checker for Inventory QR 20x10 mm + Return Damage Note V1.
 *
 * Run from project root:
 * php tools/check_inventory_qr_return_damage_note_v1.php
 */

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$root = dirname(__DIR__);
$errors = [];

function inventoryQrReturnV1Check(
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

echo "CHECK INVENTORY QR 20x10 MM + RETURN DAMAGE NOTE V1\n";
echo "====================================================\n\n";

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
    'QR View' => $root.'/packages/Webkul/Admin/src/Resources/views/inventory/assets/qr-labels.blade.php',
    'Return Controller' => $root.'/packages/Webkul/Admin/src/Http/Controllers/DeliveryOrder/DeliveryOrderReturnController.php',
    'Return Service' => $root.'/packages/Webkul/Invoice/src/Services/DeliveryOrderReturnService.php',
    'Return View' => $root.'/packages/Webkul/Admin/src/Resources/views/delivery-orders/return.blade.php',
    'QR Test' => $root.'/tests/Unit/InventoryQrLabel20x10LayoutTest.php',
    'Return Test' => $root.'/tests/Unit/DeliveryOrderReturnDamageNoteTest.php',
];

$contents = [];

foreach ($paths as $label => $path) {
    $exists = is_file($path);
    inventoryQrReturnV1Check(
        $exists,
        "{$label} tersedia.",
        "{$label} tidak ditemukan: {$path}"
    );
    $contents[$label] = $exists ? (string) file_get_contents($path) : '';
}

$checks = [
    'Label fisik tepat 20 x 10 mm' =>
        str_contains($contents['QR View'], 'INVENTORY QR LABEL 20X10MM V1')
        && str_contains($contents['QR View'], 'width: 20mm')
        && str_contains($contents['QR View'], 'height: 10mm'),

    'Grid A4 memuat 9 x 25 label' =>
        str_contains($contents['QR View'], 'grid-template-columns: repeat(9, 20mm)')
        && str_contains($contents['QR View'], 'grid-template-rows: repeat(25, 10mm)')
        && str_contains($contents['QR View'], '$assets->chunk(225)')
        && str_contains($contents['QR View'], '225 label / A4'),

    'QR tetap persegi agar tidak terdistorsi' =>
        substr_count($contents['QR View'], 'width: 8mm') >= 1
        && substr_count($contents['QR View'], 'height: 8mm') >= 1
        && str_contains($contents['QR View'], 'Print scale: 100% / Actual size'),

    'Controller menerima array return_notes' =>
        str_contains($contents['Return Controller'], 'RETURN DAMAGED NOTE V1')
        && str_contains($contents['Return Controller'], "'return_notes.*'")
        && str_contains($contents['Return Controller'], "'max:2000'"),

    'Controller mewajibkan alasan untuk DAMAGED' =>
        str_contains($contents['Return Controller'], "!== 'damaged'")
        && str_contains($contents['Return Controller'], 'Alasan kerusakan wajib diisi')
        && str_contains($contents['Return Controller'], 'ValidationException::withMessages'),

    'Service memvalidasi ulang dan menyimpan alasan' =>
        str_contains($contents['Return Service'], 'RETURN DAMAGED NOTE V1')
        && str_contains($contents['Return Service'], 'array $returnNotes = []')
        && str_contains($contents['Return Service'], '$damageNote')
        && str_contains($contents['Return Service'], "if (\$condition === 'damaged')")
        && str_contains(
            $contents['Return Service'],
            "if (\$condition === 'damaged' && (\$notes === null || \$notes === ''))"
        )
        && str_contains($contents['Return Service'], 'finalizeSerializedCheckIn'),

    'Form alasan muncul dinamis saat DAMAGED' =>
        str_contains($contents['Return View'], 'RETURN DAMAGED NOTE V1')
        && str_contains($contents['Return View'], 'data-damage-note-container')
        && str_contains($contents['Return View'], 'name="return_notes[{{ $allocation->id }}]"')
        && str_contains($contents['Return View'], 'syncDamageNoteField')
        && str_contains($contents['Return View'], "select.value === 'damaged'"),

    'Alasan rusak tampil kembali setelah Finalize' =>
        str_contains($contents['Return View'], '$allocation->return_notes')
        && str_contains($contents['Return View'], 'Alasan Rusak:'),
];

foreach ($checks as $label => $ok) {
    inventoryQrReturnV1Check(
        $ok,
        $label,
        $label.' belum terpasang dengan benar.'
    );
}

$hasAllocationTable = Schema::hasTable('delivery_order_inventory_allocations');
inventoryQrReturnV1Check(
    $hasAllocationTable,
    'Tabel delivery_order_inventory_allocations tersedia.',
    'Tabel delivery_order_inventory_allocations tidak ditemukan.'
);

if ($hasAllocationTable) {
    $hasReturnNotes = Schema::hasColumn(
        'delivery_order_inventory_allocations',
        'return_notes'
    );
    inventoryQrReturnV1Check(
        $hasReturnNotes,
        'Kolom return_notes tersedia.',
        'Kolom return_notes tidak ditemukan.'
    );

    if ($hasReturnNotes) {
        $historicalMissingNotes = (int) DB::table(
            'delivery_order_inventory_allocations'
        )
            ->where('status', 'returned')
            ->where('return_condition', 'damaged')
            ->where(function ($query): void {
                $query->whereNull('return_notes')
                    ->orWhere('return_notes', '');
            })
            ->count();

        echo "\nDATA DIAGNOSTIC (read-only)\n";
        echo "[INFO] Return DAMAGED historis tanpa alasan: {$historicalMissingNotes}\n";

        if ($historicalMissingNotes > 0) {
            echo "[WARN] Data lama tidak diubah; Return DAMAGED baru wajib memiliki alasan.\n";
        }
    }
}

echo PHP_EOL;

if ($errors !== []) {
    echo '[FAIL] Checker menemukan '.count($errors)." masalah.\n";
    exit(1);
}

echo "[PASS] Layout QR dan Return Damage Note siap digunakan.\n";
echo "Browser test: Print A4 at 100% + Scan Return -> DAMAGED -> isi alasan -> Finalize.\n";
