<?php

/*
|--------------------------------------------------------------------------
| Per-User Email V1.3
|--------------------------------------------------------------------------
|
| Adds:
| Inbox / Draft / Outbox / Sent / Trash workflow
| Outgoing attachments
| Incoming attachment extraction
| Delivery failure details + retry
|
| Existing business modules remain untouched.
|
*/

$projectRoot = realpath(__DIR__.'/..');

if (! $projectRoot) {
    fwrite(STDERR, "Project root tidak ditemukan.\n");
    exit(1);
}

function backupOnce(string $path): void
{
    $backup =
        $path
        .'.before-user-email-v1-3.bak';

    if (
        is_file($path)
        && ! is_file($backup)
    ) {
        copy($path, $backup);
    }
}

function methodBounds(
    string $source,
    string $methodName
): ?array {
    if (
        ! preg_match(
            '/(?:public|protected|private)\s+function\s+'
            .preg_quote($methodName, '/')
            .'\s*\([^)]*\)\s*(?::\s*[^{]+)?\{/m',
            $source,
            $match,
            PREG_OFFSET_CAPTURE
        )
    ) {
        return null;
    }

    $start = $match[0][1];
    $brace = strpos($source, '{', $start);

    if ($brace === false) {
        return null;
    }

    $depth = 0;
    $length = strlen($source);

    for ($index = $brace; $index < $length; $index++) {
        $char = $source[$index];

        if ($char === '{') {
            $depth++;
        } elseif ($char === '}') {
            $depth--;

            if ($depth === 0) {
                return [$start, $index + 1];
            }
        }
    }

    return null;
}

/*
|--------------------------------------------------------------------------
| 1. Provider
|--------------------------------------------------------------------------
*/

$providerPath =
    $projectRoot
    .'/bootstrap/providers.php';

$providerSource =
    file_get_contents($providerPath);

$provider =
    '\\Webkul\\Admin\\Providers\\UserEmailMailboxServiceProvider::class';

if (! str_contains($providerSource, $provider)) {
    $end = strrpos($providerSource, '];');

    if ($end === false) {
        fwrite(STDERR, "providers.php format tidak dikenali.\n");
        exit(2);
    }

    backupOnce($providerPath);

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

    echo "[PASS] UserEmailMailboxServiceProvider registered.\n";
} else {
    echo "[SKIP] Mailbox provider already registered.\n";
}

/*
|--------------------------------------------------------------------------
| 2. UserEmailMessage casts
|--------------------------------------------------------------------------
*/

$modelPath =
    $projectRoot
    .'/packages/Webkul/Admin/src/Models/UserEmailMessage.php';

if (! is_file($modelPath)) {
    fwrite(STDERR, "UserEmailMessage.php tidak ditemukan.\n");
    exit(3);
}

$model =
    file_get_contents($modelPath);

if (! str_contains($model, "'failed_at' => 'datetime'")) {
    $anchor =
        "'sent_at' => 'datetime',";

    $pos =
        strpos(
            $model,
            $anchor
        );

    if ($pos === false) {
        fwrite(
            STDERR,
            "sent_at datetime cast anchor tidak ditemukan. "
            ."Pastikan V1.2.1 sudah terpasang.\n"
        );
        exit(4);
    }

    backupOnce($modelPath);

    $insertAt =
        $pos
        + strlen($anchor);

    $model =
        substr_replace(
            $model,
            "\n        'failed_at' => 'datetime',",
            $insertAt,
            0
        );

    file_put_contents(
        $modelPath,
        $model
    );

    echo "[PASS] failed_at datetime cast added.\n";
}

/*
|--------------------------------------------------------------------------
| 3. Patch MyEmailComposeController::send()
|--------------------------------------------------------------------------
*/

$composeControllerPath =
    $projectRoot
    .'/packages/Webkul/Admin/src/Http/Controllers/UserEmail/MyEmailComposeController.php';

if (! is_file($composeControllerPath)) {
    fwrite(STDERR, "MyEmailComposeController.php tidak ditemukan.\n");
    exit(5);
}

