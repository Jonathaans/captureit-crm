<?php

declare(strict_types=1);

/**
 * MY EMAIL READABLE SAFE V4
 *
 * V1/V2/V3/V3.1 memakai DOM guessing / iframe dan hasilnya tidak stabil.
 * V4 mengambil jalan yang lebih sederhana:
 *
 * 1. Restore message.blade.php dari backup BERSIH sebelum V1.
 * 2. Cari exact Blade echo yang menampilkan $message->html_body.
 * 3. Ganti HANYA echo body tersebut.
 * 4. Sanitasi HTML di server dengan DOMDocument.
 * 5. Render HTML aman langsung di card email. TANPA iframe/srcdoc/JS.
 *
 * Reply / Reply All / Back / Attachments / Move to Trash tetap berasal
 * dari source original dan tidak dibangun ulang.
 */

$root = dirname(__DIR__);

$target =
    $root
    . '/packages/Webkul/Admin/src/Resources/views/user-email/message.blade.php';

$cleanBackupPattern =
    $target
    . '.bak-my-email-safe-html-render-v1-*';

$marker =
    'MY EMAIL READABLE SAFE V4';

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
        static function (string $a, string $b): int {
            $aTime = filemtime($a) ?: 0;
            $bTime = filemtime($b) ?: 0;

            if ($aTime === $bTime) {
                return strcmp($b, $a);
            }

            return $bTime <=> $aTime;
        }
    );

    return $files[0] ?? null;
}

function atomicWrite(string $path, string $contents): void
{
    $tmp =
        $path
        . '.tmp-'
        . bin2hex(
            random_bytes(4)
        );

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

echo "MY EMAIL READABLE SAFE V4\n";
echo "=========================\n\n";

if (!is_file($target)) {
    fail(
        "Target Blade tidak ditemukan:\n{$target}"
    );
}

$current =
    file_get_contents($target);

if ($current === false) {
    fail(
        'Gagal membaca message.blade.php saat ini.'
    );
}

if (str_contains($current, $marker)) {
    echo "V4 sudah terpasang. Tidak ada perubahan.\n";
    exit(0);
}

/*
|--------------------------------------------------------------------------
| CLEAN SOURCE
|--------------------------------------------------------------------------
|
| Backup V1 dibuat SEBELUM patch My Email pertama diterapkan. Karena semua
| eksperimen berikutnya hanya menyentuh view ini, clean backup adalah titik
| paling aman untuk berhenti mewarisi sisa iframe/script yang rusak.
|
*/

$cleanBackup =
    latestFile(
        $cleanBackupPattern
    );

if (!$cleanBackup) {
    fail(
        "Backup bersih sebelum V1 tidak ditemukan.\n"
        . "Pattern:\n{$cleanBackupPattern}\n\n"
        . "Source TIDAK diubah."
    );
}

$clean =
    file_get_contents(
        $cleanBackup
    );

if ($clean === false) {
    fail(
        "Gagal membaca clean backup:\n{$cleanBackup}"
    );
}

echo "Clean source:\n{$cleanBackup}\n\n";

/*
 * Safety: clean source must still be the normal incoming-email detail page.
 */
$cleanRequirements = [
    'Reply',
    'Reply All',
    'admin.my-email.attachments.download',
    'admin.my-email.trash.move',
    '</x-admin::layouts>',
];

foreach ($cleanRequirements as $needle) {
    if (!str_contains($clean, $needle)) {
        fail(
            "Clean backup tidak lolos preflight: {$needle}\n"
            . "Source TIDAK diubah."
        );
    }
}

/*
|--------------------------------------------------------------------------
| FIND THE REAL BODY ECHO
|--------------------------------------------------------------------------
*/

$attachmentsPos =
    strpos(
        $clean,
        'Attachments ('
    );

$searchArea =
    $attachmentsPos !== false
        ? substr(
            $clean,
            0,
            $attachmentsPos
        )
        : $clean;

preg_match_all(
    '~\{\{\s*([\s\S]*?)\s*\}\}~',
    $searchArea,
    $matches,
    PREG_SET_ORDER
);

$candidates = [];

foreach ($matches as $match) {
    $expression =
        trim(
            (string) (
                $match[1]
                ?? ''
            )
        );

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
        $candidates[] = [
            'full' =>
                $match[0],

            'expression' =>
                $expression,
        ];
    }
}

