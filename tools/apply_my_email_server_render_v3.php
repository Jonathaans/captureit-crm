<?php

declare(strict_types=1);

/**
 * MY EMAIL SERVER RENDER V3
 *
 * Berdasarkan diagnostic:
 * - target view sudah pasti:
 *   packages/Webkul/Admin/src/Resources/views/user-email/message.blade.php
 * - controller show() hanya mengirim $message
 * - model memiliki html_body (dipakai juga oleh reply compose)
 * - Reply / Reply All / Back sudah ada dan harus dipertahankan
 *
 * V3 berhenti menebak DOM dengan JavaScript.
 *
 * Strategi:
 * 1. hapus renderer V2;
 * 2. cari exact Blade echo yang mencetak $message->html_body;
 * 3. ganti HANYA echo body tersebut dengan server-side iframe srcdoc;
 * 4. sandbox iframe TANPA allow-scripts / allow-same-origin / allow-forms;
 * 5. <base target="_blank"> membuat link normal dapat diklik ke tab baru.
 *
 * Tidak mengubah controller/database/route/reply logic.
 */

$root = dirname(__DIR__);

$target =
    $root
    . '/packages/Webkul/Admin/src/Resources/views/user-email/message.blade.php';

$v2Marker =
    '{{-- MY EMAIL NORMAL READER V2 --}}';

$v3Marker =
    '{{-- MY EMAIL SERVER RENDER V3 --}}';

