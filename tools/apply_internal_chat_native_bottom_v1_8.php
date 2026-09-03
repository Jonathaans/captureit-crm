<?php

declare(strict_types=1);

/**
 * INTERNAL CHAT NATIVE BOTTOM V1.8
 *
 * CLEAN structural solution. No new runtime scroll loop.
 *
 * Current diagnostic already proves:
 * - backend loads latest 50
 * - messages are rendered OLD -> NEW
 * - new messages append to #crm-chat-message-stack
 *
 * Problem:
 * browser still opens the scroll viewport at its physical top.
 *
 * Solution:
 * Make #crm-chat-messages a column-reverse SCROLL VIEWPORT, while keeping
 * the ONE inner #crm-chat-message-stack in normal flex-column order.
 *
 * Important:
 * This DOES NOT reverse message order.
 *
 * Outer viewport:
 *   flex-direction: column-reverse
 *
 * Inner stack:
 *   flex-direction: column
 *   oldest ... newest
 *
 * Because the outer viewport has only ONE stack child:
 * - initial native position = bottom
 * - latest remains visually at bottom
 * - scrolling upward reveals older history
 * - no setTimeout/poll race is needed
 *
 * Only modifies chat.blade.php CSS classes and removes prior standalone
 * scroll experiments if present.
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

function removeStandaloneScript(string $source, string $marker): string
{
    while (($markerPos = strpos($source, $marker)) !== false) {
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
            break;
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
            && ($scriptOpen - $ifPos) < 240
        ) {
            $endifPos =
                strpos(
                    $source,
                    '@endif',
                    $scriptClose
                );

            if (
                $endifPos !== false
                && ($endifPos - $scriptClose) < 240
            ) {
                $start = $ifPos;
                $end = $endifPos + strlen('@endif');
            }
        }

        $source =
            substr($source, 0, $start)
            . substr($source, $end);
    }

    return $source;
}

echo "INTERNAL CHAT NATIVE BOTTOM V1.8\n";
echo "================================\n\n";

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
| Backend preflight: it must already be latest 50
|--------------------------------------------------------------------------
*/

$indexStart = strpos($controller, 'public function index(');
$indexEnd = strpos($controller, 'public function startDirect(');

if (
    $indexStart === false
    || $indexEnd === false
    || $indexEnd <= $indexStart
) {
    fail('Boundary controller index() tidak ditemukan.');
}

$index =
    substr(
        $controller,
        $indexStart,
        $indexEnd - $indexStart
    );

$latest50 =
    preg_match(
        '~->orderByDesc\s*\(\s*[\'"]id[\'"]\s*\)[\s\S]*?'
        . '->limit\s*\(\s*50\s*\)[\s\S]*?'
        . '->get\s*\(\s*\)[\s\S]*?'
        . '->sortBy\s*\(\s*[\'"]id[\'"]\s*\)[\s\S]*?'
        . '->values\s*\(\s*\)~',
        $index
    ) === 1;

echo
    ($latest50 ? '[OK]   ' : '[FAIL] ')
    . "Backend latest 50 + ascending display\n";

if (!$latest50) {
    fail(
        'Backend belum latest-50. V1.8 dibatalkan dan controller tidak disentuh.'
    );
}

/*
|--------------------------------------------------------------------------
| Structural preflight
|--------------------------------------------------------------------------
*/

$oldRootClass =
    'class="flex flex-1 flex-col overflow-y-auto bg-gray-100 p-5"';

$newRootClass =
    'class="flex min-h-0 flex-1 flex-col-reverse overflow-y-auto bg-gray-100 p-5"';

$oldStackClass =
    'class="mt-auto flex w-full flex-col"';

$newStackClass =
    'class="flex w-full shrink-0 flex-col"';

$alreadyNative =
    str_contains(
        $chat,
        $newRootClass
    )
    && str_contains(
        $chat,
        $newStackClass
    );

