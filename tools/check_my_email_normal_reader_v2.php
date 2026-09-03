<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$target =
    $root . '/packages/Webkul/Admin/src/Resources/views/user-email/message.blade.php';

echo "CHECK MY EMAIL NORMAL READER V2\n";
echo "===============================\n\n";

$content =
    is_file($target)
        ? file_get_contents($target)
        : '';

$checks = [
    'Target Blade tersedia' =>
        $content !== '',

    'V2 marker satu kali' =>
        substr_count(
            $content,
            'MY EMAIL NORMAL READER V2'
        ) === 1,

    'V1 renderer sudah hilang' =>
        !str_contains(
            $content,
            'MY EMAIL SAFE HTML RENDER V1'
        ),

    'Direct-text body detection tersedia' =>
        str_contains(
            $content,
            'directTextOf'
        )
        && str_contains(
            $content,
            'findRawHtmlBody'
        ),

    'Sandbox iframe tersedia' =>
        str_contains(
            $content,
            'allow-popups allow-popups-to-escape-sandbox'
        ),

    'Tidak memberi sandbox allow-scripts' =>
        !preg_match(
            '~setAttribute\s*\(\s*[\'"]sandbox[\'"]\s*,\s*[\'"][^\'"]*allow-scripts~s',
            $content
        ),

    'Sanitizer tersedia' =>
        str_contains(
            $content,
            'DOMParser'
        )
        && str_contains(
            $content,
            'sanitizeHtml'
        ),

    'External link dibuka tab baru' =>
        str_contains(
            $content,
            "'target'"
        )
        && str_contains(
            $content,
            "'_blank'"
        ),

    'Body saja yang diganti' =>
        str_contains(
            $content,
            'host.replaceChildren'
        ),
];

$failed = [];

foreach ($checks as $label => $ok) {
    echo
        ($ok ? '[OK]   ' : '[FAIL] ')
        . $label
        . PHP_EOL;

    if (!$ok) {
        $failed[] = $label;
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
echo "2. Buka /admin/my-email/messages/23.\n";
echo "3. Subject/From/Received tetap tampil normal.\n";
echo "4. Tombol Reply + Reply All tetap terlihat.\n";
echo "5. Body email harus tampil seperti email normal, bukan source HTML/JS.\n";
echo "6. Klik link eksternal -> harus buka tab baru.\n";

exit(0);
