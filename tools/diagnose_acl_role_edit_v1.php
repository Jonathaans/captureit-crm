<?php

declare(strict_types=1);

/**
 * READ-ONLY ACL ROLE EDIT DIAGNOSTIC V1
 *
 * Tidak mengubah file apa pun.
 * Mencari item ACL yang tidak memiliki field wajib seperti "key" atau "name".
 */

$root = dirname(__DIR__);
$aclPath = $root . '/packages/Webkul/Admin/src/Config/acl.php';

echo "ACL ROLE EDIT DIAGNOSTIC V1\n";
echo "===========================\n\n";

if (!is_file($aclPath)) {
    fwrite(STDERR, "FAIL: acl.php tidak ditemukan: {$aclPath}\n");
    exit(1);
}

$acl = require $aclPath;

if (!is_array($acl)) {
    fwrite(STDERR, "FAIL: acl.php tidak return array.\n");
    exit(1);
}

$issues = [];

$walk = function (array $items, string $parent = 'root') use (&$walk, &$issues): void {
    foreach ($items as $index => $item) {
        $path = $parent . '[' . $index . ']';

        if (!is_array($item)) {
            $issues[] = [
                'path' => $path,
                'type' => 'not-array',
                'item' => $item,
            ];
            continue;
        }

        $key = $item['key'] ?? null;
        $name = $item['name'] ?? null;

        if ($key === null || trim((string) $key) === '') {
            $issues[] = [
                'path' => $path,
                'type' => 'missing-key',
                'item' => $item,
            ];
        }

        if ($name === null || trim((string) $name) === '') {
            $issues[] = [
                'path' => $path,
                'type' => 'missing-name',
                'key'  => $key,
                'item' => $item,
            ];
        }

        foreach (['children', 'items', 'acl'] as $childKey) {
            if (isset($item[$childKey]) && is_array($item[$childKey])) {
                $walk($item[$childKey], $path . '.' . $childKey);
            }
        }
    }
};

$walk($acl);

if (!$issues) {
    echo "[OK] Semua item ACL memiliki key dan name.\n";
    echo "\nJika halaman Role Edit masih error, masalahnya kemungkinan ada pada proses nested ACL di Webkul\\Core\\Acl.php.\n";
    exit(0);
}

echo "Ditemukan " . count($issues) . " masalah ACL:\n\n";

foreach ($issues as $n => $issue) {
    echo "#" . ($n + 1) . "\n";
    echo "type : " . ($issue['type'] ?? '-') . "\n";
    echo "path : " . ($issue['path'] ?? '-') . "\n";
    echo "key  : " . (($issue['key'] ?? null) ?: '-') . "\n";
    echo "item : " . var_export($issue['item'] ?? null, true) . "\n";
    echo str_repeat('-', 60) . "\n";
}

echo "\nHASIL: FAIL\n";
echo "Kirim output ini ke chat. Tool ini TIDAK mengubah source.\n";

exit(2);
