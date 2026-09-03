<?php

declare(strict_types=1);

/**
 * SIDEBAR MENU POSITION FIX V1
 *
 * Masalah:
 * - My Email punya sort sangat besar (sebelumnya 89).
 * - Internal Chat Audit punya sort sangat besar (sebelumnya 999).
 * - Pada sidebar yang tingginya terbatas, keduanya jatuh jauh di bawah fold
 *   dan tampak seolah-olah "hilang".
 *
 * Fix:
 * - My Email diletakkan tepat setelah Mail.
 * - Internal Chat Audit diletakkan tepat setelah Operations Dashboard.
 *
 * Implementasi aman:
 * - Tidak memakai regex replacement global.
 * - Hanya mengganti BARIS sort di block menu berdasarkan key.
 * - Backup + PHP lint + rollback otomatis.
 * - Tidak menyentuh ACL, role, route, database, atau Webkul\Core\Acl.
 */

$root = dirname(__DIR__);

$menuPath =
    $root . '/packages/Webkul/Admin/src/Config/menu.php';

function fail(string $message, int $code = 1): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit($code);
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

/**
 * Return [line index of key, line index of closing block, indentation].
 */
function findMenuBlock(array $lines, string $key): array
{
    $keyLine = null;

    foreach ($lines as $index => $line) {
        if (
            preg_match(
                "~['\"]key['\"]\s*=>\s*['\"]"
                . preg_quote($key, '~')
                . "['\"]~",
                $line
            )
        ) {
            if ($keyLine !== null) {
                throw new RuntimeException(
                    "Menu key '{$key}' ditemukan lebih dari sekali."
                );
            }

            $keyLine = $index;
        }
    }

    if ($keyLine === null) {
        throw new RuntimeException(
            "Menu key '{$key}' tidak ditemukan."
        );
    }

    $start = $keyLine;

    while ($start >= 0) {
        if (preg_match('/^\s*\[\s*$/', $lines[$start])) {
            break;
        }

        $start--;
    }

    if ($start < 0) {
        throw new RuntimeException(
            "Pembuka block menu '{$key}' tidak ditemukan."
        );
    }

    preg_match('/^(\s*)/', $lines[$start], $match);

    $indent =
        $match[1] ?? '';

    $end = null;

    for ($i = $keyLine + 1; $i < count($lines); $i++) {
        if (
            preg_match(
                '/^'
                . preg_quote($indent, '/')
                . '\],\s*$/',
                $lines[$i]
            )
        ) {
            $end = $i;
            break;
        }
    }

    if ($end === null) {
        throw new RuntimeException(
            "Penutup block menu '{$key}' tidak ditemukan."
        );
    }

    return [
        $start,
        $end,
        $indent,
    ];
}

function readSort(array $lines, string $key): float
{
    [$start, $end] =
        findMenuBlock(
            $lines,
            $key
        );

    for ($i = $start; $i <= $end; $i++) {
        if (
            preg_match(
                "~['\"]sort['\"]\s*=>\s*([0-9]+(?:\.[0-9]+)?)~",
                $lines[$i],
                $match
            )
        ) {
            return (float) $match[1];
        }
    }

    throw new RuntimeException(
        "Sort menu '{$key}' tidak ditemukan."
    );
}

function updateSort(
    array &$lines,
    string $key,
    float $newSort
): void {
    [$start, $end] =
        findMenuBlock(
            $lines,
            $key
        );

    $sortLine = null;

    for ($i = $start; $i <= $end; $i++) {
        if (
            preg_match(
                "~^(\s*)['\"]sort['\"]\s*=>~",
                $lines[$i],
                $match
            )
        ) {
            if ($sortLine !== null) {
                throw new RuntimeException(
                    "Block '{$key}' memiliki lebih dari satu sort."
                );
            }

            $sortLine = $i;
            $indent =
                $match[1] ?? '';
        }
    }

    if ($sortLine === null) {
        throw new RuntimeException(
            "Sort menu '{$key}' tidak ditemukan."
        );
    }

    $formatted =
        rtrim(
            rtrim(
                number_format(
                    $newSort,
                    2,
                    '.',
                    ''
                ),
                '0'
            ),
            '.'
        );

    $lines[$sortLine] =
        $indent
        . "'sort'       => "
        . $formatted
        . ',';
}

echo "SIDEBAR MENU POSITION FIX V1\n";
echo "============================\n\n";

if (!is_file($menuPath)) {
    fail(
        "menu.php tidak ditemukan: {$menuPath}"
    );
}

$original =
    file_get_contents(
        $menuPath
    );

if ($original === false) {
    fail(
        'Gagal membaca menu.php.'
    );
}

