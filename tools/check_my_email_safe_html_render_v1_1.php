<?php

declare(strict_types=1);

/**
 * CHECK MY EMAIL SAFE HTML RENDER V1.1
 *
 * Fix checker false-positive:
 * V1 checker mencari string "allow-scripts" di seluruh file Blade.
 * Padahal patch V1 memiliki komentar:
 *   "allow-scripts intentionally NOT present"
 *
 * Jadi source aman, tetapi checker lama salah membaca komentar sebagai permission.
 *
 * V1.1 hanya memeriksa NILAI sandbox attribute yang benar-benar dipakai.
 */

$root =
    dirname(
        __DIR__
    );

$viewsRoot =
    $root
    . '/packages/Webkul/Admin/src/Resources/views';

$marker =
    'MY EMAIL SAFE HTML RENDER V1';

echo "CHECK MY EMAIL SAFE HTML RENDER V1.1\n";
echo "====================================\n\n";

if (!is_dir($viewsRoot)) {
    echo "[FAIL] Views root tidak ditemukan.\n";
    exit(1);
}

$matches = [];

$iterator =
    new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(
            $viewsRoot,
            FilesystemIterator::SKIP_DOTS
        )
    );

foreach ($iterator as $file) {
    if (
        !$file->isFile()
        || !str_ends_with(
            strtolower(
                $file->getPathname()
            ),
            '.blade.php'
        )
    ) {
        continue;
    }

    $content =
        file_get_contents(
            $file->getPathname()
        );

    if (
        $content !== false
        && str_contains(
            $content,
            $marker
        )
    ) {
        $matches[] = [
            'path' =>
                $file->getPathname(),

            'content' =>
                $content,
        ];
    }
}

$content =
    $matches[0]['content']
    ?? '';

$path =
    $matches[0]['path']
    ?? '';

$sandboxValue =
    null;

/*
 * Patch V1 sets:
 *
 * iframe.setAttribute(
 *     'sandbox',
 *     'allow-popups allow-popups-to-escape-sandbox'
 * );
 *
 * Extract THAT actual value instead of scanning comments.
 */
if (
    preg_match(
        '~setAttribute\s*\(\s*[\'"]sandbox[\'"]\s*,\s*[\'"]([^\'"]*)[\'"]\s*\)~s',
        $content,
        $sandboxMatch
    ) === 1
) {
    $sandboxValue =
        trim(
            $sandboxMatch[1]
        );
}

$checks = [
    'Marker ditemukan tepat 1 Blade' =>
        count($matches) === 1,

    'Sandbox attribute ditemukan' =>
        $sandboxValue !== null,

    'Sandbox mengizinkan popup link' =>
        $sandboxValue !== null
        && str_contains(
            $sandboxValue,
            'allow-popups'
        )
        && str_contains(
            $sandboxValue,
            'allow-popups-to-escape-sandbox'
        ),

    'Sandbox TIDAK mengizinkan scripts' =>
        $sandboxValue !== null
        && !preg_match(
            '~(?:^|\s)allow-scripts(?:\s|$)~',
            $sandboxValue
        ),

    'Sandbox TIDAK mengizinkan same-origin' =>
        $sandboxValue !== null
        && !preg_match(
            '~(?:^|\s)allow-same-origin(?:\s|$)~',
            $sandboxValue
        ),

    'HTML sanitizer tersedia' =>
        str_contains(
            $content,
            'DOMParser'
        )
        && str_contains(
            $content,
            'sanitizeEmailHtml'
        ),

    'Script/iframe/form dibuang sanitizer' =>
        str_contains(
            $content,
            "'script'"
        )
        && str_contains(
            $content,
            "'iframe'"
        )
        && str_contains(
            $content,
            "'form'"
        ),

    'Unsafe javascript/vbscript URL diblok' =>
        str_contains(
            $content,
            'javascript|vbscript'
        ),

    'Link dibuka tab baru' =>
        str_contains(
            $content,
            "'target'"
        )
        && str_contains(
            $content,
            "'_blank'"
        ),

    'Iframe menggunakan srcdoc' =>
        str_contains(
            $content,
            'iframe.srcdoc'
        ),
];

if ($path !== '') {
    $normalizedPath =
        str_replace(
            '\\',
            '/',
            $path
        );

    $normalizedRoot =
        rtrim(
            str_replace(
                '\\',
                '/',
                $root
            ),
            '/'
        );

    $relative =
        str_starts_with(
            $normalizedPath,
            $normalizedRoot . '/'
        )
            ? substr(
                $normalizedPath,
                strlen(
                    $normalizedRoot
                ) + 1
            )
            : $normalizedPath;

    echo "[INFO] Patched Blade: {$relative}\n";

    if ($sandboxValue !== null) {
        echo "[INFO] Effective sandbox: {$sandboxValue}\n";
    }

    echo PHP_EOL;
}

$failed = [];

foreach ($checks as $label => $ok) {
    echo
        ($ok ? '[OK]   ' : '[FAIL] ')
        . $label
        . PHP_EOL;

    if (!$ok) {
        $failed[] =
            $label;
    }
}

echo PHP_EOL;

if ($failed) {
    echo "HASIL: FAIL\n";
    exit(1);
}

echo "HASIL: PASS\n";
echo "\nTidak perlu menjalankan installer V1 lagi.\n";
echo "QA browser:\n";
echo "1. Ctrl + Shift + R.\n";
echo "2. Buka /admin/my-email/messages/23.\n";
echo "3. Isi email harus tampil normal, bukan raw HTML.\n";
echo "4. Klik link http/https -> buka tab baru.\n";
echo "5. Reply dan Reply All tetap berfungsi.\n";

exit(0);
