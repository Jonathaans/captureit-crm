<?php

/*
|--------------------------------------------------------------------------
| Internal Chat Audit V1.1 - Operations Dashboard Menu Hotfix
|--------------------------------------------------------------------------
|
| Fixes:
| Undefined array key "name"
| Webkul\Core\Menu.php
|
| Cause:
| V1 could create a child key under:
| operational-dashboard.internal-chat-audit
|
| while this CRM actually uses an Operations Dashboard parent key/route.
| Arr::undot() then creates a synthetic parent without "name", and Menu.php
| crashes while rendering the sidebar.
|
| This patch:
| 1. Removes the bad Internal Chat Audit menu entry added by V1.
| 2. Detects the ACTUAL Operations Dashboard menu parent from the current
|    customized menu.php.
| 3. Re-adds Internal Chat Audit as a real child of that parent.
|
| No controller / route / DB / migration changes.
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

$source =
    file_get_contents($menu);

if ($source === false) {
    fwrite(STDERR, "menu.php tidak dapat dibaca.\n");
    exit(3);
}

$backup =
    $menu
    .'.before-internal-chat-audit-v1-1-menu-hotfix.bak';

if (! is_file($backup)) {
    if (! copy($menu, $backup)) {
        fwrite(STDERR, "Gagal membuat backup menu.php.\n");
        exit(4);
    }
}

/*
|--------------------------------------------------------------------------
| Remove ONLY the Internal Chat Audit array added by V1
|--------------------------------------------------------------------------
*/

$entryPattern =
    "/\n\s*\[\s*"
    ."'key'\s*=>\s*'[^']*internal-chat-audit'\s*,\s*"
    ."'name'\s*=>\s*'Internal Chat Audit'\s*,\s*"
    ."'route'\s*=>\s*'admin\.operational-dashboard\.internal-chat-audit\.index'\s*,\s*"
    ."'sort'\s*=>\s*999\s*,\s*"
    ."'icon'\s*=>\s*'icon-message'\s*,\s*"
    ."\]\s*,?/s";

$source =
    preg_replace(
        $entryPattern,
        "\n",
        $source,
        -1,
        $removed
    );

if ($source === null) {
    copy($backup, $menu);
    fwrite(STDERR, "Regex menu cleanup gagal.\n");
    exit(5);
}

/*
|--------------------------------------------------------------------------
| Detect actual Operations Dashboard parent entry
|--------------------------------------------------------------------------
|
| We look for a menu array containing a route whose value includes:
| operations-dashboard
|
| Then extract its key.
|
*/

$parentKey =
    null;

if (
    preg_match_all(
        "/\[(.*?)\]/s",
        $source,
        $blocks
    )
) {
    foreach ($blocks[1] as $block) {
        if (
            ! str_contains(
                strtolower($block),
                'operations-dashboard'
            )
        ) {
            continue;
        }

        if (
            preg_match(
                "/'key'\s*=>\s*'([^']+)'/",
                $block,
                $keyMatch
            )
        ) {
            $parentKey =
                $keyMatch[1];

            break;
        }
    }
}

if (! $parentKey) {
    copy($backup, $menu);

    fwrite(
        STDERR,
        "Parent Operations Dashboard tidak ditemukan di current menu.php.\n"
        ."Backup dipulihkan. Patch dihentikan agar tidak membuat parent palsu lagi.\n"
    );

    exit(6);
}

$newKey =
    $parentKey
    .'.internal-chat-audit';

if (
    ! str_contains(
        $source,
        "'key'   => '".$newKey."'"
    )
    && ! str_contains(
        $source,
        "'key' => '".$newKey."'"
    )
) {
    $finalPos =
        strrpos(
            $source,
            '];'
        );

    if ($finalPos === false) {
        copy($backup, $menu);
        fwrite(STDERR, "Akhir menu array tidak ditemukan.\n");
        exit(7);
    }

    $entry =
        "\n    [\n"
        ."        'key'   => '"
        .$newKey
        ."',\n"
        ."        'name'  => 'Internal Chat Audit',\n"
        ."        'route' => 'admin.operational-dashboard.internal-chat-audit.index',\n"
        ."        'sort'  => 999,\n"
        ."        'icon'  => 'icon-message',\n"
        ."    ],\n";

    $source =
        substr_replace(
            $source,
            $entry,
            $finalPos,
            0
        );
}

if (
    file_put_contents(
        $menu,
        $source
    ) === false
) {
    copy($backup, $menu);
    fwrite(STDERR, "Gagal menulis menu.php. Backup dipulihkan.\n");
    exit(8);
}

$written =
    file_get_contents($menu);

if (
    $written === false
    || ! str_contains(
        $written,
        "'key'   => '".$newKey."'"
    )
) {
    copy($backup, $menu);

    fwrite(
        STDERR,
        "Post-write validation gagal. Backup dipulihkan.\n"
    );

    exit(9);
}

echo "[PASS] Bad synthetic audit menu parent removed.\n";
echo "[PASS] Actual Operations Dashboard parent detected: {$parentKey}\n";
echo "[PASS] Internal Chat Audit menu key installed: {$newKey}\n";
echo "[PASS] No controller / route / database changes.\n";
