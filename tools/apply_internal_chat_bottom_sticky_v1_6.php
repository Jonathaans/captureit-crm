<?php

declare(strict_types=1);

/**
 * INTERNAL CHAT BOTTOM STICKY V1.6
 *
 * Tujuan:
 * 1. Room dibuka langsung ke chat terbaru / paling bawah.
 * 2. Pesan baru tetap append ke bawah.
 * 3. Kalau user scroll ke atas untuk baca histori, incoming message tidak
 *    memaksa turun sampai user kembali dekat bawah.
 * 4. Initial load mengambil 300 pesan TERBARU, bukan 300 pesan PERTAMA.
 *
 * Strategi:
 * - Tidak mengubah struktur Blade / @if / @endif.
 * - Tidak membungkus message HTML.
 * - Menambah SATU standalone JS sebelum </x-admin::layouts>.
 * - Controller hanya mengubah query initial message pada method index().
 *
 * File:
 * - InternalChatController.php
 * - chat.blade.php
 */

$root = dirname(__DIR__);

$controllerPath =
    $root . '/packages/Webkul/Admin/src/Http/Controllers/InternalCommunication/InternalChatController.php';

$chatPath =
    $root . '/packages/Webkul/Admin/src/Resources/views/internal-communication/chat.blade.php';

$marker =
    'INTERNAL CHAT BOTTOM STICKY V1.6';

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

function phpLint(string $path): array
{
    exec(
        escapeshellarg(PHP_BINARY)
        . ' -l '
        . escapeshellarg($path)
        . ' 2>&1',
        $output,
        $code
    );

    return [$code, implode(PHP_EOL, $output)];
}

echo "INTERNAL CHAT BOTTOM STICKY V1.6\n";
echo "================================\n\n";

foreach ([$controllerPath, $chatPath] as $path) {
    if (!is_file($path)) {
        fail("File tidak ditemukan:\n{$path}");
    }
}

$controllerOriginal =
    file_get_contents($controllerPath);

$chatOriginal =
    file_get_contents($chatPath);

if (
    $controllerOriginal === false
    || $chatOriginal === false
) {
    fail('Gagal membaca source.');
}

if (!str_contains($controllerOriginal, 'class InternalChatController')) {
    fail('Preflight gagal: InternalChatController tidak dikenali.');
}

if (!str_contains($chatOriginal, 'id="crm-chat-messages"')) {
    fail('Preflight gagal: crm-chat-messages tidak ditemukan.');
}

if (!str_contains($chatOriginal, 'id="crm-chat-send-form"')) {
    fail('Preflight gagal: crm-chat-send-form tidak ditemukan.');
}

$stamp = date('Ymd-His');

$controllerBackup =
    $controllerPath . '.bak-internal-chat-bottom-sticky-v1_6-' . $stamp;

$chatBackup =
    $chatPath . '.bak-internal-chat-bottom-sticky-v1_6-' . $stamp;

foreach (
    [
        [$controllerPath, $controllerBackup],
        [$chatPath, $chatBackup],
    ]
    as [$source, $backup]
) {
    if (!copy($source, $backup)) {
        fail("Gagal membuat backup:\n{$backup}");
    }
}

echo "Backup dibuat.\n\n";

