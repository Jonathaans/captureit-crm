<?php

declare(strict_types=1);

/**
 * MY EMAIL DOUBLE-ESCAPE FIX V5
 *
 * Berdasarkan diagnostic V5:
 * - Git HEAD / clean pre-V1 sudah punya iframe + srcdoc.
 * - Body asli memakai:
 *      srcdoc="{{ e($message->html_body) }}"
 *
 * Blade {{ ... }} SUDAH melakukan escaping.
 * Memanggil e() di dalam {{ }} membuat HTML email ter-escape DUA KALI.
 *
 * Akibatnya iframe menerima:
 *      &lt;html&gt;...
 * sebagai TEXT, bukan HTML document.
 *
 * Fix minimal:
 *      srcdoc="{{ e($message->html_body) }}"
 * menjadi:
 *      srcdoc="{{ $message->html_body }}"
 *
 * Blade tetap melakukan SATU escaping yang memang diperlukan untuk attribute.
 * Browser kemudian decode attribute entity dan srcdoc merender HTML normal.
 *
 * Tool ini:
 * 1. Mengambil baseline bersih pre-V1 jika ada; fallback ke Git HEAD.
 * 2. Tidak memakai V1/V2/V3/V4/V3.1 code.
 * 3. Mengubah HANYA double-escape expression.
 * 4. Memastikan iframe memiliki sandbox.
 * 5. Reply / Reply All / Back / Attachments / Trash tetap baseline original.
 */

$root = dirname(__DIR__);

$relative =
    'packages/Webkul/Admin/src/Resources/views/user-email/message.blade.php';

$target =
    $root . '/' . $relative;

$cleanPattern =
    $target . '.bak-my-email-safe-html-render-v1-*';

$marker =
    'MY EMAIL DOUBLE ESCAPE FIX V5';

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
        throw new RuntimeException("Gagal menulis temp file: {$tmp}");
    }

    if (!rename($tmp, $path)) {
        @unlink($tmp);
        throw new RuntimeException("Gagal mengganti file: {$path}");
    }
}

echo "MY EMAIL DOUBLE-ESCAPE FIX V5\n";
echo "=============================\n\n";

if (!is_file($target)) {
    fail("Target tidak ditemukan:\n{$target}");
}

$current =
    file_get_contents($target);

if ($current === false) {
    fail('Gagal membaca current message.blade.php.');
}

/*
|--------------------------------------------------------------------------
| LOAD CLEAN BASELINE
|--------------------------------------------------------------------------
*/

$cleanBackup =
    latestFile($cleanPattern);

$baseline = null;
$baselineLabel = null;

if ($cleanBackup) {
    $baseline =
        file_get_contents($cleanBackup);

    if ($baseline !== false) {
        $baselineLabel =
            'clean pre-V1 backup: ' . $cleanBackup;
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
            "Clean backup tidak tersedia dan git show gagal:\n"
            . implode(PHP_EOL, $gitOutput)
        );
    }

    $baseline =
        implode(
            PHP_EOL,
            $gitOutput
        );

    $baselineLabel =
        'Git HEAD: ' . $relative;
}

echo "Baseline:\n{$baselineLabel}\n\n";

/*
|--------------------------------------------------------------------------
| BASELINE PREFLIGHT
|--------------------------------------------------------------------------
*/

