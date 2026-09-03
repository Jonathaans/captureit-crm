<?php

declare(strict_types=1);

/**
 * MY EMAIL DOUBLE-ESCAPE FIX V5.1
 *
 * Fix installer V5:
 * V5 berhasil menemukan:
 *   srcdoc="{{ e($message->html_body) }}"
 * tetapi replacement internal-nya gagal.
 *
 * V5.1 memakai exact string replacement, bukan preg_replace replacement.
 *
 * Baseline:
 * - clean pre-V1 backup jika tersedia
 * - fallback Git HEAD
 *
 * Satu perubahan utama:
 *   srcdoc="{{ e($message->html_body) }}"
 * menjadi:
 *   srcdoc="{{ $message->html_body }}"
 *
 * Blade {{ }} tetap melakukan satu escaping.
 */

$root = dirname(__DIR__);

$relative =
    'packages/Webkul/Admin/src/Resources/views/user-email/message.blade.php';

$target =
    $root . '/' . $relative;

$cleanPattern =
    $target . '.bak-my-email-safe-html-render-v1-*';

$marker =
    'MY EMAIL DOUBLE ESCAPE FIX V5.1';

$oldSrcdoc =
    'srcdoc="{{ e($message->html_body) }}"';

$newSrcdoc =
    'srcdoc="{{ $message->html_body }}"';

