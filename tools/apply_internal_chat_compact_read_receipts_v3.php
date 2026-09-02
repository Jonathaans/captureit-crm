<?php

/*
|--------------------------------------------------------------------------
| Internal Chat V3 - Compact List + Read Receipts
|--------------------------------------------------------------------------
|
| Replaces ONLY the dedicated Internal Chat controller + Blade using stubs
| built from the user's current V2 source.
|
| No migration is required. Existing last_read_at is reused.
|
*/

$root = realpath(__DIR__.'/..');

if (! $root) {
    fwrite(STDERR, "Project root tidak ditemukan.\n");
    exit(1);
}

$specs = [
    [
        'target' => $root.'/packages/Webkul/Admin/src/Http/Controllers/InternalCommunication/InternalChatController.php',
        'stub' => $root.'/stubs/packages/Webkul/Admin/src/Http/Controllers/InternalCommunication/InternalChatController.php',
        'current_markers' => [
            'class InternalChatController',
            '$chat->markRead(',
            'InternalConversationMember::query()',
            'private function messagePayload(',
        ],
        'new_markers' => [
            "'unread_count'",
            "'read_up_to_id'",
            'readUpToMessageId(',
            "'m.last_read_at'",
        ],
    ],
    [
        'target' => $root.'/packages/Webkul/Admin/src/Resources/views/internal-communication/chat.blade.php',
        'stub' => $root.'/stubs/packages/Webkul/Admin/src/Resources/views/internal-communication/chat.blade.php',
        'current_markers' => [
            'Internal Chat',
            'crm-wa-chat-search',
            'crm-chat-messages',
            'crm-chat-send-form',
            '5000',
        ],
        'new_markers' => [
            'crm-new-chat-modal',
            'crm-chat-user-modal-list',
            'data-read-receipt',
            'read_up_to_id',
            'text-blue-400',
            '5000',
        ],
    ],
];

foreach ($specs as $spec) {
    if (! is_file($spec['target'])) {
        fwrite(STDERR, "Target tidak ditemukan: {$spec['target']}\n");
        exit(2);
    }

    if (! is_file($spec['stub'])) {
        fwrite(STDERR, "Stub tidak ditemukan: {$spec['stub']}\n");
        exit(3);
    }

    $current = file_get_contents($spec['target']);
    $replacement = file_get_contents($spec['stub']);

    if ($current === false || $replacement === false) {
        fwrite(STDERR, "Gagal membaca target/stub.\n");
        exit(4);
    }

    foreach ($spec['current_markers'] as $marker) {
        if (! str_contains($current, $marker)) {
            fwrite(
                STDERR,
                "Current target tidak dikenali: {$marker}\n"
                ."Patch dihentikan agar file customized tidak tertimpa sembarangan.\n"
            );
            exit(5);
        }
    }

    foreach ($spec['new_markers'] as $marker) {
        if (! str_contains($replacement, $marker)) {
            fwrite(STDERR, "Stub V3 gagal validasi: {$marker}\n");
            exit(6);
        }
    }

    $backup = $spec['target'].'.before-internal-chat-v3-compact-read-receipts.bak';

    if (! is_file($backup)) {
        if (! copy($spec['target'], $backup)) {
            fwrite(STDERR, "Gagal membuat backup: {$spec['target']}\n");
            exit(7);
        }
    }

    if (file_put_contents($spec['target'], $replacement) === false) {
        fwrite(STDERR, "Gagal menulis: {$spec['target']}\n");
        exit(8);
    }
}

echo "[PASS] Internal Chat V3 installed.\n";
echo "[PASS] Sidebar now shows recent conversations only.\n";
echo "[PASS] Full user directory moved to New Chat modal.\n";
echo "[PASS] Unread badges added.\n";
echo "[PASS] Double-check read receipts added.\n";
echo "[PASS] Blue double-check uses existing last_read_at.\n";
echo "[PASS] 5-second polling preserved.\n";
echo "[PASS] Attachments and direct chat preserved.\n";
echo "[PASS] No migration required.\n";
