<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$target =
    $root
    . '/packages/Webkul/Admin/src/Resources/views/user-email/message.blade.php';

$files =
    glob(
        $target
        . '.bak-before-my-email-double-escape-v5_1-*'
    )
    ?: [];

if (!$files) {
    fwrite(
        STDERR,
        "Safety backup V5.1 tidak ditemukan.\n"
    );
    exit(1);
}

usort(
    $files,
    static fn (string $a, string $b): int =>
        (filemtime($b) ?: 0)
        <=>
        (filemtime($a) ?: 0)
);

$backup =
    $files[0];

if (!copy($backup, $target)) {
    fwrite(
        STDERR,
        "Gagal restore:\n{$backup}\n"
    );
    exit(1);
}

echo "Restored:\n{$backup}\n";

chdir($root);

passthru(
    escapeshellarg(PHP_BINARY)
    . ' '
    . escapeshellarg(
        $root . '/artisan'
    )
    . ' view:clear'
);

echo "\nROLLBACK V5.1 SELESAI.\n";
