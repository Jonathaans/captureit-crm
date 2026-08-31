<?php

$path = __DIR__
    .'/../packages/Webkul/Admin/src/Config/menu.php';

if (! is_file($path)) {
    fwrite(STDERR, "menu.php tidak ditemukan: {$path}\n");
    exit(1);
}

$source = file_get_contents($path);

if ($source === false) {
    fwrite(STDERR, "Tidak dapat membaca menu.php\n");
    exit(1);
}

if (
    str_contains($source, "'key'   => 'inventory.consumables'")
    || str_contains($source, "'key' => 'inventory.consumables'")
) {
    echo "Menu Consumables sudah ada. Tidak ada perubahan.\n";
    exit(0);
}

$backup = $path.'.phase5b-before-consumables.bak';

if (! is_file($backup)) {
    copy($path, $backup);
}

/*
 * Repurpose the existing operational Inventory Items menu row.
 * The inventory.items route / ACL remain intact for internal master use.
 */
$keyPattern = "/'key'\\s*=>\\s*'inventory\\.items'/";

if (preg_match($keyPattern, $source, $keyMatch, PREG_OFFSET_CAPTURE)) {
    $keyOffset = $keyMatch[0][1];

    $blockStart = strrpos(
        substr($source, 0, $keyOffset),
        '['
    );

    $nextBlock = strpos(
        $source,
        '], [',
        $keyOffset
    );

    if ($blockStart !== false && $nextBlock !== false) {
        $blockEnd = $nextBlock + 1;
        $block = substr(
            $source,
            $blockStart,
            $blockEnd - $blockStart
        );

        $newBlock = preg_replace(
            "/'key'\\s*=>\\s*'inventory\\.items'/",
            "'key'   => 'inventory.consumables'",
            $block,
            1
        );

        $newBlock = preg_replace(
            "/'name'\\s*=>\\s*[^,]+,/",
            "'name'  => 'Consumables',",
            $newBlock,
            1
        );

        $newBlock = preg_replace(
            "/'route'\\s*=>\\s*'admin\\.inventory\\.items\\.index'/",
            "'route' => 'admin.inventory.consumables.index'",
            $newBlock,
            1
        );

        $newBlock = preg_replace(
            "/'sort'\\s*=>\\s*\\d+/",
            "'sort'  => 2",
            $newBlock,
            1
        );

        $source = substr_replace(
            $source,
            $newBlock,
            $blockStart,
            $blockEnd - $blockStart
        );

        file_put_contents($path, $source);

        echo "Inventory Items sidebar entry berhasil diubah menjadi Consumables.\n";
        echo "Backup: {$backup}\n";
        exit(0);
    }
}

/*
 * Fallback: insert Consumables immediately before Inventory Assets.
 */
$assetsKey = "/(?=\\[\\s*'key'\\s*=>\\s*'inventory\\.assets'\\s*,)/s";

$entry = <<<'PHP'

[
        'key'        => 'inventory.consumables',
        'name'       => 'Consumables',
        'route'      => 'admin.inventory.consumables.index',
        'sort'       => 2,
        'icon-class' => '',
    ], 
PHP;

$patched = preg_replace(
    $assetsKey,
    $entry,
    $source,
    1,
    $count
);

if ($count !== 1) {
    fwrite(
        STDERR,
        "Entry inventory.items / inventory.assets tidak ditemukan. menu.php tidak diubah.\n"
    );
    exit(2);
}

file_put_contents($path, $patched);

echo "Consumables berhasil ditambahkan sebelum Assets.\n";
echo "Backup: {$backup}\n";
