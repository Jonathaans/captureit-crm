<?php

declare(strict_types=1);

/**
 * MY EMAIL SERVER RENDER V3.1
 *
 * Fix blank iframe from V3.
 *
 * Root cause:
 * V3 put the whole HTML email directly into iframe srcdoc="{{ ... }}".
 * For large Microsoft/Outlook HTML bodies this can become fragile in the
 * generated HTML attribute and may produce an empty frame.
 *
 * V3.1:
 * - keeps server-side selection of $message->html_body
 * - keeps Reply / Reply All / Back untouched
 * - removes V3 block only
 * - uses an EMPTY sandboxed iframe
 * - passes email HTML to JavaScript through safe json_encode()
 * - assigns iframe.srcdoc at runtime
 * - no DOM guessing / no body selector hunting
 *
 * Security:
 * iframe sandbox DOES NOT include:
 * - allow-scripts
 * - allow-same-origin
 * - allow-forms
 * - allow-top-navigation
 *
 * Link behavior:
 * injected <base target="_blank"> plus allow-popups.
 */

$root = dirname(__DIR__);

$target =
    $root
    . '/packages/Webkul/Admin/src/Resources/views/user-email/message.blade.php';

$v3Marker =
    '{{-- MY EMAIL SERVER RENDER V3 --}}';

$v31Marker =
    '{{-- MY EMAIL SERVER RENDER V3.1 --}}';

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
            "Gagal menulis temporary file: {$tmp}"
        );
    }

    if (!rename($tmp, $path)) {
        @unlink($tmp);

        throw new RuntimeException(
            "Gagal mengganti file: {$path}"
        );
    }
}

echo "MY EMAIL SERVER RENDER V3.1\n";
echo "===========================\n\n";

if (!is_file($target)) {
    fail("Target Blade tidak ditemukan:\n{$target}");
}

$original =
    file_get_contents($target);

if ($original === false) {
    fail('Gagal membaca message.blade.php.');
}

if (str_contains($original, $v31Marker)) {
    echo "V3.1 sudah terpasang. Tidak ada perubahan.\n";
    exit(0);
}

$markerPos =
    strpos(
        $original,
        $v3Marker
    );

if ($markerPos === false) {
    fail(
        "Marker V3 tidak ditemukan.\n"
        . "Jangan jalankan patch ini pada source yang berbeda."
    );
}

/*
 * V3 replacement always ends at the iframe closing tag.
 * We replace only that exact injected block.
 */
$iframeEnd =
    strpos(
        $original,
        '</iframe>',
        $markerPos
    );

if ($iframeEnd === false) {
    fail(
        'Closing </iframe> V3 tidak ditemukan. Patch dibatalkan.'
    );
}

$iframeEnd +=
    strlen('</iframe>');

$v3Block =
    substr(
        $original,
        $markerPos,
        $iframeEnd - $markerPos
    );

if (
    !str_contains(
        $v3Block,
        '$message->html_body'
    )
    || !str_contains(
        $v3Block,
        '$emailFrameHtml'
    )
) {
    fail(
        'Block V3 tidak sesuai signature yang diharapkan. Patch dibatalkan.'
    );
}

$stamp =
    date('Ymd-His');

$backup =
    $target
    . '.bak-my-email-server-render-v3_1-'
    . $stamp;

if (!copy($target, $backup)) {
    fail("Gagal membuat backup:\n{$backup}");
}

echo "Backup:\n{$backup}\n\n";

