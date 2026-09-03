<?php

declare(strict_types=1);

/**
 * INTERNAL CHAT INITIAL BOTTOM ONLY V1.5
 *
 * Setelah emergency rollback, UI chat sudah normal lagi.
 * Patch ini sengaja SANGAT KECIL:
 * - tidak mengubah HTML structure
 * - tidak mengubah Blade @if/@endif
 * - tidak mengubah controller
 * - tidak mengubah message ordering
 * - tidak mengubah send/polling/reply/edit/delete
 *
 * Hanya menambahkan 1 script plain-JS sebelum </x-admin::layouts>.
 *
 * Target:
 * - saat room dibuka -> scroll ke paling bawah
 * - chronology tetap lama -> baru
 * - pesan baru tetap lanjut di bawah via logic existing
 */

$root = dirname(__DIR__);

$chatPath =
    $root . '/packages/Webkul/Admin/src/Resources/views/internal-communication/chat.blade.php';

$marker =
    'INTERNAL CHAT INITIAL BOTTOM ONLY V1.5';

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

echo "INTERNAL CHAT INITIAL BOTTOM ONLY V1.5\n";
echo "=====================================\n\n";

if (!is_file($chatPath)) {
    fail("chat.blade.php tidak ditemukan:\n{$chatPath}");
}

$original = file_get_contents($chatPath);

if ($original === false) {
    fail('Gagal membaca chat.blade.php.');
}

if (!str_contains($original, 'id="crm-chat-messages"')) {
    fail('Preflight gagal: crm-chat-messages tidak ditemukan.');
}

if (str_contains($original, $marker)) {
    echo "V1.5 sudah terpasang. Tidak ada perubahan.\n";
    exit(0);
}

$closing = '</x-admin::layouts>';

if (substr_count($original, $closing) !== 1) {
    fail(
        'Preflight gagal: closing </x-admin::layouts> tidak ditemukan tepat 1 kali.'
    );
}

$backup =
    $chatPath
    . '.bak-internal-chat-initial-bottom-only-v1_5-'
    . date('Ymd-His');

if (!copy($chatPath, $backup)) {
    fail("Gagal membuat backup:\n{$backup}");
}

echo "Backup:\n{$backup}\n\n";

$script = <<<'BLADE'

    {{-- INTERNAL CHAT INITIAL BOTTOM ONLY V1.5 --}}
    <script>
        (() => {
            const bootInternalChatBottomOnlyV15 = () => {
                const root =
                    document.getElementById(
                        'crm-chat-messages'
                    );

                if (
                    !root
                    || root.dataset.initialBottomOnlyV15 === '1'
                ) {
                    return;
                }

                root.dataset.initialBottomOnlyV15 =
                    '1';

                /*
                 * Screenshot current UI confirms crm-chat-messages itself
                 * now has a visible vertical scrollbar. So we do not touch
                 * layout anymore. We only own the initial scroll position.
                 */
                const goBottom = () => {
                    root.scrollTop =
                        root.scrollHeight;
                };

                /*
                 * First paint + short settling window.
                 * No MutationObserver, no flex rewrite, no scrollIntoView.
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
                    50,
                    120,
                    250,
                    500,
                    900,
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
                        once: true,
                    }
                );

                window.addEventListener(
                    'pageshow',
                    goBottom,
                    {
                        once: true,
                    }
                );
            };

            if (
                document.readyState === 'loading'
            ) {
                document.addEventListener(
                    'DOMContentLoaded',
                    bootInternalChatBottomOnlyV15,
                    {
                        once: true,
                    }
                );
            } else {
                bootInternalChatBottomOnlyV15();
            }
        })();
    </script>

BLADE;

try {
    $updated =
        str_replace(
            $closing,
            $script . $closing,
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

    foreach (
        [
            $marker,
            'const goBottom',
            'root.scrollTop',
            'root.scrollHeight',
            'DOMContentLoaded',
        ]
        as $needle
    ) {
        if (!str_contains($check, $needle)) {
            throw new RuntimeException(
                "Post-write validation gagal: {$needle}"
            );
        }
    }

    if (substr_count($check, $marker) !== 1) {
        throw new RuntimeException(
            'Marker V1.5 tidak tepat 1 kali.'
        );
    }

    echo "Patch PASS.\n";
    echo "- Hanya initial scroll position yang ditambahkan.\n";
    echo "- Struktur Blade tidak diubah.\n";
    echo "- Chronology tidak diubah.\n";
    echo "- Controller/database/menu/ACL tidak diubah.\n\n";

    chdir($root);

    echo "Membersihkan Laravel cache...\n";

    passthru(
        escapeshellarg(PHP_BINARY)
        . ' '
        . escapeshellarg($root . '/artisan')
        . ' optimize:clear',
        $clearCode
    );

    if ($clearCode !== 0) {
        echo
            "\nPERINGATAN: optimize:clear exit code {$clearCode}.\n";
    }

    echo "\nSELESAI.\n";
    echo "Jalankan checker:\n";
    echo "php tools/check_internal_chat_initial_bottom_only_v1_5.php\n";
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
