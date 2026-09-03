<?php

declare(strict_types=1);

/**
 * INTERNAL CHAT STICKY LATEST 50 V1.6
 *
 * Final behavior:
 * - chronological order stays OLD -> NEW
 * - latest message stays at the bottom
 * - opening a room immediately shows the bottom/newest message
 * - only latest 50 messages are loaded initially
 * - new messages keep the viewport pinned to bottom IF user is already at bottom
 * - if user scrolls upward to read history, auto-pin stops
 *
 * V1.6 also keeps a low-frequency bottom guard because another chat/poll script
 * may reset scrollTop after initial load. This is the issue visible in QA:
 * initial pin happens, then a later runtime step can return the room to top.
 *
 * Files:
 * - InternalChatController.php
 * - chat.blade.php
 */

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

    if ($scriptOpen === false || $scriptClose === false) {
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
        && ($scriptOpen - $ifPos) < 220
    ) {
        $endifPos =
            strpos(
                $source,
                '@endif',
                $scriptClose
            );

        if (
            $endifPos !== false
            && ($endifPos - $scriptClose) < 220
        ) {
            $start = $ifPos;
            $end = $endifPos + strlen('@endif');
        }
    }

    return
        substr($source, 0, $start)
        . substr($source, $end);
}

echo "INTERNAL CHAT STICKY LATEST 50 V1.6\n";
echo "===================================\n\n";

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
        fail(
            "Preflight gagal: {$needle} tidak ditemukan. "
            . "Struktur bottom-stack sebelumnya diperlukan."
        );
    }
}

$stamp = date('Ymd-His');

$controllerBackup =
    $controllerPath . '.bak-sticky-latest50-v1_6-' . $stamp;

$chatBackup =
    $chatPath . '.bak-sticky-latest50-v1_6-' . $stamp;

if (
    !copy($controllerPath, $controllerBackup)
    || !copy($chatPath, $chatBackup)
) {
    fail('Gagal membuat backup.');
}

echo "Backup dibuat.\n\n";