if (count($candidates) !== 1) {
    echo
        "Kandidat html_body echo: "
        . count($candidates)
        . "\n";

    foreach ($candidates as $index => $candidate) {
        echo
            '#'
            . ($index + 1)
            . ' '
            . $candidate['expression']
            . PHP_EOL;
    }

    fail(
        "Exact body echo tidak ditemukan tepat 1 kali.\n"
        . "Source TIDAK diubah."
    );
}

$bodyEcho =
    $candidates[0]['full'];

echo "Exact body echo:\n";
echo trim($bodyEcho) . "\n\n";

/*
|--------------------------------------------------------------------------
| NEW SAFE SERVER-SIDE BODY RENDERER
|--------------------------------------------------------------------------
*/

$replacement = <<<'BLADE'
{{-- MY EMAIL READABLE SAFE V4 --}}
@php
    $rawEmailHtml =
        trim(
            (string) (
                $message->html_body
                ?? ''
            )
        );

    $rawEmailText =
        trim(
            (string) (
                $message->text_body
                ?? ''
            )
        );

    $safeEmailHtml =
        '';

    $looksLikeHtml =
        $rawEmailHtml !== ''
        && preg_match(
            '/<\s*(?:html|body|head|div|p|table|span|a|br)\b/i',
            $rawEmailHtml
        );

    if (
        $looksLikeHtml
        && class_exists(
            \DOMDocument::class
        )
    ) {
        $previousLibxmlState =
            libxml_use_internal_errors(
                true
            );

        try {
            $dom =
                new \DOMDocument(
                    '1.0',
                    'UTF-8'
                );

            $wrappedHtml =
                '<!doctype html>'
                .'<html><head>'
                .'<meta charset="utf-8">'
                .'</head><body>'
                .$rawEmailHtml
                .'</body></html>';

            $loaded =
                $dom->loadHTML(
                    $wrappedHtml,
                    LIBXML_HTML_NODEFDTD
                    | LIBXML_NOERROR
                    | LIBXML_NOWARNING
                );

            if ($loaded) {
                /*
                 * These elements can execute code, submit forms, navigate,
                 * import external CSS, or otherwise behave unlike normal
                 * readable email content.
                 */
                $blockedTags = [
                    'script',
                    'style',
                    'iframe',
                    'object',
                    'embed',
                    'form',
                    'input',
                    'button',
                    'textarea',
                    'select',
                    'option',
                    'link',
                    'meta',
                    'base',
                    'svg',
                    'math',
                    'video',
                    'audio',
                    'source',
                ];

                foreach ($blockedTags as $blockedTag) {
                    while (
                        $dom
                            ->getElementsByTagName(
                                $blockedTag
                            )
                            ->length
                        > 0
                    ) {
                        $node =
                            $dom
                                ->getElementsByTagName(
                                    $blockedTag
                                )
                                ->item(
                                    0
                                );

                        if (
                            $node
                            && $node->parentNode
                        ) {
                            $node
                                ->parentNode
                                ->removeChild(
                                    $node
                                );
                        } else {
                            break;
                        }
                    }
                }

                $xpath =
                    new \DOMXPath(
                        $dom
                    );

                $elements =
                    $xpath->query(
                        '//*'
                    );

                if ($elements) {
                    foreach ($elements as $element) {
                        if (
                            ! $element
                            instanceof \DOMElement
                        ) {
                            continue;
                        }

                        /*
                         * Strip every email-supplied attribute first.
                         * We selectively restore only attributes required for
                         * readable links/tables.
                         */
                        $originalHref =
                            strtolower(
                                $element->tagName
                            ) === 'a'
                                ? trim(
                                    (string) $element->getAttribute(
                                        'href'
                                    )
                                )
                                : '';

                        $colspan =
                            in_array(
                                strtolower(
                                    $element->tagName
                                ),
                                ['td', 'th'],
                                true
                            )
                                ? trim(
                                    (string) $element->getAttribute(
                                        'colspan'
                                    )
                                )
                                : '';

                        $rowspan =
                            in_array(
                                strtolower(
                                    $element->tagName
                                ),
                                ['td', 'th'],
                                true
                            )
                                ? trim(
                                    (string) $element->getAttribute(
                                        'rowspan'
                                    )
                                )
                                : '';

                        while (
                            $element
                                ->attributes
                                ->length
                            > 0
                        ) {
                            $attribute =
                                $element
                                    ->attributes
                                    ->item(
                                        0
                                    );

                            if (! $attribute) {
                                break;
                            }

                            $element
                                ->removeAttributeNode(
                                    $attribute
                                );
                        }

                        if (
                            strtolower(
                                $element->tagName
                            ) === 'a'
                            && $originalHref !== ''
                            && preg_match(
                                '/^(?:https?:|mailto:|tel:|#)/i',
                                $originalHref
                            )
                        ) {
                            $element->setAttribute(
                                'href',
                                $originalHref
                            );

                            $element->setAttribute(
                                'target',
                                '_blank'
                            );

                            $element->setAttribute(
                                'rel',
                                'noopener noreferrer'
                            );
                        }

                        if (
                            $colspan !== ''
                            && ctype_digit(
                                $colspan
                            )
                        ) {
                            $element->setAttribute(
                                'colspan',
                                $colspan
                            );
                        }

                        if (
                            $rowspan !== ''
                            && ctype_digit(
                                $rowspan
                            )
                        ) {
                            $element->setAttribute(
                                'rowspan',
                                $rowspan
                            );
                        }
                    }
                }

                $bodyNodes =
                    $dom
                        ->getElementsByTagName(
                            'body'
                        );

                $body =
                    $bodyNodes->item(
                        0
                    );

                if ($body) {
                    foreach (
                        iterator_to_array(
                            $body->childNodes
                        )
                        as $child
                    ) {
                        $safeEmailHtml .=
                            $dom->saveHTML(
                                $child
                            );
                    }
                }
            }
        } catch (\Throwable $emailRenderException) {
            $safeEmailHtml =
                '';
        } finally {
            libxml_clear_errors();

            libxml_use_internal_errors(
                $previousLibxmlState
            );
        }
    }

    /*
     * Fallback is intentionally boring and reliable:
     * readable plain text + clickable http(s) URLs.
     */
    if (
        trim(
            $safeEmailHtml
        ) === ''
    ) {
        $fallbackText =
            $rawEmailText !== ''
                ? $rawEmailText
                : trim(
                    html_entity_decode(
                        strip_tags(
                            $rawEmailHtml
                        ),
                        ENT_QUOTES
                        | ENT_HTML5,
                        'UTF-8'
                    )
                );

        $urlPattern =
            '~(https?://[^\s<>"\']+)~iu';

        $parts =
            preg_split(
                $urlPattern,
                $fallbackText,
                -1,
                PREG_SPLIT_DELIM_CAPTURE
            )
            ?: [];

        $safeParts =
            [];

        foreach ($parts as $part) {
            if (
                preg_match(
                    '~^https?://~i',
                    $part
                )
            ) {
                $safeUrl =
                    e(
                        $part
                    );

                $safeParts[] =
                    '<a href="'
                    .$safeUrl
                    .'" target="_blank" rel="noopener noreferrer">'
                    .$safeUrl
                    .'</a>';
            } else {
                $safeParts[] =
                    e(
                        $part
                    );
            }
        }

        $safeEmailHtml =
            nl2br(
                implode(
                    '',
                    $safeParts
                )
            );
    }
