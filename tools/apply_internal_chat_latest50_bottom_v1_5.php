<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$controllerPath =
    $root . '/packages/Webkul/Admin/src/Http/Controllers/InternalCommunication/InternalChatController.php';

$chatPath =
    $root . '/packages/Webkul/Admin/src/Resources/views/internal-communication/chat.blade.php';

function fail(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

function restore(string $backup, string $target): void
{
    if (is_file($backup)) {
        copy($backup, $target);
    }
}

function writeAtomic(string $path, string $contents): void
{
    $tmp = $path . '.tmp-' . bin2hex(random_bytes(4));

    if (file_put_contents($tmp, $contents) === false) {
        @unlink($tmp);
        throw new RuntimeException("Gagal menulis temp file: {$tmp}");
    }

    if (!rename($tmp, $path)) {
        @unlink($tmp);
        throw new RuntimeException("Gagal mengganti file: {$path}");
    }
}

function removeStandaloneScript(string $source, string $marker): string
{
    $markerPos = strpos($source, $marker);

    if ($markerPos === false) {
        return $source;
    }

    $scriptOpen =
        strrpos(
            substr($source, 0, $markerPos),
            '<script>'
        );

    $scriptClose =
        strpos(
            $source,
            '</script>',
            $markerPos
        );

    if (
        $scriptOpen === false
        || $scriptClose === false
    ) {
        return $source;
    }

    $start = $scriptOpen;
    $end = $scriptClose + strlen('</script>');

    $ifPos =
        strrpos(
            substr($source, 0, $scriptOpen),
            '@if ($conversation)'
        );

    if (
        $ifPos !== false
        && ($scriptOpen - $ifPos) < 180
    ) {
        $endifPos =
            strpos(
                $source,
                '@endif',
                $scriptClose
            );

        if (
            $endifPos !== false
            && ($endifPos - $scriptClose) < 180
        ) {
            $start = $ifPos;
            $end = $endifPos + strlen('@endif');
        }
    }

    return
        substr($source, 0, $start)
        . substr($source, $end);
}

echo "INTERNAL CHAT LATEST 50 + BOTTOM V1.5\n";
echo "=====================================\n\n";

foreach ([$controllerPath, $chatPath] as $path) {
    if (!is_file($path)) {
        fail("File tidak ditemukan: {$path}");
    }
}

$controllerOriginal = file_get_contents($controllerPath);
$chatOriginal = file_get_contents($chatPath);

if ($controllerOriginal === false || $chatOriginal === false) {
    fail('Gagal membaca source.');
}

foreach (
    [
        'id="crm-chat-messages"',
        'id="crm-chat-message-stack"',
        'id="crm-chat-bottom"',
        'messageStack.appendChild(',
    ]
    as $needle
) {
    if (!str_contains($chatOriginal, $needle)) {
        fail("Preflight gagal: {$needle} tidak ditemukan.");
    }
}

$stamp = date('Ymd-His');

$controllerBackup =
    $controllerPath . '.bak-latest50-bottom-v1_5-' . $stamp;

$chatBackup =
    $chatPath . '.bak-latest50-bottom-v1_5-' . $stamp;

if (
    !copy($controllerPath, $controllerBackup)
    || !copy($chatPath, $chatBackup)
) {
    fail('Gagal membuat backup.');
}

echo "Backup dibuat.\n\n";

try {
    /*
     * CONTROLLER
     * Replace initial $messages query inside index() only.
     */
    $controller = $controllerOriginal;

    $indexStart = strpos($controller, 'public function index(');
    $indexEnd = strpos($controller, 'public function startDirect(');

    if (
        $indexStart === false
        || $indexEnd === false
        || $indexEnd <= $indexStart
    ) {
        throw new RuntimeException(
            'Boundary index()/startDirect() tidak ditemukan.'
        );
    }

    $before = substr($controller, 0, $indexStart);
    $indexMethod = substr(
        $controller,
        $indexStart,
        $indexEnd - $indexStart
    );
    $after = substr($controller, $indexEnd);

    $pattern =
        '~\$messages\s*=\s*InternalMessage::query\(\)[\s\S]*?'
        . '->where\(\s*[\'"]conversation_id[\'"]\s*,\s*\$conversationId\s*\)'
        . '[\s\S]*?->whereNull\(\s*[\'"]deleted_at[\'"]\s*\)'
        . '[\s\S]*?(?:->orderByDesc\(\s*[\'"]id[\'"]\s*\)|->orderBy\(\s*[\'"]id[\'"]\s*\))'
        . '[\s\S]*?->limit\(\s*\d+\s*\)'
        . '[\s\S]*?->get\(\)'
        . '(?:[\s\S]*?->sortBy\(\s*[\'"]id[\'"]\s*\)[\s\S]*?->values\(\))?'
        . '\s*;~';

    $replacement = <<<'PHP'
$messages =
                InternalMessage::query()
                    ->with(
                        'attachments'
                    )
                    ->where(
                        'conversation_id',
                        $conversationId
                    )
                    ->whereNull(
                        'deleted_at'
                    )
                    ->orderByDesc(
                        'id'
                    )
                    ->limit(
                        50
                    )
                    ->get()
                    ->sortBy(
                        'id'
                    )
                    ->values();
PHP;

    $patchedIndex =
        preg_replace(
            $pattern,
            $replacement,
            $indexMethod,
            1,
            $count
        );

    if (!is_string($patchedIndex) || $count !== 1) {
        throw new RuntimeException(
            "Initial messages query tidak ditemukan tepat 1 kali di index(). count="
            . (string) ($count ?? -1)
        );
    }

    $marker =
        <<<'PHP'
            /*
             * INTERNAL CHAT LATEST 50 V1.5
             * Initial room memuat 50 pesan terbaru saja.
             */

PHP;

    $msgPos = strpos($patchedIndex, '$messages =');

    if ($msgPos === false) {
        throw new RuntimeException('$messages result tidak ditemukan.');
    }

    $patchedIndex =
        substr($patchedIndex, 0, $msgPos)
        . $marker
        . substr($patchedIndex, $msgPos);

    $controller =
        $before
        . $patchedIndex
        . $after;

    /*
     * CHAT
     * Remove prior standalone initial-scroll scripts.
     */
    $chat = $chatOriginal;

    foreach (
        [
            'INTERNAL CHAT WHATSAPP FINAL SCROLL V1.4.1',
            'INTERNAL CHAT WHATSAPP FINAL SCROLL V1.4',
            'INTERNAL CHAT WHATSAPP INITIAL BOTTOM V1.3',
            'INTERNAL CHAT FORCE OPEN LATEST V1.2 START',
        ]
        as $legacy
    ) {
        $chat = removeStandaloneScript($chat, $legacy);
    }

    /*
     * Embedded V1.1 block cleanup.
     */
    $embedded =
        '/* INTERNAL CHAT INITIAL LATEST V1.1 */';

    $embeddedPos =
        strpos($chat, $embedded);

    if ($embeddedPos !== false) {
        $receiptPos =
            strpos(
                $chat,
                'updateReadReceipts(',
                $embeddedPos
            );

        if ($receiptPos === false) {
            throw new RuntimeException(
                'Boundary embedded V1.1 tidak ditemukan.'
            );
        }

        $chat =
            substr($chat, 0, $embeddedPos)
            . "messagesRoot.scrollTop =\n                    messagesRoot.scrollHeight;\n\n                "
            . substr($chat, $receiptPos);
    }

    $closing = '</x-admin::layouts>';

    if (substr_count($chat, $closing) !== 1) {
        throw new RuntimeException(
            'Closing layout tidak ditemukan tepat 1 kali.'
        );
    }

    $script = <<<'BLADE'

    {{-- INTERNAL CHAT LATEST50 BOTTOM V1.5 --}}
    @if ($conversation)
        <script>
            (() => {
                const bootLatest50BottomV15 = () => {
                    const root =
                        document.getElementById(
                            'crm-chat-messages'
                        );

                    const stack =
                        document.getElementById(
                            'crm-chat-message-stack'
                        );

                    const bottom =
                        document.getElementById(
                            'crm-chat-bottom'
                        );

                    if (
                        !root
                        || !stack
                        || !bottom
                        || root.dataset.latest50BottomV15
                            === '1'
                    ) {
                        return;
                    }

                    root.dataset.latest50BottomV15 =
                        '1';

                    /*
                     * WhatsApp behavior:
                     * short history menempel ke bawah;
                     * long history tetap scroll normal.
                     */
                    stack.style.minHeight =
                        '100%';

                    stack.style.marginTop =
                        '0';

                    stack.style.justifyContent =
                        'flex-end';

                    stack.style.flexShrink =
                        '0';

                    root.style.overflowAnchor =
                        'none';

                    if (
                        'scrollRestoration'
                        in history
                    ) {
                        history.scrollRestoration =
                            'manual';
                    }

                    const pinBottom = () => {
                        void stack.offsetHeight;

                        root.scrollTop =
                            root.scrollHeight;

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

                    let userScrolled =
                        false;

                    let interval =
                        null;

                    const stopPin = () => {
                        userScrolled =
                            true;

                        if (interval) {
                            clearInterval(
                                interval
                            );

                            interval =
                                null;
                        }

                        root.style.overflowAnchor =
                            '';
                    };

                    root.addEventListener(
                        'wheel',
                        stopPin,
                        {
                            once:
                                true,

                            passive:
                                true,
                        }
                    );

                    root.addEventListener(
                        'touchstart',
                        stopPin,
                        {
                            once:
                                true,

                            passive:
                                true,
                        }
                    );

                    pinBottom();

                    requestAnimationFrame(
                        () => {
                            pinBottom();

                            requestAnimationFrame(
                                pinBottom
                            );
                        }
                    );

                    const startedAt =
                        Date.now();

                    interval =
                        setInterval(
                            () => {
                                if (
                                    userScrolled
                                    || Date.now()
                                        - startedAt
                                        > 1200
                                ) {
                                    if (interval) {
                                        clearInterval(
                                            interval
                                        );

                                        interval =
                                            null;
                                    }

                                    root.style.overflowAnchor =
                                        '';

                                    return;
                                }

                                pinBottom();
                            },
                            40
                        );

                    addEventListener(
                        'load',
                        () => {
                            if (!userScrolled) {
                                pinBottom();
                            }
                        },
                        {
                            once:
                                true,
                        }
                    );

                    addEventListener(
                        'pageshow',
                        () => {
                            if (!userScrolled) {
                                pinBottom();
                            }
                        },
                        {
                            once:
                                true,
                        }
                    );

                    document.fonts
                        ?.ready
                        ?.then(
                            () => {
                                if (!userScrolled) {
                                    pinBottom();
                                }
                            }
                        );
                };

                if (
                    document.readyState
                    === 'loading'
                ) {
                    document.addEventListener(
                        'DOMContentLoaded',
                        bootLatest50BottomV15,
                        {
                            once:
                                true,
                        }
                    );
                } else {
                    bootLatest50BottomV15();
                }
            })();
        </script>
    @endif

BLADE;

    $chat =
        str_replace(
            $closing,
            $script . $closing,
            $chat
        );

    writeAtomic($controllerPath, $controller);
    writeAtomic($chatPath, $chat);

    exec(
        escapeshellarg(PHP_BINARY)
        . ' -l '
        . escapeshellarg($controllerPath)
        . ' 2>&1',
        $lintOut,
        $lintCode
    );

    if ($lintCode !== 0) {
        throw new RuntimeException(
            "Controller lint gagal:\n"
            . implode(PHP_EOL, $lintOut)
        );
    }

    $controllerCheck =
        file_get_contents($controllerPath);

    $chatCheck =
        file_get_contents($chatPath);

    if (
        $controllerCheck === false
        || $chatCheck === false
    ) {
        throw new RuntimeException(
            'Gagal membaca hasil patch.'
        );
    }

    foreach (
        [
            'INTERNAL CHAT LATEST 50 V1.5',
            '->orderByDesc(',
            '->limit(',
            '50',
            '->sortBy(',
        ]
        as $needle
    ) {
        if (!str_contains($controllerCheck, $needle)) {
            throw new RuntimeException(
                "Controller validation gagal: {$needle}"
            );
        }
    }

    foreach (
        [
            'INTERNAL CHAT LATEST50 BOTTOM V1.5',
            "stack.style.minHeight",
            "stack.style.justifyContent",
            "'flex-end'",
            'const pinBottom',
            'root.scrollHeight',
            'messageStack.appendChild(',
        ]
        as $needle
    ) {
        if (!str_contains($chatCheck, $needle)) {
            throw new RuntimeException(
                "Chat validation gagal: {$needle}"
            );
        }
    }

    echo "Patch PASS.\n";
    echo "- Initial load hanya latest 50 message.\n";
    echo "- Chronology tetap lama -> baru.\n";
    echo "- Newest ditempel ke bawah.\n";
    echo "- Saat open room langsung ke bottom.\n";
    echo "- Pesan baru tetap append di bawah.\n\n";

    chdir($root);

    passthru(
        escapeshellarg(PHP_BINARY)
        . ' '
        . escapeshellarg($root . '/artisan')
        . ' optimize:clear'
    );

    echo "\nSELESAI.\n";
    echo "Jalankan checker:\n";
    echo "php tools/check_internal_chat_latest50_bottom_v1_5.php\n";
} catch (Throwable $e) {
    restore($controllerBackup, $controllerPath);
    restore($chatBackup, $chatPath);

    fwrite(
        STDERR,
        "\nPATCH GAGAL: "
        . $e->getMessage()
        . "\n"
    );

    fwrite(
        STDERR,
        "Controller dan chat.blade.php dipulihkan dari backup otomatis.\n"
    );

    exit(1);
}
