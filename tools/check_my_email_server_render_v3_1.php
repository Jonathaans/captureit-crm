<?php

declare(strict_types=1);

$root =
    dirname(
        __DIR__
    );

$target =
    $root
    . '/packages/Webkul/Admin/src/Resources/views/user-email/message.blade.php';

echo "CHECK MY EMAIL SERVER RENDER V3.1\n";
echo "=================================\n\n";

$content =
    is_file($target)
        ? file_get_contents($target)
        : '';

$checks = [
    'Target Blade tersedia' =>
        $content !== '',

    'V3.1 marker satu kali' =>
        substr_count(
            $content,
            'MY EMAIL SERVER RENDER V3.1'
        ) === 1,

    'V3 marker lama sudah hilang' =>
        !str_contains(
            $content,
            'MY EMAIL SERVER RENDER V3 --'
        ),

    'html_body dipakai langsung' =>
        str_contains(
            $content,
            '$message->html_body'
        ),

    'text_body fallback tersedia' =>
        str_contains(
            $content,
            '$message->text_body'
        ),

    'JSON-safe transport tersedia' =>
        str_contains(
            $content,
            '$emailFrameJson'
        )
        && str_contains(
            $content,
            'JSON_HEX_TAG'
        ),

    'Iframe kosong + runtime srcdoc tersedia' =>
        str_contains(
            $content,
            'id="my-email-message-frame-v31"'
        )
        && str_contains(
            $content,
            'frame.srcdoc'
        ),

    'Sandbox aman' =>
        str_contains(
            $content,
            'sandbox="allow-popups allow-popups-to-escape-sandbox"'
        )
        && preg_match(
            '~sandbox="[^"]*(?:allow-scripts|allow-same-origin|allow-forms|allow-top-navigation)[^"]*"~i',
            $content
        ) !== 1,

    'Link default tab baru' =>
        str_contains(
            $content,
            '<base target="_blank">'
        ),

    'Reply tetap ada' =>
        str_contains(
            $content,
            'Reply'
        ),

    'Reply All tetap ada' =>
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
echo "3. Body harus tampil normal dan tidak blank.\n";
echo "4. Reply dan Reply All tetap terlihat.\n";
echo "5. Klik Teams/Box/http/https link -> buka tab baru.\n";

exit(0);