$requiredBaseline = [
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

foreach ($requiredBaseline as $needle) {
    if (!str_contains($baseline, $needle)) {
        fail(
            "Baseline preflight gagal: {$needle}\n"
            . "Source TIDAK diubah."
        );
    }
}

if (
    substr_count($baseline, '<x-admin::layouts>')
    !== 1
    || substr_count($baseline, '</x-admin::layouts>')
    !== 1
) {
    fail(
        "Baseline layout tidak seimbang.\nSource TIDAK diubah."
    );
}

/*
|--------------------------------------------------------------------------
| FIND DOUBLE-ESCAPED SRCDOC
|--------------------------------------------------------------------------
*/

$pattern =
    '~srcdoc\s*=\s*(["\'])\s*\{\{\s*e\s*\(\s*\$message->html_body\s*\)\s*\}\}\s*\1~';

preg_match_all(
    $pattern,
    $baseline,
    $matches,
    PREG_SET_ORDER | PREG_OFFSET_CAPTURE
);

if (count($matches) !== 1) {
    echo "Double-escaped srcdoc matches: " . count($matches) . "\n";

    fail(
        "Expected tepat 1 srcdoc=\"{{ e(\$message->html_body) }}\".\n"
        . "Source TIDAK diubah."
    );
}

$fullMatch =
    $matches[0][0][0];

$matchOffset =
    $matches[0][0][1];

echo "Exact double-escaped srcdoc ditemukan:\n";
echo $fullMatch . "\n\n";

/*
|--------------------------------------------------------------------------
| CHECK / HARDEN THE SAME IFRAME OPENING TAG
|--------------------------------------------------------------------------
*/

$iframeOpen =
    strrpos(
        substr($baseline, 0, $matchOffset),
        '<iframe'
    );

$iframeClose =
    strpos(
        $baseline,
        '>',
        $matchOffset
    );

if (
    $iframeOpen === false
    || $iframeClose === false
    || $iframeClose <= $iframeOpen
) {
    fail(
        "Opening iframe untuk srcdoc tidak ditemukan.\nSource TIDAK diubah."
    );
}

$iframeTag =
    substr(
        $baseline,
        $iframeOpen,
        $iframeClose - $iframeOpen + 1
    );

$patchedIframeTag =
    $iframeTag;

/*
 * Preserve existing sandbox if present.
 * If absent, add a conservative sandbox that still lets links/popups work.
 */
if (
    !preg_match(
        '~\bsandbox\s*=~i',
        $patchedIframeTag
    )
) {
    $patchedIframeTag =
        preg_replace(
            '~^<iframe\b~i',
            '<iframe sandbox="allow-popups allow-popups-to-escape-sandbox"',
            $patchedIframeTag,
            1,
            $sandboxCount
        );

    if (!is_string($patchedIframeTag) || $sandboxCount !== 1) {
        fail(
            "Gagal menambahkan sandbox ke iframe.\nSource TIDAK diubah."
        );
    }
}

/*
 * Never allow active-script / same-origin combination from our patch.
 * Existing baseline with unsafe sandbox causes abort instead of guessing.
 */
if (
    preg_match(
        '~\bsandbox\s*=\s*(["\'])([^"\']*)\1~i',
        $patchedIframeTag,
        $sandboxMatch
    ) === 1
) {
    $tokens =
        strtolower(
            (string) ($sandboxMatch[2] ?? '')
        );

    if (
        str_contains($tokens, 'allow-scripts')
        || str_contains($tokens, 'allow-same-origin')
    ) {
        fail(
            "Baseline iframe sandbox terlalu luas:\n{$patchedIframeTag}\n"
            . "Source TIDAK diubah."
        );
    }
}

/*
|--------------------------------------------------------------------------
| APPLY THE ONE REAL FIX
|--------------------------------------------------------------------------
*/

$fixedSrcdoc =
    preg_replace(
        '~e\s*\(\s*\$message->html_body\s*\)~',
        '$message->html_body',
        $fullMatch,
        1,
        $expressionCount
    );

if (
    !is_string($fixedSrcdoc)
    || $expressionCount !== 1
) {
    fail(
        "Gagal membuat fixed srcdoc expression.\nSource TIDAK diubah."
    );
}

$patchedIframeTag =
    str_replace(
        $fullMatch,
        $fixedSrcdoc,
        $patchedIframeTag,
        $srcdocCount
    );

if ($srcdocCount !== 1) {
    fail(
        "srcdoc replacement count={$srcdocCount}, expected 1.\nSource TIDAK diubah."
    );
}

$patched =
    substr($baseline, 0, $iframeOpen)
    . $patchedIframeTag
    . substr($baseline, $iframeClose + 1);

/*
 * Add only a Blade comment marker outside the iframe.
 */
$closing =
    '</x-admin::layouts>';

$patched =
    str_replace(
        $closing,
        "\n{{-- {$marker} --}}\n" . $closing,
        $patched,
        $markerCount
    );

if ($markerCount !== 1) {
    fail(
        "Marker insertion gagal.\nSource TIDAK diubah."
    );
}

/*
|--------------------------------------------------------------------------
| SAFETY BACKUP CURRENT STATE
|--------------------------------------------------------------------------
*/

$safety =
    $target
    . '.bak-before-my-email-double-escape-v5-'
    . date('Ymd-His');

if (!copy($target, $safety)) {
    fail(
        "Gagal membuat safety backup current state:\n{$safety}"
    );
}

echo "Safety backup current state:\n{$safety}\n\n";

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

    $required = [
        $marker,
        'srcdoc="{{ $message->html_body }}"',
        '$message->text_body',
        'Reply',
        'Reply All',
        'admin.my-email.attachments.download',
        'admin.my-email.trash.move',
        '<iframe',
        'sandbox=',
    ];

    foreach ($required as $needle) {
        if (!str_contains($check, $needle)) {
            throw new RuntimeException(
                "Post-write validation gagal: {$needle}"
            );
        }
    }

    if (
        str_contains(
            $check,
            'srcdoc="{{ e($message->html_body) }}"'
        )
    ) {
        throw new RuntimeException(
            'Double-escaped srcdoc masih tersisa.'
        );
    }

    $forbiddenMarkers = [
        'MY EMAIL SAFE HTML RENDER V1',
        'MY EMAIL NORMAL READER V2',
        'MY EMAIL SERVER RENDER V3',
        'MY EMAIL READABLE SAFE V4',
    ];

    foreach ($forbiddenMarkers as $oldMarker) {
        if (str_contains($check, $oldMarker)) {
            throw new RuntimeException(
                "Patch lama masih tersisa: {$oldMarker}"
            );
        }
    }

    echo "Patch PASS.\n";
    echo "- View dipulihkan ke baseline bersih.\n";
    echo "- Hanya double escaping html_body yang diperbaiki.\n";
    echo "- srcdoc sekarang memakai SATU Blade escape.\n";
    echo "- iframe sandbox dipertahankan/diperketat.\n";
    echo "- Reply / Reply All / Back / Attachments / Trash tetap baseline original.\n";
    echo "- Tidak ada JavaScript renderer baru.\n";
    echo "- Controller/database/routes tidak diubah.\n\n";

    chdir($root);

    passthru(
        escapeshellarg(PHP_BINARY)
        . ' '
        . escapeshellarg($root . '/artisan')
        . ' view:clear'
    );

    echo "\nSELESAI.\n";
    echo "Jalankan checker:\n";
    echo "php tools/check_my_email_double_escape_fix_v5.php\n";
} catch (Throwable $e) {
    copy($safety, $target);

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
