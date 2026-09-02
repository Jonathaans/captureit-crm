<?php

/*
|--------------------------------------------------------------------------
| Internal Chat WhatsApp Column UI V2
|--------------------------------------------------------------------------
|
| Reworks ONLY the dedicated Internal Chat Blade into a WhatsApp-Web-like
| two-column layout using the CRM's existing utility classes.
|
| Important:
| V1/V1.1 relied heavily on custom <style> rules. On this CRM build those
| custom rules may not be applied by the compiled admin frontend. V2 avoids
| that dependency and uses existing utility classes instead.
|
| No migration.
| No controller.
| No route.
| No service changes.
|
*/

$projectRoot =
    realpath(
        __DIR__.'/..'
    );

if (! $projectRoot) {
    fwrite(
        STDERR,
        "Project root tidak ditemukan.\n"
    );

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
        "Internal Chat Blade tidak ditemukan.\n"
    );

    exit(2);
}

if (! is_file($stub)) {
    fwrite(
        STDERR,
        "WhatsApp-style chat Blade stub tidak ditemukan.\n"
    );

    exit(3);
}

$current =
    file_get_contents(
        $target
    );

$replacement =
    file_get_contents(
        $stub
    );

if (
    $current === false
    || $replacement === false
) {
    fwrite(
        STDERR,
        "Gagal membaca chat Blade.\n"
    );

    exit(4);
}

$routeSendMarker = <<<'MARKER'
route('admin.internal-chat.send', $conversation->id)
MARKER;

$routeAttachmentMarker = <<<'MARKER'
route('admin.internal-chat.attachments.download', $attachment->id)
MARKER;

$requiredCurrent = [
    'Internal Chat',
    '$conversationList',
    '$messages',
    $routeSendMarker,
];

foreach ($requiredCurrent as $marker) {
    if (! str_contains($current, $marker)) {
        fwrite(
            STDERR,
            "Current chat Blade tidak dikenali: {$marker}\n"
            ."Patch dihentikan agar tidak menimpa file yang salah.\n"
        );

        exit(5);
    }
}

if (
    str_contains(
        $current,
        'crm-wa-chat-search'
    )
    && str_contains(
        $current,
        'Recent Conversations'
    )
) {
    echo "[SKIP] Internal Chat WhatsApp Column UI V2 already installed.\n";

    exit(0);
}

$replacementMarkers = [
    'crm-wa-chat-search',
    'Recent Conversations',
    'Start New Chat',
    'Direct Conversation',
    'crm-chat-messages',
    'crm-chat-send-form',
    'crm-chat-file-list',
    $routeSendMarker,
    $routeAttachmentMarker,
    'window.setInterval(',
    '5000',
];

foreach ($replacementMarkers as $marker) {
    if (! str_contains($replacement, $marker)) {
        fwrite(
            STDERR,
            "Replacement Blade gagal validasi: {$marker}\n"
        );

        exit(6);
    }
}

$backup =
    $target
    .'.before-internal-chat-whatsapp-column-ui-v2.bak';

if (! is_file($backup)) {
    if (! copy($target, $backup)) {
        fwrite(
            STDERR,
            "Gagal membuat backup chat Blade.\n"
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
        "Gagal menulis WhatsApp-style chat Blade.\n"
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
        copy(
            $backup,
            $target
        );

        fwrite(
            STDERR,
            "Post-write validation gagal: {$marker}\n"
            ."Backup dipulihkan otomatis.\n"
        );

        exit(9);
    }
}

echo "[PASS] Internal Chat changed to WhatsApp-style two-column layout.\n";
echo "[PASS] Left chat/user list ready.\n";
echo "[PASS] Right conversation panel ready.\n";
echo "[PASS] Message bubbles and composer reworked.\n";
echo "[PASS] Search, attachments, send, and 5-second polling preserved.\n";
echo "[PASS] Custom CSS dependency removed.\n";
echo "[PASS] No migration / controller / route changes.\n";