function fail(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

function atomicWrite(string $path, string $contents): void
{
    $tmp =
        $path
        . '.tmp-'
        . bin2hex(random_bytes(4));

    if (
        file_put_contents(
            $tmp,
            $contents
        ) === false
    ) {
        @unlink($tmp);

        throw new RuntimeException(
            "Gagal menulis temp file: {$tmp}"
        );
    }

    if (!rename($tmp, $path)) {
        @unlink($tmp);

        throw new RuntimeException(
            "Gagal mengganti file: {$path}"
        );
    }
}

function removeRendererBlock(
    string $source,
    string $marker
): array {
    $count = 0;

    while (
        ($markerPos = strpos($source, $marker))
        !== false
    ) {
        $scriptOpen =
            strpos(
                $source,
                '<script>',
                $markerPos
            );

        $scriptClose =
            $scriptOpen !== false
                ? strpos(
                    $source,
                    '</script>',
                    $scriptOpen
                )
                : false;

        if (
            $scriptOpen === false
            || $scriptClose === false
        ) {
            throw new RuntimeException(
                'Renderer V2 marker ditemukan, tetapi block script tidak lengkap.'
            );
        }

        $end =
            $scriptClose
            + strlen('</script>');

        $source =
            substr(
                $source,
                0,
                $markerPos
            )
            . substr(
                $source,
                $end
            );

        $count++;
    }

    return [$source, $count];
}

echo "MY EMAIL SERVER RENDER V3\n";
echo "=========================\n\n";

if (!is_file($target)) {
    fail(
        "Target Blade tidak ditemukan:\n{$target}"
    );
}

$original =
    file_get_contents($target);

if ($original === false) {
    fail(
        'Gagal membaca message.blade.php.'
    );
}

if (
    str_contains(
        $original,
        $v3Marker
    )
) {
    echo "V3 sudah terpasang. Tidak ada perubahan.\n";
    exit(0);
}

$stamp =
    date('Ymd-His');

$backup =
    $target
    . '.bak-my-email-server-render-v3-'
    . $stamp;

if (!copy($target, $backup)) {
    fail(
        "Gagal membuat backup:\n{$backup}"
    );
}

echo "Backup:\n{$backup}\n\n";

try {
    $blade =
        $original;

    /*
    |--------------------------------------------------------------------------
    | 1. Remove failed V2 runtime renderer
    |--------------------------------------------------------------------------
    */

    [
        $blade,
        $removedV2,
    ] =
        removeRendererBlock(
            $blade,
            $v2Marker
        );

    echo
        "Cleanup renderer V2: {$removedV2} block\n";

    /*
    |--------------------------------------------------------------------------
    | 2. Find exact escaped Blade echo that outputs incoming email HTML
    |--------------------------------------------------------------------------
    |
    | We intentionally do NOT assume its surrounding <div> structure.
    | Only the echo itself is replaced, so Reply/Reply All/Attachments/Trash
    | directives cannot become unbalanced.
    |
    */

    $searchAreaEnd =
        strpos(
            $blade,
            'Attachments ('
        );

    $searchArea =
        $searchAreaEnd !== false
            ? substr(
                $blade,
                0,
                $searchAreaEnd
            )
            : $blade;

    preg_match_all(
        '~\{\{\s*([\s\S]*?)\s*\}\}~',
        $searchArea,
        $echoMatches,
        PREG_SET_ORDER
    );

    $htmlCandidates = [];

    foreach ($echoMatches as $match) {
        $full =
            $match[0];

        $expression =
            $match[1]
            ?? '';

        if (
            str_contains(
                $expression,
                '$message'
            )
            && str_contains(
                $expression,
                'html_body'
            )
        ) {
            $htmlCandidates[] = [
                'full' =>
                    $full,

                'expression' =>
                    trim($expression),
            ];
        }
    }

    if (count($htmlCandidates) !== 1) {
        echo "\nKandidat body HTML ditemukan: "
            . count($htmlCandidates)
            . "\n";

        foreach ($htmlCandidates as $i => $candidate) {
            echo
                '#'
                . ($i + 1)
                . ': '
                . $candidate['expression']
                . PHP_EOL;
        }

        throw new RuntimeException(
            'Exact html_body echo tidak ditemukan tepat 1 kali. '
            . 'Source dibatalkan agar tidak menebak.'
        );
    }

    $bodyEcho =
        $htmlCandidates[0]['full'];

    echo "\nExact body echo ditemukan:\n";
    echo
        trim(
            $bodyEcho
        )
        . "\n\n";

    /*
    |--------------------------------------------------------------------------
    | 3. Server-side rendering block
    |--------------------------------------------------------------------------
    |
    | The iframe srcdoc attribute is rendered with normal Blade {{ }} escaping.
    | Browser decodes the attribute as an HTML document INSIDE the sandbox.
    |
    | Sandbox deliberately DOES NOT include:
    | - allow-scripts
    | - allow-same-origin
    | - allow-forms
    | - allow-top-navigation
    |
    | Existing HTML email layout/CSS remains visually useful while active
    | content cannot execute in CRM origin.
    |
    */

    $replacement = <<<'BLADE'
{{-- MY EMAIL SERVER RENDER V3 --}}
@php
    $incomingEmailHtml =
        trim(
            (string) (
                $message->html_body
                ?? ''
            )
        );

    $incomingEmailText =
        trim(
            (string) (
                $message->text_body
                ?? ''
            )
        );

    if ($incomingEmailHtml !== '') {
        $emailReaderHead =
            '<base target="_blank">'
            .'<style>'
            .'html,body{margin:0;padding:0;background:#fff;color:#111827;max-width:100%;}'
            .'body{box-sizing:border-box;padding:18px;font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:1.55;}'
            .'img,table{max-width:100%!important;}'
            .'img{height:auto!important;}'
            .'a[href]{color:#2563eb!important;text-decoration:underline!important;cursor:pointer!important;}'
            .'</style>';

        $headCount =
            0;

        $emailFrameHtml =
            preg_replace(
                '/<head(\s[^>]*)?>/i',
                '$0'
                    .$emailReaderHead,
                $incomingEmailHtml,
                1,
                $headCount
            );

        if (
            ! is_string(
                $emailFrameHtml
            )
            || $headCount < 1
        ) {
            $emailFrameHtml =
                '<!doctype html><html><head>'
                .$emailReaderHead
                .'</head><body>'
                .$incomingEmailHtml
                .'</body></html>';
        }
    } else {
        $emailFrameHtml =
            '<!doctype html><html><head>'
            .'<base target="_blank">'
            .'<style>'
            .'html,body{margin:0;padding:0;background:#fff;color:#111827;}'
            .'body{box-sizing:border-box;padding:18px;font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:1.55;white-space:pre-wrap;overflow-wrap:anywhere;}'
            .'a[href]{color:#2563eb;text-decoration:underline;}'
            .'</style>'
            .'</head><body>'
            .e(
                $incomingEmailText
            )
            .'</body></html>';
    }
@endphp

<iframe
    title="Email message"
    sandbox="allow-popups allow-popups-to-escape-sandbox"
    referrerpolicy="no-referrer"
    srcdoc="{{ $emailFrameHtml }}"
    class="block w-full rounded-lg border-0 bg-white"
    style="width:100%;height:62vh;min-height:440px;border:0;background:#fff;"
></iframe>
BLADE;

    $blade =
        str_replace(
            $bodyEcho,
            $replacement,
            $blade,
            $replaceCount
        );

    if ($replaceCount !== 1) {
        throw new RuntimeException(
            "Body echo replacement count={$replaceCount}, expected 1."
        );
    }

    /*
    |--------------------------------------------------------------------------
    | 4. Write + structural validation
    |--------------------------------------------------------------------------
    */

    atomicWrite(
        $target,
        $blade
    );

    $check =
        file_get_contents(
            $target
        );

    if ($check === false) {
        throw new RuntimeException(
            'Gagal membaca hasil patch.'
        );
    }

    $required = [
        $v3Marker,
        '$message->html_body',
        '$message->text_body',
        'srcdoc="{{ $emailFrameHtml }}"',
        'sandbox="allow-popups allow-popups-to-escape-sandbox"',
        '<base target="_blank">',
        'Reply',
        'Reply All',
    ];

    foreach ($required as $needle) {
        if (
            !str_contains(
                $check,
                $needle
            )
        ) {
            throw new RuntimeException(
                "Validation gagal: {$needle}"
            );
        }
    }

    if (
        str_contains(
            $check,
            $v2Marker
        )
    ) {
        throw new RuntimeException(
            'Renderer V2 masih tersisa.'
        );
    }

    /*
     * The sandbox must not acquire dangerous tokens.
     */
    if (
        preg_match(
            '~sandbox="[^"]*(?:allow-scripts|allow-same-origin|allow-forms|allow-top-navigation)[^"]*"~i',
            $check
        ) === 1
    ) {
        throw new RuntimeException(
            'Sandbox memiliki permission yang terlalu luas.'
        );
    }

    echo "Patch PASS.\n";
    echo "- Renderer JS V2 dibersihkan.\n";
    echo "- Raw html_body tidak lagi dicetak sebagai text.\n";
    echo "- HTML email dirender server-side ke sandboxed iframe.\n";
    echo "- Link email memakai base target=_blank.\n";
    echo "- Reply / Reply All tetap dipertahankan.\n";
    echo "- Controller/database/routes tidak diubah.\n\n";

    chdir($root);

    echo "Membersihkan compiled Blade...\n";

    passthru(
        escapeshellarg(PHP_BINARY)
        . ' '
        . escapeshellarg(
            $root
            . '/artisan'
        )
        . ' view:clear',
        $clearCode
    );

    if ($clearCode !== 0) {
        echo
            "\nPERINGATAN: view:clear exit code {$clearCode}.\n";
    }

    echo "\nSELESAI.\n";
    echo "Jalankan checker:\n";
    echo "php tools/check_my_email_server_render_v3.php\n";
} catch (Throwable $e) {
    copy(
        $backup,
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
        "message.blade.php dipulihkan dari backup otomatis.\n"
    );

    exit(1);
}
