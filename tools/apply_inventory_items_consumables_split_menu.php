<?php

$path = __DIR__
    .'/../packages/Webkul/Admin/src/Config/menu.php';

if (! is_file($path)) {
    fwrite(
        STDERR,
        "menu.php tidak ditemukan: {$path}\n"
    );

    exit(1);
}

$source = file_get_contents($path);

if ($source === false) {
    fwrite(
        STDERR,
        "Tidak dapat membaca menu.php\n"
    );

    exit(1);
}

$backup = $path
    .'.phase5b1-before-items-consumables-split.bak';

if (! is_file($backup)) {
    copy(
        $path,
        $backup
    );
}

$hasItems = preg_match(
    "/'key'\\s*=>\\s*'inventory\\.items'/",
    $source
) === 1;

$hasConsumables = preg_match(
    "/'key'\\s*=>\\s*'inventory\\.consumables'/",
    $source
) === 1;

$itemsEntry = <<<'PHP'

    [
        'key'        => 'inventory.items',
        'name'       => 'Inventory Items',
        'route'      => 'admin.inventory.items.index',
        'sort'       => 2,
        'icon-class' => '',
    ],
PHP;

$consumablesEntry = <<<'PHP'

    [
        'key'        => 'inventory.consumables',
        'name'       => 'Consumables',
        'route'      => 'admin.inventory.consumables.index',
        'sort'       => 3,
        'icon-class' => '',
    ],
PHP;

if (! $hasItems && $hasConsumables) {
    $pattern = "/(?=\\[\\s*'key'\\s*=>\\s*'inventory\\.consumables'\\s*,)/s";

    $source = preg_replace(
        $pattern,
        $itemsEntry,
        $source,
        1,
        $count
    );

    if ($count !== 1) {
        fwrite(
            STDERR,
            "Gagal insert Inventory Items sebelum Consumables.\n"
        );

        exit(2);
    }

    $hasItems = true;
}

if ($hasItems && ! $hasConsumables) {
    $pattern = "/(?=\\[\\s*'key'\\s*=>\\s*'inventory\\.assets'\\s*,)/s";

    $source = preg_replace(
        $pattern,
        $consumablesEntry,
        $source,
        1,
        $count
    );

    if ($count !== 1) {
        fwrite(
            STDERR,
            "Gagal insert Consumables sebelum Assets.\n"
        );

        exit(3);
    }

    $hasConsumables = true;
}

if (! $hasItems && ! $hasConsumables) {
    fwrite(
        STDERR,
        "Entry Inventory Items / Consumables tidak ditemukan.\n"
    );

    exit(4);
}

/*
 * Only normalize the known Inventory submenu blocks.
 * The rest of the customized menu.php remains untouched.
 */
$sortMap = [
    'inventory.dashboard' => 1,
    'inventory.items' => 2,
    'inventory.consumables' => 3,
    'inventory.assets' => 4,
    'inventory.movements' => 5,
    'inventory.maintenance' => 6,
    'inventory.stock-opname' => 7,
    'inventory.alerts' => 8,
    'inventory.qa' => 9,
];

foreach ($sortMap as $key => $sort) {
    $pattern = "/('key'\\s*=>\\s*'"
        .preg_quote($key, '/')
        ."'.*?'sort'\\s*=>\\s*)\\d+/s";

    $source = preg_replace(
        $pattern,
        '${1}'.$sort,
        $source,
        1
    );
}

file_put_contents(
    $path,
    $source
);

echo "Inventory menu split selesai.\n";
echo "Expected order:\n";
echo "1 Dashboard\n";
echo "2 Inventory Items\n";
echo "3 Consumables\n";
echo "4 Assets\n";
echo "5 Movements\n";
echo "6 Maintenance\n";
echo "7 Stock Opname\n";
echo "8 Alerts & Reorder\n";
echo "9 Warehouse QA\n";
echo "Backup: {$backup}\n";
