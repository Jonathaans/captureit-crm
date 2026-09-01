<?php

/*
|--------------------------------------------------------------------------
| Per-User Email V1.2.1 - sent_at Cast Hotfix
|--------------------------------------------------------------------------
|
| Fix:
| "Call to a member function format() on string"
|
| Cause:
| user_email_messages.sent_at exists in DB, but UserEmailMessage model did not
| cast sent_at to datetime.
|
| No migration.
| No controller changes.
| No route changes.
|
*/

$projectRoot = realpath(__DIR__.'/..');

if (! $projectRoot) {
    fwrite(STDERR, "Project root tidak ditemukan.\n");
    exit(1);
}

$path =
    $projectRoot
    .'/packages/Webkul/Admin/src/Models/UserEmailMessage.php';

if (! is_file($path)) {
    fwrite(
        STDERR,
        "UserEmailMessage.php tidak ditemukan.\n"
    );
    exit(2);
}

$source = file_get_contents($path);

if ($source === false) {
    fwrite(
        STDERR,
        "UserEmailMessage.php tidak dapat dibaca.\n"
    );
    exit(3);
}

$marker = "'sent_at' => 'datetime'";

if (str_contains($source, $marker)) {
    echo "[SKIP] sent_at datetime cast sudah terpasang.\n";
    exit(0);
}

$old = <<<'PHP'
    protected $casts = [
        'received_at' => 'datetime',
        'read_at' => 'datetime',
    ];
PHP;

$new = <<<'PHP'
    protected $casts = [
        'received_at' => 'datetime',
        'read_at' => 'datetime',
        'sent_at' => 'datetime',
    ];
PHP;

$count = substr_count($source, $old);

if ($count !== 1) {
    fwrite(
        STDERR,
        "Cast block berbeda dari V1 yang diketahui. "
        ."Expected 1 target block, found {$count}. "
        ."Patch dihentikan tanpa mengubah file.\n"
    );
    exit(4);
}

$backup =
    $path
    .'.before-user-email-v1-2-1-sent-at-cast.bak';

if (! is_file($backup)) {
    copy($path, $backup);
}

$source = str_replace(
    $old,
    $new,
    $source,
    $replaced
);

if ($replaced !== 1) {
    fwrite(
        STDERR,
        "Patch gagal. File tidak ditulis.\n"
    );
    exit(5);
}

file_put_contents(
    $path,
    $source
);

echo "[PASS] user_email_messages.sent_at sekarang dicast sebagai datetime.\n";
echo "[PASS] Sent detail tidak lagi memanggil format() pada string.\n";
echo "[PASS] Tidak ada migration.\n";
