<?php

declare(strict_types=1);

/**
 * INTERNAL CHAT WHATSAPP FINAL SCROLL V1.4.1
 *
 * Fix V1.4 installer:
 * - V1.1 marker berada DI DALAM main chat script, bukan standalone script.
 * - Karena itu cleanup V1.4 lama tidak pernah menghapusnya.
 *
 * V1.4.1:
 * 1. Pertahankan chronology lama -> baru.
 * 2. Pertahankan V1.3 message stack (short history tetap di bawah).
 * 3. Hapus V1.2 standalone initial script.
 * 4. Hapus V1.3 standalone initial script.
 * 5. Hapus V1.1 embedded jumpToLatest block dengan boundary yang aman:
 *      marker -> updateReadReceipts(...)
 *    lalu restore satu simple scroll statement.
 * 6. Pasang SATU final initial pin-to-bottom script.
 *
 * Hanya mengubah:
 * packages/Webkul/Admin/src/Resources/views/internal-communication/chat.blade.php
 */

$root = dirname(__DIR__);

$chatPath =
    $root . '/packages/Webkul/Admin/src/Resources/views/internal-communication/chat.blade.php';

$finalMarker =
    'INTERNAL CHAT WHATSAPP FINAL SCROLL V1.4.1';

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

function removeStandaloneBladeScript(
    string $source,
    string $marker,
    string $label
): string {
    $markerPos = strpos($source, $marker);

    if ($markerPos === false) {
        echo "- {$label}: 0 block\n";
        return $source;
    }

    /*
     * Standalone legacy patches were wrapped by:
     * @if ($conversation)
     *   <script> ... marker ... </script>
     * @endif
     */
    $ifPos = strrpos(
        substr($source, 0, $markerPos),
        '@if ($conversation)'
    );

    $scriptOpenPos = strrpos(
        substr($source, 0, $markerPos),
        '<script>'
    );

    $scriptClosePos = strpos(
        $source,
        '</script>',
        $markerPos
    );

    if (
        $scriptOpenPos === false
        || $scriptClosePos === false
    ) {
        throw new RuntimeException(
            "{$label}: enclosing script tidak ditemukan."
        );
    }

    $removeStart = $scriptOpenPos;
    $removeEnd =
        $scriptClosePos
        + strlen('</script>');

    /*
     * Include the @if/@endif wrapper only when the @if belongs directly
     * to this standalone patch.
     */
    if (
        $ifPos !== false
        && $ifPos < $scriptOpenPos
        && (
            $scriptOpenPos - $ifPos
        ) < 120
    ) {
        $endifPos = strpos(
            $source,
            '@endif',
            $scriptClosePos
        );

        if (
            $endifPos !== false
            && (
                $endifPos - $scriptClosePos
            ) < 120
        ) {
            $removeStart = $ifPos;
            $removeEnd =
                $endifPos
                + strlen('@endif');
        }
    }

    $updated =
        substr($source, 0, $removeStart)
        . substr($source, $removeEnd);

    echo "- {$label}: 1 block\n";

    return $updated;
}

function restoreEmbeddedV11InitialScroll(
    string $source
): string {
    $marker =
        '/* INTERNAL CHAT INITIAL LATEST V1.1 */';

    $markerPos =
        strpos(
            $source,
            $marker
        );

    if ($markerPos === false) {
        echo "- V1.1 embedded: 0 block\n";
        return $source;
    }

    /*
     * Uploaded/source-audited chat script shows the original order:
     *
     * messagesRoot.scrollTop = messagesRoot.scrollHeight;
     *
     * updateReadReceipts(...)
     *
     * V1.1 replaced ONLY the initial scroll statement with a large
     * jumpToLatest block. Therefore updateReadReceipts is the safest
     * boundary for restoring the original statement.
     */
    $anchorPos =
        strpos(
            $source,
            'updateReadReceipts(',
            $markerPos
        );

    if ($anchorPos === false) {
        throw new RuntimeException(
            'V1.1 embedded boundary updateReadReceipts() tidak ditemukan.'
        );
    }

    /*
     * Do not touch appendMessage() scroll logic earlier in the same script.
     */
    $replacement =
        <<<'JS'
messagesRoot.scrollTop =
                    messagesRoot.scrollHeight;


JS;

    $updated =
        substr($source, 0, $markerPos)
        . $replacement
        . substr($source, $anchorPos);

    echo "- V1.1 embedded: 1 block restored\n";

    return $updated;
}

