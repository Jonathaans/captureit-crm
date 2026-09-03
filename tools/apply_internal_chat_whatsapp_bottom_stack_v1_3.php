<?php

declare(strict_types=1);

/**
 * INTERNAL CHAT WHATSAPP BOTTOM STACK V1.3
 *
 * Tujuan:
 * - Jika histori chat pendek: pesan menempel ke BAWAH, dekat composer.
 * - Jika histori chat panjang: room langsung membuka posisi PALING BAWAH.
 * - Pesan baru tetap masuk ke stack yang sama.
 *
 * Root cause UI:
 * #crm-chat-messages adalah flex-column, tetapi message children dimulai dari
 * atas. Ketika total message belum melebihi tinggi viewport, scrollTop tidak
 * punya efek karena memang tidak ada area untuk di-scroll.
 *
 * Solusi WhatsApp-style:
 * #crm-chat-messages
 *   -> #crm-chat-message-stack (margin-top:auto)
 *      -> Direct Conversation
 *      -> messages...
 *      -> #crm-chat-bottom
 *
 * margin-top:auto:
 * - short history => seluruh stack terdorong ke bawah.
 * - long history  => auto margin menjadi 0 dan overflow normal.
 *
 * Hanya mengubah chat.blade.php.
 */

$root = dirname(__DIR__);

$chatPath =
    $root . '/packages/Webkul/Admin/src/Resources/views/internal-communication/chat.blade.php';

function fail(string $message, int $code = 1): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit($code);
}

function atomicWrite(string $path, string $contents): void
{
    $tmp = $path . '.tmp-' . bin2hex(random_bytes(4));

    if (file_put_contents($tmp, $contents) === false) {
        @unlink($tmp);
        throw new RuntimeException("Gagal menulis temporary file: {$tmp}");
    }

    if (!rename($tmp, $path)) {
        @unlink($tmp);
        throw new RuntimeException("Gagal mengganti file: {$path}");
    }
}

function restore(string $backup, string $target): void
{
    if (is_file($backup)) {
        copy($backup, $target);
    }
}

echo "INTERNAL CHAT WHATSAPP BOTTOM STACK V1.3\n";
echo "========================================\n\n";

if (!is_file($chatPath)) {
    fail("chat.blade.php tidak ditemukan: {$chatPath}");
}

$original = file_get_contents($chatPath);

if ($original === false) {
    fail('Gagal membaca chat.blade.php.');
}

if (!str_contains($original, 'id="crm-chat-messages"')) {
    fail('Preflight gagal: #crm-chat-messages tidak ditemukan.');
}

if (
    str_contains(
        $original,
        'INTERNAL CHAT WHATSAPP BOTTOM STACK V1.3'
    )
) {
    echo "V1.3 sudah terpasang. Tidak ada perubahan.\n";
    exit(0);
}

$stamp = date('Ymd-His');

$backup =
    $chatPath . '.bak-internal-chat-whatsapp-bottom-stack-v1_3-' . $stamp;

if (!copy($chatPath, $backup)) {
    fail("Gagal membuat backup: {$backup}");
}

echo "Backup:\n{$backup}\n\n";