$lineEnding =
    str_contains(
        $original,
        "\r\n"
    )
        ? "\r\n"
        : "\n";

$lines =
    preg_split(
        '/\r\n|\n|\r/',
        $original
    );

if (!is_array($lines)) {
    fail(
        'Gagal memecah menu.php menjadi lines.'
    );
}

try {
    $operationsSort =
        readSort(
            $lines,
            'operations-dashboard'
        );

    $mailSort =
        readSort(
            $lines,
            'mail'
        );

    $myEmailOldSort =
        readSort(
            $lines,
            'my-email'
        );

    $auditOldSort =
        readSort(
            $lines,
            'internal-chat-audit'
        );

    /*
     * Fractional sort keeps the target immediately after its related menu
     * without needing to renumber every menu in the CRM.
     */
    $auditNewSort =
        $operationsSort
        + 0.10;

    $myEmailNewSort =
        $mailSort
        + 0.10;

    echo "Current menu sorts:\n";
    echo "- Operations Dashboard : {$operationsSort}\n";
    echo "- Internal Chat Audit  : {$auditOldSort}\n";
    echo "- Mail                 : {$mailSort}\n";
    echo "- My Email             : {$myEmailOldSort}\n\n";

    echo "New menu sorts:\n";
    echo "- Internal Chat Audit  : {$auditNewSort}\n";
    echo "- My Email             : {$myEmailNewSort}\n\n";

    updateSort(
        $lines,
        'internal-chat-audit',
        $auditNewSort
    );

    updateSort(
        $lines,
        'my-email',
        $myEmailNewSort
    );

    $updated =
        implode(
            $lineEnding,
            $lines
        );

    $stamp =
        date('Ymd-His');

    $backup =
        $menuPath
        . '.bak-sidebar-menu-position-v1-'
        . $stamp;

    if (!copy($menuPath, $backup)) {
        throw new RuntimeException(
            "Gagal membuat backup: {$backup}"
        );
    }

    echo "Backup:\n{$backup}\n\n";

    $tmp =
        $menuPath
        . '.tmp-'
        . bin2hex(
            random_bytes(4)
        );

    if (
        file_put_contents(
            $tmp,
            $updated
        ) === false
    ) {
        @unlink($tmp);

        throw new RuntimeException(
            'Gagal menulis temporary menu.php.'
        );
    }

    if (!rename($tmp, $menuPath)) {
        @unlink($tmp);

        throw new RuntimeException(
            'Gagal mengganti menu.php.'
        );
    }

    [$lintCode, $lintOutput] =
        phpLint(
            $menuPath
        );

    if ($lintCode !== 0) {
        copy(
            $backup,
            $menuPath
        );

        throw new RuntimeException(
            "menu.php lint gagal dan sudah di-rollback:\n{$lintOutput}"
        );
    }

    /*
     * Re-read and verify exact sort values.
     */
    $checkText =
        file_get_contents(
            $menuPath
        );

    $checkLines =
        preg_split(
            '/\r\n|\n|\r/',
            (string) $checkText
        );

    $auditCheck =
        readSort(
            $checkLines,
            'internal-chat-audit'
        );

    $myEmailCheck =
        readSort(
            $checkLines,
            'my-email'
        );

    if (
        abs(
            $auditCheck
            - $auditNewSort
        ) > 0.001
        || abs(
            $myEmailCheck
            - $myEmailNewSort
        ) > 0.001
    ) {
        copy(
            $backup,
            $menuPath
        );

        throw new RuntimeException(
            'Post-write sort verification gagal; menu.php sudah di-rollback.'
        );
    }

    echo "Patch PASS.\n";
    echo "- Internal Chat Audit sekarang tepat setelah Operations Dashboard.\n";
    echo "- My Email sekarang tepat setelah Mail.\n";
    echo "- ACL / role / database tidak diubah.\n\n";

    chdir(
        $root
    );

    echo "Clearing Laravel cache...\n";

    passthru(
        escapeshellarg(
            PHP_BINARY
        )
        . ' '
        . escapeshellarg(
            $root
            . '/artisan'
        )
        . ' optimize:clear',
        $clearCode
    );

    if ($clearCode !== 0) {
        echo
            "\nPERINGATAN: optimize:clear exit code {$clearCode}.\n";
    }

    echo "\nSELESAI.\n";
    echo "Jalankan checker:\n";
    echo "php tools/check_sidebar_menu_position_fix_v1.php\n";
} catch (Throwable $e) {
    fwrite(
        STDERR,
        "\nPATCH GAGAL: "
        . $e->getMessage()
        . "\n"
    );

    exit(1);
}
