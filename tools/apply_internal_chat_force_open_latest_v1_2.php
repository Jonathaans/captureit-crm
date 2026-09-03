<?php

declare(strict_types=1);

/**
 * INTERNAL CHAT FORCE OPEN LATEST V1.2
 *
 * Root cause:
 * #crm-chat-messages has overflow-y-auto, but its surrounding chat shell
 * uses a minimum-height layout rather than a bounded viewport height.
 * In that state the browser page can become the real scroll container,
 * so messagesRoot.scrollTop = messagesRoot.scrollHeight appears to do nothing.
 *
 * V1.2:
 * - Does NOT touch controller/database/routes/menu/ACL.
 * - Adds one authoritative layout + initial-scroll script at the END of chat.blade.php.
 * - Measures available viewport height at runtime.
 * - Forces the chat shell to a bounded height.
 * - Adds min-height:0 through the flex chain.
 * - Forces #crm-chat-messages to be the actual scrolling element.
 * - Jumps to latest after layout settles, including attachment/font resizing.
 */

$root = dirname(__DIR__);

$chatPath =
    $root . '/packages/Webkul/Admin/src/Resources/views/internal-communication/chat.blade.php';

$startMarker =
    '{{-- INTERNAL CHAT FORCE OPEN LATEST V1.2 START --}}';

$endMarker =
    '{{-- INTERNAL CHAT FORCE OPEN LATEST V1.2 END --}}';

function fail(string $message, int $code = 1): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit($code);
}

function atomicWrite(string $path, string $contents): void
{
    $tmp =
        $path . '.tmp-' . bin2hex(random_bytes(4));

    if (file_put_contents($tmp, $contents) === false) {
        @unlink($tmp);

        throw new RuntimeException(
            "Gagal menulis temporary file: {$tmp}"
        );
    }

    if (!rename($tmp, $path)) {
        @unlink($tmp);

        throw new RuntimeException(
            "Gagal mengganti file: {$path}"
        );
    }
}

function restore(string $backup, string $target): void
{
    if (is_file($backup)) {
        copy($backup, $target);
    }
}

echo "INTERNAL CHAT FORCE OPEN LATEST V1.2\n";
echo "====================================\n\n";

if (!is_file($chatPath)) {
    fail("chat.blade.php tidak ditemukan: {$chatPath}");
}

$original =
    file_get_contents($chatPath);

if ($original === false) {
    fail('Gagal membaca chat.blade.php.');
}

if (
    !str_contains(
        $original,
        'id="crm-chat-messages"'
    )
) {
    fail(
        'Preflight gagal: #crm-chat-messages tidak ditemukan.'
    );
}

if (str_contains($original, $startMarker)) {
    echo "V1.2 sudah terpasang. Tidak ada perubahan.\n";
    exit(0);
}

$closing =
    '</x-admin::layouts>';

if (
    substr_count(
        $original,
        $closing
    ) !== 1
) {
    fail(
        'Preflight gagal: closing </x-admin::layouts> tidak ditemukan tepat 1 kali.'
    );
}

$stamp =
    date('Ymd-His');

$backup =
    $chatPath
    . '.bak-internal-chat-force-open-latest-v1_2-'
    . $stamp;

if (!copy($chatPath, $backup)) {
    fail("Gagal membuat backup: {$backup}");
}

echo "Backup:\n{$backup}\n\n";

$patch = <<<'BLADE'

