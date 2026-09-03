<?php

declare(strict_types=1);

/**
 * INTERNAL CHAT WHATSAPP FINAL SCROLL V1.4
 *
 * Target behavior:
 * 1. Chronology tetap normal: lama -> baru.
 * 2. Newest selalu berada di bawah.
 * 3. Saat room dibuka, viewport langsung berada di bawah.
 * 4. Pesan berikutnya tetap append ke bawah.
 * 5. Setelah user mulai scroll manual, initial auto-pin berhenti.
 *
 * V1.4 membersihkan script initial-scroll eksperimen sebelumnya
 * (V1.1, V1.2, V1.3 initial block), lalu memasang SATU authoritative
 * viewport/initial-scroll controller.
 *
 * V1.3 message stack tetap dipertahankan karena itu yang membuat short history
 * menempel ke bawah seperti WhatsApp.
 *
 * Hanya mengubah:
 * packages/Webkul/Admin/src/Resources/views/internal-communication/chat.blade.php
 */

$root = dirname(__DIR__);

$chatPath =
    $root . '/packages/Webkul/Admin/src/Resources/views/internal-communication/chat.blade.php';

$finalMarker =
    'INTERNAL CHAT WHATSAPP FINAL SCROLL V1.4';

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

function removeBlock(
    string $source,
    string $pattern,
    string $label
): string {
    $updated =
        preg_replace(
            $pattern,
            '',
            $source,
            -1,
            $count
        );

    if (!is_string($updated)) {
        throw new RuntimeException(
            "Gagal membersihkan {$label}."
        );
    }

    echo
        "- cleanup {$label}: {$count} block\n";

    return $updated;
}

echo "INTERNAL CHAT WHATSAPP FINAL SCROLL V1.4\n";
echo "========================================\n\n";

if (!is_file($chatPath)) {
    fail(
        "chat.blade.php tidak ditemukan:\n{$chatPath}"
    );
}

$original =
    file_get_contents(
        $chatPath
    );

if ($original === false) {
    fail(
        'Gagal membaca chat.blade.php.'
    );
}

if (
    !str_contains(
        $original,
        'id="crm-chat-messages"'
    )
) {
    fail(
        'Preflight gagal: crm-chat-messages tidak ditemukan.'
    );
}

if (
    !str_contains(
        $original,
        'id="crm-chat-message-stack"'
    )
    || !str_contains(
        $original,
        'id="crm-chat-bottom"'
    )
) {
    fail(
        'Preflight gagal: V1.3 message stack belum ditemukan. '
        . 'Patch dibatalkan agar tidak menebak struktur HTML.'
    );
}

if (
    !str_contains(
        $original,
        'messageStack.appendChild('
    )
) {
    fail(
        'Preflight gagal: dynamic append belum memakai messageStack.'
    );
}

if (
    str_contains(
        $original,
        $finalMarker
    )
) {
    echo "V1.4 sudah terpasang. Tidak ada perubahan.\n";
    exit(0);
}

$stamp =
    date(
        'Ymd-His'
    );

$backup =
    $chatPath
    . '.bak-internal-chat-whatsapp-final-scroll-v1_4-'
    . $stamp;

if (!copy($chatPath, $backup)) {
    fail(
        "Gagal membuat backup:\n{$backup}"
    );
}

echo "Backup:\n{$backup}\n\n";