@endphp

<div
    class="crm-readable-email-body"
    style="
        white-space:normal !important;
        overflow-wrap:anywhere;
        color:#111827;
        font-family:Arial,Helvetica,sans-serif;
        font-size:14px;
        line-height:1.6;
    "
>
    <style>
        .crm-readable-email-body p {
            margin: 0 0 12px;
        }

        .crm-readable-email-body div {
            max-width: 100%;
        }

        .crm-readable-email-body a[href] {
            color: #2563eb;
            text-decoration: underline;
            cursor: pointer;
        }

        .crm-readable-email-body table {
            max-width: 100%;
            border-collapse: collapse;
        }

        .crm-readable-email-body td,
        .crm-readable-email-body th {
            padding: 4px 6px;
            vertical-align: top;
        }

        .crm-readable-email-body blockquote {
            margin: 12px 0;
            padding-left: 12px;
            border-left: 3px solid #d1d5db;
            color: #4b5563;
        }

        .crm-readable-email-body hr {
            margin: 16px 0;
            border: 0;
            border-top: 1px solid #e5e7eb;
        }
    </style>

    {!! $safeEmailHtml !!}
</div>
BLADE;

/*
|--------------------------------------------------------------------------
| SAFETY BACKUP CURRENT BROKEN STATE
|--------------------------------------------------------------------------
*/