$composeController =
    file_get_contents(
        $composeControllerPath
    );

$sendMarker =
    'USER EMAIL V1.3 DELIVERY SERVICE';

if (! str_contains($composeController, $sendMarker)) {
    $bounds =
        methodBounds(
            $composeController,
            'send'
        );

    if (! $bounds) {
        fwrite(STDERR, "send() method tidak ditemukan.\n");
        exit(6);
    }

    [$start, $end] = $bounds;

    $replacement = <<<'PHP'
public function send(
        Request $request,
        \Webkul\Admin\Services\UserEmailDeliveryService $delivery
    ): RedirectResponse {
        /* USER EMAIL V1.3 DELIVERY SERVICE */

        $user =
            $this->user();

        $account =
            $this->account(
                $user->id
            );

        $validated =
            $request->validate([
                'to' => [
                    'required',
                    'string',
                    'max:5000',
                ],
                'cc' => [
                    'nullable',
                    'string',
                    'max:5000',
                ],
                'subject' => [
                    'required',
                    'string',
                    'max:998',
                ],
                'message' => [
                    'required',
                    'string',
                    'max:200000',
                ],
                'reply_to_message_id' => [
                    'nullable',
                    'integer',
                ],
                'draft_message_id' => [
                    'nullable',
                    'integer',
                ],
                'attachments' => [
                    'nullable',
                    'array',
                    'max:5',
                ],
                'attachments.*' => [
                    'file',
                    'max:10240',
                ],
            ]);

        try {
            $replyTo = null;

            if (
                ! empty(
                    $validated['reply_to_message_id']
                )
            ) {
                $replyTo =
                    UserEmailMessage::query()
                        ->where(
                            'user_id',
                            $user->id
                        )
                        ->findOrFail(
                            (int) $validated[
                                'reply_to_message_id'
                            ]
                        );
            }

            $draft = null;

            if (
                ! empty(
                    $validated['draft_message_id']
                )
            ) {
                $draft =
                    UserEmailMessage::query()
                        ->where(
                            'user_id',
                            $user->id
                        )
                        ->where(
                            'folder',
                            'DRAFT'
                        )
                        ->findOrFail(
                            (int) $validated[
                                'draft_message_id'
                            ]
                        );
            }

            $sent =
                $delivery->send(
                    $account,
                    $validated['to'],
                    $validated['cc']
                        ?? '',
                    trim(
                        $validated['subject']
                    ),
                    $validated['message'],
                    $request->file(
                        'attachments',
                        []
                    ),
                    $draft,
                    $replyTo
                );

            session()->flash(
                'success',
                'Email berhasil dikirim dari '
                .$account->email_address
                .'.'
            );

            return redirect()->route(
                'admin.my-email.sent.show',
                $sent->id
            );
        } catch (Throwable $exception) {
            session()->flash(
                'warning',
                $exception->getMessage()
            );

            return redirect()->route(
                'admin.my-email.outbox'
            );
        }
    }
PHP;

    backupOnce(
        $composeControllerPath
    );

    $composeController =
        substr_replace(
            $composeController,
            $replacement,
            $start,
            $end - $start
        );

    file_put_contents(
        $composeControllerPath,
        $composeController
    );

    echo "[PASS] Compose send() upgraded to Draft/Outbox/Attachment delivery.\n";
} else {
    echo "[SKIP] Compose controller V1.3 delivery already applied.\n";
}

/*
|--------------------------------------------------------------------------
| 4. Patch Compose Blade
|--------------------------------------------------------------------------
*/

$composeViewPath =
    $projectRoot
    .'/packages/Webkul/Admin/src/Resources/views/user-email/compose.blade.php';

if (! is_file($composeViewPath)) {
    fwrite(STDERR, "compose.blade.php tidak ditemukan.\n");
    exit(7);
}

$composeView =
    file_get_contents(
        $composeViewPath
    );

$composeMarker =
    'USER EMAIL V1.3 ATTACHMENTS DRAFT';