function fail(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

function latestFile(string $pattern): ?string
{
    $files = glob($pattern) ?: [];

    if (!$files) {
        return null;
    }

    usort(
        $files,
        static fn (string $a, string $b): int =>
            (filemtime($b) ?: 0)
            <=>
            (filemtime($a) ?: 0)
    );

    return $files[0] ?? null;
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

echo "MY EMAIL DOUBLE-ESCAPE FIX V5.1\n";
echo "===============================\n\n";

if (!is_file($target)) {
    fail("Target tidak ditemukan:\n{$target}");
}

$current =
    file_get_contents($target);

if ($current === false) {
    fail('Gagal membaca current message.blade.php.');
}

if (str_contains($current, $marker)) {
    echo "V5.1 sudah terpasang. Tidak ada perubahan.\n";
    exit(0);
}

/*
|--------------------------------------------------------------------------
| LOAD CLEAN BASELINE
|--------------------------------------------------------------------------
*/

$baseline = null;
$baselineLabel = null;

$cleanBackup =
    latestFile($cleanPattern);

if ($cleanBackup !== null) {
    $candidate =
        file_get_contents($cleanBackup);

    if (is_string($candidate)) {
        $baseline =
            $candidate;

        $baselineLabel =
            'clean pre-V1 backup: '
            . $cleanBackup;
    }
}

if (!is_string($baseline)) {
    $command =
        'git -C '
        . escapeshellarg($root)
        . ' show HEAD:'
        . escapeshellarg($relative)
        . ' 2>&1';

    exec(
        $command,
        $gitOutput,
        $gitCode
    );

    if ($gitCode !== 0) {
        fail(
            "Clean backup tidak tersedia dan Git HEAD gagal dibaca:\n"
            . implode(PHP_EOL, $gitOutput)
        );
    }

    $baseline =
        implode(
            PHP_EOL,
            $gitOutput
        );

    $baselineLabel =
        'Git HEAD: '
        . $relative;
}

echo "Baseline:\n{$baselineLabel}\n\n";

/*
|--------------------------------------------------------------------------
| PREFLIGHT
|--------------------------------------------------------------------------
*/

$required = [
    '<x-admin::layouts>',
    '</x-admin::layouts>',
    'Reply',
    'Reply All',
    'admin.my-email.attachments.download',
    'admin.my-email.trash.move',
    '$message->html_body',
    '$message->text_body',
    '<iframe',
    'srcdoc=',
];

foreach ($required as $needle) {
    if (!str_contains($baseline, $needle)) {
        fail(
            "Baseline preflight gagal: {$needle}\n"
            . "Source TIDAK diubah."
        );
    }
}

if (
    substr_count(
        $baseline,
        '<x-admin::layouts>'
    ) !== 1
    || substr_count(
        $baseline,
        '</x-admin::layouts>'
    ) !== 1
) {
    fail(
        "Baseline layout tidak seimbang.\n"
        . "Source TIDAK diubah."
    );
}

$oldCount =
    substr_count(
        $baseline,
        $oldSrcdoc
    );

echo "Exact old srcdoc count: {$oldCount}\n";

if ($oldCount !== 1) {
    fail(
        "Expected tepat 1:\n{$oldSrcdoc}\n"
        . "Source TIDAK diubah."
    );
}

/*
|--------------------------------------------------------------------------
| EXACT REPLACEMENT
|--------------------------------------------------------------------------
*/

$patched =
    str_replace(
        $oldSrcdoc,
        $newSrcdoc,
        $baseline,
        $replaceCount
    );

echo "Exact replacement count: {$replaceCount}\n";

if ($replaceCount !== 1) {
    fail(
        "Replacement gagal.\n"
        . "Source TIDAK diubah."
    );
}

/*
|--------------------------------------------------------------------------
| ENSURE SANDBOX ON THE SAME IFRAME
|--------------------------------------------------------------------------
*/

$newPos =
    strpos(
        $patched,
        $newSrcdoc
    );

if ($newPos === false) {
    fail(
        "Fixed srcdoc tidak ditemukan setelah replace.\n"
        . "Source TIDAK diubah."
    );
}

$iframeStart =
    strrpos(
        substr(
            $patched,
            0,
            $newPos
        ),
        '<iframe'
    );

$iframeEnd =
    strpos(
        $patched,
        '>',
        $newPos
    );

if (
    $iframeStart === false
    || $iframeEnd === false
    || $iframeEnd <= $iframeStart
) {
    fail(
        "Opening iframe tidak dapat diisolasi.\n"
        . "Source TIDAK diubah."
    );
}

$iframeTag =
    substr(
        $patched,
        $iframeStart,
        $iframeEnd - $iframeStart + 1
    );

echo "\nIframe sebelum hardening:\n";
echo $iframeTag . "\n\n";

if (
    !preg_match(
        '~\bsandbox(?:\s*=|\s|>)~i',
        $iframeTag
    )
) {
    $hardenedTag =
        preg_replace(
            '~^<iframe\b~i',
            '<iframe sandbox="allow-popups allow-popups-to-escape-sandbox"',
            $iframeTag,
            1,
            $sandboxAddCount
        );

    if (
        !is_string($hardenedTag)
        || $sandboxAddCount !== 1
    ) {
        fail(
            "Gagal menambahkan sandbox.\n"
            . "Source TIDAK diubah."
        );
    }

    $patched =
        substr(
            $patched,
            0,
            $iframeStart
        )
        . $hardenedTag
        . substr(
            $patched,
            $iframeEnd + 1
        );

    $iframeTag =
        $hardenedTag;

    echo "Sandbox ditambahkan.\n";
} else {
    echo "Sandbox existing dipertahankan.\n";
}

/*
 * Abort if sandbox explicitly grants scripts or same-origin.
 */
if (
    preg_match(
        '~\bsandbox\s*=\s*(["\'])([^"\']*)\1~i',
        $iframeTag,
        $sandboxMatch
    ) === 1
) {
    $tokens =
        strtolower(
            (string) (
                $sandboxMatch[2]
                ?? ''
            )
        );

    if (
        str_contains(
            $tokens,
            'allow-scripts'
        )
        || str_contains(
            $tokens,
            'allow-same-origin'
        )
    ) {
        fail(
            "Sandbox baseline terlalu luas:\n{$iframeTag}\n"
            . "Source TIDAK diubah."
        );
    }
}

/*
|--------------------------------------------------------------------------
| ADD MARKER
|--------------------------------------------------------------------------
*/

$closing =
    '</x-admin::layouts>';

$patched =
    str_replace(
        $closing,
        "\n{{-- {$marker} --}}\n"
        . $closing,
        $patched,
        $markerCount
    );

if ($markerCount !== 1) {
    fail(
        "Marker insertion gagal.\n"
        . "Source TIDAK diubah."
    );
}

/*
|--------------------------------------------------------------------------
| SAFETY BACKUP CURRENT STATE
|--------------------------------------------------------------------------
*/

$safety =
    $target
    . '.bak-before-my-email-double-escape-v5_1-'
    . date('Ymd-His');

if (!copy($target, $safety)) {
    fail(
        "Gagal membuat safety backup:\n{$safety}"
    );
}

echo "\nSafety backup current state:\n{$safety}\n\n";

try {
    atomicWrite(
        $target,
        $patched
    );

    $check =
        file_get_contents($target);

    if ($check === false) {
        throw new RuntimeException(
            'Gagal membaca hasil patch.'
        );
    }

    if (
        substr_count(
            $check,
            $newSrcdoc
        ) !== 1
    ) {
        throw new RuntimeException(
            'Fixed srcdoc tidak tepat 1 kali.'
        );
    }

    if (
        str_contains(
            $check,
            $oldSrcdoc
        )
    ) {
        throw new RuntimeException(
            'Double-escaped srcdoc masih tersisa.'
        );
    }

    foreach (
        [
            'Reply',
            'Reply All',
            'admin.my-email.attachments.download',
            'admin.my-email.trash.move',
            '$message->text_body',
            $marker,
        ]
        as $needle
    ) {
        if (!str_contains($check, $needle)) {
            throw new RuntimeException(
                "Validation gagal: {$needle}"
            );
        }
    }

    echo "Patch PASS.\n";
    echo "- Baseline bersih dipakai.\n";
    echo "- Exact double escape diperbaiki 1 kali.\n";
    echo "- Tidak ada V1/V2/V3/V4 renderer baru.\n";
    echo "- Reply / Reply All / Attachments / Trash tetap original.\n";
    echo "- Controller/database/routes tidak diubah.\n\n";

    chdir($root);

    passthru(
        escapeshellarg(PHP_BINARY)
        . ' '
        . escapeshellarg(
            $root
            . '/artisan'
        )
        . ' view:clear'
    );

    echo "\nSELESAI.\n";
    echo "Jalankan checker:\n";
    echo "php tools/check_my_email_double_escape_fix_v5_1.php\n";
} catch (Throwable $e) {
    copy(
        $safety,
        $target
    );

    fwrite(
        STDERR,
        "\nPATCH GAGAL: "
        . $e->getMessage()
        . "\n"
    );

    fwrite(
        STDERR,
        "Current state dipulihkan dari safety backup.\n"
    );

    exit(1);
}