$replacement = <<<'BLADE'
{{-- MY EMAIL SERVER RENDER V3.1 --}}
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

    $readerHead =
        '<base target="_blank">'
        .'<style>'
        .'html,body{margin:0;padding:0;background:#fff;color:#111827;max-width:100%;}'
        .'body{box-sizing:border-box;padding:18px;font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:1.55;overflow-wrap:anywhere;}'
        .'img,table{max-width:100%!important;}'
        .'img{height:auto!important;}'
        .'a[href]{color:#2563eb!important;text-decoration:underline!important;cursor:pointer!important;}'
        .'</style>';

    if ($incomingEmailHtml !== '') {
        $headCount =
            0;

        $emailFrameHtml =
            preg_replace(
                '/<head(\s[^>]*)?>/i',
                '$0'
                    .$readerHead,
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
                .$readerHead
                .'</head><body>'
                .$incomingEmailHtml
                .'</body></html>';
        }
    } else {
        $emailFrameHtml =
            '<!doctype html><html><head>'
            .$readerHead
            .'</head><body style="white-space:pre-wrap">'
            .e(
                $incomingEmailText
            )
            .'</body></html>';
    }

    /*
     * JSON_HEX_* prevents literal </script>, quotes and angle brackets from
     * breaking the parent document while preserving the original HTML string.
     */
    $emailFrameJson =
        json_encode(
            $emailFrameHtml,
            JSON_HEX_TAG
            | JSON_HEX_AMP
            | JSON_HEX_APOS
            | JSON_HEX_QUOT
            | JSON_UNESCAPED_SLASHES
            | JSON_UNESCAPED_UNICODE
        );

    if ($emailFrameJson === false) {
        $emailFrameJson =
            json_encode(
                '<!doctype html><html><body>Email content could not be rendered.</body></html>'
            );
    }
@endphp

<iframe
    id="my-email-message-frame-v31"
    title="Email message"
    sandbox="allow-popups allow-popups-to-escape-sandbox"
    referrerpolicy="no-referrer"
    class="block w-full rounded-lg border-0 bg-white"
    style="width:100%;height:62vh;min-height:440px;border:0;background:#fff;"
></iframe>

<script>
(() => {
    const frame =
        document.getElementById(
            'my-email-message-frame-v31'
        );

    if (!frame) {
        return;
    }

    const emailHtml =
        {!! $emailFrameJson !!};

    frame.srcdoc =
        emailHtml;
})();
</script>
BLADE;

try {
    $updated =
        substr(
            $original,
            0,
            $markerPos
        )
        . $replacement
        . substr(
            $original,
            $iframeEnd
        );

    atomicWrite(
        $target,
        $updated
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
        $v31Marker,
        'my-email-message-frame-v31',
        '$emailFrameJson',
        'JSON_HEX_TAG',
        'frame.srcdoc',
        'allow-popups allow-popups-to-escape-sandbox',
        '<base target="_blank">',
        'Reply',
        'Reply All',
    ];

    foreach ($required as $needle) {
        if (!str_contains($check, $needle)) {
            throw new RuntimeException(
                "Validation gagal: {$needle}"
            );
        }
    }

    if (str_contains($check, $v3Marker)) {
        throw new RuntimeException(
            'Marker V3 lama masih tersisa.'
        );
    }

    if (
        preg_match(
            '~sandbox="[^"]*(?:allow-scripts|allow-same-origin|allow-forms|allow-top-navigation)[^"]*"~i',
            $check
        ) === 1
    ) {
        throw new RuntimeException(
            'Sandbox memiliki permission terlalu luas.'
        );
    }

    echo "Patch PASS.\n";
    echo "- V3 srcdoc attribute diganti runtime assignment.\n";
    echo "- HTML body tetap berasal langsung dari message->html_body.\n";
    echo "- Tidak ada DOM guessing.\n";
    echo "- Reply / Reply All / Back tetap dipertahankan.\n";
    echo "- Link email tetap diarahkan ke tab baru.\n";
    echo "- Controller/database/routes tidak diubah.\n\n";

    chdir($root);

    echo "Membersihkan compiled Blade...\n";

    passthru(
        escapeshellarg(PHP_BINARY)
        . ' '
        . escapeshellarg($root . '/artisan')
        . ' view:clear',
        $clearCode
    );

    if ($clearCode !== 0) {
        echo
            "\nPERINGATAN: view:clear exit code {$clearCode}.\n";
    }

    echo "\nSELESAI.\n";
    echo "Jalankan checker:\n";
    echo "php tools/check_my_email_server_render_v3_1.php\n";
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
