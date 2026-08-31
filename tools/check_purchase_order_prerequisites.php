<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Webkul\Invoice\Models\Expense;

require __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';

$app->make(Kernel::class)->bootstrap();

echo "PURCHASE ORDER PRE-FLIGHT\n";
echo "=========================\n\n";

$expenseTable = (new Expense())->getTable();

if (! Schema::hasTable($expenseTable)) {
    echo "FAIL: Expense table tidak ditemukan: {$expenseTable}\n";
    exit(1);
}

$columns = Schema::getColumnListing($expenseTable);

echo "Expense table: {$expenseTable}\n";
echo "Columns: ".implode(', ', $columns)."\n\n";

$required = [
    'id',
    'invoice_id',
    'category',
    'amount',
    'expense_date',
];

$missing = array_values(
    array_diff($required, $columns)
);

if ($missing) {
    echo "FAIL: Missing required Expense columns: "
        .implode(', ', $missing)
        ."\n";

    exit(2);
}

$requiredWithoutDefault = DB::select(
    "
        SELECT
            COLUMN_NAME,
            DATA_TYPE,
            IS_NULLABLE,
            COLUMN_DEFAULT,
            EXTRA
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
          AND IS_NULLABLE = 'NO'
          AND COLUMN_DEFAULT IS NULL
          AND EXTRA NOT LIKE '%auto_increment%'
        ORDER BY ORDINAL_POSITION
    ",
    [$expenseTable]
);

$knownProvided = [
    'invoice_id',
    'category',
    'amount',
    'expense_date',
    'description',
    'notes',
    'reference_type',
    'reference_id',
    'reference_number',
    'purchase_order_id',
    'user_id',
    'created_by',
    'created_by_name',
    'created_at',
    'updated_at',
];

$unknownRequired = [];

foreach ($requiredWithoutDefault as $column) {
    if (
        ! in_array(
            $column->COLUMN_NAME,
            $knownProvided,
            true
        )
    ) {
        $unknownRequired[] = $column->COLUMN_NAME;
    }
}

if ($unknownRequired) {
    echo "WARN: Expense table punya required custom columns yang belum dikenali:\n";

    foreach ($unknownRequired as $column) {
        echo " - {$column}\n";
    }

    echo "\nCreate/Draft PO aman, tetapi JANGAN Release PO sebelum mapping disesuaikan.\n";
    exit(3);
}

echo "PASS: Expense schema kompatibel untuk auto-post Purchase Order.\n";