if (! str_contains($composeView, $composeMarker)) {
    $formAnchor =
        'method="POST"'
        ."\n"
        .'            action="{{ route(\'admin.my-email.send\') }}"';

    $formPos =
        strpos(
            $composeView,
            $formAnchor
        );

    if ($formPos === false) {
        fwrite(
            STDERR,
            "Compose form anchor tidak ditemukan.\n"
        );
        exit(8);
    }

    $formTagStart =
        strrpos(
            substr(
                $composeView,
                0,
                $formPos
            ),
            '<form'
        );

    $formTagEnd =
        strpos(
            $composeView,
            '>',
            $formPos
        );

    if (
        $formTagStart === false
        || $formTagEnd === false
    ) {
        fwrite(STDERR, "Compose form tag tidak dikenali.\n");
        exit(9);
    }

    $formTag =
        substr(
            $composeView,
            $formTagStart,
            $formTagEnd - $formTagStart + 1
        );

    if (! str_contains($formTag, 'enctype=')) {
        $newFormTag =
            substr(
                $formTag,
                0,
                -1
            )
            ."\n            enctype=\"multipart/form-data\"\n        >";

        $composeView =
            substr_replace(
                $composeView,
                $newFormTag,
                $formTagStart,
                strlen($formTag)
            );
    }

    $messageBlockEndAnchor =
        "                </div>\n            </div>\n\n            <div class=\"mt-5 flex justify-end\">";

    $pos =
        strpos(
            $composeView,
            $messageBlockEndAnchor
        );

    if ($pos === false) {
        fwrite(
            STDERR,
            "Compose action anchor tidak ditemukan.\n"
        );
        exit(10);
    }

    $replacement = <<<'BLADE'
                </div>

                <!-- USER EMAIL V1.3 ATTACHMENTS DRAFT -->
                <div>
                    <label class="mb-1.5 block text-sm font-semibold">
                        Attachments
                    </label>

                    <input
                        type="file"
                        name="attachments[]"
                        multiple
                        class="w-full rounded-md border px-3 py-2"
                    >

                    <div class="mt-1 text-xs text-gray-500">
                        Maximum 5 files, 10 MB each.
                    </div>

                    @if (($draftMessage ?? null))
                        <input
                            type="hidden"
                            name="draft_message_id"
                            value="{{ $draftMessage->id }}"
                        >
                    @endif
                </div>
            </div>

            <div class="mt-5 flex flex-wrap justify-end gap-2">
                <button
                    type="submit"
                    formaction="{{ route('admin.my-email.drafts.save') }}"
                    class="secondary-button"
                >
                    Save Draft
                </button>
BLADE;

    backupOnce(
        $composeViewPath
    );

    $composeView =
        substr_replace(
            $composeView,
            $replacement,
            $pos,
            strlen(
                "                </div>\n            </div>\n\n            <div class=\"mt-5 flex justify-end\">"
            )
        );

    file_put_contents(
        $composeViewPath,
        $composeView
    );

    echo "[PASS] Compose Attachment + Save Draft added.\n";
} else {
    echo "[SKIP] Compose Attachment/Draft already added.\n";
}

/*
|--------------------------------------------------------------------------
| 5. Patch Inbox folder navigation
|--------------------------------------------------------------------------
*/

$inboxPath =
    $projectRoot
    .'/packages/Webkul/Admin/src/Resources/views/user-email/inbox.blade.php';

$inbox =
    file_get_contents(
        $inboxPath
    );

$inboxMarker =
    'USER EMAIL V1.3 FOLDER NAV';

