<?php

declare(strict_types=1);

/**
 * MY EMAIL NORMAL READER V2
 *
 * Fixes V1 selector bug:
 * V1 could select a large parent container whose textContent also contained
 * the injected renderer script itself. The result looked like the whole page
 * and JavaScript source were rendered as email content.
 *
 * V2:
 * - targets the known active Blade:
 *   packages/Webkul/Admin/src/Resources/views/user-email/message.blade.php
 * - removes V1 renderer block cleanly
 * - installs one safer renderer
 * - detects ONLY elements whose DIRECT TEXT NODE contains raw HTML email
 * - renders that one body area inside a sandboxed iframe
 * - keeps Reply / Reply All / Back / Move to Trash buttons untouched
 * - clickable http/https/mailto/tel links open in a new tab
 * - scripts/forms/iframes/event handlers from email are stripped
 *
 * No database/controller/route changes.
 */

$root = dirname(__DIR__);

$target =
    $root . '/packages/Webkul/Admin/src/Resources/views/user-email/message.blade.php';

$oldMarker =
    '{{-- MY EMAIL SAFE HTML RENDER V1 --}}';

$newMarker =
    '{{-- MY EMAIL NORMAL READER V2 --}}';

function fail(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
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

function removeStandaloneScriptByMarker(
    string $source,
    string $marker
): array {
    $count = 0;

    while (($markerPos = strpos($source, $marker)) !== false) {
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
                "Marker ditemukan tetapi block <script> tidak lengkap: {$marker}"
            );
        }

        $end =
            $scriptClose
            + strlen('</script>');

        $source =
            substr($source, 0, $markerPos)
            . substr($source, $end);

        $count++;
    }

    return [$source, $count];
}

echo "MY EMAIL NORMAL READER V2\n";
echo "=========================\n\n";

if (!is_file($target)) {
    fail(
        "Blade target tidak ditemukan:\n{$target}"
    );
}

$original =
    file_get_contents($target);

if ($original === false) {
    fail('Gagal membaca message.blade.php.');
}

if (str_contains($original, $newMarker)) {
    echo "V2 sudah terpasang. Tidak ada perubahan.\n";
    exit(0);
}

$stamp = date('Ymd-His');

$backup =
    $target
    . '.bak-my-email-normal-reader-v2-'
    . $stamp;

if (!copy($target, $backup)) {
    fail(
        "Gagal membuat backup:\n{$backup}"
    );
}

echo "Backup:\n{$backup}\n\n";