echo "INTERNAL CHAT WHATSAPP FINAL SCROLL V1.4.1\n";
echo "==========================================\n\n";

if (!is_file($chatPath)) {
    fail("chat.blade.php tidak ditemukan:\n{$chatPath}");
}

$original =
    file_get_contents($chatPath);

if ($original === false) {
    fail('Gagal membaca chat.blade.php.');
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
    if (!str_contains($original, $needle)) {
        fail(
            "Preflight gagal: {$needle} tidak ditemukan. "
            . "Patch dibatalkan agar tidak menebak."
        );
    }
}

if (str_contains($original, $finalMarker)) {
    echo "V1.4.1 sudah terpasang. Tidak ada perubahan.\n";
    exit(0);
}

$stamp =
    date('Ymd-His');

$backup =
    $chatPath
    . '.bak-internal-chat-whatsapp-final-scroll-v1_4_1-'
    . $stamp;

if (!copy($chatPath, $backup)) {
    fail("Gagal membuat backup:\n{$backup}");
}

echo "Backup:\n{$backup}\n\n";

try {
    $chat = $original;

    echo "Cleanup initial-scroll lama:\n";

    /*
     * V1.2 standalone block.
     */
    if (
        str_contains(
            $chat,
            'INTERNAL CHAT FORCE OPEN LATEST V1.2 START'
        )
    ) {
        $start =
            strpos(
                $chat,
                '{{-- INTERNAL CHAT FORCE OPEN LATEST V1.2 START --}}'
            );

        $endMarker =
            '{{-- INTERNAL CHAT FORCE OPEN LATEST V1.2 END --}}';

        $end =
            strpos(
                $chat,
                $endMarker,
                $start
            );

        if ($start === false || $end === false) {
            throw new RuntimeException(
                'Boundary V1.2 tidak lengkap.'
            );
        }

        $end +=
            strlen($endMarker);

        $chat =
            substr($chat, 0, $start)
            . substr($chat, $end);

        echo "- V1.2: 1 block\n";
    } else {
        echo "- V1.2: 0 block\n";
    }

    /*
     * V1.3 standalone initial script only.
     * The V1.3 HTML message stack MUST remain.
     */
    $chat =
        removeStandaloneBladeScript(
            $chat,
            'INTERNAL CHAT WHATSAPP INITIAL BOTTOM V1.3',
            'V1.3 initial'
        );

    /*
     * V1.1 is embedded inside the main conversation script.
     */
    $chat =
        restoreEmbeddedV11InitialScroll(
            $chat
        );

    /*
     * Old V1 standalone script if present.
     */
    if (
        str_contains(
            $chat,
            'INTERNAL CHAT INITIAL LATEST V1 */'
        )
        || str_contains(
            $chat,
            'INTERNAL CHAT INITIAL LATEST V1 --'
        )
    ) {
        $chat =
            removeStandaloneBladeScript(
                $chat,
                'INTERNAL CHAT INITIAL LATEST V1',
                'V1 initial'
            );
    } else {
        echo "- V1 initial: 0 block\n";
    }

    /*
     * Safety: V1.3 structural behavior must still exist.
     */
    foreach (
        [
            'id="crm-chat-message-stack"',
            'class="mt-auto flex w-full flex-col"',
            'id="crm-chat-bottom"',
            'messageStack.appendChild(',
        ]
        as $needle
    ) {
        if (!str_contains($chat, $needle)) {
            throw new RuntimeException(
                "Cleanup mengganggu bottom-stack: {$needle}"
            );
        }
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
            'Closing </x-admin::layouts> tidak ditemukan tepat 1 kali.'
        );
    }

    /*
     * Final initial behavior:
     *
     * - chronology untouched
     * - stack stays bottom-aligned
     * - repeat only during initial settling
     * - user's deliberate history scroll stops the temporary pin
     *
     * No layout resizing here. Screenshot confirms the current message panel
     * is already a real scroll viewport; the remaining bug is competing
     * initial-scroll scripts.
     */
    $finalScript =
        <<<'BLADE'

    {{-- INTERNAL CHAT WHATSAPP FINAL SCROLL V1.4.1 --}}
    @if ($conversation)
        <script>
            (() => {
                const bootWhatsAppFinalScrollV141 = () => {
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
                        || root.dataset.finalScrollV141
                            === '1'
                    ) {
                        return;
                    }

                    root.dataset.finalScrollV141 =
                        '1';

                    /*
                     * Short history stays anchored to composer/bottom.
                     * Chronological order remains untouched.
                     */
                    stack.style.marginTop =
                        'auto';

                    if (
                        'scrollRestoration'
                        in history
                    ) {
                        history.scrollRestoration =
                            'manual';
                    }

                    const atBottom = () =>
                        (
                            root.scrollHeight
                            - root.clientHeight
                            - root.scrollTop
                        )
                        <= 4;

                    const pinToNewest = () => {
                        /*
                         * Root is already the visible scroll panel in the
                         * current CRM layout. Assign twice around one layout
                         * flush to survive late sizing.
                         */
                        root.scrollTop =
                            root.scrollHeight;

                        void root.offsetHeight;

                        root.scrollTop =
                            root.scrollHeight;

                        if (!atBottom()) {
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

                    let userTouchedHistory =
                        false;

                    let interval =
                        null;

                    const stopInitialPin = () => {
                        userTouchedHistory =
                            true;

                        if (interval) {
                            window.clearInterval(
                                interval
                            );

                            interval =
                                null;
                        }
                    };

                    [
                        'wheel',
                        'touchstart',
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

                    /*
                     * Immediate + two animation frames.
                     */
                    pinToNewest();

                    window.requestAnimationFrame(
                        () => {
                            pinToNewest();

                            window.requestAnimationFrame(
                                pinToNewest
                            );
                        }
                    );

                    /*
                     * Retry briefly while browser restores layout/font state.
                     * Stop as soon as user deliberately scrolls history.
                     */
                    const startedAt =
                        Date.now();

                    interval =
                        window.setInterval(
                            () => {
                                if (
                                    userTouchedHistory
                                    || Date.now()
                                        - startedAt
                                        > 1500
                                ) {
                                    if (interval) {
                                        window.clearInterval(
                                            interval
                                        );

                                        interval =
                                            null;
                                    }

                                    return;
                                }

                                pinToNewest();
                            },
                            40
                        );

                    window.addEventListener(
                        'load',
                        () => {
                            if (!userTouchedHistory) {
                                pinToNewest();
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
                            if (!userTouchedHistory) {
                                pinToNewest();
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
                                if (!userTouchedHistory) {
                                    pinToNewest();
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
                        bootWhatsAppFinalScrollV141,
                        {
                            once:
                                true,
                        }
                    );
                } else {
                    bootWhatsAppFinalScrollV141();
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
        'const pinToNewest',
        'root.scrollTop',
        'root.scrollHeight',
        '1500',
        'userTouchedHistory',
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
            'V1.4.1 final marker tidak tepat 1 kali.'
        );
    }

    echo "\nPatch PASS.\n";
    echo "- V1.1 embedded initial block dibersihkan dengan boundary exact.\n";
    echo "- V1.2/V1.3 old initial scripts dibersihkan.\n";
    echo "- Chronology tetap lama -> baru.\n";
    echo "- Newest tetap di bawah.\n";
    echo "- Open room langsung dipaksa ke bawah.\n";
    echo "- Incoming/sent message tetap append di bawah.\n";
    echo "- Tidak mengubah controller/database/menu/ACL.\n\n";

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
    echo "php tools/check_internal_chat_whatsapp_final_scroll_v1_4_1.php\n";
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
