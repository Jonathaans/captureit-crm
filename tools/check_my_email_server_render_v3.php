<?php

declare(strict_types=1);

$root =
    dirname(
        __DIR__
    );

$target =
    $root
    . '/packages/Webkul/Admin/src/Resources/views/user-email/message.blade.php';

echo "CHECK MY EMAIL SERVER RENDER V3\n";
echo "===============================\n\n";

$content =
    is_file($target)
        ? file_get_contents($target)
        : '';

$checks = [
    'Target Blade tersedia' =>
        $content !== '',

    'V3 marker satu kali' =>
        substr_count(
            $content,
            'MY EMAIL SERVER RENDER V3'
        ) === 1,

    'V2 runtime renderer sudah hilang' =>
        !str_contains(
            $content,
            'MY EMAIL NORMAL READER V2'
        ),

    'html_body digunakan langsung' =>
        str_contains(
            $content,
            '$message->html_body'
        ),

    'text_body fallback tersedia' =>
        str_contains(
            $content,
            '$message->text_body'
        ),

    'Sandboxed iframe tersedia' =>
        str_contains(
            $content,
            'sandbox="allow-popups allow-popups-to-escape-sandbox"'
        ),

    'srcdoc server-side tersedia' =>
        str_contains(
            $content,
            'srcdoc="{{ $emailFrameHtml }}"'
        ),

    'Link default membuka tab baru' =>
        str_contains(
            $content,
            '<base target="_blank">'
        ),

    'Sandbox tidak allow-scripts' =>
        preg_match(
            '~sandbox="[^"]*allow-scripts[^"]*"~i',
            $content
        ) !== 1,

    'Sandbox tidak allow-same-origin' =>
        preg_match(
            '~sandbox="[^"]*allow-same-origin[^"]*"~i',
            $content
        ) !== 1,

    'Reply tetap tersedia' =>
        str_contains(
            $content,
            'Reply'
        ),

    'Reply All tetap tersedia' =>
        str_contains(
            $content,
            'Reply All'
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
echo "2. Buka /admin/my-email/messages/23.\n";
echo "3. Subject / From / Received harus tetap tampil.\n";
echo "4. Reply dan Reply All harus tetap terlihat.\n";
echo "5. Body harus tampil seperti email normal, bukan tag <html> mentah.\n";
echo "6. Klik link Teams/Box/http/https -> harus membuka tab baru.\n";

exit(0);