try {
    $chat =
        $original;

    echo "Membersihkan initial-scroll patch lama:\n";

    /*
     * V1.2 has explicit START/END markers.
     */
    $chat =
        removeBlock(
            $chat,
            '~\s*\{\{-- INTERNAL CHAT FORCE OPEN LATEST V1\.2 START --\}\}[\s\S]*?\{\{-- INTERNAL CHAT FORCE OPEN LATEST V1\.2 END --\}\}\s*~',
            'V1.2'
        );

    /*
     * V1.3 initial block is separate from the V1.3 HTML stack.
     * IMPORTANT: only remove the INITIAL script, not messageStack HTML/append.
     */
    $chat =
        removeBlock(
            $chat,
            '~\s*\{\{-- INTERNAL CHAT WHATSAPP INITIAL BOTTOM V1\.3 --\}\}\s*@if\s*\(\$conversation\)[\s\S]*?@endif\s*~',
            'V1.3 initial'
        );

    /*
     * Remove prior V1.1 initial script if it is still present.
     */
    $chat =
        removeBlock(
            $chat,
            '~\s*\{\{-- INTERNAL CHAT INITIAL LATEST V1\.1 --\}\}\s*@if\s*\(\$conversation\)[\s\S]*?@endif\s*~',
            'V1.1 initial'
        );

    /*
     * Older V1 marker, if ever applied.
     */
    $chat =
        removeBlock(
            $chat,
            '~\s*\{\{-- INTERNAL CHAT INITIAL LATEST V1 --\}\}\s*@if\s*\(\$conversation\)[\s\S]*?@endif\s*~',
            'V1 initial'
        );

    /*
     * Guard: HTML stack MUST remain after cleanup.
     */
    if (
        !str_contains(
            $chat,
            'id="crm-chat-message-stack"'
        )
        || !str_contains(
            $chat,
            'id="crm-chat-bottom"'
        )
        || !str_contains(
            $chat,
            'messageStack.appendChild('
        )
    ) {
        throw new RuntimeException(
            'Cleanup mengganggu V1.3 message stack. Rollback.'
        );
    }

    $closing =
        '</x-admin::layouts>';

    if (
        substr_count(
            $chat,
            $closing
        ) !== 1
    ) {
        throw new RuntimeException(
            'Closing x-admin::layouts tidak ditemukan tepat 1 kali.'
        );
    }

    $finalScript =
        <<<'BLADE'

    {{-- INTERNAL CHAT WHATSAPP FINAL SCROLL V1.4 --}}
    @if ($conversation)
        <script>
            (() => {
                const bootWhatsAppFinalV14 = () => {
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

                    const section =
                        root?.closest(
                            'section'
                        );

                    const shell =
                        section?.parentElement
                        || null;

                    if (
                        !root
                        || !stack
                        || !bottom
                        || !section
                        || !shell
                    ) {
                        return;
                    }

                    if (
                        root.dataset.whatsappFinalV14
                        === '1'
                    ) {
                        return;
                    }

                    root.dataset.whatsappFinalV14 =
                        '1';

                    /*
                     * Browser scroll restoration can race with our own
                     * initial position after navigation/back-forward.
                     */
                    if (
                        'scrollRestoration'
                        in history
                    ) {
                        history.scrollRestoration =
                            'manual';
                    }

                    /*
                     * Short history:
                     * mt-auto on #crm-chat-message-stack pushes the whole
                     * chronological stack to the bottom.
                     *
                     * Long history:
                     * stack overflows normally and root becomes the scroll
                     * viewport.
                     */
                    stack.style.marginTop =
                        'auto';

                    stack.style.flexShrink =
                        '0';

                    /*
                     * Build ONE bounded chat viewport.
                     * Without min-height:0, flex children can expand the page
                     * instead of scrolling inside #crm-chat-messages.
                     */
                    const sizeViewport = () => {
                        const shellTop =
                            Math.max(
                                0,
                                shell
                                    .getBoundingClientRect()
                                    .top
                            );

                        const footerSafe =
                            72;

                        const available =
                            Math.max(
                                420,
                                window.innerHeight
                                - shellTop
                                - footerSafe
                            );

                        shell.style.height =
                            `${available}px`;

                        shell.style.maxHeight =
                            `${available}px`;

                        shell.style.minHeight =
                            '0';

                        shell.style.overflow =
                            'hidden';

                        section.style.height =
                            '100%';

                        section.style.minHeight =
                            '0';

                        section.style.overflow =
                            'hidden';

                        root.style.flex =
                            '1 1 auto';

                        root.style.minHeight =
                            '0';

                        root.style.overflowY =
                            'auto';

                        root.style.overflowX =
                            'hidden';

                        root.style.overscrollBehavior =
                            'contain';

                        /*
                         * Prevent browser scroll anchoring from pulling the
                         * viewport back toward older content while we establish
                         * initial position.
                         */
                        root.style.overflowAnchor =
                            'none';
                    };

                    const distanceFromBottom = () =>
                        Math.max(
                            0,
                            root.scrollHeight
                            - root.clientHeight
                            - root.scrollTop
                        );

                    const forceBottom = () => {
                        sizeViewport();

                        /*
                         * Flush layout after height/min-height changes.
                         */
                        void root.offsetHeight;

                        root.scrollTop =
                            root.scrollHeight;

                        /*
                         * Normally scrollTop is enough now that root is the
                         * real scroll container. Use the sentinel only as a
                         * fallback when a browser still reports a gap.
                         */
                        if (
                            distanceFromBottom()
                            > 3
                        ) {
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
                        }
                    };

                    /*
                     * Initial auto-pin is temporary.
                     * It runs long enough to survive:
                     * - admin layout paint
                     * - font settling
                     * - attachment dimensions
                     * - browser scroll restoration
                     *
                     * As soon as the user deliberately interacts with the
                     * message viewport, we stop forcing position.
                     */
                    let userInteracted =
                        false;

                    let timer =
                        null;

                    const stopInitialPin = () => {
                        userInteracted =
                            true;

                        if (timer) {
                            window.clearInterval(
                                timer
                            );

                            timer =
                                null;
                        }
                    };

                    [
                        'wheel',
                        'touchstart',
                        'pointerdown',
                    ].forEach(
                        (eventName) => {
                            root.addEventListener(
                                eventName,
                                stopInitialPin,
                                {
                                    once:
                                        true,

                                    passive:
                                        true,
                                }
                            );
                        }
                    );

                    window.requestAnimationFrame(
                        () => {
                            window.requestAnimationFrame(
                                forceBottom
                            );
                        }
                    );

                    forceBottom();

                    const startedAt =
                        Date.now();

                    timer =
                        window.setInterval(
                            () => {
                                if (
                                    userInteracted
                                    || Date.now()
                                        - startedAt
                                        > 2200
                                ) {
                                    if (timer) {
                                        window.clearInterval(
                                            timer
                                        );

                                        timer =
                                            null;
                                    }

                                    /*
                                     * Normal browser anchoring may resume after
                                     * initial room positioning is complete.
                                     */
                                    root.style.overflowAnchor =
                                        '';

                                    return;
                                }

                                forceBottom();
                            },
                            50
                        );

                    document.fonts
                        ?.ready
                        ?.then(
                            () => {
                                if (
                                    !userInteracted
                                ) {
                                    forceBottom();
                                }
                            }
                        );

                    window.addEventListener(
                        'load',
                        () => {
                            if (
                                !userInteracted
                            ) {
                                forceBottom();
                            }
                        },
                        {
                            once:
                                true,
                        }
                    );

                    window.addEventListener(
                        'pageshow',
                        () => {
                            if (
                                !userInteracted
                            ) {
                                forceBottom();
                            }
                        },
                        {
                            once:
                                true,
                        }
                    );

                    let resizeTimer =
                        null;

                    window.addEventListener(
                        'resize',
                        () => {
                            window.clearTimeout(
                                resizeTimer
                            );

                            resizeTimer =
                                window.setTimeout(
                                    () => {
                                        const wasNearBottom =
                                            distanceFromBottom()
                                            <= 24;

                                        sizeViewport();

                                        if (
                                            wasNearBottom
                                        ) {
                                            root.scrollTop =
                                                root.scrollHeight;
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
                        bootWhatsAppFinalV14,
                        {
                            once:
                                true,
                        }
                    );
                } else {
                    bootWhatsAppFinalV14();
                }
            })();
        </script>
    @endif

BLADE;

    $chat =
        str_replace(
            $closing,
            $finalScript
            . $closing,
            $chat
        );

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
        $finalMarker,
        'id="crm-chat-message-stack"',
        'id="crm-chat-bottom"',
        'messageStack.appendChild(',
        'const sizeViewport',
        'const forceBottom',
        'root.scrollTop',
        'root.scrollHeight',
        '2200',
        'userInteracted',
        'history.scrollRestoration',
    ];

    foreach ($required as $needle) {
        if (!str_contains($check, $needle)) {
            throw new RuntimeException(
                "Post-write validation gagal: {$needle}"
            );
        }
    }

    $forbidden = [
        'INTERNAL CHAT FORCE OPEN LATEST V1.2 START',
        'INTERNAL CHAT WHATSAPP INITIAL BOTTOM V1.3',
        'INTERNAL CHAT INITIAL LATEST V1.1',
    ];

    foreach ($forbidden as $needle) {
        if (str_contains($check, $needle)) {
            throw new RuntimeException(
                "Script konflik lama masih ada: {$needle}"
            );
        }
    }

    if (
        substr_count(
            $check,
            $finalMarker
        ) !== 1
    ) {
        throw new RuntimeException(
            'Final V1.4 marker tidak tepat 1 kali.'
        );
    }

    echo "\nPatch PASS.\n";
    echo "- Chronology tetap lama -> baru.\n";
    echo "- Short history menempel ke bawah.\n";
    echo "- Room open dipaksa ke newest selama initial settle.\n";
    echo "- Pesan baru tetap append ke bawah via messageStack.\n";
    echo "- User scroll manual tidak ditarik kembali setelah interaction.\n";
    echo "- Script initial-scroll V1.1/V1.2/V1.3 lama dibersihkan.\n";
    echo "- Controller/database/menu/ACL tidak diubah.\n\n";

    chdir(
        $root
    );

    echo "Membersihkan Laravel cache...\n";

    passthru(
        escapeshellarg(
            PHP_BINARY
        )
        . ' '
        . escapeshellarg(
            $root
            . '/artisan'
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
    echo "php tools/check_internal_chat_whatsapp_final_scroll_v1_4.php\n";
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
