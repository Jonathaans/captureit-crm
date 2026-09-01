<?php

/*
|--------------------------------------------------------------------------
| Internal Chat Modern UI V1.1 Installer Hotfix
|--------------------------------------------------------------------------
|
| Memperbaiki bug interpolasi variable pada installer V1.
|
| V1 menulis marker Blade dalam string PHP double-quoted, sehingga
| $conversation->id dan $attachment->id dievaluasi oleh installer.
|
| V1.1 memakai NOWDOC literal marker.
|
| UI only:
| - chat.blade.php
|
| No migration.
| No route change.
| No controller change.
|
*/

$projectRoot = realpath(__DIR__.'/..');

if (! $projectRoot) {
    fwrite(STDERR, "Project root tidak ditemukan.\n");
    exit(1);
}

$target =
    $projectRoot
    .'/packages/Webkul/Admin/src/Resources/views/internal-communication/chat.blade.php';

$stub =
    $projectRoot
    .'/stubs/packages/Webkul/Admin/src/Resources/views/internal-communication/chat.blade.php';

if (! is_file($target)) {
    fwrite(
        STDERR,
        "File target chat.blade.php tidak ditemukan.\n"
        ."Install Internal Notifications + Chat terlebih dahulu.\n"
    );

    exit(2);
}

if (! is_file($stub)) {
    fwrite(
        STDERR,
        "Stub modern chat.blade.php tidak ditemukan di ZIP.\n"
    );

    exit(3);
}

$current = file_get_contents($target);
$replacement = file_get_contents($stub);

if (
    $current === false
    || $replacement === false
) {
    fwrite(
        STDERR,
        "Gagal membaca file chat view.\n"
    );

    exit(4);
}

/*
|--------------------------------------------------------------------------
| Literal Blade markers
|--------------------------------------------------------------------------
*/

$routeSendMarker = <<<'MARKER'
route('admin.internal-chat.send', $conversation->id)
MARKER;

$routeAttachmentMarker = <<<'MARKER'
route('admin.internal-chat.attachments.download', $attachment->id)
MARKER;

$requiredMarkers = [
    'Internal Chat',
    $routeSendMarker,
    '$conversationList',
    '$messages',
];

foreach ($requiredMarkers as $marker) {
    if (! str_contains($current, $marker)) {
        fwrite(
            STDERR,
            "Marker wajib tidak ditemukan pada file target: {$marker}\n"
            ."Patch dihentikan agar tidak menimpa file yang salah.\n"
        );

        exit(5);
    }
}

/*
|--------------------------------------------------------------------------
| Idempotent
|--------------------------------------------------------------------------
*/

if (
    str_contains(
        $current,
        'crm-modern-chat-shell'
    )
    && str_contains(
        $current,
        'crm-modern-chat-title'
    )
) {
    echo "[SKIP] Internal Chat Modern UI sudah terpasang.\n";

    exit(0);
}

/*
|--------------------------------------------------------------------------
| Validate replacement BEFORE write
|--------------------------------------------------------------------------
*/

$replacementMarkers = [
    'crm-modern-chat-shell',
    'crm-modern-chat-title',
    'crm-modern-conversation-card',
    'crm-modern-send-button',
    'crm-modern-empty-icon',
    'crm-chat-file-list',
    $routeAttachmentMarker,
    $routeSendMarker,
    'window.setInterval(pollMessages, 5000);',
];

foreach ($replacementMarkers as $marker) {
    if (! str_contains($replacement, $marker)) {
        fwrite(
            STDERR,
            "Stub modern chat gagal validasi: {$marker}\n"
        );

        exit(6);
    }
}

$backup =
    $target
    .'.before-internal-chat-modern-ui-v1-1.bak';

if (! is_file($backup)) {
    if (! copy($target, $backup)) {
        fwrite(
            STDERR,
            "Gagal membuat backup chat.blade.php.\n"
        );

        exit(7);
    }
}

if (
    file_put_contents(
        $target,
        $replacement
    ) === false
) {
    fwrite(
        STDERR,
        "Gagal menulis chat.blade.php modern.\n"
    );

    exit(8);
}

$written =
    file_get_contents(
        $target
    );

foreach ($replacementMarkers as $marker) {
    if (
        $written === false
        || ! str_contains(
            $written,
            $marker
        )
    ) {
        if (is_file($backup)) {
            copy(
                $backup,
                $target
            );
        }

        fwrite(
            STDERR,
            "Validasi gagal setelah write: {$marker}\n"
            ."Backup dipulihkan otomatis.\n"
        );

        exit(9);
    }
}

echo "[PASS] Installer interpolation bug fixed.\n";
echo "[PASS] Internal Chat UI berhasil dirework ke desain modern.\n";
echo "[PASS] Conversation list menjadi card layout yang lebih rapi.\n";
echo "[PASS] Chat panel, bubble, composer, dan attachment area sudah diperbarui.\n";
echo "[PASS] Search sidebar aktif untuk conversation dan start new chat.\n";
echo "[PASS] Backend route/controller tidak diubah.\n";
echo "[PASS] No migration.\n";