{{-- INTERNAL CHAT FORCE OPEN LATEST V1.2 START --}}
<script>
(() => {
    const bootLatestChatViewportV12 = () => {
        const messagesRoot =
            document.getElementById(
                'crm-chat-messages'
            );

        if (
            !messagesRoot
            || messagesRoot.dataset.latestViewportV12
                === '1'
        ) {
            return;
        }

        messagesRoot.dataset.latestViewportV12 =
            '1';

        const chatSection =
            messagesRoot.closest(
                'section'
            );

        /*
         * Usually:
         * shell
         *   aside
         *   section
         *     header
         *     #crm-chat-messages
         *     composer
         */
        const chatShell =
            chatSection?.parentElement
            || messagesRoot.parentElement;

        let initialPhase =
            true;

        let resizeTimer =
            null;

        const applyChatViewport = () => {
            if (!chatShell) {
                return;
            }

            const shellTop =
                Math.max(
                    0,
                    chatShell
                        .getBoundingClientRect()
                        .top
                );

            /*
             * Leave room for the CRM footer / safe bottom area.
             * Height is calculated from the ACTUAL shell position rather than
             * guessing the admin header height.
             */
            const bottomSafeArea =
                72;

            const available =
                Math.max(
                    420,
                    window.innerHeight
                    - shellTop
                    - bottomSafeArea
                );

            chatShell.style.height =
                `${available}px`;

            chatShell.style.minHeight =
                '0';

            chatShell.style.maxHeight =
                `${available}px`;

            chatShell.style.overflow =
                'hidden';

            if (chatSection) {
                chatSection.style.minHeight =
                    '0';

                chatSection.style.height =
                    '100%';

                chatSection.style.overflow =
                    'hidden';
            }

            messagesRoot.style.minHeight =
                '0';

            messagesRoot.style.height =
                'auto';

            messagesRoot.style.overflowY =
                'auto';

            messagesRoot.style.overscrollBehavior =
                'contain';

            messagesRoot.style.scrollBehavior =
                'auto';
        };

        const latestMessage = () => {
            const nodes =
                messagesRoot.querySelectorAll(
                    '[data-message-id]'
                );

            return nodes.length > 0
                ? nodes[nodes.length - 1]
                : null;
        };

        const jumpToLatest = () => {
            applyChatViewport();

            /*
             * Force layout before assigning scrollTop.
             */
            void messagesRoot.offsetHeight;

            messagesRoot.scrollTop =
                messagesRoot.scrollHeight;

            const last =
                latestMessage();

            if (!last) {
                return;
            }

            /*
             * The bounded shell should make messagesRoot the true scroll
             * container. scrollIntoView is kept as a fallback for browsers
             * that restore page position after the first paint.
             */
            last.scrollIntoView({
                behavior:
                    'auto',

                block:
                    'end',

                inline:
                    'nearest',
            });

            messagesRoot.scrollTop =
                messagesRoot.scrollHeight;
        };

        /*
         * Run after increasingly settled layout stages.
         */
        const scheduleInitialJump = () => {
            window.requestAnimationFrame(
                () => {
                    window.requestAnimationFrame(
                        jumpToLatest
                    );
                }
            );

            [
                0,
                60,
                150,
                320,
                650,
                1100,
            ].forEach(
                (delay) => {
                    window.setTimeout(
                        jumpToLatest,
                        delay
                    );
                }
            );
        };

        scheduleInitialJump();

        window.addEventListener(
            'load',
            () => {
                jumpToLatest();

                window.setTimeout(
                    jumpToLatest,
                    120
                );
            },
            {
                once:
                    true,
            }
        );

        /*
         * Images / attachment chips / fonts can change content height after
         * first paint. During initial phase we keep the chat pinned to latest.
         * It stops after 1.8 s, so later user scrolling is never hijacked.
         */
        let observer =
            null;

        if ('ResizeObserver' in window) {
            observer =
                new ResizeObserver(
                    () => {
                        if (initialPhase) {
                            jumpToLatest();
                        }
                    }
                );

            observer.observe(
                messagesRoot
            );
        }

        window.setTimeout(
            () => {
                initialPhase =
                    false;

                observer?.disconnect();
            },
            1800
        );

        window.addEventListener(
            'resize',
            () => {
                window.clearTimeout(
                    resizeTimer
                );

                resizeTimer =
                    window.setTimeout(
                        () => {
                            applyChatViewport();

                            /*
                             * Only pin to latest during initial opening.
                             * Normal resizing later preserves the user's place.
                             */
                            if (initialPhase) {
                                jumpToLatest();
                            }
                        },
                        80
                    );
            }
        );
    };

    if (
        document.readyState
        === 'loading'
    ) {
        document.addEventListener(
            'DOMContentLoaded',
            bootLatestChatViewportV12,
            {
                once:
                    true,
            }
        );
    } else {
        bootLatestChatViewportV12();
    }
})();
</script>
{{-- INTERNAL CHAT FORCE OPEN LATEST V1.2 END --}}

BLADE;

try {
    $updated =
        str_replace(
            $closing,
            $patch . $closing,
            $original
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
            'Post-write gagal membaca chat.blade.php.'
        );
    }

    $required = [
        $startMarker,
        $endMarker,
        'applyChatViewport',
        'jumpToLatest',
        'messagesRoot.scrollHeight',
        'last.scrollIntoView',
        'ResizeObserver',
        'chatShell.style.height',
        "chatSection.style.minHeight",
    ];

    foreach ($required as $needle) {
        if (!str_contains($check, $needle)) {
            throw new RuntimeException(
                "Post-write validation gagal: {$needle}"
            );
        }
    }

    echo "Patch PASS.\n";
    echo "- Chat shell sekarang dibatasi mengikuti viewport aktual.\n";
    echo "- #crm-chat-messages menjadi scroll container yang nyata.\n";
    echo "- Saat membuka room, langsung dipaksa ke message terbaru.\n";
    echo "- Initial resize/font/attachment settling ikut ditangani.\n";
    echo "- Setelah 1.8 detik, scroll user tidak lagi diganggu.\n";
    echo "- Controller/database/routes/menu/ACL tidak diubah.\n\n";

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
    echo "php tools/check_internal_chat_force_open_latest_v1_2.php\n";
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
