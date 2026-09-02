<?php

/*
|--------------------------------------------------------------------------
| Internal Chat V3.1
|--------------------------------------------------------------------------
|
| Adds on top of V3:
| - Reply to message
| - Edit own message
| - Soft-delete own message
| - Cross-user edit/delete polling sync
| - Global unread badge on the existing Chat widget
|
| No migration. Existing schema already has:
| reply_to_message_id, edited_at, deleted_at, last_read_at.
|
*/

$root =
    realpath(
        __DIR__.'/..'
    );

if (! $root) {
    fwrite(
        STDERR,
        "Project root tidak ditemukan.\n"
    );

    exit(1);
}

function backupOnce(
    string $path,
    string $suffix
): void {
    $backup =
        $path
        .$suffix;

    if (! is_file($backup)) {
        if (! copy(
            $path,
            $backup
        )) {
            throw new RuntimeException(
                "Gagal membuat backup: {$backup}"
            );
        }
    }
}

function validateMarkers(
    string $source,
    array $markers,
    string $label
): void {
    foreach ($markers as $marker) {
        if (! str_contains($source, $marker)) {
            fwrite(
                STDERR,
                "{$label} tidak dikenali. Marker hilang: {$marker}\n"
            );

            exit(10);
        }
    }
}

$targets = [
    [
        'label' =>
            'InternalChatController',

        'target' =>
            $root
            .'/packages/Webkul/Admin/src/Http/Controllers/InternalCommunication/InternalChatController.php',

        'stub' =>
            $root
            .'/stubs/packages/Webkul/Admin/src/Http/Controllers/InternalCommunication/InternalChatController.php',

        'current_markers' => [
            'class InternalChatController',
            "'unread_count'",
            "'read_up_to_id'",
            'readUpToMessageId(',
        ],

        'new_markers' => [
            'public function updateMessage(',
            'public function deleteMessage(',
            'public function unreadSummary(',
            "'changed_messages'",
            "'deleted_message_ids'",
            "'reply_to_message_id'",
        ],
    ],
    [
        'label' =>
            'Internal Chat Blade',

        'target' =>
            $root
            .'/packages/Webkul/Admin/src/Resources/views/internal-communication/chat.blade.php',

        'stub' =>
            $root
            .'/stubs/packages/Webkul/Admin/src/Resources/views/internal-communication/chat.blade.php',

        'current_markers' => [
            'crm-new-chat-modal',
            'data-read-receipt',
            'crm-chat-send-form',
            '5000',
        ],

        'new_markers' => [
            'crm-chat-reply-preview',
            'data-reply-message',
            'data-edit-message',
            'data-delete-message',
            'admin.internal-chat.messages.update',
            'admin.internal-chat.messages.delete',
            'sync_after',
        ],
    ],
];

foreach ($targets as $spec) {
    if (
        ! is_file(
            $spec['target']
        )
        || ! is_file(
            $spec['stub']
        )
    ) {
        fwrite(
            STDERR,
            "Target/stub tidak ditemukan untuk {$spec['label']}.\n"
        );

        exit(2);
    }

    $current =
        file_get_contents(
            $spec['target']
        );

    $replacement =
        file_get_contents(
            $spec['stub']
        );

    if (
        $current === false
        || $replacement === false
    ) {
        fwrite(
            STDERR,
            "Gagal membaca {$spec['label']}.\n"
        );

        exit(3);
    }

    validateMarkers(
        $current,
        $spec['current_markers'],
        $spec['label'].' current'
    );

    validateMarkers(
        $replacement,
        $spec['new_markers'],
        $spec['label'].' V3.1'
    );

    backupOnce(
        $spec['target'],
        '.before-internal-chat-v3-1.bak'
    );

    if (
        file_put_contents(
            $spec['target'],
            $replacement
        ) === false
    ) {
        fwrite(
            STDERR,
            "Gagal menulis {$spec['label']}.\n"
        );

        exit(4);
    }

    echo "[PASS] {$spec['label']} V3.1 installed.\n";
}

/*
|--------------------------------------------------------------------------
| Global unread badge partial
|--------------------------------------------------------------------------
*/

$partialSource =
    $root
    .'/stubs/packages/Webkul/Admin/src/Resources/views/internal-communication/chat-unread-badge.blade.php';

$partialTarget =
    $root
    .'/packages/Webkul/Admin/src/Resources/views/internal-communication/chat-unread-badge.blade.php';

if (! is_file($partialSource)) {
    fwrite(
        STDERR,
        "Global unread partial stub tidak ditemukan.\n"
    );

    exit(5);
}

if (
    ! is_dir(
        dirname(
            $partialTarget
        )
    )
) {
    mkdir(
        dirname(
            $partialTarget
        ),
        0775,
        true
    );
}

if (
    file_put_contents(
        $partialTarget,
        file_get_contents(
            $partialSource
        )
    ) === false
) {
    fwrite(
        STDERR,
        "Gagal memasang global unread partial.\n"
    );

    exit(6);
}

echo "[PASS] Global unread badge partial installed.\n";

/*
|--------------------------------------------------------------------------
| Append partial to existing dedicated widget.
|--------------------------------------------------------------------------
|
| Do not replace widget.blade.php because it is already customized.
|
*/

