<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$target =
    $root
    . '/packages/Webkul/Admin/src/Resources/views/user-email/message.blade.php';

echo "CHECK MY EMAIL READABLE SAFE V4\n";
echo "===============================\n\n";

$content =
    is_file($target)
        ? file_get_contents($target)
        : '';

$checks = [
    'Target Blade tersedia' =>
        $content !== '',

    'V4 marker satu kali' =>
        substr_count(
            $content,
            'MY EMAIL READABLE SAFE V4'
        ) === 1,

    'Direct server html_body render aktif' =>
        str_contains(
            $content,
            '$message->html_body'
        )
        && str_contains(
            $content,
            '$safeEmailHtml'
        ),

    'DOMDocument sanitizer tersedia' =>
        str_contains(
            $content,
            'DOMDocument'
        )
        && str_contains(
            $content,
            'DOMXPath'
        ),

    'Dangerous element list tersedia' =>
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

    'Link aman membuka tab baru' =>
        str_contains(
            $content,
            'target="_blank"'
        )
        && str_contains(
            $content,
            'noopener noreferrer'
        ),

    'Plain-text fallback tersedia' =>
        str_contains(
            $content,
            '$message->text_body'
        )
        && str_contains(
            $content,
            'strip_tags'
        ),

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

    'Attachment tetap tersedia' =>
        str_contains(
            $content,
            'admin.my-email.attachments.download'
        ),

    'Move to Trash tetap tersedia' =>
        str_contains(
            $content,
            'admin.my-email.trash.move'
        ),

    'Tidak ada renderer iframe lama' =>
        !str_contains(
            $content,
            'my-email-message-frame-v31'
        )
        && !str_contains(
            $content,
            'iframe.srcdoc'
        )
        && !str_contains(
            $content,
            'frame.srcdoc'
        ),

    'V1/V2/V3 marker lama hilang' =>
        !str_contains(
            $content,
            'MY EMAIL SAFE HTML RENDER V1'
        )
        && !str_contains(
            $content,
            'MY EMAIL NORMAL READER V2'
        )
        && !str_contains(
            $content,
            'MY EMAIL SERVER RENDER V3'
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
echo "3. Body harus berisi teks email normal, bukan blank/raw HTML.\n";
echo "4. Reply dan Reply All tetap terlihat.\n";
echo "5. Klik link Teams/Box/http/https -> tab baru.\n";
echo "6. Attachments dan Move to Trash tetap bekerja.\n";

exit(0);
