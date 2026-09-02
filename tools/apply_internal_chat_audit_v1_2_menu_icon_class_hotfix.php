<?php

/*
|--------------------------------------------------------------------------
| Internal Chat Audit V1.2 - Menu icon-class Hotfix
|--------------------------------------------------------------------------
|
| Fixes:
| Undefined array key "icon-class"
|
| Webkul\Core\Menu::processSubMenuItems() expects every submenu item to have:
| - key
| - name
| - route
| - sort
| - icon-class
|
| V1/V1.1 inserted the audit submenu using "icon" instead of "icon-class".
|
| Scope:
| - packages/Webkul/Admin/src/Config/menu.php ONLY
| - no route/controller/database/migration changes
|
*/

$root = realpath(__DIR__.'/..');

if (! $root) {
    fwrite(STDERR, "Project root tidak ditemukan.\n");
    exit(1);
}

$menu =
    $root
    .'/packages/Webkul/Admin/src/Config/menu.php';

if (! is_file($menu)) {
    fwrite(STDERR, "menu.php tidak ditemukan.\n");
    exit(2);
}

$source = file_get_contents($menu);

if ($source === false) {
    fwrite(STDERR, "menu.php tidak dapat dibaca.\n");
    exit(3);
}

$backup =
    $menu
    .'.before-internal-chat-audit-v1-2-icon-class-hotfix.bak';

if (! is_file($backup)) {
    if (! copy($menu, $backup)) {
        fwrite(STDERR, "Gagal membuat backup menu.php.\n");
        exit(4);
    }
}

/*
|--------------------------------------------------------------------------
| Locate ONLY the Internal Chat Audit menu block
|--------------------------------------------------------------------------
*/

$pattern =
    "/\[\s*"
    ."'key'\s*=>\s*'([^']*internal-chat-audit)'\s*,"
    ."(.*?)"
    ."'route'\s*=>\s*'admin\.operational-dashboard\.internal-chat-audit\.index'\s*,"
    ."(.*?)"
    ."\]/s";

if (
    preg_match(
        $pattern,
        $source,
        $match,
        PREG_OFFSET_CAPTURE
    ) !== 1
) {
    fwrite(
        STDERR,
        "Internal Chat Audit menu block tidak ditemukan.\n"
        ."Patch dihentikan agar menu customized tidak terganggu.\n"
    );

    exit(5);
}

$block =
    $match[0][0];

$offset =
    $match[0][1];

/*
|--------------------------------------------------------------------------
| Normalize icon key to icon-class
|--------------------------------------------------------------------------
*/

if (
    preg_match(
        "/'icon-class'\s*=>\s*'[^']*'/",
        $block
    ) === 1
) {
    $newBlock =
        preg_replace(
            "/'icon-class'\s*=>\s*'[^']*'/",
            "'icon-class' => 'icon-message'",
            $block,
            1
        );
} elseif (
    preg_match(
        "/'icon'\s*=>\s*'[^']*'/",
        $block
    ) === 1
) {
    $newBlock =
        preg_replace(
            "/'icon'\s*=>\s*'[^']*'/",
            "'icon-class' => 'icon-message'",
            $block,
            1
        );
} else {
    /*
     * Insert before closing ] if neither key exists.
     */
    $closing =
        strrpos(
            $block,
            ']'
        );

    if ($closing === false) {
        fwrite(
            STDERR,
            "Menu block closing bracket tidak ditemukan.\n"
        );

        exit(6);
    }

    $newBlock =
        substr_replace(
            $block,
            "        'icon-class' => 'icon-message',\n    ",
            $closing,
            0
        );
}

if (
    ! str_contains(
        $newBlock,
        "'icon-class' => 'icon-message'"
    )
) {
    fwrite(
        STDERR,
        "Normalisasi icon-class gagal.\n"
    );

    exit(7);
}

$source =
    substr_replace(
        $source,
        $newBlock,
        $offset,
        strlen($block)
    );

if (
    file_put_contents(
        $menu,
        $source
    ) === false
) {
    copy($backup, $menu);

    fwrite(
        STDERR,
        "Gagal menulis menu.php. Backup dipulihkan.\n"
    );

    exit(8);
}

$written =
    file_get_contents(
        $menu
    );

if (
    $written === false
    || ! str_contains(
        $written,
        "'route' => 'admin.operational-dashboard.internal-chat-audit.index'"
    )
    || ! str_contains(
        $written,
        "'icon-class' => 'icon-message'"
    )
) {
    copy($backup, $menu);

    fwrite(
        STDERR,
        "Post-write validation gagal. Backup dipulihkan.\n"
    );

    exit(9);
}

echo "[PASS] Internal Chat Audit menu icon key normalized.\n";
echo "[PASS] icon-class = icon-message.\n";
echo "[PASS] Audit route/menu key preserved.\n";
echo "[PASS] No controller / route / database changes.\n";