$safetyBackup =
    $target
    . '.bak-before-my-email-readable-safe-v4-'
    . date('Ymd-His');

if (!copy($target, $safetyBackup)) {
    fail(
        "Gagal membuat safety backup current state:\n{$safetyBackup}"
    );
}

echo "Safety backup current state:\n{$safetyBackup}\n\n";

try {
    $patched =
        str_replace(
            $bodyEcho,
            $replacement,
            $clean,
            $replaceCount
        );

    if ($replaceCount !== 1) {
        throw new RuntimeException(
            "Body replacement count={$replaceCount}, expected 1."
        );
    }

    /*
     * Remove inherited whitespace-pre-wrap close to the body if present.
     * The wrapper already forces white-space:normal, so this is cosmetic.
     */
    atomicWrite(
        $target,
        $patched
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
        $marker,
        '$message->html_body',
        '$message->text_body',
        'DOMDocument',
        'DOMXPath',
        'crm-readable-email-body',
        'target="_blank"',
        'Reply',
        'Reply All',
        'admin.my-email.attachments.download',
        'admin.my-email.trash.move',
    ];

    foreach ($required as $needle) {
        if (!str_contains($check, $needle)) {
            throw new RuntimeException(
                "Post-write validation gagal: {$needle}"
            );
        }
    }

    $forbidden = [
        'MY EMAIL SAFE HTML RENDER V1',
        'MY EMAIL NORMAL READER V2',
        'MY EMAIL SERVER RENDER V3',
        'my-email-message-frame-v31',
        'iframe.srcdoc',
        'frame.srcdoc',
    ];

    foreach ($forbidden as $needle) {
        if (str_contains($check, $needle)) {
            throw new RuntimeException(
                "Renderer lama masih tersisa: {$needle}"
            );
        }
    }

    echo "Patch PASS.\n";
    echo "- Source dipulihkan dulu ke clean pre-V1.\n";
    echo "- Semua iframe/srcdoc/DOM-guessing lama dibuang.\n";
    echo "- html_body disanitasi server-side lalu dirender langsung.\n";
    echo "- Link http/https/mailto/tel dapat diklik.\n";
    echo "- Reply / Reply All / Back / Attachments / Trash tetap original.\n";
    echo "- Controller/database/routes tidak diubah.\n\n";

    chdir($root);

    echo "Membersihkan compiled Blade...\n";

    passthru(
        escapeshellarg(PHP_BINARY)
        . ' '
        . escapeshellarg(
            $root . '/artisan'
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
    echo "php tools/check_my_email_readable_safe_v4.php\n";
} catch (\Throwable $exception) {
    copy(
        $safetyBackup,
        $target
    );

    fwrite(
        STDERR,
        "\nPATCH GAGAL: "
        . $exception->getMessage()
        . "\n"
    );

    fwrite(
        STDERR,
        "Current state sebelum V4 dipulihkan otomatis.\n"
    );

    exit(1);
}