try {
    $chat = $original;

    /*
    |--------------------------------------------------------------------------
    | 1. Isolate only the messages HTML block
    |--------------------------------------------------------------------------
    */

    $messagesIdPos =
        strpos(
            $chat,
            'id="crm-chat-messages"'
        );

    if ($messagesIdPos === false) {
        throw new RuntimeException(
            '#crm-chat-messages tidak ditemukan.'
        );
    }

    $composerPos =
        strpos(
            $chat,
            'id="crm-chat-send-form"',
            $messagesIdPos
        );

    if ($composerPos === false) {
        throw new RuntimeException(
            '#crm-chat-send-form tidak ditemukan setelah message container.'
        );
    }

    $htmlBeforeMessages =
        substr(
            $chat,
            0,
            $messagesIdPos
        );

    $messagesToComposer =
        substr(
            $chat,
            $messagesIdPos,
            $composerPos - $messagesIdPos
        );

    $htmlAfterComposer =
        substr(
            $chat,
            $composerPos
        );

    /*
     * Insert message stack immediately before the Direct Conversation block.
     */
    $directAnchor =
        '<div class="mb-5 text-center">';

    if (
        substr_count(
            $messagesToComposer,
            $directAnchor
        ) !== 1
    ) {
        throw new RuntimeException(
            'Direct Conversation anchor tidak ditemukan tepat 1 kali di message block.'
        );
    }

    $stackOpen =
        <<<'BLADE'
<div
                            id="crm-chat-message-stack"
                            class="mt-auto flex w-full flex-col"
                        >

BLADE;

    $messagesToComposer =
        str_replace(
            $directAnchor,
            $stackOpen . $directAnchor,
            $messagesToComposer
        );

    /*
     * Close the stack immediately before the closing div of crm-chat-messages.
     *
     * The outer @endforeach is followed by the message-root closing </div>
     * and then the composer container. We only search within the isolated
     * message-to-composer block.
     */
    $tailPattern =
        <<<'BLADE'
                        @endforeach
                    </div>

                    <div class="border-t bg-white p-4">
BLADE;

    $tailReplacement =
        <<<'BLADE'
                        @endforeach

                            <div
                                id="crm-chat-bottom"
                                aria-hidden="true"
                                style="height:1px; width:100%; flex:0 0 auto;"
                            ></div>
                        </div>
                    </div>

                    <div class="border-t bg-white p-4">
BLADE;

    if (
        substr_count(
            $messagesToComposer,
            $tailPattern
        ) !== 1
    ) {
        throw new RuntimeException(
            'Outer message @endforeach / composer anchor tidak ditemukan tepat 1 kali.'
        );
    }

    $messagesToComposer =
        str_replace(
            $tailPattern,
            $tailReplacement,
            $messagesToComposer
        );

    $chat =
        $htmlBeforeMessages
        . $messagesToComposer
        . $htmlAfterComposer;

    /*
    |--------------------------------------------------------------------------
    | 2. Dynamic messages must append inside the stack, not root
    |--------------------------------------------------------------------------
    */

    $messagesRootDeclaration =
        <<<'JS'
                const messagesRoot =
                    document.getElementById(
                        'crm-chat-messages'
                    );
JS;

    $messageStackDeclaration =
        <<<'JS'
                const messagesRoot =
                    document.getElementById(
                        'crm-chat-messages'
                    );

                /* INTERNAL CHAT WHATSAPP BOTTOM STACK V1.3 */
                const messageStack =
                    document.getElementById(
                        'crm-chat-message-stack'
                    )
                    || messagesRoot;
JS;

    if (
        substr_count(
            $chat,
            $messagesRootDeclaration
        ) < 1
    ) {
        throw new RuntimeException(
            'messagesRoot JS declaration tidak ditemukan.'
        );
    }

    /*
     * Only replace the FIRST declaration in the main conversation script.
     * Additional patch scripts may also declare messagesRoot.
     */
    $chat =
        preg_replace(
            '~'
            . preg_quote(
                $messagesRootDeclaration,
                '~'
            )
            . '~',
            str_replace(
                '$',
                '\\$',
                $messageStackDeclaration
            ),
            $chat,
            1,
            $declarationCount
        );

    if (!is_string($chat) || $declarationCount !== 1) {
        throw new RuntimeException(
            'Gagal memasang messageStack declaration.'
        );
    }

    /*
     * New-message append should target the bottom stack.
     */
    $oldAppend =
        <<<'JS'
                    messagesRoot.appendChild(
                        wrapper
                    );
JS;

    $newAppend =
        <<<'JS'
                    messageStack.appendChild(
                        wrapper
                    );
JS;

    $appendCount =
        substr_count(
            $chat,
            $oldAppend
        );

    if ($appendCount < 1) {
        throw new RuntimeException(
            'messagesRoot.appendChild(wrapper) tidak ditemukan.'
        );
    }

    $chat =
        str_replace(
            $oldAppend,
            $newAppend,
            $chat
        );

    /*
    |--------------------------------------------------------------------------
    | 3. Authoritative initial pin-to-bottom
    |--------------------------------------------------------------------------
    */

    $closingLayout =
        '</x-admin::layouts>';

    if (
        substr_count(
            $chat,
            $closingLayout
        ) !== 1
    ) {
        throw new RuntimeException(
            'Closing x-admin::layouts tidak ditemukan tepat 1 kali.'
        );
    }

    $initialScript =
        <<<'BLADE'

    {{-- INTERNAL CHAT WHATSAPP INITIAL BOTTOM V1.3 --}}
    @if ($conversation)
        <script>
            (() => {
                const bootWhatsAppBottomV13 = () => {
                    const root =
                        document.getElementById(
                            'crm-chat-messages'
                        );

                    const bottom =
                        document.getElementById(
                            'crm-chat-bottom'
                        );

                    if (
                        !root
                        || !bottom
                    ) {
                        return;
                    }

                    /*
                     * Browser may restore a previous scroll position during
                     * navigation. We explicitly own the initial room position.
                     */
                    if (
                        'scrollRestoration'
                        in history
                    ) {
                        history.scrollRestoration =
                            'manual';
                    }

                    const pinToNewest = () => {
                        /*
                         * Long history: scroll the dedicated message viewport.
                         */
                        root.scrollTop =
                            root.scrollHeight;

                        /*
                         * Native anchor/focus-style fallback also scrolls any
                         * nested scrollable ancestors to reveal the sentinel.
                         */
                        bottom.scrollIntoView({
                            behavior:
                                'auto',

                            block:
                                'end',

                            inline:
                                'nearest',
                        });

                        root.scrollTop =
                            root.scrollHeight;
                    };

                    window.requestAnimationFrame(
                        () => {
                            window.requestAnimationFrame(
                                pinToNewest
                            );
                        }
                    );

                    [
                        0,
                        50,
                        150,
                        350,
                        700,
                    ].forEach(
                        (delay) => {
                            window.setTimeout(
                                pinToNewest,
                                delay
                            );
                        }
                    );

                    window.addEventListener(
                        'pageshow',
                        pinToNewest,
                        {
                            once:
                                true,
                        }
                    );

                    window.addEventListener(
                        'load',
                        pinToNewest,
                        {
                            once:
                                true,
                        }
                    );
                };

                if (
                    document.readyState
                    === 'loading'
                ) {
                    document.addEventListener(
                        'DOMContentLoaded',
                        bootWhatsAppBottomV13,
                        {
                            once:
                                true,
                        }
                    );
                } else {
                    bootWhatsAppBottomV13();
                }
            })();
        </script>
    @endif

BLADE;

    $chat =
        str_replace(
            $closingLayout,
            $initialScript
            . $closingLayout,
            $chat
        );

    /*
    |--------------------------------------------------------------------------
    | WRITE + VERIFY
    |--------------------------------------------------------------------------
    */

    atomicWrite(
        $chatPath,
        $chat
    );

    $check =
        file_get_contents(
            $chatPath
        );

    if ($check === false) {
        throw new RuntimeException(
            'Post-write gagal membaca chat.blade.php.'
        );
    }

    $required = [
        'INTERNAL CHAT WHATSAPP BOTTOM STACK V1.3',
        'id="crm-chat-message-stack"',
        'class="mt-auto flex w-full flex-col"',
        'id="crm-chat-bottom"',
        'const messageStack',
        'messageStack.appendChild(',
        'INTERNAL CHAT WHATSAPP INITIAL BOTTOM V1.3',
        'history.scrollRestoration',
        'bottom.scrollIntoView',
        'root.scrollTop',
    ];

    foreach ($required as $needle) {
        if (!str_contains($check, $needle)) {
            throw new RuntimeException(
                "Post-write validation gagal: {$needle}"
            );
        }
    }

    /*
     * Sanity: ensure original root remains and only one bottom stack/sentinel.
     */
    if (
        substr_count(
            $check,
            'id="crm-chat-message-stack"'
        ) !== 1
        || substr_count(
            $check,
            'id="crm-chat-bottom"'
        ) !== 1
    ) {
        throw new RuntimeException(
            'Duplicate message stack / bottom sentinel terdeteksi.'
        );
    }

    echo "Patch PASS.\n";
    echo "- Short chat: message stack sekarang menempel ke bawah.\n";
    echo "- Long chat: initial room langsung scroll ke newest.\n";
    echo "- New incoming/sent message masuk ke stack yang sama.\n";
    echo "- Composer tetap di bawah, tidak ikut scroll.\n";
    echo "- Controller/database/menu/ACL tidak diubah.\n\n";

    chdir($root);

    echo "Membersihkan Laravel cache...\n";

    passthru(
        escapeshellarg(PHP_BINARY)
        . ' '
        . escapeshellarg(
            $root . '/artisan'
        )
        . ' optimize:clear',
        $clearCode
    );

    if ($clearCode !== 0) {
        echo
            "\nPERINGATAN: optimize:clear exit code {$clearCode}.\n";
    }

    echo "\nSELESAI.\n";
    echo "Jalankan checker:\n";
    echo "php tools/check_internal_chat_whatsapp_bottom_stack_v1_3.php\n";
} catch (Throwable $e) {
    restore(
        $backup,
        $chatPath
    );

    fwrite(
        STDERR,
        "\nPATCH GAGAL: "
        . $e->getMessage()
        . "\n"
    );

    fwrite(
        STDERR,
        "chat.blade.php dipulihkan dari backup otomatis.\n"
    );

    exit(1);
}