if (! str_contains($inbox, $inboxMarker)) {
    $composeAnchor =
        '<!-- USER EMAIL V1.2 COMPOSE SENT BUTTONS -->';

    $pos =
        strpos(
            $inbox,
            $composeAnchor
        );

    if ($pos === false) {
        fwrite(
            STDERR,
            "Inbox V1.2 Compose marker tidak ditemukan.\n"
        );
        exit(11);
    }

    $nav = <<<'BLADE'
                <!-- USER EMAIL V1.3 FOLDER NAV -->
                <a href="{{ route('admin.my-email.drafts') }}" class="secondary-button">Draft</a>
                <a href="{{ route('admin.my-email.outbox') }}" class="secondary-button">Outbox</a>
                <a href="{{ route('admin.my-email.trash') }}" class="secondary-button">Trash</a>

BLADE;

    backupOnce(
        $inboxPath
    );

    $inbox =
        substr_replace(
            $inbox,
            $nav,
            $pos,
            0
        );

    file_put_contents(
        $inboxPath,
        $inbox
    );

    echo "[PASS] Inbox Draft/Outbox/Trash navigation added.\n";
}

/*
|--------------------------------------------------------------------------
| 6. Patch received message attachment + Trash section
|--------------------------------------------------------------------------
*/

$messageViewPath =
    $projectRoot
    .'/packages/Webkul/Admin/src/Resources/views/user-email/message.blade.php';

$messageView =
    file_get_contents(
        $messageViewPath
    );

$messageMarker =
    'USER EMAIL V1.3 ATTACHMENT LIST';

if (! str_contains($messageView, $messageMarker)) {
    $closing =
        strrpos(
            $messageView,
            '</div>'
        );

    if ($closing === false) {
        fwrite(STDERR, "Message view closing div tidak ditemukan.\n");
        exit(12);
    }

    $section = <<<'BLADE'

        <!-- USER EMAIL V1.3 ATTACHMENT LIST -->
        @php
            $attachments = \Webkul\Admin\Models\UserEmailAttachment::query()
                ->where('user_id', auth()->guard('user')->id())
                ->where('message_id', $message->id)
                ->get();
        @endphp

        @if ($attachments->isNotEmpty())
            <div class="rounded-xl border bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <h2 class="font-bold">
                    Attachments ({{ $attachments->count() }})
                </h2>

                <div class="mt-3 flex flex-wrap gap-2">
                    @foreach ($attachments as $attachment)
                        <a
                            href="{{ route('admin.my-email.attachments.download', $attachment->id) }}"
                            class="secondary-button"
                        >
                            {{ $attachment->original_name }}
                            ({{ number_format($attachment->size / 1024, 1) }} KB)
                        </a>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="flex justify-end">
            <form
                method="POST"
                action="{{ route('admin.my-email.trash.move', $message->id) }}"
            >
                @csrf
                <button class="secondary-button">
                    Move to Trash
                </button>
            </form>
        </div>

BLADE;

    backupOnce(
        $messageViewPath
    );

    $messageView =
        substr_replace(
            $messageView,
            $section,
            $closing,
            0
        );

    file_put_contents(
        $messageViewPath,
        $messageView
    );

    echo "[PASS] Received email Attachment + Trash controls added.\n";
}

/*
|--------------------------------------------------------------------------
| 7. Patch sent detail attachment + Trash section
|--------------------------------------------------------------------------
*/

$sentViewPath =
    $projectRoot
    .'/packages/Webkul/Admin/src/Resources/views/user-email/sent-message.blade.php';

$sentView =
    file_get_contents(
        $sentViewPath
    );

$sentMarker =
    'USER EMAIL V1.3 SENT ATTACHMENT LIST';

