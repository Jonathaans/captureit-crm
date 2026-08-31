<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

require __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';

$app->make(
    Kernel::class
)->bootstrap();

if (! Schema::hasTable('delivery_orders')) {
    fwrite(
        STDERR,
        "delivery_orders table tidak ditemukan.\n"
    );

    exit(1);
}

$indexes = DB::select(
    'SHOW INDEX FROM delivery_orders'
);

$grouped = [];

foreach ($indexes as $index) {
    $keyName =
        (string) $index->Key_name;

    $grouped[$keyName][] =
        $index;
}

$blockingUnique = [];

foreach ($grouped as $keyName => $rows) {
    usort(
        $rows,
        fn ($a, $b) =>
            ((int) $a->Seq_in_index)
            <=>
            ((int) $b->Seq_in_index)
    );

    $columns = array_map(
        fn ($row) =>
            (string) $row->Column_name,
        $rows
    );

    $isUnique =
        ((int) $rows[0]->Non_unique)
        === 0;

    if (
        $isUnique
        && $columns === [
            'invoice_id',
        ]
    ) {
        $blockingUnique[] =
            $keyName;
    }
}

if ($blockingUnique) {
    fwrite(
        STDERR,
        "FAIL: invoice_id masih memiliki UNIQUE index: "
            .implode(
                ', ',
                $blockingUnique
            )
            ."\n"
    );

    fwrite(
        STDERR,
        "Jangan generate SJ kedua sebelum index ini diperbaiki.\n"
    );

    exit(2);
}

echo "PASS: delivery_orders.invoice_id mendukung one-to-many.\n";
echo "Satu Invoice dapat memiliki beberapa Surat Jalan.\n";
