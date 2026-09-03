<?php

declare(strict_types=1);

/**
 * INTERNAL CHAT OPEN LATEST V1.1
 *
 * Fix V1:
 * - V1 terlalu mengandalkan exact block formatting controller.
 * - V1.1 mencari query pesan HANYA di method index() dengan pattern whitespace-tolerant.
 * - Blade tidak ditambal berdasarkan layout penuh; hanya initial scroll TERAKHIR
 *   yang diganti dengan jumpToLatest() yang juga memakai scrollIntoView().
 *
 * Target:
 * 1. Initial conversation load = 300 pesan TERBARU.
 * 2. Display tetap oldest -> newest di dalam window 300 pesan terbaru.
 * 3. Saat membuka conversation, viewport langsung ke pesan terakhir.
 *
 * Hanya mengubah:
 * - InternalChatController.php
 * - chat.blade.php
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

echo "INTERNAL CHAT OPEN LATEST V1.1\n";
echo "==============================\n\n";

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

if (
    !str_contains(
        $controllerOriginal,
        'class InternalChatController'
    )
) {
    fail('InternalChatController tidak dikenali.');
}

if (
    !str_contains(
        $chatOriginal,
        'id="crm-chat-messages"'
    )
) {
    fail('crm-chat-messages tidak ditemukan di chat.blade.php.');
}

$stamp = date('Ymd-His');

$controllerBackup =
    $controllerPath . '.bak-internal-chat-open-latest-v1_1-' . $stamp;

$chatBackup =
    $chatPath . '.bak-internal-chat-open-latest-v1_1-' . $stamp;

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
    | CONTROLLER: isolate index() only
    |--------------------------------------------------------------------------
    */

    if (
        !str_contains(
            $controller,
            'INTERNAL CHAT OPEN LATEST V1.1'
        )
    ) {
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
                'Boundary method index()/startDirect() tidak ditemukan.'
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

        /*
         * Source yang ter-upload memang berisi:
         * orderBy('id')->limit(300)->get()
         * tetapi formatting lokal dapat berbeda. Pattern ini sengaja hanya
         * toleran whitespace dan hanya dijalankan di method index().
         */
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
                $queryReplacementCount
            );

        if (
            !is_string($patchedIndex)
            || $queryReplacementCount !== 1
        ) {
            throw new RuntimeException(
                "Query initial 300 pesan tidak ditemukan tepat 1 kali di method index(). "
                . "count="
                . (string) ($queryReplacementCount ?? -1)
            );
        }

        /*
         * Marker placed immediately before the $messages assignment.
         * The query itself is the authoritative behavior.
         */
        $messageAssignmentPos =
            strpos(
                $patchedIndex,
                '$messages =',
                strpos(
                    $patchedIndex,
                    'if ($conversationId > 0)'
                ) ?: 0
            );

        if ($messageAssignmentPos === false) {
            throw new RuntimeException(
                '$messages assignment di conversation block tidak ditemukan.'
            );
        }

        $marker =
            <<<'PHP'
            /*
             * INTERNAL CHAT OPEN LATEST V1.1
             * Initial render mengambil 300 pesan terbaru lalu menampilkannya
             * kembali secara ascending.
             */

