<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$target =
    $root
    . '/packages/Webkul/Admin/src/Resources/views/user-email/message.blade.php';

echo "CHECK MY EMAIL DOUBLE-ESCAPE FIX V5\n";
echo "===================================\n\n";

$content =
    is_file($target)
        ? file_get_contents($target)
        : '';

$checks = [
    'Target Blade tersedia' =>
        $content !== '',

    'V5 marker satu kali' =>
        substr_count(
            $content,
            'MY EMAIL DOUBLE ESCAPE FIX V5'
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

    'Single-escaped srcdoc aktif' =>
        preg_match(
            '~srcdoc\s*=\s*(["\'])\s*\{\{\s*\$message->html_body\s*\}\}\s*\1~',
            $content
        ) === 1,

    'Double escape e(html_body) sudah hilang' =>
        preg_match(
            '~srcdoc\s*=\s*(["\'])\s*\{\{\s*e\s*\(\s*\$message->html_body\s*\)\s*\}\}\s*\1~',
            $content
        ) !== 1,

    'Text-body fallback tetap ada' =>
        str_contains(
            $content,
            '$message->text_body'
        ),

    'Iframe tetap ada' =>
        str_contains(
            $content,
            '<iframe'
        )
        && str_contains(
            $content,
            'srcdoc='
        ),

    'Iframe sandbox tersedia' =>
        preg_match(
            '~<iframe\b[^>]*\bsandbox\s*=~is',
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

    'Attachments tetap ada' =>
        str_contains(
            $content,
            'admin.my-email.attachments.download'
        ),

    'Move to Trash tetap ada' =>
        str_contains(
            $content,
            'admin.my-email.trash.move'
        ),

    'Eksperimen V1-V4 tidak ikut terbawa' =>
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
echo "\nQA browser:\n";
echo "1. Ctrl + Shift + R.\n";
echo "2. Buka /admin/my-email/messages/23.\n";
echo "3. Subject / From / Received tetap tampil.\n";
echo "4. Reply / Reply All tetap tampil.\n";
echo "5. Body HTML harus tampil sebagai email normal, bukan raw <html> dan bukan blank.\n";
echo "6. Klik link di body; link harus dapat dinavigasi dari iframe.\n";
echo "7. Attachment dan Move to Trash tetap tersedia.\n";

exit(0);
