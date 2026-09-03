<?php

declare(strict_types=1);

/**
 * INTERNAL CHAT SCROLL GUARD + LATEST 50 V1.7
 *
 * Berdasarkan diagnostic post-rollback:
 * - backend SUDAH benar: newest-first -> limit(50) -> sortBy(id)
 * - DOM SUDAH benar: old -> new
 * - dynamic append SUDAH benar: messageStack.appendChild(wrapper)
 * - initial scroll SUDAH ada, tetapi runtime masih kembali ke posisi atas
 *
 * Maka V1.7 TIDAK mengubah controller dan TIDAK mengubah Blade directives.
 * Ia hanya menambahkan PURE JAVASCRIPT scroll guard.
 *
 * Tidak ada @if/@endif baru, sehingga risiko syntax Blade seperti V1.6 dihindari.
 */

$root = dirname(__DIR__);

$controllerPath =
    $root . '/packages/Webkul/Admin/src/Http/Controllers/InternalCommunication/InternalChatController.php';

$chatPath =
    $root . '/packages/Webkul/Admin/src/Resources/views/internal-communication/chat.blade.php';

$marker =
    'INTERNAL CHAT SCROLL GUARD LATEST50 V1.7';

function fail(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

function atomicWrite(string $path, string $contents): void
{
    $tmp =
        $path . '.tmp-' . bin2hex(random_bytes(4));

    if (file_put_contents($tmp, $contents) === false) {
        @unlink($tmp);
        throw new RuntimeException("Gagal menulis temporary file: {$tmp}");
    }

    if (!rename($tmp, $path)) {
        @unlink($tmp);
        throw new RuntimeException("Gagal mengganti file: {$path}");
    }
}

echo "INTERNAL CHAT SCROLL GUARD + LATEST 50 V1.7\n";
echo "===========================================\n\n";

foreach ([$controllerPath, $chatPath] as $path) {
    if (!is_file($path)) {
        fail("File tidak ditemukan: {$path}");
    }
}

$controller =
    file_get_contents($controllerPath);

$chat =
    file_get_contents($chatPath);

if ($controller === false || $chat === false) {
    fail('Gagal membaca source.');
}

/*
|--------------------------------------------------------------------------
| Preflight: prove current backend/DOM state before touching anything
|--------------------------------------------------------------------------
*/

$indexStart =
    strpos(
        $controller,
        'public function index('
    );

$indexEnd =
    strpos(
        $controller,
        'public function startDirect('
    );

if (
    $indexStart === false
    || $indexEnd === false
    || $indexEnd <= $indexStart
) {
    fail('Preflight gagal: boundary index()/startDirect() tidak ditemukan.');
}

$index =
    substr(
        $controller,
        $indexStart,
        $indexEnd - $indexStart
    );

$backendChecks = [
    'orderByDesc(id)' =>
        preg_match(
            '~->orderByDesc\s*\(\s*[\'"]id[\'"]\s*\)~',
            $index
        ) === 1,

    'limit(50)' =>
        preg_match(
            '~->limit\s*\(\s*50\s*\)~',
            $index
        ) === 1,

    'sortBy(id)' =>
        preg_match(
            '~->sortBy\s*\(\s*[\'"]id[\'"]\s*\)~',
            $index
        ) === 1,

    'values()' =>
        preg_match(
            '~->values\s*\(\s*\)~',
            $index
        ) === 1,
];

echo "Backend preflight:\n";

foreach ($backendChecks as $label => $ok) {
    echo
        ($ok ? '[OK]   ' : '[FAIL] ')
        . $label
        . PHP_EOL;

    if (!$ok) {
        fail(
            "Backend latest-50 belum sesuai. V1.7 dibatalkan; "
            . "controller tidak akan disentuh."
        );
    }
}

echo "\nDOM preflight:\n";

$domChecks = [
    'crm-chat-messages' =>
        'id="crm-chat-messages"',

    'crm-chat-message-stack' =>
        'id="crm-chat-message-stack"',

    'crm-chat-bottom' =>
        'id="crm-chat-bottom"',

    'dynamic append to stack' =>
        'messageStack.appendChild(',
];

foreach ($domChecks as $label => $needle) {
    $ok =
        str_contains(
            $chat,
            $needle
        );

    echo
        ($ok ? '[OK]   ' : '[FAIL] ')
        . $label
        . PHP_EOL;

    if (!$ok) {
        fail(
            "DOM preflight gagal: {$label}. "
            . "Patch dibatalkan agar tidak menebak."
        );
    }
}

if (str_contains($chat, $marker)) {
    echo "\nV1.7 sudah terpasang. Tidak ada perubahan.\n";
    exit(0);
}

$closing =
    '</x-admin::layouts>';

if (substr_count($chat, $closing) !== 1) {
    fail(
        'Closing </x-admin::layouts> tidak ditemukan tepat 1 kali.'
    );
}

$stamp =
    date('Ymd-His');

$backup =
    $chatPath
    . '.bak-scroll-guard-latest50-v1_7-'
    . $stamp;

if (!copy($chatPath, $backup)) {
    fail("Gagal membuat backup: {$backup}");
}

echo "\nBackup:\n{$backup}\n\n";

try {
    /*
     * PURE JS ONLY.
     * No Blade conditional directive is introduced here.
     */
    $script = <<<'BLADE'

    {{-- INTERNAL CHAT SCROLL GUARD LATEST50 V1.7 --}}
    <script>
        (() => {
            const bootInternalChatScrollGuardV17 = () => {
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
                    || root.dataset.scrollGuardV17
                        === '1'
                ) {
                    return;
                }

                root.dataset.scrollGuardV17 =
                    '1';

                /*
                 * Do NOT reorder messages.
                 *
                 * DOM remains:
                 * oldest
                 * ...
                 * newest
                 *
                 * Short history is merely positioned at the bottom.
                 */
                stack.style.minHeight =
                    '100%';

                stack.style.marginTop =
                    '0';

                stack.style.justifyContent =
                    'flex-end';

                stack.style.flexShrink =
                    '0';

                root.style.scrollBehavior =
                    'auto';

                if (
                    'scrollRestoration'
                    in history
                ) {
                    history.scrollRestoration =
                        'manual';
                }

                const maxScrollTop = () =>
                    Math.max(
                        0,
                        root.scrollHeight
                        - root.clientHeight
                    );

                const distanceFromBottom = () =>
                    Math.max(
                        0,
                        maxScrollTop()
                        - root.scrollTop
                    );

                let followNewest =
                    true;

                let programmaticScroll =
                    false;

                let recentUserIntentAt =
                    0;

                const goBottom = () => {
                    if (!followNewest) {
                        return;
                    }

                    programmaticScroll =
                        true;

                    /*
                     * Flush layout after min-height / justify-end.
                     */
                    void stack.offsetHeight;

                    root.scrollTop =
                        maxScrollTop();

                    window.requestAnimationFrame(
                        () => {
                            root.scrollTop =
                                maxScrollTop();

                            programmaticScroll =
                                false;
                        }
                    );
                };

                /*
                 * Initial room open.
                 * Repeat across the common layout/hydration windows.
                 */
                goBottom();

                [
                    0,
                    30,
                    80,
                    160,
                    320,
                    650,
                    1200,
                    2200,
                    5200,
                ].forEach(
                    (delay) => {
                        window.setTimeout(
                            goBottom,
                            delay
                        );
                    }
                );

                /*
                 * User scroll-up intent disables following newest.
                 * Programmatic scroll changes from existing chat code/polling
                 * do NOT disable it.
                 */
                root.addEventListener(
                    'wheel',
                    (event) => {
                        recentUserIntentAt =
                            Date.now();

                        if (event.deltaY < 0) {
                            followNewest =
                                false;
                        }
                    },
                    {
                        passive:
                            true,
                    }
                );

                let touchStartY =
                    null;

                root.addEventListener(
                    'touchstart',
                    (event) => {
                        recentUserIntentAt =
                            Date.now();

                        touchStartY =
                            event.touches?.[0]?.clientY
                            ?? null;
                    },
                    {
                        passive:
                            true,
                    }
                );

                root.addEventListener(
                    'touchmove',
                    (event) => {
                        recentUserIntentAt =
                            Date.now();

                        const y =
                            event.touches?.[0]?.clientY
                            ?? null;

                        if (
                            touchStartY !== null
                            && y !== null
                            && y > touchStartY + 4
                        ) {
                            followNewest =
                                false;
                        }
                    },
                    {
                        passive:
                            true,
                    }
                );

                root.addEventListener(
                    'scroll',
                    () => {
                        if (programmaticScroll) {
                            return;
                        }

                        const nearBottom =
                            distanceFromBottom()
                            <= 24;

                        if (nearBottom) {
                            /*
                             * Once user returns to bottom, resume normal
                             * WhatsApp-style following of incoming messages.
                             */
                            followNewest =
                                true;

                            return;
                        }

                        /*
                         * Only treat a non-bottom scroll as deliberate when it
                         * follows a recent human gesture.
                         */
                        if (
                            Date.now()
                            - recentUserIntentAt
                            < 500
                        ) {
                            followNewest =
                                false;
                        }
                    },
                    {
                        passive:
                            true,
                    }
                );

                /*
                 * Existing polling/appending can mutate the DOM after open.
                 * Keep newest visible only while the user is following newest.
                 */
                const mutationObserver =
                    new MutationObserver(
                        () => {
                            if (followNewest) {
                                goBottom();
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
                 * Font/attachment/layout changes can increase scrollHeight
                 * without adding a new message.
                 */
                if (
                    'ResizeObserver'
                    in window
                ) {
                    const resizeObserver =
                        new ResizeObserver(
                            () => {
                                if (followNewest) {
                                    goBottom();
                                }
                            }
                        );

                    resizeObserver.observe(
                        stack
                    );
                }

                /*
                 * Persistent low-frequency guard.
                 *
                 * Diagnostic proved the main chat script already tries one
                 * initial scroll, yet QA still lands at top. This guard makes
                 * the expected bottom position authoritative while the user
                 * is following newest, including after the 5-second poll.
                 */
                window.setInterval(
                    () => {
                        if (
                            followNewest
                            && distanceFromBottom()
                                > 6
                        ) {
                            goBottom();
                        }
                    },
                    600
                );

                window.addEventListener(
                    'load',
                    goBottom,
                    {
                        once:
                            true,
                    }
                );

                window.addEventListener(
                    'pageshow',
                    goBottom,
                    {
                        once:
                            true,
                    }
                );

                document.fonts
                    ?.ready
                    ?.then(
                        goBottom
                    );
            };

            if (
                document.readyState
                === 'loading'
            ) {
                document.addEventListener(
                    'DOMContentLoaded',
                    bootInternalChatScrollGuardV17,
                    {
                        once:
                            true,
                    }
                );
            } else {
                bootInternalChatScrollGuardV17();
            }
        })();
    </script>

BLADE;

    $updated =
        str_replace(
            $closing,
            $script
            . $closing,
            $chat
        );

    atomicWrite(
        $chatPath,
        $updated
    );

    $check =
        file_get_contents(
            $chatPath
        );

    if ($check === false) {
        throw new RuntimeException(
            'Gagal membaca chat.blade.php setelah patch.'
        );
    }

    $required = [
        $marker,
        'const goBottom',
        'const maxScrollTop',
        'MutationObserver',
        'ResizeObserver',
        'followNewest',
        '600',
        'messageStack.appendChild(',
    ];

    foreach ($required as $needle) {
        if (!str_contains($check, $needle)) {
            throw new RuntimeException(
                "Post-write validation gagal: {$needle}"
            );
        }
    }

    /*
     * We did not introduce any Blade @if/@endif.
     * Still ensure the final marker is unique.
     */
    if (
        substr_count(
            $check,
            $marker
        ) !== 1
    ) {
        throw new RuntimeException(
            'V1.7 marker tidak tepat 1 kali.'
        );
    }

    echo "Patch PASS.\n";
    echo "- Controller tidak diubah; latest 50 sudah benar.\n";
    echo "- DOM chronology tidak diubah.\n";
    echo "- Pure-JS scroll guard ditambahkan tanpa @if/@endif baru.\n";
    echo "- Open room mengikuti newest di paling bawah.\n";
    echo "- Poll/mutation/layout reset akan dikoreksi saat followNewest aktif.\n";
    echo "- User scroll ke atas tetap dihormati.\n\n";

    chdir($root);

    passthru(
        escapeshellarg(PHP_BINARY)
        . ' '
        . escapeshellarg($root . '/artisan')
        . ' view:clear'
    );

    echo "\nSELESAI.\n";
    echo "Jalankan checker:\n";
    echo "php tools/check_internal_chat_scroll_guard_latest50_v1_7.php\n";
} catch (Throwable $e) {
    copy(
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
