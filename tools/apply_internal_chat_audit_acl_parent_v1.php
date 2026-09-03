<?php

declare(strict_types=1);

/**
 * FIX INTERNAL CHAT AUDIT ACL PARENT V1
 *
 * Root cause:
 * effective ACL contains:
 *   operational-dashboard.internal-chat-audit
 *
 * but there is no parent ACL item:
 *   operational-dashboard
 *
 * Webkul\Core\Acl::prepareAclItems() converts dotted keys into a nested tree.
 * That creates a synthetic "operational-dashboard" node without name/route/sort,
 * then Role Edit crashes on $aclItem['name'].
 *
 * Fix:
 *   operational-dashboard.internal-chat-audit
 * becomes:
 *   internal-chat-audit
 *
 * This matches the top-level menu approach already used to keep
 * Operations Dashboard clickable.
 *
 * Only modifies:
 * packages/Webkul/Admin/src/Config/acl.php
 */

$root = dirname(__DIR__);

$aclPath =
    $root . '/packages/Webkul/Admin/src/Config/acl.php';

$oldKey =
    'operational-dashboard.internal-chat-audit';

$newKey =
    'internal-chat-audit';

function fail(string $message, int $code = 1): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit($code);
}

function atomicWrite(string $path, string $contents): void
{
    $tmp =
        $path . '.tmp-' . bin2hex(random_bytes(4));

    if (file_put_contents($tmp, $contents) === false) {
        @unlink($tmp);
        throw new RuntimeException("Gagal menulis temporary file: {$tmp}");
    }

    if (!rename($tmp, $path)) {
        @unlink($tmp);
        throw new RuntimeException("Gagal mengganti file: {$path}");
    }
}

function phpLint(string $path): array
{
    exec(
        escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($path) . ' 2>&1',
        $output,
        $code
    );

    return [$code, implode(PHP_EOL, $output)];
}

echo "FIX INTERNAL CHAT AUDIT ACL PARENT V1\n";
echo "=====================================\n\n";

if (!is_file($aclPath)) {
    fail("acl.php tidak ditemukan: {$aclPath}");
}

$original =
    file_get_contents($aclPath);

if ($original === false) {
    fail('Gagal membaca acl.php.');
}

$oldPattern =
    "~(['\"]key['\"]\s*=>\s*['\"])"
    . preg_quote($oldKey, '~')
    . "(['\"])~";

$newPattern =
    "~['\"]key['\"]\s*=>\s*['\"]"
    . preg_quote($newKey, '~')
    . "['\"]~";

$oldCount =
    preg_match_all(
        $oldPattern,
        $original
    );

$newCount =
    preg_match_all(
        $newPattern,
        $original
    );

echo "Preflight:\n";
echo "- old key count : {$oldCount}\n";
echo "- new key count : {$newCount}\n\n";

if ($oldCount === 0 && $newCount === 1) {
    echo "ACL sudah ter-fix. Tidak ada perubahan.\n";
    exit(0);
}

if ($oldCount !== 1) {
    fail(
        "Preflight gagal: expected tepat 1 key '{$oldKey}', ditemukan {$oldCount}. "
        . "Patch dibatalkan agar tidak menebak."
    );
}

if ($newCount > 0) {
    fail(
        "Preflight gagal: key '{$newKey}' sudah ada {$newCount} kali. "
        . "Patch dibatalkan agar tidak membuat duplikat."
    );
}

$stamp =
    date('Ymd-His');

$backup =
    $aclPath
    . '.bak-internal-chat-audit-acl-parent-v1-'
    . $stamp;

if (!copy($aclPath, $backup)) {
    fail("Gagal membuat backup: {$backup}");
}

echo "Backup:\n{$backup}\n\n";

try {
    $updated =
        preg_replace(
            $oldPattern,
            '$1' . $newKey . '$2',
            $original,
            1,
            $replacementCount
        );

    if (!is_string($updated) || $replacementCount !== 1) {
        throw new RuntimeException(
            "Replacement gagal. count="
            . (string) ($replacementCount ?? -1)
        );
    }

    atomicWrite(
        $aclPath,
        $updated
    );

    [$lintCode, $lintOutput] =
        phpLint(
            $aclPath
        );

    if ($lintCode !== 0) {
        throw new RuntimeException(
            "PHP lint gagal:\n{$lintOutput}"
        );
    }

    $check =
        file_get_contents($aclPath);

    if ($check === false) {
        throw new RuntimeException(
            'Gagal membaca acl.php setelah patch.'
        );
    }

    if (
        preg_match_all(
            $oldPattern,
            $check
        ) !== 0
    ) {
        throw new RuntimeException(
            'Old dotted ACL key masih ada.'
        );
    }

    if (
        preg_match_all(
            $newPattern,
            $check
        ) !== 1
    ) {
        throw new RuntimeException(
            'New standalone ACL key tidak tepat 1.'
        );
    }

    echo "Patch ACL PASS.\n";
    echo "- {$oldKey}\n";
    echo "  menjadi\n";
    echo "- {$newKey}\n\n";

    chdir($root);

    echo "Membersihkan Laravel cache...\n";

    passthru(
        escapeshellarg(PHP_BINARY)
        . ' '
        . escapeshellarg($root . '/artisan')
        . ' optimize:clear',
        $clearCode
    );

    if ($clearCode !== 0) {
        echo "\nPERINGATAN: optimize:clear exit code {$clearCode}.\n";
        echo "Jalankan manual: php artisan optimize:clear\n";
    }

    echo "\nSELESAI.\n";
    echo "Jalankan checker:\n";
    echo "php tools/check_internal_chat_audit_acl_parent_v1.php\n";
} catch (Throwable $e) {
    copy(
        $backup,
        $aclPath
    );

    fwrite(
        STDERR,
        "\nPATCH GAGAL: "
        . $e->getMessage()
        . "\n"
    );

    fwrite(
        STDERR,
        "acl.php dipulihkan dari backup otomatis.\n"
    );

    exit(1);
}
