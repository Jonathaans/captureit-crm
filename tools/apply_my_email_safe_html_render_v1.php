<?php

declare(strict_types=1);

/**
 * MY EMAIL SAFE HTML RENDER V1
 *
 * Tujuan:
 * - Email HTML tidak lagi tampil sebagai source <html>...</html>.
 * - HTML dirender seperti email normal.
 * - Link http/https/mailto/tel dapat diklik dan dibuka di tab baru.
 * - Script / iframe / form / event-handler dari email tidak dijalankan.
 *
 * Implementasi:
 * - Auto-detect Blade detail "My Email" di source LOKAL yang sedang aktif.
 * - Tambahkan PURE JavaScript renderer ke view detail saja.
 * - Renderer mengambil escaped HTML yang saat ini terlihat sebagai text,
 *   sanitizes it in a detached DOM, lalu menampilkannya di sandboxed iframe.
 *
 * Kenapa client-side iframe?
 * - Tidak perlu menebak nama field body ($message->body, html_body, dll).
 * - Tidak perlu {!! $body !!}, yang berbahaya untuk email eksternal.
 * - Sandboxed iframe mengisolasi CSS HTML email dari UI CRM.
 *
 * Usage:
 *   php tools/apply_my_email_safe_html_render_v1.php
 *
 * Optional explicit path bila autodetect tidak unik:
 *   php tools/apply_my_email_safe_html_render_v1.php "packages/Webkul/Admin/src/Resources/views/.../show.blade.php"
 */

$root = dirname(__DIR__);

$viewsRoot =
    $root . '/packages/Webkul/Admin/src/Resources/views';

$marker =
    'MY EMAIL SAFE HTML RENDER V1';

function fail(string $message, int $code = 1): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit($code);
}

function relativePath(string $root, string $path): string
{
    $root =
        rtrim(
            str_replace('\\', '/', $root),
            '/'
        );

    $path =
        str_replace('\\', '/', $path);

    if (str_starts_with($path, $root . '/')) {
        return substr(
            $path,
            strlen($root) + 1
        );
    }

    return $path;
}

function candidateScore(string $path, string $content): int
{
    $score = 0;

    $normalizedPath =
        strtolower(
            str_replace('\\', '/', $path)
        );

    $lower =
        strtolower($content);

    if (str_contains($normalizedPath, 'my-email')) {
        $score += 20;
    }

    if (
        str_contains($normalizedPath, '/email/')
        || str_contains($normalizedPath, '/emails/')
    ) {
        $score += 4;
    }

    if (str_contains($lower, 'reply all')) {
        $score += 18;
    }

    if (
        str_contains($lower, 'admin.my-email')
        || str_contains($lower, 'my-email')
    ) {
        $score += 16;
    }

    if (str_contains($lower, 'received')) {
        $score += 5;
    }

    if (str_contains($lower, 'reply')) {
        $score += 4;
    }

    if (str_contains($lower, 'message')) {
        $score += 2;
    }

    /*
     * Detail page tends to contain Back + Reply buttons.
     */
    if (
        str_contains($lower, 'back')
        && str_contains($lower, 'reply')
    ) {
        $score += 4;
    }

    return $score;
}

function discoverTarget(
    string $root,
    string $viewsRoot,
    ?string $explicit
): array {
    if ($explicit !== null && trim($explicit) !== '') {
        $path =
            str_replace(
                ['/', '\\'],
                DIRECTORY_SEPARATOR,
                trim($explicit)
            );

        if (
            !str_starts_with(
                $path,
                DIRECTORY_SEPARATOR
            )
            && !preg_match(
                '/^[A-Za-z]:[\\\\\/]/',
                $path
            )
        ) {
            $path =
                $root
                . DIRECTORY_SEPARATOR
                . ltrim(
                    $path,
                    DIRECTORY_SEPARATOR
                );
        }

        if (!is_file($path)) {
            fail(
                "Explicit Blade path tidak ditemukan:\n{$path}"
            );
        }

        $content =
            file_get_contents($path);

        if ($content === false) {
            fail(
                "Gagal membaca explicit Blade:\n{$path}"
            );
        }

        return [
            $path,
            [
                [
                    'path' =>
                        $path,

                    'score' =>
                        candidateScore(
                            $path,
                            $content
                        ),
                ],
            ],
        ];
    }

    if (!is_dir($viewsRoot)) {
        fail(
            "Views root tidak ditemukan:\n{$viewsRoot}"
        );
    }

    $candidates = [];

    $iterator =
        new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $viewsRoot,
                FilesystemIterator::SKIP_DOTS
            )
        );

    foreach ($iterator as $file) {
        if (!$file->isFile()) {
            continue;
        }

        $path =
            $file->getPathname();

        if (
            !str_ends_with(
                strtolower($path),
                '.blade.php'
            )
        ) {
            continue;
        }

        $content =
            file_get_contents($path);

        if ($content === false) {
            continue;
        }

        $score =
            candidateScore(
                $path,
                $content
            );

        if ($score <= 0) {
            continue;
        }

        $candidates[] = [
            'path' =>
                $path,

            'score' =>
                $score,
        ];
    }

    usort(
        $candidates,
        static fn (array $a, array $b): int =>
            $b['score']
            <=> $a['score']
    );

    if (!$candidates) {
        fail(
            "Tidak menemukan kandidat Blade My Email detail.\n"
            . "Installer tidak mengubah source."
        );
    }

    $best =
        $candidates[0];

    $secondScore =
        $candidates[1]['score']
        ?? -1;

    /*
     * Require a meaningful winner.
     */
    if (
        $best['score'] < 12
        || $best['score'] === $secondScore
    ) {
        echo "Kandidat Blade ditemukan, tetapi tidak cukup unik:\n\n";

        foreach (
            array_slice(
                $candidates,
                0,
                10
            )
            as $candidate
        ) {
            echo
                '- score '
                . str_pad(
                    (string) $candidate['score'],
                    3,
                    ' ',
                    STR_PAD_LEFT
                )
                . ' : '
                . relativePath(
                    $root,
                    $candidate['path']
                )
                . PHP_EOL;
        }

        echo "\nTidak ada source yang diubah.\n";
        echo "Jalankan ulang dengan explicit path kandidat yang benar.\n";

        exit(2);
    }

    return [
        $best['path'],
        $candidates,
    ];
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

