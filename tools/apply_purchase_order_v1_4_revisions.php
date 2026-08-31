<?php

$projectRoot = realpath(
    __DIR__.'/..'
);

if (! $projectRoot) {
    fwrite(
        STDERR,
        "Project root tidak ditemukan.\n"
    );

    exit(1);
}

$menuPath =
    $projectRoot
    .'/packages/Webkul/Admin/src/Config/menu.php';

if (! is_file($menuPath)) {
    fwrite(
        STDERR,
        "menu.php tidak ditemukan.\n"
    );

    exit(2);
}

$source =
    file_get_contents(
        $menuPath
    );

if ($source === false) {
    fwrite(
        STDERR,
        "menu.php tidak dapat dibaca.\n"
    );

    exit(3);
}

$backup =
    $menuPath
    .'.before-po-v1-4-icon.bak';

if (! is_file($backup)) {
    copy(
        $menuPath,
        $backup
    );
}

if (
    preg_match(
        "/'key'\\s*=>\\s*'purchase-orders'/",
        $source,
        $keyMatch,
        PREG_OFFSET_CAPTURE
    ) !== 1
) {
    fwrite(
        STDERR,
        "Menu Purchase Orders tidak ditemukan.\n"
    );

    exit(4);
}

$keyPosition =
    $keyMatch[0][1];

$blockStart =
    strrpos(
        substr(
            $source,
            0,
            $keyPosition
        ),
        '['
    );

$blockEnd =
    strpos(
        $source,
        '],',
        $keyPosition
    );

if (
    $blockStart === false
    || $blockEnd === false
) {
    fwrite(
        STDERR,
        "Block Purchase Orders menu tidak dapat dibaca.\n"
    );

    exit(5);
}

$blockEnd += 2;

$block =
    substr(
        $source,
        $blockStart,
        $blockEnd - $blockStart
    );

/*
 * icon-quote is confirmed present in the current project's icon font.
 */
if (
    preg_match(
        "/'icon-class'\\s*=>\\s*'[^']*'/",
        $block
    ) === 1
) {
    $block =
        preg_replace(
            "/'icon-class'\\s*=>\\s*'[^']*'/",
            "'icon-class' => 'icon-quote'",
            $block,
            1
        );
} else {
    $block =
        preg_replace(
            '/\\],\\s*$/',
            "    'icon-class' => 'icon-quote',\n    ],",
            $block,
            1
        );
}

$source =
    substr_replace(
        $source,
        $block,
        $blockStart,
        $blockEnd - $blockStart
    );

file_put_contents(
    $menuPath,
    $source
);

echo "[PASS] Purchase Orders menu icon = icon-quote.\n";
echo "Backup: {$backup}\n";