if (! str_contains($sentView, $sentMarker)) {
    $closing =
        strrpos(
            $sentView,
            '</div>'
        );

    if ($closing === false) {
        fwrite(STDERR, "Sent view closing div tidak ditemukan.\n");
        exit(13);
    }

    $section = <<<'BLADE'

        <!-- USER EMAIL V1.3 SENT ATTACHMENT LIST -->
        @php
            $attachments = \Webkul\Admin\Models\UserEmailAttachment::query()
                ->where('user_id', auth()->guard('user')->id())
                ->where('message_id', $message->id)
                ->get();
        @endphp

        @if ($attachments->isNotEmpty())
            <div class="rounded-xl border bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <h2 class="font-bold">
                    Attachments ({{ $attachments->count() }})
                </h2>

                <div class="mt-3 flex flex-wrap gap-2">
                    @foreach ($attachments as $attachment)
                        <a
                            href="{{ route('admin.my-email.attachments.download', $attachment->id) }}"
                            class="secondary-button"
                        >
                            {{ $attachment->original_name }}
                            ({{ number_format($attachment->size / 1024, 1) }} KB)
                        </a>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="flex justify-end">
            <form
                method="POST"
                action="{{ route('admin.my-email.trash.move', $message->id) }}"
            >
                @csrf
                <button class="secondary-button">
                    Move to Trash
                </button>
            </form>
        </div>

BLADE;

    backupOnce(
        $sentViewPath
    );

    $sentView =
        substr_replace(
            $sentView,
            $section,
            $closing,
            0
        );

    file_put_contents(
        $sentViewPath,
        $sentView
    );

    echo "[PASS] Sent email Attachment + Trash controls added.\n";
}

/*
|--------------------------------------------------------------------------
| 8. Patch UserEmailSyncService for incoming attachments
|--------------------------------------------------------------------------
*/

$syncPath =
    $projectRoot
    .'/packages/Webkul/Admin/src/Services/UserEmailSyncService.php';

$sync =
    file_get_contents(
        $syncPath
    );

$syncMarker =
    'USER EMAIL V1.3 INCOMING ATTACHMENTS';

if (! str_contains($sync, $syncMarker)) {
    $oldConstructor = <<<'PHP'
    public function __construct(
        protected PurePhpImapClient $imap,
        protected Rfc822EmailParser $parser
    ) {
    }
PHP;

    $newConstructor = <<<'PHP'
    public function __construct(
        protected PurePhpImapClient $imap,
        protected Rfc822EmailParser $parser,
        protected Rfc822AttachmentExtractor $attachmentExtractor,
        protected UserEmailAttachmentService $attachmentService
    ) {
    }
PHP;

    if (
        substr_count(
            $sync,
            $oldConstructor
        ) !== 1
    ) {
        fwrite(
            STDERR,
            "UserEmailSyncService constructor berbeda dari V1.1. "
            ."Patch dihentikan tanpa menulis file.\n"
        );
        exit(14);
    }

    $sync =
        str_replace(
            $oldConstructor,
            $newConstructor,
            $sync
        );

    $oldMessageStart = <<<'PHP'
                UserEmailMessage::query()
                    ->updateOrCreate(
PHP;

    if (
        substr_count(
            $sync,
            $oldMessageStart
        ) !== 1
    ) {
        fwrite(
            STDERR,
            "UserEmailSyncService updateOrCreate anchor tidak unik.\n"
        );
        exit(15);
    }

    $sync =
        str_replace(
            $oldMessageStart,
            "                /* {$syncMarker} */\n"
            ."                \$storedMessage =\n"
            ."                    UserEmailMessage::query()\n"
            ."                        ->updateOrCreate(\n",
            $sync
        );

    $accountUidAnchor = <<<'PHP'
                $account->imap_last_uid =
PHP;

    $uidPos =
        strpos(
            $sync,
            $accountUidAnchor
        );

    if ($uidPos === false) {
        fwrite(
            STDERR,
            "UserEmailSyncService UID anchor tidak ditemukan.\n"
        );
        exit(16);
    }

    $attachmentBlock = <<<'PHP'

                $incomingAttachments =
                    $this->attachmentExtractor
                        ->extract(
                            $raw
                        );

                if ($incomingAttachments) {
                    $this->attachmentService
                        ->storeIncomingExtracted(
                            $storedMessage,
                            $incomingAttachments
                        );
                }

PHP;

    $sync =
        substr_replace(
            $sync,
            $attachmentBlock,
            $uidPos,
            0
        );

    backupOnce(
        $syncPath
    );

    file_put_contents(
        $syncPath,
        $sync
    );

    echo "[PASS] Incoming attachment extraction added to IMAP sync.\n";
}

echo "\n";
echo "PER-USER EMAIL V1.3 installer selesai.\n";
echo "Next: php artisan migrate\n";