echo "MY EMAIL SAFE HTML RENDER V1\n";
echo "============================\n\n";

$explicit =
    $argv[1]
    ?? null;

[
    $targetPath,
    $candidates,
] =
    discoverTarget(
        $root,
        $viewsRoot,
        $explicit
    );

echo "Target Blade:\n";
echo relativePath(
    $root,
    $targetPath
) . "\n\n";

echo "Top autodetect candidates:\n";

foreach (
    array_slice(
        $candidates,
        0,
        5
    )
    as $candidate
) {
    echo
        '- score '
        . str_pad(
            (string) $candidate['score'],
            3,
            ' ',
            STR_PAD_LEFT
        )
        . ' : '
        . relativePath(
            $root,
            $candidate['path']
        )
        . PHP_EOL;
}

echo PHP_EOL;

$original =
    file_get_contents(
        $targetPath
    );

if ($original === false) {
    fail(
        'Gagal membaca target Blade.'
    );
}

if (
    str_contains(
        $original,
        $marker
    )
) {
    echo "V1 sudah terpasang. Tidak ada perubahan.\n";
    exit(0);
}

$stamp =
    date(
        'Ymd-His'
    );

$backup =
    $targetPath
    . '.bak-my-email-safe-html-render-v1-'
    . $stamp;

if (!copy($targetPath, $backup)) {
    fail(
        "Gagal membuat backup:\n{$backup}"
    );
}

echo "Backup:\n";
echo relativePath(
    $root,
    $backup
) . "\n\n";

$script = <<<'BLADE'

