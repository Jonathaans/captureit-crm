<?php

/*
|--------------------------------------------------------------------------
| Internal Chat V3.1.2 - Blade Route Template Hotfix
|--------------------------------------------------------------------------
|
| Fixes Blade compiler error:
|
| Unclosed '[' ... does not match ')'
|
| Root cause:
| Blade @json(...) is parsing a nested route(...) call containing an array
| literal for conversationId/messageId. The PHP itself is valid, but this
| Blade build can misparse the nested expression.
|
| Fix:
| Precompute both URLs inside a plain @php block, then pass only the simple
| variables into @json().
|
| Scope:
| - chat.blade.php ONLY
| - no controller
| - no route
| - no migration
| - no database
|
*/

$root = realpath(__DIR__.'/..');

if (! $root) {
    fwrite(STDERR, "Project root tidak ditemukan.\n");
    exit(1);
}

$target =
    $root
    .'/packages/Webkul/Admin/src/Resources/views/internal-communication/chat.blade.php';

if (! is_file($target)) {
    fwrite(STDERR, "chat.blade.php tidak ditemukan.\n");
    exit(2);
}

$source = file_get_contents($target);

if ($source === false) {
    fwrite(STDERR, "Gagal membaca chat.blade.php.\n");
    exit(3);
}

if (
    str_contains(
        $source,
        'INTERNAL CHAT V3.1.2 SAFE ROUTE TEMPLATES'
    )
) {
    echo "[SKIP] V3.1.2 already installed.\n";
    exit(0);
}

$old = <<<'BLADE'
                const editUrlTemplate =
                    @json(
                        route(
                            'admin.internal-chat.messages.update',
                            [
                                'conversationId' =>
                                    $conversation->id,

                                'messageId' =>
                                    '__MESSAGE_ID__',
                            ]
                        )
                    );

                const deleteUrlTemplate =
                    @json(
                        route(
                            'admin.internal-chat.messages.delete',
                            [
                                'conversationId' =>
                                    $conversation->id,

                                'messageId' =>
                                    '__MESSAGE_ID__',
                            ]
                        )
                    );
BLADE;

$new = <<<'BLADE'
                {{-- INTERNAL CHAT V3.1.2 SAFE ROUTE TEMPLATES --}}
                @php
                    $crmEditMessageUrlTemplate =
                        route(
                            'admin.internal-chat.messages.update',
                            [
                                'conversationId' =>
                                    $conversation->id,

                                'messageId' =>
                                    '__MESSAGE_ID__',
                            ]
                        );

                    $crmDeleteMessageUrlTemplate =
                        route(
                            'admin.internal-chat.messages.delete',
                            [
                                'conversationId' =>
                                    $conversation->id,

                                'messageId' =>
                                    '__MESSAGE_ID__',
                            ]
                        );
                @endphp

                const editUrlTemplate =
                    @json(
                        $crmEditMessageUrlTemplate
                    );

                const deleteUrlTemplate =
                    @json(
                        $crmDeleteMessageUrlTemplate
                    );
BLADE;

if (! str_contains($source, $old)) {
    fwrite(
        STDERR,
        "Block route template V3.1 tidak ditemukan.\n"
        ."Patch dihentikan agar tidak merusak Blade yang berbeda.\n"
    );
    exit(4);
}

$backup =
    $target
    .'.before-internal-chat-v3-1-2-route-template-hotfix.bak';

if (! is_file($backup)) {
    if (! copy($target, $backup)) {
        fwrite(STDERR, "Gagal membuat backup Blade.\n");
        exit(5);
    }
}

$source = str_replace(
    $old,
    $new,
    $source,
    $count
);

if ($count !== 1) {
    copy($backup, $target);

    fwrite(
        STDERR,
        "Jumlah replacement tidak tepat: {$count}.\n"
        ."Backup dipulihkan otomatis.\n"
    );

    exit(6);
}

if (
    file_put_contents(
        $target,
        $source
    ) === false
) {
    copy($backup, $target);

    fwrite(
        STDERR,
        "Gagal menulis Blade. Backup dipulihkan.\n"
    );

    exit(7);
}

$written = file_get_contents($target);

$required = [
    'INTERNAL CHAT V3.1.2 SAFE ROUTE TEMPLATES',
    '$crmEditMessageUrlTemplate',
    '$crmDeleteMessageUrlTemplate',
    '@json(',
    'crm-chat-send-form',
    'data-read-receipt',
    'data-reply-message',
    'data-edit-message',
    'data-delete-message',
    '5000',
];

foreach ($required as $marker) {
    if (
        $written === false
        || ! str_contains(
            $written,
            $marker
        )
    ) {
        copy($backup, $target);

        fwrite(
            STDERR,
            "Post-write validation gagal: {$marker}\n"
            ."Backup dipulihkan otomatis.\n"
        );

        exit(8);
    }
}

echo "[PASS] Blade route templates changed to safe precomputed variables.\n";
echo "[PASS] Reply/Edit/Delete UI preserved.\n";
echo "[PASS] Read receipt preserved.\n";
echo "[PASS] Attachment/send/polling preserved.\n";
echo "[PASS] No route/controller/database changes.\n";
