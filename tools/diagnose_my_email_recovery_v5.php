<?php

declare(strict_types=1);

/**
 * MY EMAIL RECOVERY DIAGNOSTIC V5
 *
 * READ-ONLY.
 * Tidak mengubah file, database, cache, atau git.
 *
 * Membandingkan:
 * - message.blade.php aktif
 * - seluruh backup message.blade.php terkait My Email
 * - versi Git HEAD untuk file yang sama
 *
 * Tujuan:
 * menemukan baseline paling sehat sebelum patch HTML reader berikutnya.
 */

$root = dirname(__DIR__);

$relative =
    'packages/Webkul/Admin/src/Resources/views/user-email/message.blade.php';

$target =
    $root . '/' . $relative;

function classify(string $content): array
{
    return [
        'bytes' => strlen($content),
        'lines' => substr_count($content, "\n") + 1,
        'layout_open' => substr_count($content, '<x-admin::layouts>'),
        'layout_close' => substr_count($content, '</x-admin::layouts>'),
        'reply' => substr_count($content, 'Reply'),
        'reply_all' => substr_count($content, 'Reply All'),
        'html_body' => substr_count($content, 'html_body'),
        'text_body' => substr_count($content, 'text_body'),
        'attachments' => substr_count($content, 'admin.my-email.attachments.download'),
        'trash' => substr_count($content, 'admin.my-email.trash.move'),
        'v1' => substr_count($content, 'MY EMAIL SAFE HTML RENDER V1'),
        'v2' => substr_count($content, 'MY EMAIL NORMAL READER V2'),
        'v3' => substr_count($content, 'MY EMAIL SERVER RENDER V3'),
        'v4' => substr_count($content, 'MY EMAIL READABLE SAFE V4'),
        'iframe' => substr_count(strtolower($content), '<iframe'),
        'srcdoc' => substr_count(strtolower($content), 'srcdoc'),
    ];
}

function printInfo(string $label, string $content): void
{
    $i = classify($content);

    echo "\n=== {$label} ===\n";
    echo "bytes        : {$i['bytes']}\n";
    echo "lines        : {$i['lines']}\n";
    echo "layout open  : {$i['layout_open']}\n";
    echo "layout close : {$i['layout_close']}\n";
    echo "Reply        : {$i['reply']}\n";
    echo "Reply All    : {$i['reply_all']}\n";
    echo "html_body    : {$i['html_body']}\n";
    echo "text_body    : {$i['text_body']}\n";
    echo "attachments  : {$i['attachments']}\n";
    echo "trash        : {$i['trash']}\n";
    echo "V1 marker    : {$i['v1']}\n";
    echo "V2 marker    : {$i['v2']}\n";
    echo "V3 marker    : {$i['v3']}\n";
    echo "V4 marker    : {$i['v4']}\n";
    echo "iframe       : {$i['iframe']}\n";
    echo "srcdoc       : {$i['srcdoc']}\n";
    echo "sha1         : " . sha1($content) . "\n";
}

echo "MY EMAIL RECOVERY DIAGNOSTIC V5\n";
echo "===============================\n";

if (!is_file($target)) {
    fwrite(STDERR, "Target tidak ditemukan:\n{$target}\n");
    exit(1);
}

$current = file_get_contents($target);

if ($current === false) {
    fwrite(STDERR, "Gagal membaca target aktif.\n");
    exit(1);
}

printInfo('CURRENT ACTIVE', $current);

$backups = glob($target . '.bak-*') ?: [];

usort(
    $backups,
    static fn (string $a, string $b): int =>
        (filemtime($a) ?: 0) <=> (filemtime($b) ?: 0)
);

echo "\nBACKUP COUNT: " . count($backups) . "\n";

foreach ($backups as $backup) {
    $content = file_get_contents($backup);

    if ($content === false) {
        continue;
    }

    printInfo(
        basename($backup),
        $content
    );
}

/*
 * Compare with Git HEAD without modifying working tree.
 */
echo "\n=== GIT HEAD VERSION ===\n";

$command =
    'git -C '
    . escapeshellarg($root)
    . ' show HEAD:'
    . escapeshellarg($relative)
    . ' 2>&1';

exec(
    $command,
    $gitOutput,
    $gitCode
);

if ($gitCode !== 0) {
    echo "git show gagal (exit {$gitCode})\n";
    echo implode("\n", $gitOutput) . "\n";
} else {
    $gitContent =
        implode(
            "\n",
            $gitOutput
        );

    printInfo(
        'GIT HEAD ' . $relative,
        $gitContent
    );

    echo "\nGIT HEAD BODY ECHO CANDIDATES\n";
    echo "-----------------------------\n";

    preg_match_all(
        '~\{\{\s*([\s\S]*?)\s*\}\}~',
        $gitContent,
        $matches,
        PREG_SET_ORDER
    );

    foreach ($matches as $match) {
        $expr =
            trim(
                (string) (
                    $match[1]
                    ?? ''
                )
            );

        if (
            str_contains($expr, '$message')
            && (
                str_contains($expr, 'html_body')
                || str_contains($expr, 'text_body')
                || str_contains($expr, 'body')
            )
        ) {
            echo '- ' . $expr . "\n";
        }
    }
}

echo "\nHASIL: READ-ONLY COMPLETE\n";
echo "Kirim seluruh output ini.\n";

exit(0);