{{-- MY EMAIL SAFE HTML RENDER V1 --}}
<script>
(() => {
    const routeLooksLikeMyEmailMessage =
        /\/admin\/my-email\/messages\/[^/]+\/?$/i
            .test(
                window.location.pathname
            );

    if (! routeLooksLikeMyEmailMessage) {
        return;
    }

    const htmlSignature =
        /<(?:!doctype|html|head|body|meta|style|div|p|span|table|tr|td|a|br)\b/i;

    const findEscapedEmailBodyHost = () => {
        const scope =
            document.querySelector(
                'main'
            )
            || document.body;

        const candidates =
            Array.from(
                scope.querySelectorAll(
                    'pre, article, section, div, p'
                )
            )
                .filter(
                    (element) => {
                        if (
                            element.closest(
                                'script, style, iframe'
                            )
                        ) {
                            return false;
                        }

                        const text =
                            (
                                element.textContent
                                || ''
                            ).trim();

                        if (
                            text.length
                            < 80
                        ) {
                            return false;
                        }

                        return htmlSignature.test(
                            text
                        );
                    }
                );

        if (! candidates.length) {
            return null;
        }

        /*
         * Escaped HTML is normally one text node inside the smallest
         * content panel. Choosing the shortest matching element avoids
         * replacing the whole page/card when a smaller body host exists.
         */
        candidates.sort(
            (a, b) =>
                (
                    a.textContent
                    || ''
                ).length
                -
                (
                    b.textContent
                    || ''
                ).length
        );

        return candidates[0];
    };

    const sanitizeEmailHtml = (
        rawHtml
    ) => {
        const parser =
            new DOMParser();

        const documentNode =
            parser.parseFromString(
                rawHtml,
                'text/html'
            );

        /*
         * Active / navigational content is not allowed.
         * CSS remains isolated inside the sandboxed iframe.
         */
        documentNode
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
                    'meta[http-equiv]',
                    'base',
                    'link[rel="import"]',
                ].join(',')
            )
            .forEach(
                (node) =>
                    node.remove()
            );

        documentNode
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

        documentNode
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
                        ! /^(?:https?:|mailto:|tel:|#)/i
                            .test(
                                href
                            )
                    ) {
                        /*
                         * Relative links in email HTML are ambiguous inside
                         * srcdoc. Keep their label, remove unsafe navigation.
                         */
                        anchor.removeAttribute(
                            'href'
                        );

                        return;
                    }

                    anchor.setAttribute(
                        'target',
                        '_blank'
                    );

                    anchor.setAttribute(
                        'rel',
                        'noopener noreferrer'
                    );
                }
            );

        documentNode
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
            documentNode.createElement(
                'style'
            );

        style.textContent = `
            html,
            body {
                margin: 0 !important;
                padding: 0 !important;
                max-width: 100% !important;
                overflow-wrap: anywhere;
                word-break: normal;
                font-family: Arial, Helvetica, sans-serif;
                background: #fff;
                color: #111827;
            }

            body {
                padding: 16px !important;
                box-sizing: border-box;
            }

            table {
                max-width: 100% !important;
            }

            a {
                color: #2563eb !important;
                text-decoration: underline !important;
                cursor: pointer !important;
            }
        `;

        (
            documentNode.head
            || documentNode.documentElement
        ).appendChild(
            style
        );

        return (
            '<!doctype html>'
            + documentNode.documentElement.outerHTML
        );
    };

    const render = () => {
        const host =
            findEscapedEmailBodyHost();

        if (! host) {
            return;
        }

        const rawHtml =
            (
                host.textContent
                || ''
            ).trim();

        if (
            ! htmlSignature.test(
                rawHtml
            )
        ) {
            return;
        }

        const iframe =
            document.createElement(
                'iframe'
            );

        iframe.setAttribute(
            'title',
            'Email content'
        );

        /*
         * allow-scripts intentionally NOT present.
         * allow-same-origin intentionally NOT present.
         */
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
            '64vh';

        iframe.style.minHeight =
            '420px';

        iframe.style.border =
            '0';

        iframe.style.background =
            '#fff';

        iframe.style.borderRadius =
            '8px';

        iframe.srcdoc =
            sanitizeEmailHtml(
                rawHtml
            );

        host.replaceChildren(
            iframe
        );

        host.style.overflow =
            'hidden';

        host.style.padding =
            '0';

        host.dataset.safeEmailHtmlRendered =
            '1';
    };

    if (
        document.readyState
        === 'loading'
    ) {
        document.addEventListener(
            'DOMContentLoaded',
            render,
            {
                once:
                    true,
            }
        );
    } else {
        render();
    }
})();
</script>

BLADE;

try {
    $updated = null;

    if (
        substr_count(
            $original,
            '</x-admin::layouts>'
        ) === 1
    ) {
        $updated =
            str_replace(
                '</x-admin::layouts>',
                $script
                . '</x-admin::layouts>',
                $original
            );
    } elseif (
        substr_count(
            $original,
            '@endsection'
        ) === 1
    ) {
        $updated =
            str_replace(
                '@endsection',
                $script
                . '@endsection',
                $original
            );
    } else {
        /*
         * Pure HTML/JS can safely be appended at EOF when this is a dedicated
         * detail Blade. No Blade directive is introduced.
         */
        $updated =
            rtrim(
                $original
            )
            . PHP_EOL
            . PHP_EOL
            . $script;
    }

    atomicWrite(
        $targetPath,
        $updated
    );

    $check =
        file_get_contents(
            $targetPath
        );

    if ($check === false) {
        throw new RuntimeException(
            'Gagal membaca hasil patch.'
        );
    }

    $required = [
        $marker,
        'sandbox',
        'allow-popups allow-popups-to-escape-sandbox',
        'DOMParser',
        'sanitizeEmailHtml',
        'iframe.srcdoc',
        "anchor.setAttribute(\n                        'target',\n                        '_blank'",
        'javascript|vbscript',
    ];

    foreach ($required as $needle) {
        if (!str_contains($check, $needle)) {
            throw new RuntimeException(
                "Post-write validation gagal: {$needle}"
            );
        }
    }

    echo "Patch PASS.\n";
    echo "- Raw HTML email akan dirender dalam sandboxed iframe.\n";
    echo "- Script/form/iframe/event-handler email diblok.\n";
    echo "- Link http/https/mailto/tel dibuka di tab baru.\n";
    echo "- CSS email terisolasi dari UI CRM.\n\n";

    chdir(
        $root
    );

    echo "Membersihkan compiled Blade views...\n";

    passthru(
        escapeshellarg(
            PHP_BINARY
        )
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
    echo "php tools/check_my_email_safe_html_render_v1.php\n";
} catch (Throwable $e) {
    copy(
        $backup,
        $targetPath
    );

    fwrite(
        STDERR,
        "\nPATCH GAGAL: "
        . $e->getMessage()
        . "\n"
    );

    fwrite(
        STDERR,
        "Blade dipulihkan dari backup otomatis.\n"
    );

    exit(1);
}