try {
    /*
    |--------------------------------------------------------------------------
    | CONTROLLER: enforce latest 50 in index()
    |--------------------------------------------------------------------------
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

    $beforeIndex = substr($controller, 0, $indexStart);

    $indexMethod =
        substr(
            $controller,
            $indexStart,
            $indexEnd - $indexStart
        );

    $afterIndex = substr($controller, $indexEnd);

    $alreadyLatest50 =
        preg_match(
            '~->orderByDesc\s*\(\s*[\'"]id[\'"]\s*\)[\s\S]*?'
            . '->limit\s*\(\s*50\s*\)[\s\S]*?'
            . '->get\s*\(\s*\)[\s\S]*?'
            . '->sortBy\s*\(\s*[\'"]id[\'"]\s*\)[\s\S]*?'
            . '->values\s*\(\s*\)~',
            $indexMethod
        ) === 1;

    if (!$alreadyLatest50) {
        $queryPattern =
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
                $queryPattern,
                $replacement,
                $indexMethod,
                1,
                $queryCount
            );

        if (!is_string($patchedIndex) || $queryCount !== 1) {
            throw new RuntimeException(
                "Query initial message tidak ditemukan tepat 1 kali di index(). count="
                . (string) ($queryCount ?? -1)
            );
        }

        $indexMethod = $patchedIndex;
    }

    if (
        !str_contains(
            $indexMethod,
            'INTERNAL CHAT STICKY LATEST 50 V1.6'
        )
    ) {
        $messagesPos = strpos($indexMethod, '$messages =');

        if ($messagesPos === false) {
            throw new RuntimeException(
                '$messages assignment tidak ditemukan setelah patch.'
            );
        }

        $marker = <<<'PHP'
            /*
             * INTERNAL CHAT STICKY LATEST 50 V1.6
             * Initial room memuat 50 pesan terbaru, lalu display ascending.
             */

PHP;

        $indexMethod =
            substr($indexMethod, 0, $messagesPos)
            . $marker
            . substr($indexMethod, $messagesPos);
    }

    $controller =
        $beforeIndex
        . $indexMethod
        . $afterIndex;

    /*
    |--------------------------------------------------------------------------
    | CHAT: remove prior competing standalone scroll scripts
    |--------------------------------------------------------------------------
    */

    $chat = $chatOriginal;

    foreach (
        [
            'INTERNAL CHAT LATEST50 BOTTOM V1.5',
            'INTERNAL CHAT WHATSAPP FINAL SCROLL V1.4.1',
            'INTERNAL CHAT WHATSAPP FINAL SCROLL V1.4',
            'INTERNAL CHAT WHATSAPP INITIAL BOTTOM V1.3',
            'INTERNAL CHAT FORCE OPEN LATEST V1.2 START',
        ]
        as $marker
    ) {
        $chat = removeStandaloneScript($chat, $marker);
    }

    /*
     * Remove embedded V1.1 initial-scroll block if still present.
     */
    $embeddedMarker =
        '/* INTERNAL CHAT INITIAL LATEST V1.1 */';

    $embeddedPos =
        strpos($chat, $embeddedMarker);

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

    /*
    |--------------------------------------------------------------------------
    | One authoritative sticky-bottom runtime
    |--------------------------------------------------------------------------
    */

    $script = <<<'BLADE'

    {{-- INTERNAL CHAT STICKY BOTTOM V1.6 --}}
    @if ($conversation)
        <script>
            (() => {
                const bootStickyBottomV16 = () => {
                    const root =
                        document.getElementById(
                            'crm-chat-messages'
                        );

                    const stack =
                        document.getElementById(
                            'crm-chat-message-stack'
                        );

                    if (
                        !root
                        || !stack
                        || root.dataset.stickyBottomV16
                            === '1'
                    ) {
                        return;
                    }

                    root.dataset.stickyBottomV16 =
                        '1';

                    /*
                     * IMPORTANT:
                     * This does NOT reorder messages.
                     *
                     * DOM remains:
                     * old
                     * old
                     * ...
                     * newest
                     *
                     * For short histories, justify-end only positions the
                     * chronological stack near the composer.
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

                    const distanceFromBottom = () =>
                        Math.max(
                            0,
                            root.scrollHeight
                            - root.clientHeight
                            - root.scrollTop
                        );

                    let sticky =
                        true;

                    let userIntent =
                        false;

                    let pinning =
                        false;

                    const pinBottom = () => {
                        if (!sticky) {
                            return;
                        }

                        pinning =
                            true;

                        void stack.offsetHeight;

                        root.scrollTop =
                            root.scrollHeight;

                        requestAnimationFrame(
                            () => {
                                root.scrollTop =
                                    root.scrollHeight;

                                pinning =
                                    false;
                            }
                        );
                    };

                    /*
                     * Only an actual user gesture may disable sticky mode.
                     * Programmatic scroll changes from polling/scripts do not.
                     */
                    const beginUserIntent = () => {
                        userIntent =
                            true;
                    };

                    [
                        'wheel',
                        'touchstart',
                        'pointerdown',
                    ].forEach(
                        (eventName) => {
                            root.addEventListener(
                                eventName,
                                beginUserIntent,
                                {
                                    passive:
                                        true,
                                }
                            );
                        }
                    );

                    root.addEventListener(
                        'scroll',
                        () => {
                            if (pinning) {
                                return;
                            }

                            const nearBottom =
                                distanceFromBottom()
                                <= 32;

                            if (nearBottom) {
                                sticky =
                                    true;

                                userIntent =
                                    false;

                                return;
                            }

                            if (userIntent) {
                                /*
                                 * User intentionally went upward to inspect
                                 * history. Stop following newest until they
                                 * return near the bottom themselves.
                                 */
                                sticky =
                                    false;

                                userIntent =
                                    false;
                            }
                        },
                        {
                            passive:
                                true,
                        }
                    );

                    /*
                     * Initial room open: newest immediately visible.
                     */
                    pinBottom();

                    requestAnimationFrame(
                        () => {
                            pinBottom();

                            requestAnimationFrame(
                                pinBottom
                            );
                        }
                    );

                    [
                        50,
                        150,
                        350,
                        750,
                        1500,
                    ].forEach(
                        (delay) => {
                            setTimeout(
                                pinBottom,
                                delay
                            );
                        }
                    );

                    /*
                     * DOM mutations cover incoming/polled messages.
                     * If user is following newest, stay at bottom.
                     * If they scrolled upward, do nothing.
                     */
                    const mutationObserver =
                        new MutationObserver(
                            () => {
                                if (sticky) {
                                    pinBottom();
                                }
                            }
                        );

                    mutationObserver.observe(
                        stack,
                        {
                            childList:
                                true,

                            subtree:
                                true,
                        }
                    );

                    /*
                     * Attachment/font sizing can increase the scrollHeight
                     * after message DOM already exists.
                     */
                    if (
                        'ResizeObserver'
                        in window
                    ) {
                        const resizeObserver =
                            new ResizeObserver(
                                () => {
                                    if (sticky) {
                                        pinBottom();
                                    }
                                }
                            );

                        resizeObserver.observe(
                            stack
                        );
                    }

                    /*
                     * Low-frequency guard:
                     * some existing runtime/poll code may assign scrollTop
                     * after the initial scripts finish. While user is still
                     * following newest, restore the expected bottom position.
                     *
                     * 750 ms is deliberately low-frequency and trivial cost.
                     */
                    setInterval(
                        () => {
                            if (
                                sticky
                                && distanceFromBottom()
                                    > 8
                            ) {
                                pinBottom();
                            }
                        },
                        750
                    );

                    window.addEventListener(
                        'load',
                        pinBottom,
                        {
                            once:
                                true,
                        }
                    );

                    window.addEventListener(
                        'pageshow',
                        pinBottom,
                        {
                            once:
                                true,
                        }
                    );

                    document.fonts
                        ?.ready
                        ?.then(
                            pinBottom
                        );
                };

                if (
                    document.readyState
                    === 'loading'
                ) {
                    document.addEventListener(
                        'DOMContentLoaded',
                        bootStickyBottomV16,
                        {
                            once:
                                true,
                        }
                    );
                } else {
                    bootStickyBottomV16();
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

    atomicWrite($controllerPath, $controller);
    atomicWrite($chatPath, $chat);

    exec(
        escapeshellarg(PHP_BINARY)
        . ' -l '
        . escapeshellarg($controllerPath)
        . ' 2>&1',
        $lintOutput,
        $lintCode
    );

    if ($lintCode !== 0) {
        throw new RuntimeException(
            "Controller PHP lint gagal:\n"
            . implode(PHP_EOL, $lintOutput)
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
            'INTERNAL CHAT STICKY LATEST 50 V1.6',
            '->orderByDesc(',
            '->sortBy(',
            '->values();',
        ]
        as $needle
    ) {
        if (!str_contains($controllerCheck, $needle)) {
            throw new RuntimeException(
                "Controller validation gagal: {$needle}"
            );
        }
    }

    if (
        preg_match(
            '~->limit\s*\(\s*50\s*\)~',
            $controllerCheck
        ) !== 1
    ) {
        throw new RuntimeException(
            'Controller limit(50) validation gagal.'
        );
    }

    foreach (
        [
            'INTERNAL CHAT STICKY BOTTOM V1.6',
            'stack.style.justifyContent',
            "'flex-end'",
            'const pinBottom',
            'MutationObserver',
            'ResizeObserver',
            'setInterval',
            'distanceFromBottom',
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
    echo "- Initial backend load = latest 50 messages.\n";
    echo "- Message order tetap old -> new.\n";
    echo "- Open room langsung mengikuti newest di bottom.\n";
    echo "- Poll/DOM changes tidak boleh mengembalikan room ke top.\n";
    echo "- New message terus diikuti jika user masih di bottom.\n";
    echo "- User scroll up -> sticky berhenti sampai user kembali ke bottom.\n\n";

    chdir($root);

    passthru(
        escapeshellarg(PHP_BINARY)
        . ' '
        . escapeshellarg($root . '/artisan')
        . ' optimize:clear'
    );

    echo "\nSELESAI.\n";
    echo "Jalankan checker:\n";
    echo "php tools/check_internal_chat_sticky_latest50_v1_6.php\n";
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
