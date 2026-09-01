<?php

/*
|--------------------------------------------------------------------------
| Per-User Email V1.2 - Reply / Compose / Sent
|--------------------------------------------------------------------------
|
| Additive:
| - new compose/sent provider
| - new controller/service/views
| - migration for threading metadata
|
| Surgical UI patch:
| - add Compose + Sent buttons to existing My Inbox
| - add Reply + Reply All buttons to existing received-email detail
|
| Existing IMAP transport and SMTP connection settings are not replaced.
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

function backupOnce(
    string $path,
    string $suffix
): void {
    $backup =
        $path
        .$suffix;

    if (
        is_file($path)
        && ! is_file($backup)
    ) {
        copy(
            $path,
            $backup
        );
    }
}

/*
|--------------------------------------------------------------------------
| 1. Register new provider
|--------------------------------------------------------------------------
*/

$providerPath =
    $projectRoot
    .'/bootstrap/providers.php';

if (! is_file($providerPath)) {
    fwrite(
        STDERR,
        "bootstrap/providers.php tidak ditemukan.\n"
    );
    exit(2);
}

$providerSource =
    file_get_contents(
        $providerPath
    );

$provider =
    '\\Webkul\\Admin\\Providers\\UserEmailComposeServiceProvider::class';

if (
    str_contains(
        $providerSource,
        $provider
    )
) {
    echo "[SKIP] UserEmailComposeServiceProvider already registered.\n";
} else {
    $end =
        strrpos(
            $providerSource,
            '];'
        );

    if ($end === false) {
        fwrite(
            STDERR,
            "providers.php format tidak dikenali.\n"
        );
        exit(3);
    }

    backupOnce(
        $providerPath,
        '.before-user-email-v1-2.bak'
    );

    $providerSource =
        substr_replace(
            $providerSource,
            "    {$provider},\n",
            $end,
            0
        );

    file_put_contents(
        $providerPath,
        $providerSource
    );

    echo "[PASS] UserEmailComposeServiceProvider registered.\n";
}

/*
|--------------------------------------------------------------------------
| 2. Patch My Inbox header
|--------------------------------------------------------------------------
*/

$inboxPath =
    $projectRoot
    .'/packages/Webkul/Admin/src/Resources/views/user-email/inbox.blade.php';

if (! is_file($inboxPath)) {
    fwrite(
        STDERR,
        "My Email inbox Blade tidak ditemukan.\n"
    );
    exit(4);
}

$inbox =
    file_get_contents(
        $inboxPath
    );

$inboxMarker =
    'USER EMAIL V1.2 COMPOSE SENT BUTTONS';

if (
    str_contains(
        $inbox,
        $inboxMarker
    )
) {
    echo "[SKIP] Inbox Compose/Sent buttons already added.\n";
} else {
    $anchor =
        '<a'
        ."\n"
        .'                    href="{{ route(\'admin.my-email.settings\') }}"';

    $pos =
        strpos(
            $inbox,
            $anchor
        );

    if ($pos === false) {
        fwrite(
            STDERR,
            "Inbox Email Settings button anchor tidak ditemukan. Patch dihentikan agar tidak merusak Blade.\n"
        );
        exit(5);
    }

    $buttons = <<<'BLADE'
                <!-- USER EMAIL V1.2 COMPOSE SENT BUTTONS -->
                <a
                    href="{{ route('admin.my-email.compose') }}"
                    class="primary-button"
                >
                    + Compose
                </a>

                <a
                    href="{{ route('admin.my-email.sent') }}"
                    class="secondary-button"
                >
                    Sent
                </a>

BLADE;

    backupOnce(
        $inboxPath,
        '.before-user-email-v1-2.bak'
    );

    $inbox =
        substr_replace(
            $inbox,
            $buttons,
            $pos,
            0
        );

    file_put_contents(
        $inboxPath,
        $inbox
    );

    echo "[PASS] Compose + Sent buttons added to My Inbox.\n";
}

/*
|--------------------------------------------------------------------------
| 3. Patch received message detail with Reply buttons
|--------------------------------------------------------------------------
*/

$messagePath =
    $projectRoot
    .'/packages/Webkul/Admin/src/Resources/views/user-email/message.blade.php';

if (! is_file($messagePath)) {
    fwrite(
        STDERR,
        "Received email message Blade tidak ditemukan.\n"
    );
    exit(6);
}

$message =
    file_get_contents(
        $messagePath
    );

$messageMarker =
    'USER EMAIL V1.2 REPLY BUTTONS';

if (
    str_contains(
        $message,
        $messageMarker
    )
) {
    echo "[SKIP] Reply buttons already added.\n";
} else {
    $backAnchor =
        '<a'
        ."\n"
        .'                href="{{ route(\'admin.my-email.inbox\') }}"';

    $pos =
        strpos(
            $message,
            $backAnchor
        );

    if ($pos === false) {
        fwrite(
            STDERR,
            "Received email Back button anchor tidak ditemukan. Patch dihentikan agar tidak merusak Blade.\n"
        );
        exit(7);
    }

    $buttons = <<<'BLADE'
            <!-- USER EMAIL V1.2 REPLY BUTTONS -->
            <div class="flex gap-2">
                <a
                    href="{{ route('admin.my-email.compose', ['reply_to' => $message->id, 'mode' => 'reply']) }}"
                    class="primary-button"
                >
                    Reply
                </a>

                <a
                    href="{{ route('admin.my-email.compose', ['reply_to' => $message->id, 'mode' => 'reply_all']) }}"
                    class="secondary-button"
                >
                    Reply All
                </a>
            </div>

BLADE;

    backupOnce(
        $messagePath,
        '.before-user-email-v1-2.bak'
    );

    $message =
        substr_replace(
            $message,
            $buttons,
            $pos,
            0
        );

    file_put_contents(
        $messagePath,
        $message
    );

    echo "[PASS] Reply + Reply All buttons added to received email detail.\n";
}

echo "\n";
echo "PER-USER EMAIL V1.2 installer selesai.\n";
echo "Next: php artisan migrate\n";