try {
    $controller = $controllerOriginal;
    $chat = $chatOriginal;

    /*
    |--------------------------------------------------------------------------
    | A. Initial query: newest 300, displayed ascending.
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
        throw new RuntimeException(
            'Boundary index()/startDirect() tidak ditemukan.'
        );
    }

    $beforeIndex =
        substr(
            $controller,
            0,
            $indexStart
        );

    $indexMethod =
        substr(
            $controller,
            $indexStart,
            $indexEnd - $indexStart
        );

    $afterIndex =
        substr(
            $controller,
            $indexEnd
        );

    $alreadyLatest =
        str_contains(
            $indexMethod,
            '->orderByDesc('
        )
        && str_contains(
            $indexMethod,
            '->sortBy('
        )
        && str_contains(
            $indexMethod,
            '->values()'
        );

    if (!$alreadyLatest) {
        $pattern =
            '~->orderBy\s*\(\s*[\'"]id[\'"]\s*\)'
            . '\s*->limit\s*\(\s*300\s*\)'
            . '\s*->get\s*\(\s*\)\s*;~';

        $replacement =
            <<<'PHP'
->orderByDesc(
                        'id'
                    )
                    ->limit(
                        300
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
                $queryCount
            );

        if (
            !is_string($patchedIndex)
            || $queryCount !== 1
        ) {
            throw new RuntimeException(
                "Query initial orderBy(id)->limit(300)->get() tidak ditemukan tepat 1 kali di index(). count="
                . (string) ($queryCount ?? -1)
            );
        }

        $indexMethod =
            $patchedIndex;

        $controller =
            $beforeIndex
            . $indexMethod
            . $afterIndex;

        echo "- Controller: initial query diubah ke newest 300.\n";
    } else {
        echo "- Controller: newest-300 query sudah terpasang.\n";
    }

    /*
    |--------------------------------------------------------------------------
    | B. Add one standalone scroll controller only.
    |--------------------------------------------------------------------------
    */

    if (!str_contains($chat, $marker)) {
        $closing =
            '</x-admin::layouts>';

        if (substr_count($chat, $closing) !== 1) {
            throw new RuntimeException(
                'Closing </x-admin::layouts> tidak ditemukan tepat 1 kali.'
            );
        }

        $script =
            <<<'BLADE'

    {{-- INTERNAL CHAT BOTTOM STICKY V1.6 --}}
    <script>
        (() => {
            const bootInternalChatBottomStickyV16 = () => {
                const root =
                    document.getElementById(
                        'crm-chat-messages'
                    );

                const form =
                    document.getElementById(
                        'crm-chat-send-form'
                    );

                if (
                    !root
                    || root.dataset.bottomStickyV16 === '1'
                ) {
                    return;
                }

                root.dataset.bottomStickyV16 =
                    '1';

                let stickToBottom =
                    true;

                let programmaticUntil =
                    0;

                const distanceFromBottom = (
                    element
                ) =>
                    Math.max(
                        0,
                        element.scrollHeight
                        - element.clientHeight
                        - element.scrollTop
                    );

                const isScrollable = (
                    element
                ) => {
                    if (!element) {
                        return false;
                    }

                    const style =
                        window.getComputedStyle(
                            element
                        );

                    const overflowY =
                        style.overflowY;

                    return (
                        overflowY === 'auto'
                        || overflowY === 'scroll'
                    )
                    && element.scrollHeight
                        > element.clientHeight + 2;
                };

                /*
                 * Prefer #crm-chat-messages. If a future CSS change moves
                 * scrolling to an ancestor, find that ancestor instead.
                 */
                const findScroller = () => {
                    if (isScrollable(root)) {
                        return root;
                    }

                    let node =
                        root.parentElement;

                    while (
                        node
                        && node !== document.body
                    ) {
                        if (isScrollable(node)) {
                            return node;
                        }

                        node =
                            node.parentElement;
                    }

                    return root;
                };

                const ensureSentinel = () => {
                    let sentinel =
                        document.getElementById(
                            'crm-chat-scroll-end-v16'
                        );

                    if (!sentinel) {
                        sentinel =
                            document.createElement(
                                'div'
                            );

                        sentinel.id =
                            'crm-chat-scroll-end-v16';

                        sentinel.setAttribute(
                            'aria-hidden',
                            'true'
                        );

                        sentinel.style.height =
                            '1px';

                        sentinel.style.flex =
                            '0 0 auto';

                        root.appendChild(
                            sentinel
                        );
                    }

                    return sentinel;
                };

                const sentinel =
                    ensureSentinel();

                const goBottom = () => {
                    const scroller =
                        findScroller();

                    programmaticUntil =
                        Date.now() + 160;

                    scroller.scrollTop =
                        scroller.scrollHeight;

                    /*
                     * Flush layout, then assign once more.
                     */
                    void scroller.offsetHeight;

                    scroller.scrollTop =
                        scroller.scrollHeight;

                    if (
                        distanceFromBottom(
                            scroller
                        ) > 4
                    ) {
                        sentinel.scrollIntoView({
                            behavior:
                                'auto',

                            block:
                                'end',

                            inline:
                                'nearest',
                        });

                        scroller.scrollTop =
                            scroller.scrollHeight;
                    }
                };

                /*
                 * Track whether user deliberately moved away from bottom.
                 */
                const bindScrollState = () => {
                    const scroller =
                        findScroller();

                    if (
                        scroller.dataset.bottomStickyStateV16
                        === '1'
                    ) {
                        return;
                    }

                    scroller.dataset.bottomStickyStateV16 =
                        '1';

                    scroller.addEventListener(
                        'scroll',
                        () => {
                            if (
                                Date.now()
                                < programmaticUntil
                            ) {
                                return;
                            }

                            stickToBottom =
                                distanceFromBottom(
                                    scroller
                                ) <= 90;
                        },
                        {
                            passive:
                                true,
                        }
                    );
                };

                bindScrollState();

                /*
                 * Initial open: force bottom through paint/font settling.
                 */
                goBottom();

                window.requestAnimationFrame(
                    () => {
                        goBottom();

                        window.requestAnimationFrame(
                            goBottom
                        );
                    }
                );

                [
                    60,
                    140,
                    300,
                    650,
                    1100,
                ].forEach(
                    (delay) => {
                        window.setTimeout(
                            goBottom,
                            delay
                        );
                    }
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

                /*
                 * Sending a message should always continue at the bottom.
                 * Capture phase means this runs even if the main chat submit
                 * handler is defined elsewhere.
                 */
                if (form) {
                    form.addEventListener(
                        'submit',
                        () => {
                            stickToBottom =
                                true;

                            goBottom();

                            window.setTimeout(
                                goBottom,
                                0
                            );

                            window.setTimeout(
                                goBottom,
                                120
                            );
                        },
                        true
                    );
                }

                /*
                 * Handles both:
                 * - AJAX send append
                 * - polling incoming append
                 *
                 * If user is reading older history, incoming messages do not
                 * yank them down. If already near bottom, stay pinned.
                 */
                const observer =
                    new MutationObserver(
                        (mutations) => {
                            let messageAdded =
                                false;

                            for (
                                const mutation
                                of mutations
                            ) {
                                for (
                                    const node
                                    of mutation.addedNodes
                                ) {
                                    if (
                                        node.nodeType
                                        !== Node.ELEMENT_NODE
                                    ) {
                                        continue;
                                    }

                                    if (
                                        node.id
                                        === 'crm-chat-scroll-end-v16'
                                    ) {
                                        continue;
                                    }

                                    if (
                                        node.matches?.(
                                            '[data-message-id]'
                                        )
                                        || node.querySelector?.(
                                            '[data-message-id]'
                                        )
                                    ) {
                                        messageAdded =
                                            true;

                                        break;
                                    }
                                }

                                if (messageAdded) {
                                    break;
                                }
                            }

                            if (
                                messageAdded
                                && stickToBottom
                            ) {
                                window.requestAnimationFrame(
                                    goBottom
                                );
                            }
                        }
                    );

                observer.observe(
                    root,
                    {
                        childList:
                            true,

                        subtree:
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
                    bootInternalChatBottomStickyV16,
                    {
                        once:
                            true,
                    }
                );
            } else {
                bootInternalChatBottomStickyV16();
            }
        })();
    </script>

BLADE;

        $chat =
            str_replace(
                $closing,
                $script
                . $closing,
                $chat
            );

        echo "- Blade: standalone bottom-sticky script ditambahkan.\n";
    } else {
        echo "- Blade: V1.6 sudah ada.\n";
    }

    /*
    |--------------------------------------------------------------------------
    | Write + validate + rollback
    |--------------------------------------------------------------------------
    */

    atomicWrite(
        $controllerPath,
        $controller
    );

    atomicWrite(
        $chatPath,
        $chat
    );

    [$lintCode, $lintOutput] =
        phpLint(
            $controllerPath
        );

    if ($lintCode !== 0) {
        throw new RuntimeException(
            "Controller PHP lint gagal:\n{$lintOutput}"
        );
    }

    $controllerCheck =
        file_get_contents(
            $controllerPath
        );

    $chatCheck =
        file_get_contents(
            $chatPath
        );

    if (
        $controllerCheck === false
        || $chatCheck === false
    ) {
        throw new RuntimeException(
            'Post-write gagal membaca source.'
        );
    }

    $indexCheckStart =
        strpos(
            $controllerCheck,
            'public function index('
        );

    $indexCheckEnd =
        strpos(
            $controllerCheck,
            'public function startDirect('
        );

    $indexCheck =
        substr(
            $controllerCheck,
            $indexCheckStart,
            $indexCheckEnd - $indexCheckStart
        );

    foreach (
        [
            '->orderByDesc(',
            '->limit(',
            '300',
            '->sortBy(',
            '->values();',
        ]
        as $needle
    ) {
        if (!str_contains($indexCheck, $needle)) {
            throw new RuntimeException(
                "Controller validation gagal: {$needle}"
            );
        }
    }

    foreach (
        [
            $marker,
            'const goBottom',
            'const findScroller',
            'const observer',
            'MutationObserver',
            'stickToBottom',
            'crm-chat-scroll-end-v16',
            "form.addEventListener(\n                        'submit'",
        ]
        as $needle
    ) {
        if (!str_contains($chatCheck, $needle)) {
            throw new RuntimeException(
                "Blade validation gagal: {$needle}"
            );
        }
    }

    if (
        substr_count(
            $chatCheck,
            $marker
        ) !== 1
    ) {
        throw new RuntimeException(
            'V1.6 marker tidak tepat 1 kali.'
        );
    }

    echo "\nPatch PASS.\n";
    echo "- Open room -> bottom/latest.\n";
    echo "- Send -> tetap lanjut di bawah.\n";
    echo "- Incoming saat dekat bawah -> tetap pinned.\n";
    echo "- User scroll ke atas -> tidak dipaksa turun.\n";
    echo "- Initial DB window -> 300 message terbaru.\n";
    echo "- Struktur Blade/@if/@endif tidak diubah.\n\n";

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
    echo "php tools/check_internal_chat_bottom_sticky_v1_6.php\n";
} catch (Throwable $e) {
    restore(
        $controllerBackup,
        $controllerPath
    );

    restore(
        $chatBackup,
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
        "Controller + chat.blade.php dipulihkan dari backup otomatis.\n"
    );

    exit(1);
}
