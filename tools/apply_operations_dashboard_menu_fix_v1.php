<?php

declare(strict_types=1);

/**
 * Operations Dashboard Menu Fix V1
 *
 * Tujuan:
 * - Mengembalikan Operations Dashboard menjadi link yang bisa diklik.
 * - Memisahkan Internal Chat Audit dari key parent "operations-dashboard".
 * - Menyelaraskan key ACL jika key lama ditemukan.
 * - Membuat backup otomatis.
 * - Melakukan validasi setelah penulisan.
 * - Restore otomatis jika validasi gagal.
 *
 * Jalankan dari root project:
 * php tools/apply_operations_dashboard_menu_fix_v1.php
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

function fail(string $message, int $code = 1): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit($code);
}

function backupFile(string $path, string $suffix): string
{
    $backup = $path . '.bak-' . $suffix;

    if (!copy($path, $backup)) {
        fail("Gagal membuat backup: {$path}");
    }

    return $backup;
}

function atomicWrite(string $path, string $contents): void
{
    $tmp = $path . '.tmp-' . bin2hex(random_bytes(4));

    if (file_put_contents($tmp, $contents) === false) {
        @unlink($tmp);
        fail("Gagal menulis file sementara: {$tmp}");
    }

    if (!rename($tmp, $path)) {
        @unlink($tmp);
        fail("Gagal mengganti file: {$path}");
    }
}

function restore(string $backup, string $target): void
{
    if (is_file($backup)) {
        copy($backup, $target);
    }
}

function countExactKeyAssignments(string $contents, string $key): int
{
    $quoted = preg_quote($key, '~');

    return preg_match_all(
        "~['\"]key['\"]\s*=>\s*['\"]{$quoted}['\"]~",
        $contents
    );
}

echo "OPERATIONS DASHBOARD MENU FIX V1" . PHP_EOL;
echo "================================" . PHP_EOL . PHP_EOL;

foreach ([$menuPath, $aclPath] as $path) {
    if (!is_file($path)) {
        fail("File tidak ditemukan: {$path}");
    }
}

$menuOriginal = file_get_contents($menuPath);
$aclOriginal = file_get_contents($aclPath);

if ($menuOriginal === false || $aclOriginal === false) {
    fail('Gagal membaca menu.php atau acl.php.');
}

/*
 * Preflight validation.
 */
if (strpos($menuOriginal, "'route'      => 'admin.operations-dashboard.index'") === false
    && strpos($menuOriginal, "'route' => 'admin.operations-dashboard.index'") === false
    && strpos($menuOriginal, '"admin.operations-dashboard.index"') === false
    && strpos($menuOriginal, "'admin.operations-dashboard.index'") === false
) {
    fail('Preflight gagal: route admin.operations-dashboard.index tidak ditemukan di menu.php.');
}

if (strpos($menuOriginal, 'admin.operational-dashboard.internal-chat-audit.index') === false) {
    fail('Preflight gagal: route Internal Chat Audit tidak ditemukan di menu.php.');
}

$oldMenuCount = countExactKeyAssignments($menuOriginal, $oldKey);
$newMenuCount = countExactKeyAssignments($menuOriginal, $newKey);

if ($oldMenuCount === 0 && $newMenuCount === 1) {
    echo "menu.php sudah menggunakan key baru. Tidak perlu perubahan pada menu." . PHP_EOL;
} elseif ($oldMenuCount !== 1) {
    fail(
        "Preflight gagal: jumlah key lama di menu.php = {$oldMenuCount}. "
        . "Diharapkan tepat 1 agar patch aman."
    );
}

$timestamp = date('Ymd-His');
$menuBackup = backupFile($menuPath, "operations-dashboard-menu-fix-v1-{$timestamp}");
$aclBackup = backupFile($aclPath, "operations-dashboard-menu-fix-v1-{$timestamp}");

echo "Backup menu : {$menuBackup}" . PHP_EOL;
echo "Backup ACL  : {$aclBackup}" . PHP_EOL . PHP_EOL;

