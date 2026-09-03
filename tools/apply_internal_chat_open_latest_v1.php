<?php

declare(strict_types=1);

/**
 * INTERNAL CHAT OPEN LATEST V1
 *
 * Fix:
 * 1. Saat conversation dibuka, backend mengambil 300 PESAN TERBARU,
 *    lalu mengurutkannya kembali ascending untuk display.
 * 2. Setelah halaman selesai render, browser langsung lompat ke pesan terakhir.
 *
 * Hanya mengubah:
 * - InternalChatController.php
 * - chat.blade.php
 *
 * Tidak mengubah database, ACL, menu, route, send/edit/delete logic.
 */

$root = dirname(__DIR__);

$controllerPath =
    $root . '/packages/Webkul/Admin/src/Http/Controllers/InternalCommunication/InternalChatController.php';

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

    return [
        $code,
        implode(PHP_EOL, $output),
    ];
}

echo "INTERNAL CHAT OPEN LATEST V1\n";
echo "============================\n\n";

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

if (!str_contains($controllerOriginal, 'class InternalChatController')) {
    fail('Preflight gagal: InternalChatController tidak dikenali.');
}

if (!str_contains($chatOriginal, 'id="crm-chat-messages"')) {
    fail('Preflight gagal: crm-chat-messages tidak ditemukan.');
}

$stamp = date('Ymd-His');

$controllerBackup =
    $controllerPath . '.bak-internal-chat-open-latest-v1-' . $stamp;

$chatBackup =
    $chatPath . '.bak-internal-chat-open-latest-v1-' . $stamp;

foreach (
    [
        [$controllerPath, $controllerBackup],
        [$chatPath, $chatBackup],
    ]
    as [$source, $backup]
) {
    if (!copy($source, $backup)) {
        fail("Gagal membuat backup: {$backup}");
    }
}

echo "Backup dibuat.\n\n";

