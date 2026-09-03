<?php

declare(strict_types=1);

/**
 * CHECK MY EMAIL SAFE HTML RENDER V1
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

echo "CHECK MY EMAIL SAFE HTML RENDER V1\n";
echo "==================================\n\n";

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

$checks = [
    'Marker ditemukan tepat 1 Blade' =>
        count($matches) === 1,
];

$content =
    $matches[0]['content']
    ?? '';

if ($matches) {
    $path =
        str_replace(
            '\\',
            '/',
            $matches[0]['path']
        );

    $rootNormalized =
        rtrim(
            str_replace(
                '\\',
                '/',
                $root
            ),
            '/'
        );

    echo
        '[INFO] Patched Blade: '
        . (
            str_starts_with(
                $path,
                $rootNormalized . '/'
            )
                ? substr(
                    $path,
                    strlen(
                        $rootNormalized
                    ) + 1
                )
                : $path
        )
        . PHP_EOL
        . PHP_EOL;
}

$checks += [
    'Sandbox iframe tersedia' =>
        str_contains(
            $content,
            'sandbox'
        )
        && str_contains(
            $content,
            'allow-popups allow-popups-to-escape-sandbox'
        ),

    'Tidak ada allow-scripts' =>
        !str_contains(
            $content,
            'allow-scripts'
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

    'Unsafe JS URL diblok' =>
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
echo "\nQA browser:\n";
echo "1. Ctrl + Shift + R.\n";
echo "2. Buka My Email -> email HTML dari client.\n";
echo "3. Isi harus tampil sebagai email normal, bukan source <html>.\n";
echo "4. Klik link http/https -> harus membuka tab baru.\n";
echo "5. Reply dan Reply All harus tetap berfungsi.\n";

exit(0);