try {
    $blade = $original;

    /*
     * Remove V1. That renderer is the one visible in the screenshot.
     */
    [$blade, $v1Removed] =
        removeStandaloneScriptByMarker(
            $blade,
            $oldMarker
        );

    echo "Cleanup V1 renderer: {$v1Removed} block\n";

    $closing =
        '</x-admin::layouts>';

    if (
        substr_count(
            $blade,
            $closing
        ) !== 1
    ) {
        throw new RuntimeException(
            'Closing </x-admin::layouts> tidak ditemukan tepat 1 kali.'
        );
    }

    $renderer = <<<'BLADE'

{{-- MY EMAIL NORMAL READER V2 --}}
<script>
(() => {
    const isMessageDetail =
        /\/admin\/my-email\/messages\/[^/]+\/?$/i
            .test(
                window.location.pathname
            );

    if (!isMessageDetail) {
        return;
    }

    const htmlSignature =
        /(?:<!doctype\s+html\b|<html\b|<body\b|<head\b|<table\b|<p\b|<div\b)/i;

    const directTextOf = (
        element
    ) =>
        Array
            .from(
                element.childNodes
            )
            .filter(
                (node) =>
                    node.nodeType
                    === Node.TEXT_NODE
            )
            .map(
                (node) =>
                    node.nodeValue
                    || ''
            )
            .join('')
            .trim();

    const findRawHtmlBody = () => {
        /*
         * The key V2 difference:
         * inspect DIRECT TEXT only, never parent textContent.
         *
         * This prevents page header/buttons/our own <script> source from
         * becoming the selected email body.
         */
        const candidates =
            Array.from(
                document.body.querySelectorAll(
                    'pre, code, article, section, div, p, td'
                )
            )
                .map(
                    (element) => ({
                        element,
                        raw:
                            directTextOf(
                                element
                            ),
                    })
                )
                .filter(
                    ({ element, raw }) => {
                        if (
                            element.closest(
                                'script, style, iframe, nav'
                            )
                        ) {
                            return false;
                        }

                        if (
                            raw.length
                            < 80
                        ) {
                            return false;
                        }

                        return htmlSignature.test(
                            raw
                        );
                    }
                );

        if (!candidates.length) {
            return null;
        }

        /*
         * Prefer strongest HTML signatures, then the deepest/smallest host.
         */
        candidates.sort(
            (a, b) => {
                const strongA =
                    /(?:<!doctype\s+html\b|<html\b)/i
                        .test(a.raw)
                        ? 1
                        : 0;

                const strongB =
                    /(?:<!doctype\s+html\b|<html\b)/i
                        .test(b.raw)
                        ? 1
                        : 0;

                if (strongA !== strongB) {
                    return strongB - strongA;
                }

                const depth = (
                    element
                ) => {
                    let value = 0;
                    let node = element;

                    while (node.parentElement) {
                        value++;
                        node = node.parentElement;
                    }

                    return value;
                };

                return (
                    depth(b.element)
                    - depth(a.element)
                );
            }
        );

        return candidates[0];
    };

    const sanitizeHtml = (
        rawHtml
    ) => {
        const parser =
            new DOMParser();

        const doc =
            parser.parseFromString(
                rawHtml,
                'text/html'
            );

        doc
            .querySelectorAll(
                [
                    'script',
                    'iframe',
                    'object',
                    'embed',
                    'form',
                    'input',
                    'button',
                    'textarea',
                    'select',
                    'base',
                    'meta[http-equiv]',
                ].join(',')
            )
            .forEach(
                (node) =>
                    node.remove()
            );

        doc
            .querySelectorAll(
                '*'
            )
            .forEach(
                (element) => {
                    Array
                        .from(
                            element.attributes
                        )
                        .forEach(
                            (attribute) => {
                                const name =
                                    attribute.name
                                        .toLowerCase();

                                const value =
                                    (
                                        attribute.value
                                        || ''
                                    ).trim();

                                if (
                                    name.startsWith(
                                        'on'
                                    )
                                ) {
                                    element.removeAttribute(
                                        attribute.name
                                    );

                                    return;
                                }

                                if (
                                    (
                                        name
                                        === 'href'
                                        || name
                                        === 'src'
                                    )
                                    && /^(?:javascript|vbscript):/i
                                        .test(
                                            value
                                        )
                                ) {
                                    element.removeAttribute(
                                        attribute.name
                                    );

                                    return;
                                }

                                if (
                                    (
                                        name
                                        === 'href'
                                        || name
                                        === 'src'
                                    )
                                    && /^data:text\/html/i
                                        .test(
                                            value
                                        )
                                ) {
                                    element.removeAttribute(
                                        attribute.name
                                    );
                                }
                            }
                        );
                }
            );

        doc
            .querySelectorAll(
                'a[href]'
            )
            .forEach(
                (anchor) => {
                    const href =
                        (
                            anchor.getAttribute(
                                'href'
                            )
                            || ''
                        ).trim();

                    if (
                        /^(?:https?:|mailto:|tel:)/i
                            .test(
                                href
                            )
                    ) {
                        anchor.setAttribute(
                            'target',
                            '_blank'
                        );

                        anchor.setAttribute(
                            'rel',
                            'noopener noreferrer'
                        );

                        return;
                    }

                    /*
                     * Keep #anchor links inside the email.
                     * Remove all other ambiguous protocols/relative URLs.
                     */
                    if (!href.startsWith('#')) {
                        anchor.removeAttribute(
                            'href'
                        );
                    }
                }
            );

        doc
            .querySelectorAll(
                'img'
            )
            .forEach(
                (image) => {
                    image.setAttribute(
                        'referrerpolicy',
                        'no-referrer'
                    );

                    image.style.maxWidth =
                        '100%';

                    image.style.height =
                        'auto';
                }
            );

        const style =
            doc.createElement(
                'style'
            );

        style.textContent = `
            html,
            body {
                margin: 0 !important;
                padding: 0 !important;
                background: #ffffff !important;
                color: #111827 !important;
                max-width: 100% !important;
                overflow-wrap: anywhere;
                font-family: Arial, Helvetica, sans-serif;
                font-size: 14px;
                line-height: 1.55;
            }

            body {
                box-sizing: border-box;
                padding: 18px !important;
            }

            img,
            table {
                max-width: 100% !important;
            }

            a[href] {
                color: #2563eb !important;
                text-decoration: underline !important;
                cursor: pointer !important;
            }
        `;

        (
            doc.head
            || doc.documentElement
        ).appendChild(
            style
        );

        return (
            '<!doctype html>'
            + doc.documentElement.outerHTML
        );
    };

    const renderEmailBody = () => {
        const candidate =
            findRawHtmlBody();

        if (!candidate) {
            /*
             * Plain text email: leave existing view untouched.
             */
            return;
        }

        const {
            element:
                host,

            raw:
                rawHtml,
        } =
            candidate;

        const iframe =
            document.createElement(
                'iframe'
            );

        iframe.setAttribute(
            'title',
            'Email message'
        );

        iframe.setAttribute(
            'sandbox',
            'allow-popups allow-popups-to-escape-sandbox'
        );

        iframe.setAttribute(
            'referrerpolicy',
            'no-referrer'
        );

        iframe.style.display =
            'block';

        iframe.style.width =
            '100%';

        iframe.style.height =
            '62vh';

        iframe.style.minHeight =
            '440px';

        iframe.style.border =
            '0';

        iframe.style.background =
            '#ffffff';

        iframe.style.borderRadius =
            '8px';

        iframe.srcdoc =
            sanitizeHtml(
                rawHtml
            );

        /*
         * Replace ONLY the raw body host.
         * Reply / Reply All / Back / Move to Trash remain outside this host.
         */
        host.replaceChildren(
            iframe
        );

        host.style.padding =
            '0';

        host.style.overflow =
            'hidden';

        host.dataset.normalEmailReaderV2 =
            '1';
    };

    if (
        document.readyState
        === 'loading'
    ) {
        document.addEventListener(
            'DOMContentLoaded',
            renderEmailBody,
            {
                once:
                    true,
            }
        );
    } else {
        renderEmailBody();
    }
})();
</script>

BLADE;

    $blade =
        str_replace(
            $closing,
            $renderer
            . $closing,
            $blade
        );

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
        $newMarker,
        'directTextOf',
        'findRawHtmlBody',
        'sandbox',
        'allow-popups allow-popups-to-escape-sandbox',
        'DOMParser',
        'target',
        '_blank',
        'host.replaceChildren',
    ];

    foreach ($required as $needle) {
        if (!str_contains($check, $needle)) {
            throw new RuntimeException(
                "Validation gagal: {$needle}"
            );
        }
    }

    if (str_contains($check, $oldMarker)) {
        throw new RuntimeException(
            'V1 renderer masih tersisa.'
        );
    }

    echo "\nPatch PASS.\n";
    echo "- V1 renderer yang salah dibersihkan.\n";
    echo "- Body HTML dideteksi dari DIRECT TEXT saja.\n";
    echo "- Header dan tombol Reply/Reply All tetap utuh.\n";
    echo "- HTML email dirender di sandboxed iframe.\n";
    echo "- Link eksternal dapat diklik di tab baru.\n";
    echo "- Tidak mengubah controller/database/routes.\n\n";

    chdir($root);

    passthru(
        escapeshellarg(PHP_BINARY)
        . ' '
        . escapeshellarg($root . '/artisan')
        . ' view:clear'
    );

    echo "\nSELESAI.\n";
    echo "Jalankan checker:\n";
    echo "php tools/check_my_email_normal_reader_v2.php\n";
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
