<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$target =
    $root
    . '/packages/Webkul/Admin/src/Resources/views/user-email/message.blade.php';

echo "CHECK MY EMAIL DOUBLE-ESCAPE FIX V5.1\n";
echo "=====================================\n\n";

$content =
    is_file($target)
        ? file_get_contents($target)
        : '';

$oldSrcdoc =
    'srcdoc="{{ e($message->html_body) }}"';

$newSrcdoc =
    'srcdoc="{{ $message->html_body }}"';

$checks = [
    'Target Blade tersedia' =>
        $content !== '',

    'V5.1 marker satu kali' =>
        substr_count(
            $content,
            'MY EMAIL DOUBLE ESCAPE FIX V5.1'
        ) === 1,

    'Layout seimbang' =>
        substr_count(
            $content,
            '<x-admin::layouts>'
        ) === 1
        && substr_count(
            $content,
            '</x-admin::layouts>'
        ) === 1,

    'Fixed srcdoc tepat 1 kali' =>
        substr_count(
            $content,
            $newSrcdoc
        ) === 1,

    'Double-escaped srcdoc sudah hilang' =>
        !str_contains(
            $content,
            $oldSrcdoc
        ),

    'Iframe tersedia' =>
        str_contains(
            $content,
            '<iframe'
        ),

    'Iframe memiliki sandbox' =>
        preg_match(
            '~<iframe\b[^>]*\bsandbox(?:\s*=|\s|>)~is',
            $content
        ) === 1,

    'Sandbox tidak allow-scripts' =>
        preg_match(
            '~<iframe\b[^>]*\bsandbox\s*=\s*(["\'])[^"\']*allow-scripts[^"\']*\1~is',
            $content
        ) !== 1,

    'Sandbox tidak allow-same-origin' =>
        preg_match(
            '~<iframe\b[^>]*\bsandbox\s*=\s*(["\'])[^"\']*allow-same-origin[^"\']*\1~is',
            $content
        ) !== 1,

    'Reply tersedia' =>
        str_contains(
            $content,
            'Reply'
        ),

    'Reply All tersedia' =>
        str_contains(
            $content,
            'Reply All'
        ),

    'Attachments tersedia' =>
        str_contains(
            $content,
            'admin.my-email.attachments.download'
        ),

    'Move to Trash tersedia' =>
        str_contains(
            $content,
            'admin.my-email.trash.move'
        ),

    'Text-body fallback tersedia' =>
        str_contains(
            $content,
            '$message->text_body'
        ),

    'Tidak ada patch renderer V1-V4' =>
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
        )
        && !str_contains(
            $content,
            'MY EMAIL READABLE SAFE V4'
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
echo "\nQA:\n";
echo "1. Ctrl + Shift + R.\n";
echo "2. Buka /admin/my-email/messages/23.\n";
echo "3. Subject / From / Received tetap ada.\n";
echo "4. Reply / Reply All tetap ada.\n";
echo "5. Body harus render sebagai HTML email normal.\n";
echo "6. Body tidak boleh raw <html> dan tidak boleh blank.\n";
echo "7. Tes link di body.\n";

exit(0);