try {
    $controller = $controllerOriginal;
    $chat = $chatOriginal;

    /*
    |--------------------------------------------------------------------------
    | 1. CONTROLLER: LOAD LATEST 300, DISPLAY ASCENDING
    |--------------------------------------------------------------------------
    */

    if (
        !str_contains(
            $controller,
            'INTERNAL CHAT OPEN LATEST V1'
        )
    ) {
        $oldBlock =
            <<<'PHP'
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
                    ->orderBy(
                        'id'
                    )
                    ->limit(
                        300
                    )
                    ->get();
PHP;

        $newBlock =
            <<<'PHP'
            /*
             * INTERNAL CHAT OPEN LATEST V1
             *
             * Ambil 300 pesan TERBARU dari database, lalu urutkan kembali
             * ascending agar chronology di UI tetap lama -> baru.
             *
             * Ini penting ketika history sudah >300 pesan. Query lama mengambil
             * 300 pesan PERTAMA sehingga pesan terbaru bahkan tidak ikut render.
             */
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
                        300
                    )
                    ->get()
                    ->sortBy(
                        'id'
                    )
                    ->values();
PHP;

        if (
            substr_count(
                $controller,
                $oldBlock
            ) !== 1
        ) {
            throw new RuntimeException(
                'Anchor query 300 pesan tidak ditemukan tepat 1 kali. '
                . 'Source dibatalkan agar tidak menebak.'
            );
        }

        $controller =
            str_replace(
                $oldBlock,
                $newBlock,
                $controller
            );
    }

    /*
    |--------------------------------------------------------------------------
    | 2. BLADE: INITIAL JUMP TO LATEST
    |--------------------------------------------------------------------------
    |
    | Current appendMessage() already scrolls for newly appended messages,
    | but the current view lost the initial scroll executed at page open.
    |
    */

    if (
        !str_contains(
            $chat,
            'INTERNAL CHAT INITIAL LATEST V1'
        )
    ) {
        $closingAnchor =
            <<<'BLADE'
</x-admin::layouts>
BLADE;

        if (
            substr_count(
                $chat,
                $closingAnchor
            ) !== 1
        ) {
            throw new RuntimeException(
                'Closing x-admin::layouts tidak ditemukan tepat 1 kali.'
            );
        }

        $initialScrollScript =
            <<<'BLADE'

    {{-- INTERNAL CHAT INITIAL LATEST V1 --}}
    @if ($conversation)
        <script>
            (() => {
                const messagesRoot =
                    document.getElementById(
                        'crm-chat-messages'
                    );

                if (! messagesRoot) {
                    return;
                }

                /*
                 * Dua mekanisme sekaligus:
                 * - scrollTop untuk container yang memang scrollable.
                 * - scrollIntoView untuk layout saat browser/page yang menjadi
                 *   scroll container. Screenshot current CRM menunjukkan kasus
                 *   kedua juga bisa terjadi.
                 */
                const jumpToLatest =
                    () => {
                        messagesRoot.scrollTop =
                            messagesRoot.scrollHeight;

                        const messageNodes =
                            messagesRoot.querySelectorAll(
                                '[data-message-id]'
                            );

                        const lastMessage =
                            messageNodes.length
                                ? messageNodes[
                                    messageNodes.length - 1
                                ]
                                : null;

                        if (lastMessage) {
                            lastMessage.scrollIntoView({
                                behavior:
                                    'auto',

                                block:
                                    'end',

                                inline:
                                    'nearest',
                            });
                        }
                    };

                /*
                 * Run after layout, then once more after fonts/attachments have
                 * had time to affect dimensions. No smooth animation, so opening
                 * a long chat feels immediate.
                 */
                window.requestAnimationFrame(
                    () => {
                        window.requestAnimationFrame(
                            jumpToLatest
                        );
                    }
                );

                window.setTimeout(
                    jumpToLatest,
                    120
                );

                window.setTimeout(
                    jumpToLatest,
                    450
                );

                window.addEventListener(
                    'load',
                    jumpToLatest,
                    {
                        once:
                            true,
                    }
                );
            })();
        </script>
    @endif

BLADE;

        $chat =
            str_replace(
                $closingAnchor,
                $initialScrollScript
                . $closingAnchor,
                $chat
            );
    }

    atomicWrite(
        $controllerPath,
        $controller
    );

    atomicWrite(
        $chatPath,
        $chat
    );

    [$controllerLintCode, $controllerLint] =
        phpLint(
            $controllerPath
        );

    if ($controllerLintCode !== 0) {
        throw new RuntimeException(
            "PHP lint controller gagal:\n{$controllerLint}"
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

    $controllerChecks = [
        'INTERNAL CHAT OPEN LATEST V1',
        "->orderByDesc(\n                        'id'\n                    )",
        "->sortBy(\n                        'id'\n                    )",
        '->values();',
    ];

    foreach ($controllerChecks as $needle) {
        if (!str_contains($controllerCheck, $needle)) {
            throw new RuntimeException(
                "Controller validation gagal: "
                . substr($needle, 0, 80)
            );
        }
    }

    $chatChecks = [
        'INTERNAL CHAT INITIAL LATEST V1',
        'jumpToLatest',
        'messagesRoot.scrollHeight',
        "querySelectorAll(\n                                '[data-message-id]'",
        'lastMessage.scrollIntoView',
    ];

    foreach ($chatChecks as $needle) {
        if (!str_contains($chatCheck, $needle)) {
            throw new RuntimeException(
                "Blade validation gagal: "
                . substr($needle, 0, 80)
            );
        }
    }

    echo "Patch PASS.\n";
    echo "- Conversation membuka 300 pesan terbaru, bukan 300 pesan pertama.\n";
    echo "- Urutan display tetap chronological.\n";
    echo "- Saat chat dibuka langsung lompat ke pesan terakhir.\n";
    echo "- Send/edit/delete/polling tidak diubah.\n";
    echo "- Database/menu/ACL tidak diubah.\n\n";

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
    echo "php tools/check_internal_chat_open_latest_v1.php\n";
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
        "Controller dan chat.blade.php dipulihkan dari backup otomatis.\n"
    );

    exit(1);
}