try {
    $menuUpdated = $menuOriginal;
    $aclUpdated = $aclOriginal;

    if ($oldMenuCount === 1) {
        $menuUpdated = preg_replace(
            "~(['\"]key['\"]\s*=>\s*['\"])" . preg_quote($oldKey, '~') . "(['\"])~",
            '$1' . $newKey . '$2',
            $menuOriginal,
            1,
            $menuReplacements
        );

        if (!is_string($menuUpdated) || $menuReplacements !== 1) {
            throw new RuntimeException(
                "Patch menu.php gagal. replacement count=" . (string)($menuReplacements ?? -1)
            );
        }
    }

    /*
     * ACL diselaraskan hanya jika key assignment lama memang ada.
     * Tidak menyentuh route, controller, service provider, atau permission lain.
     */
    $oldAclCount = countExactKeyAssignments($aclOriginal, $oldKey);

    if ($oldAclCount > 1) {
        throw new RuntimeException(
            "ACL memiliki {$oldAclCount} key assignment lama. Patch dibatalkan agar tidak menebak."
        );
    }

    if ($oldAclCount === 1) {
        $aclUpdated = preg_replace(
            "~(['\"]key['\"]\s*=>\s*['\"])" . preg_quote($oldKey, '~') . "(['\"])~",
            '$1' . $newKey . '$2',
            $aclOriginal,
            1,
            $aclReplacements
        );

        if (!is_string($aclUpdated) || $aclReplacements !== 1) {
            throw new RuntimeException(
                "Patch acl.php gagal. replacement count=" . (string)($aclReplacements ?? -1)
            );
        }
    }

    atomicWrite($menuPath, $menuUpdated);
    atomicWrite($aclPath, $aclUpdated);

    /*
     * Post-write validation.
     */
    $menuCheck = file_get_contents($menuPath);
    $aclCheck = file_get_contents($aclPath);

    if ($menuCheck === false || $aclCheck === false) {
        throw new RuntimeException('Post-write validation gagal membaca file.');
    }

    if (countExactKeyAssignments($menuCheck, $oldKey) !== 0) {
        throw new RuntimeException('Post-write validation gagal: key lama masih ada di menu.php.');
    }

    if (countExactKeyAssignments($menuCheck, $newKey) !== 1) {
        throw new RuntimeException('Post-write validation gagal: key baru menu.php harus tepat 1.');
    }

    if (strpos($menuCheck, 'admin.operations-dashboard.index') === false) {
        throw new RuntimeException('Post-write validation gagal: route Operations Dashboard hilang.');
    }

    if (strpos($menuCheck, 'admin.operational-dashboard.internal-chat-audit.index') === false) {
        throw new RuntimeException('Post-write validation gagal: route Internal Chat Audit hilang.');
    }

    if ($oldAclCount === 1) {
        if (countExactKeyAssignments($aclCheck, $oldKey) !== 0) {
            throw new RuntimeException('Post-write validation gagal: key lama masih ada di acl.php.');
        }

        if (countExactKeyAssignments($aclCheck, $newKey) < 1) {
            throw new RuntimeException('Post-write validation gagal: key baru tidak ditemukan di acl.php.');
        }
    }

    echo "Patch berhasil." . PHP_EOL;
    echo "- Operations Dashboard tetap route admin.operations-dashboard.index" . PHP_EOL;
    echo "- Internal Chat Audit key: {$newKey}" . PHP_EOL;
    echo "- Tidak ada perubahan pada controller/route/service provider." . PHP_EOL . PHP_EOL;

    echo "Membersihkan cache Laravel..." . PHP_EOL;
    chdir($root);

    $php = escapeshellarg(PHP_BINARY);
    $artisan = escapeshellarg($root . DIRECTORY_SEPARATOR . 'artisan');

    passthru("{$php} {$artisan} optimize:clear", $clearCode);

    if ($clearCode !== 0) {
        echo PHP_EOL;
        echo "PERINGATAN: patch sudah berhasil, tetapi optimize:clear exit code {$clearCode}." . PHP_EOL;
        echo "Jalankan manual: php artisan optimize:clear" . PHP_EOL;
    }

    echo PHP_EOL;
    echo "SELESAI." . PHP_EOL;
    echo "Lakukan Ctrl+Shift+R di browser lalu tes Operations Dashboard." . PHP_EOL;
} catch (Throwable $e) {
    restore($menuBackup, $menuPath);
    restore($aclBackup, $aclPath);

    fwrite(STDERR, PHP_EOL . "PATCH GAGAL: " . $e->getMessage() . PHP_EOL);
    fwrite(STDERR, "Backup dipulihkan otomatis." . PHP_EOL);
    exit(1);
}
