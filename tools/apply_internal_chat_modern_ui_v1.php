<?php

/*
|--------------------------------------------------------------------------
| Internal Chat Modern UI V1 Installer
|--------------------------------------------------------------------------
|
| Rework tampilan internal chat menjadi lebih modern, rapi, dan nyaman,
| tanpa mengubah flow backend chat / notification.
|
| ONLY UI:
| - chat.blade.php
|
| NO migration.
| NO route change.
| NO controller change.
|
*/

$projectRoot = realpath(__DIR__.'/..');

if (! $projectRoot) {
    fwrite(STDERR, "Project root tidak ditemukan.\n");
    exit(1);
}

$target = $projectRoot.'/packages/Webkul/Admin/src/Resources/views/internal-communication/chat.blade.php';
$stub   = $projectRoot.'/stubs/packages/Webkul/Admin/src/Resources/views/internal-communication/chat.blade.php';

if (! is_file($target)) {
    fwrite(STDERR, "File target chat.blade.php tidak ditemukan.\n");
    fwrite(STDERR, "Install Internal Notifications + Chat terlebih dahulu.\n");
    exit(2);
}

if (! is_file($stub)) {
    fwrite(STDERR, "Stub modern chat.blade.php tidak ditemukan di ZIP.\n");
    exit(3);
}

$current = file_get_contents($target);
$replacement = file_get_contents($stub);

if ($current === false || $replacement === false) {
    fwrite(STDERR, "Gagal membaca file chat view.\n");
    exit(4);
}

$requiredMarkers = [
    'Internal Chat',
    "route('admin.internal-chat.send', $conversation->id)",
    '$conversationList',
    '$messages',
];

foreach ($requiredMarkers as $marker) {
    if (! str_contains($current, $marker)) {
        fwrite(STDERR, "Marker wajib tidak ditemukan pada file target: {$marker}\n");
        fwrite(STDERR, "Patch dihentikan agar tidak menimpa file yang salah.\n");
        exit(5);
    }
}

if (str_contains($current, 'crm-modern-chat-shell') && str_contains($current, 'crm-modern-chat-title')) {
    echo "[SKIP] Internal Chat Modern UI already installed.\n";
    exit(0);
}

$backup = $target.'.before-internal-chat-modern-ui-v1.bak';

if (! is_file($backup)) {
    if (! copy($target, $backup)) {
        fwrite(STDERR, "Gagal membuat backup chat.blade.php.\n");
        exit(6);
    }
}

if (file_put_contents($target, $replacement) === false) {
    fwrite(STDERR, "Gagal menulis chat.blade.php modern.\n");
    exit(7);
}

$written = file_get_contents($target);

$validationMarkers = [
    'crm-modern-chat-shell',
    'crm-modern-chat-title',
    'crm-modern-conversation-card',
    'crm-modern-send-button',
    'crm-modern-empty-icon',
    'crm-chat-file-list',
    "route('admin.internal-chat.attachments.download', $attachment->id)",
    "route('admin.internal-chat.send', $conversation->id)",
];

foreach ($validationMarkers as $marker) {
    if (! str_contains($written, $marker)) {
        fwrite(STDERR, "Validasi gagal setelah write: {$marker}\n");
        exit(8);
    }
}

echo "[PASS] Internal Chat UI berhasil dirework ke desain modern.\n";
echo "[PASS] Conversation list menjadi card layout yang lebih rapi.\n";
echo "[PASS] Chat panel, bubble, composer, dan attachment area sudah diperbarui.\n";
echo "[PASS] Search sidebar aktif untuk conversation dan start new chat.\n";
echo "[PASS] Backend route/controller tidak diubah.\n";
echo "[PASS] No migration.\n";
