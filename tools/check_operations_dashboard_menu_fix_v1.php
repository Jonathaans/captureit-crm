<?php

declare(strict_types=1);

/**
 * Check Operations Dashboard Menu Fix V1
 *
 * Jalankan:
 * php tools/check_operations_dashboard_menu_fix_v1.php
 */

$root = dirname(__DIR__);

$menuPath = $root . DIRECTORY_SEPARATOR . 'packages' . DIRECTORY_SEPARATOR . 'Webkul'
    . DIRECTORY_SEPARATOR . 'Admin' . DIRECTORY_SEPARATOR . 'src'
    . DIRECTORY_SEPARATOR . 'Config' . DIRECTORY_SEPARATOR . 'menu.php';

$aclPath = $root . DIRECTORY_SEPARATOR . 'packages' . DIRECTORY_SEPARATOR . 'Webkul'
    . DIRECTORY_SEPARATOR . 'Admin' . DIRECTORY_SEPARATOR . 'src'
    . DIRECTORY_SEPARATOR . 'Config' . DIRECTORY_SEPARATOR . 'acl.php';

$oldKey = 'operations-dashboard.internal-chat-audit';
$newKey = 'internal-chat-audit';

function result(string $label, bool $ok, string $detail = ''): bool
{
    echo ($ok ? '[OK]   ' : '[FAIL] ') . $label;

    if ($detail !== '') {
        echo ' - ' . $detail;
    }

    echo PHP_EOL;

    return $ok;
}

function keyCount(string $contents, string $key): int
{
    $quoted = preg_quote($key, '~');

    return preg_match_all(
        "~['\"]key['\"]\s*=>\s*['\"]{$quoted}['\"]~",
        $contents
    );
}

echo "CHECK OPERATIONS DASHBOARD MENU FIX V1" . PHP_EOL;
echo "======================================" . PHP_EOL;

$allOk = true;

$allOk = result('menu.php tersedia', is_file($menuPath), $menuPath) && $allOk;
$allOk = result('acl.php tersedia', is_file($aclPath), $aclPath) && $allOk;

if (!is_file($menuPath) || !is_file($aclPath)) {
    exit(1);
}

$menu = file_get_contents($menuPath);
$acl = file_get_contents($aclPath);

if ($menu === false || $acl === false) {
    result('File dapat dibaca', false);
    exit(1);
}

$allOk = result(
    'Operations Dashboard route masih ada',
    strpos($menu, 'admin.operations-dashboard.index') !== false
) && $allOk;

$allOk = result(
    'Internal Chat Audit route masih ada',
    strpos($menu, 'admin.operational-dashboard.internal-chat-audit.index') !== false
) && $allOk;

$allOk = result(
    'Key lama tidak ada di menu',
    keyCount($menu, $oldKey) === 0,
    'count=' . keyCount($menu, $oldKey)
) && $allOk;

$allOk = result(
    'Key baru ada tepat 1 di menu',
    keyCount($menu, $newKey) === 1,
    'count=' . keyCount($menu, $newKey)
) && $allOk;

$oldAclCount = keyCount($acl, $oldKey);
$newAclCount = keyCount($acl, $newKey);

$allOk = result(
    'ACL tidak memakai key menu lama',
    $oldAclCount === 0,
    'old=' . $oldAclCount . ', new=' . $newAclCount
) && $allOk;

echo PHP_EOL;

if ($allOk) {
    echo "HASIL: PASS" . PHP_EOL;
    exit(0);
}

echo "HASIL: FAIL" . PHP_EOL;
exit(1);