if ($alreadyNative) {
    echo "[OK]   V1.8 native bottom classes sudah terpasang.\n";
    exit(0);
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
    if (!str_contains($chat, $needle)) {
        fail(
            "Preflight gagal: {$needle} tidak ditemukan."
        );
    }
}

if (!str_contains($chat, $oldRootClass)) {
    fail(
        "Class root expected tidak ditemukan:\n{$oldRootClass}\n"
        . "Patch dibatalkan agar tidak menebak."
    );
}

if (!str_contains($chat, $oldStackClass)) {
    fail(
        "Class stack expected tidak ditemukan:\n{$oldStackClass}\n"
        . "Patch dibatalkan agar tidak menebak."
    );
}

$stamp = date('Ymd-His');

$backup =
    $chatPath
    . '.bak-native-bottom-v1_8-'
    . $stamp;

if (!copy($chatPath, $backup)) {
    fail("Gagal membuat backup: {$backup}");
}

echo "Backup:\n{$backup}\n\n";

try {
    /*
     * Remove only standalone experiments. We keep the main existing chat JS,
     * including its normal append behavior and simple scroll assignment.
     */
    foreach (
        [
            'INTERNAL CHAT SCROLL GUARD LATEST50 V1.7',
            'INTERNAL CHAT LATEST50 BOTTOM V1.5',
            'INTERNAL CHAT WHATSAPP FINAL SCROLL V1.4.1',
            'INTERNAL CHAT WHATSAPP FINAL SCROLL V1.4',
            'INTERNAL CHAT WHATSAPP INITIAL BOTTOM V1.3',
            'INTERNAL CHAT FORCE OPEN LATEST V1.2 START',
        ]
        as $marker
    ) {
        $chat =
            removeStandaloneScript(
                $chat,
                $marker
            );
    }

    /*
     * Exact 2-class structural change.
     */
    $chat =
        str_replace(
            $oldRootClass,
            $newRootClass,
            $chat,
            $rootCount
        );

    $chat =
        str_replace(
            $oldStackClass,
            $newStackClass,
            $chat,
            $stackCount
        );

    if ($rootCount !== 1) {
        throw new RuntimeException(
            "Root class replacement count={$rootCount}, expected 1."
        );
    }

    if ($stackCount !== 1) {
        throw new RuntimeException(
            "Stack class replacement count={$stackCount}, expected 1."
        );
    }

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
            'Gagal membaca hasil patch.'
        );
    }

    $checks = [
        'root flex-col-reverse' =>
            str_contains(
                $check,
                $newRootClass
            ),

        'stack normal flex-col + shrink-0' =>
            str_contains(
                $check,
                $newStackClass
            ),

        'dynamic append preserved' =>
            str_contains(
                $check,
                'messageStack.appendChild('
            ),

        'bottom sentinel preserved' =>
            str_contains(
                $check,
                'id="crm-chat-bottom"'
            ),
    ];

    foreach ($checks as $label => $ok) {
        if (!$ok) {
            throw new RuntimeException(
                "Post-write validation gagal: {$label}"
            );
        }
    }

    echo "Patch PASS.\n";
    echo "- Tidak ada message yang dibalik.\n";
    echo "- Inner stack tetap OLD -> NEW.\n";
    echo "- Outer scroll viewport sekarang column-reverse.\n";
    echo "- Browser native start position menjadi bottom/newest.\n";
    echo "- Tidak ada timer/MutationObserver/scroll guard baru.\n";
    echo "- Controller latest-50 tidak diubah.\n\n";

    chdir($root);

    passthru(
        escapeshellarg(PHP_BINARY)
        . ' '
        . escapeshellarg($root . '/artisan')
        . ' view:clear'
    );

    echo "\nSELESAI.\n";
    echo "Jalankan checker:\n";
    echo "php tools/check_internal_chat_native_bottom_v1_8.php\n";
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