$widget =
    $root
    .'/packages/Webkul/Admin/src/Resources/views/internal-communication/widget.blade.php';

if (! is_file($widget)) {
    fwrite(
        STDERR,
        "Internal communication widget tidak ditemukan.\n"
    );

    exit(7);
}

$widgetSource =
    file_get_contents(
        $widget
    );

$includeMarker =
    "@include('admin::internal-communication.chat-unread-badge')";

if (
    ! str_contains(
        $widgetSource,
        $includeMarker
    )
) {
    backupOnce(
        $widget,
        '.before-internal-chat-v3-1.bak'
    );

    $widgetSource =
        rtrim(
            $widgetSource
        )
        ."\n\n"
        .$includeMarker
        ."\n";

    if (
        file_put_contents(
            $widget,
            $widgetSource
        ) === false
    ) {
        fwrite(
            STDERR,
            "Gagal patch global widget.\n"
        );

        exit(8);
    }
}

echo "[PASS] Existing global Chat widget connected to unread badge.\n";

/*
|--------------------------------------------------------------------------
| Routes
|--------------------------------------------------------------------------
|
| Patch current InternalCommunicationServiceProvider in place.
| Business notification callbacks are intentionally preserved.
|
*/

$provider =
    $root
    .'/packages/Webkul/Admin/src/Providers/InternalCommunicationServiceProvider.php';

if (! is_file($provider)) {
    fwrite(
        STDERR,
        "InternalCommunicationServiceProvider tidak ditemukan.\n"
    );

    exit(9);
}

$providerSource =
    file_get_contents(
        $provider
    );

$requiredProviderMarkers = [
    'admin.internal-chat.index',
    'admin.internal-chat.direct',
    'admin.internal-chat.messages',
    'admin.internal-chat.send',
];

validateMarkers(
    $providerSource,
    $requiredProviderMarkers,
    'InternalCommunicationServiceProvider'
);

$newRouteMarker =
    'admin.internal-chat.unread-summary';

if (
    ! str_contains(
        $providerSource,
        $newRouteMarker
    )
) {
    $directNamePosition =
        strpos(
            $providerSource,
            'admin.internal-chat.direct'
        );

    if ($directNamePosition === false) {
        fwrite(
            STDERR,
            "Anchor route direct chat tidak ditemukan.\n"
        );

        exit(11);
    }

    $routeStart =
        strrpos(
            substr(
                $providerSource,
                0,
                $directNamePosition
            ),
            'Route::post('
        );

    if ($routeStart === false) {
        fwrite(
            STDERR,
            "Awal route direct chat tidak ditemukan.\n"
        );

        exit(12);
    }

    $lineStart =
        strrpos(
            substr(
                $providerSource,
                0,
                $routeStart
            ),
            "\n"
        );

    $lineStart =
        $lineStart === false
            ? 0
            : $lineStart + 1;

    $indent =
        substr(
            $providerSource,
            $lineStart,
            $routeStart - $lineStart
        );

    $routes =
        $indent
        ."Route::get(\n"
        .$indent
        ."    'internal-chat/unread-summary',\n"
        .$indent
        ."    [\n"
        .$indent
        ."        InternalChatController::class,\n"
        .$indent
        ."        'unreadSummary',\n"
        .$indent
        ."    ]\n"
        .$indent
        .")->name(\n"
        .$indent
        ."    'admin.internal-chat.unread-summary'\n"
        .$indent
        .");\n\n"

        .$indent
        ."Route::patch(\n"
        .$indent
        ."    'internal-chat/{conversationId}/messages/{messageId}',\n"
        .$indent
        ."    [\n"
        .$indent
        ."        InternalChatController::class,\n"
        .$indent
        ."        'updateMessage',\n"
        .$indent
        ."    ]\n"
        .$indent
        .")->name(\n"
        .$indent
        ."    'admin.internal-chat.messages.update'\n"
        .$indent
        .");\n\n"

        .$indent
        ."Route::delete(\n"
        .$indent
        ."    'internal-chat/{conversationId}/messages/{messageId}',\n"
        .$indent
        ."    [\n"
        .$indent
        ."        InternalChatController::class,\n"
        .$indent
        ."        'deleteMessage',\n"
        .$indent
        ."    ]\n"
        .$indent
        .")->name(\n"
        .$indent
        ."    'admin.internal-chat.messages.delete'\n"
        .$indent
        .");\n\n";

    backupOnce(
        $provider,
        '.before-internal-chat-v3-1.bak'
    );

    $providerSource =
        substr_replace(
            $providerSource,
            $routes,
            $routeStart,
            0
        );

    if (
        file_put_contents(
            $provider,
            $providerSource
        ) === false
    ) {
        fwrite(
            STDERR,
            "Gagal patch route Internal Chat V3.1.\n"
        );

        exit(13);
    }
}

echo "[PASS] Reply/Edit/Delete/Unread routes installed.\n";

echo "\n";
echo "Internal Chat V3.1 selesai.\n";
echo "No migration required.\n";
echo "Existing workflow notifications tetap dipertahankan.\n";