PHP;

        $patchedIndex =
            substr(
                $patchedIndex,
                0,
                $messageAssignmentPos
            )
            . $marker
            . substr(
                $patchedIndex,
                $messageAssignmentPos
            );

        $controller =
            $beforeIndex
            . $patchedIndex
            . $afterIndex;
    }

    /*
    |--------------------------------------------------------------------------
    | BLADE: replace only the LAST initial scroll statement
    |--------------------------------------------------------------------------
    |
    | appendMessage() juga memiliki:
    | messagesRoot.scrollTop = messagesRoot.scrollHeight;
    |
    | Itu tetap dipertahankan untuk pesan baru.
    | Yang diganti hanya occurrence TERAKHIR, yaitu initial page-open scroll.
    |
    */

    if (
        !str_contains(
            $chat,
            'INTERNAL CHAT INITIAL LATEST V1.1'
        )
    ) {
        $simpleScrollPattern =
            <<<'JS'
messagesRoot.scrollTop =
                    messagesRoot.scrollHeight;
JS;

        $lastScrollPos =
            strrpos(
                $chat,
                $simpleScrollPattern
            );

        if ($lastScrollPos === false) {
            /*
             * More compact formatting fallback.
             */
            $compactPattern =
                'messagesRoot.scrollTop ='
                . PHP_EOL
                . '                    messagesRoot.scrollHeight;';

            $lastScrollPos =
                strrpos(
                    $chat,
                    $compactPattern
                );

            if ($lastScrollPos === false) {
                throw new RuntimeException(
                    'Initial messagesRoot.scrollTop statement tidak ditemukan.'
                );
            }

            $simpleScrollPattern =
                $compactPattern;
        }

        $jumpBlock =
            <<<'JS'
/* INTERNAL CHAT INITIAL LATEST V1.1 */
                const jumpToLatest = () => {
                    /*
                     * Case A: crm-chat-messages is its own scroll container.
                     */
                    messagesRoot.scrollTop =
                        messagesRoot.scrollHeight;

                    /*
                     * Case B: the page itself is the scroll container.
                     * This is the important fallback for the current CRM layout.
                     */
                    const messageNodes =
                        messagesRoot.querySelectorAll(
                            '[data-message-id]'
                        );

                    const lastMessage =
                        messageNodes.length > 0
                            ? messageNodes[
                                messageNodes.length - 1
                            ]
                            : null;

                    if (! lastMessage) {
                        return;
                    }

                    const internalScrollAvailable =
                        messagesRoot.scrollHeight
                        > messagesRoot.clientHeight + 4;

                    if (! internalScrollAvailable) {
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
                 * First run after layout, then repeat after fonts/attachments
                 * can change the final message height.
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
JS;

        $chat =
            substr(
                $chat,
                0,
                $lastScrollPos
            )
            . $jumpBlock
            . substr(
                $chat,
                $lastScrollPos
                + strlen(
                    $simpleScrollPattern
                )
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

    [$lintCode, $lintOutput] =
        phpLint(
            $controllerPath
        );

    if ($lintCode !== 0) {
        throw new RuntimeException(
            "PHP lint controller gagal:\n{$lintOutput}"
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

    $controllerRequired = [
        'INTERNAL CHAT OPEN LATEST V1.1',
        '->orderByDesc(',
        '->sortBy(',
        '->values();',
    ];

    foreach ($controllerRequired as $needle) {
        if (!str_contains($controllerCheck, $needle)) {
            throw new RuntimeException(
                "Controller validation gagal: {$needle}"
            );
        }
    }

    /*
     * Verify index() no longer uses oldest-first 300 query.
     */
    $checkIndexStart =
        strpos(
            $controllerCheck,
            'public function index('
        );

    $checkIndexEnd =
        strpos(
            $controllerCheck,
            'public function startDirect('
        );

    $checkIndex =
        substr(
            $controllerCheck,
            $checkIndexStart,
            $checkIndexEnd - $checkIndexStart
        );

    if (
        preg_match(
            '~->orderBy\s*\(\s*[\'"]id[\'"]\s*\)'
            . '\s*->limit\s*\(\s*300\s*\)~',
            $checkIndex
        )
    ) {
        throw new RuntimeException(
            'Oldest-first initial 300 query masih ada di index().'
        );
    }

    $chatRequired = [
        'INTERNAL CHAT INITIAL LATEST V1.1',
        'const jumpToLatest',
        'messagesRoot.scrollHeight',
        "querySelectorAll(\n                            '[data-message-id]'",
        'lastMessage.scrollIntoView',
        'internalScrollAvailable',
    ];

    foreach ($chatRequired as $needle) {
        if (!str_contains($chatCheck, $needle)) {
            throw new RuntimeException(
                "Blade validation gagal: "
                . substr($needle, 0, 80)
            );
        }
    }

    echo "Patch PASS.\n";
    echo "- Initial load mengambil 300 pesan terbaru.\n";
    echo "- Display tetap chronological oldest -> newest.\n";
    echo "- Open chat langsung menuju pesan terakhir.\n";
    echo "- appendMessage() scroll existing tetap dipertahankan.\n";
    echo "- Tidak mengubah database/menu/ACL/route.\n\n";

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
        echo "\nPERINGATAN: optimize:clear exit code {$clearCode}.\n";
    }

    echo "\nSELESAI.\n";
    echo "Jalankan checker:\n";
    echo "php tools/check_internal_chat_open_latest_v1_1.php\n";
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
