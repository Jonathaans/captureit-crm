<?php

declare(strict_types=1);

/**
 * INTERNAL CHAT NEWEST HOTFIX V1.1
 *
 * Fixes the Blade corruption caused by treating PHP's -> operator as an HTML
 * tag terminator. It also makes opening a conversation and sending a message
 * reliably land on the newest message.
 *
 * Run from the Laravel project root:
 * php tools/apply_internal_chat_newest_hotfix_v1_1.php
 */

$root = dirname(__DIR__);
$stamp = date('Ymd-His');
$suffix = '.bak-internal-chat-newest-hotfix-v1_1-'.$stamp;

$chatPath = $root.'/packages/Webkul/Admin/src/Resources/views/internal-communication/chat.blade.php';
$controllerPath = $root.'/packages/Webkul/Admin/src/Http/Controllers/InternalCommunication/InternalChatController.php';
$manifestPath = $root.'/storage/app/internal_chat_newest_hotfix_v1_1_manifest.json';

function failChatHotfix(string $message, int $code = 1): never
{
    fwrite(STDERR, $message.PHP_EOL);
    exit($code);
}

function atomicWriteChatHotfix(string $path, string $contents): void
{
    $directory = dirname($path);

    if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
        throw new RuntimeException("Gagal membuat directory: {$directory}");
    }

    $temporary = $path.'.tmp-'.bin2hex(random_bytes(4));

    if (file_put_contents($temporary, $contents) === false) {
        @unlink($temporary);
        throw new RuntimeException("Gagal menulis temporary file: {$temporary}");
    }

    if (! rename($temporary, $path)) {
        @unlink($temporary);
        throw new RuntimeException("Gagal mengganti file: {$path}");
    }
}

function commandChatHotfix(string $command): array
{
    exec($command.' 2>&1', $output, $code);

    return [$code, implode(PHP_EOL, $output)];
}

function lintCompiledChatViewHotfix(string $root): array
{
    $matches = [];

    foreach (glob($root.'/storage/framework/views/*.php') ?: [] as $compiledView) {
        $contents = (string) file_get_contents($compiledView);

        if (str_contains($contents, 'newestPanelV11')) {
            $matches[] = $compiledView;
        }
    }

    if (count($matches) !== 1) {
        return [
            1,
            'Compiled Internal Chat view harus ditemukan tepat satu kali; count='.count($matches).'.',
        ];
    }

    return commandChatHotfix(
        escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($matches[0])
    );
}

/**
 * Returns [start, length, tag] for the unique opening tag containing id="$id".
 * The scan only accepts > outside quoted attributes, so Blade/PHP -> operators
 * inside an attribute can never terminate the tag.
 */
function openingTagByIdChatHotfix(string $source, string $id): array
{
    $needle = 'id="'.$id.'"';

    if (substr_count($source, $needle) !== 1) {
        throw new RuntimeException("ID #{$id} harus ditemukan tepat satu kali.");
    }

    $idPosition = strpos($source, $needle);
    $tagStart = strrpos(substr($source, 0, $idPosition), '<');

    if ($tagStart === false) {
        throw new RuntimeException("Pembuka tag #{$id} tidak ditemukan.");
    }

    $quote = null;
    $length = strlen($source);

    for ($index = $tagStart + 1; $index < $length; $index++) {
        $character = $source[$index];

        if ($quote !== null) {
            if ($character === $quote && ($index === 0 || $source[$index - 1] !== '\\')) {
                $quote = null;
            }

            continue;
        }

        if ($character === '"' || $character === "'") {
            $quote = $character;
            continue;
        }

        if ($character === '>') {
            $tagLength = $index - $tagStart + 1;

            return [
                $tagStart,
                $tagLength,
                substr($source, $tagStart, $tagLength),
            ];
        }
    }

    throw new RuntimeException("Penutup tag #{$id} tidak ditemukan.");
}

function setTagAttributeChatHotfix(string $tag, string $name, string $value): string
{
    $pattern = '~\\s+'.preg_quote($name, '~').'="[^"]*"~';
    $replacement = ' '.$name.'="'.$value.'"';

    if (preg_match($pattern, $tag) === 1) {
        $updated = preg_replace($pattern, $replacement, $tag, 1, $count);

        if (! is_string($updated) || $count !== 1) {
            throw new RuntimeException("Gagal memperbarui attribute {$name}.");
        }

        return $updated;
    }

    return substr($tag, 0, -1).$replacement.'>';
}

function replaceOpeningTagByIdChatHotfix(
    string $source,
    string $id,
    array $attributes
): string {
    [$start, $length, $tag] = openingTagByIdChatHotfix($source, $id);

    foreach ($attributes as $name => $value) {
        $tag = setTagAttributeChatHotfix($tag, (string) $name, (string) $value);
    }

    return substr_replace($source, $tag, $start, $length);
}

function repairCorruptedCurrentUserAttributeChatHotfix(string $source): string
{
    $pattern = <<<'REGEX'
~data-current-user-id="\{\{\s*\$currentUser-\s*style="[^"]*"\s*data-chat-newest-scroll-v1="1">id\s*\}\}"~s
REGEX;

    $source = preg_replace(
        $pattern,
        'data-current-user-id="{{ $currentUser->id }}"',
        $source,
        -1,
        $repairCount
    );

    if (! is_string($source)) {
        throw new RuntimeException('Regex repair data-current-user-id gagal.');
    }

    if ($repairCount > 1) {
        throw new RuntimeException(
            "Korupsi data-current-user-id ditemukan {$repairCount} kali; hotfix dibatalkan."
        );
    }

    if (! str_contains($source, 'data-current-user-id="{{ $currentUser->id }}"')) {
        throw new RuntimeException('Atribut data-current-user-id yang valid tidak ditemukan.');
    }

    return $source;
}

function removeNewestScriptsChatHotfix(string $source): string
{
    $patterns = [
        '~\s*\{\{--\s*INTERNAL CHAT NEWEST PANEL V1\.1 HOTFIX\s*--\}\}\s*@if\s*\(\s*\$conversation\s*\)\s*<script>[\s\S]*?</script>\s*@endif\s*~',
        '~\s*\{\{--\s*INTERNAL CHAT NEWEST PANEL V1\s*--\}\}\s*@if\s*\(\s*\$conversation\s*\)\s*<script>[\s\S]*?</script>\s*@endif\s*~',
    ];

    foreach ($patterns as $pattern) {
        $source = preg_replace($pattern, PHP_EOL, $source, -1, $count);

        if (! is_string($source)) {
            throw new RuntimeException('Pembersihan script newest lama gagal.');
        }

        if ($count > 1) {
            throw new RuntimeException('Script newest lama ditemukan lebih dari satu kali.');
        }
    }

    return $source;
}

function ensureChatShellHotfix(string $source): string
{
    $oldShell = '<div class="flex min-h-screen overflow-hidden rounded-xl border bg-white shadow-sm">';
    $newShell = '<div class="flex min-h-0 overflow-hidden rounded-xl border bg-white shadow-sm" style="height:clamp(520px,calc(100dvh - 260px),760px);min-height:0;" data-chat-shell-newest-v1="1">';

    if (str_contains($source, $oldShell)) {
        $source = str_replace($oldShell, $newShell, $source, $count);

        if ($count !== 1) {
            throw new RuntimeException('Shell Internal Chat original tidak unik.');
        }
    } elseif (str_contains($source, 'data-chat-shell-newest-v1="1"')) {
        $pattern = '~<div\s+class="flex\s+min-h-0\s+overflow-hidden\s+rounded-xl\s+border\s+bg-white\s+shadow-sm"\s+style="[^"]*"\s+data-chat-shell-newest-v1="1">~';
        $source = preg_replace($pattern, $newShell, $source, 1, $count);

        if (! is_string($source) || $count !== 1) {
            throw new RuntimeException('Shell Internal Chat hasil patch tidak valid.');
        }
    } else {
        throw new RuntimeException('Shell Internal Chat tidak ditemukan.');
    }

    $source = str_replace(
        '<aside class="{{ $conversation ? \'hidden lg:flex\' : \'flex\' }} w-full flex-col border-r bg-white lg:w-96 lg:flex-none">',
        '<aside class="{{ $conversation ? \'hidden lg:flex\' : \'flex\' }} min-h-0 w-full flex-col overflow-hidden border-r bg-white lg:w-96 lg:flex-none">',
        $source
    );

    $source = str_replace(
        '<section class="{{ $conversation ? \'flex\' : \'hidden lg:flex\' }} w-full min-w-0 flex-1 flex-col bg-gray-50">',
        '<section class="{{ $conversation ? \'flex\' : \'hidden lg:flex\' }} min-h-0 w-full min-w-0 flex-1 flex-col overflow-hidden bg-gray-50">',
        $source
    );

    return $source;
}

function newestScriptChatHotfix(): string
{
    return <<<'BLADE'

    {{-- INTERNAL CHAT NEWEST PANEL V1.1 HOTFIX --}}
    @if ($conversation)
        <script>
            (() => {
                const bootChatNewestV11 = () => {
                    const root = document.getElementById('crm-chat-messages');
                    const stack = document.getElementById('crm-chat-message-stack');
                    const bottom = document.getElementById('crm-chat-bottom');
                    const form = document.getElementById('crm-chat-send-form');

                    if (! root || ! stack || root.dataset.newestPanelV11 === '1') {
                        return;
                    }

                    root.dataset.newestPanelV11 = '1';

                    if ('scrollRestoration' in history) {
                        history.scrollRestoration = 'manual';
                    }

                    let followNewest = true;
                    let programmaticUntil = 0;
                    let pointerActive = false;

                    const distanceFromNewest = () => Math.max(
                        0,
                        root.scrollHeight - root.clientHeight - root.scrollTop
                    );

                    const goNewest = () => {
                        if (! followNewest) {
                            return;
                        }

                        programmaticUntil = Date.now() + 180;
                        root.scrollTop = root.scrollHeight;

                        if (bottom) {
                            bottom.scrollIntoView({
                                behavior: 'auto',
                                block: 'end',
                                inline: 'nearest',
                            });
                        }

                        root.scrollTop = root.scrollHeight;
                    };

                    const scheduleNewest = () => {
                        window.requestAnimationFrame(() => {
                            goNewest();
                            window.requestAnimationFrame(goNewest);
                        });

                        [0, 60, 160, 350, 700, 1200, 2000].forEach((delay) => {
                            window.setTimeout(goNewest, delay);
                        });
                    };

                    const guardNewest = (duration = 2600) => {
                        const until = Date.now() + duration;
                        const timer = window.setInterval(() => {
                            if (! followNewest || Date.now() >= until) {
                                window.clearInterval(timer);
                                return;
                            }

                            goNewest();
                        }, 140);
                    };

                    root.addEventListener('wheel', (event) => {
                        if (event.deltaY < 0) {
                            followNewest = false;
                        } else if (distanceFromNewest() <= 96) {
                            followNewest = true;
                        }
                    }, { passive: true });

                    let touchY = null;

                    root.addEventListener('touchstart', (event) => {
                        touchY = event.touches?.[0]?.clientY ?? null;
                    }, { passive: true });

                    root.addEventListener('touchmove', (event) => {
                        const currentY = event.touches?.[0]?.clientY ?? null;

                        if (touchY !== null && currentY !== null && currentY > touchY + 4) {
                            followNewest = false;
                        }
                    }, { passive: true });

                    root.addEventListener('pointerdown', () => {
                        pointerActive = true;
                    }, { passive: true });

                    window.addEventListener('pointerup', () => {
                        pointerActive = false;
                    }, { passive: true });

                    root.addEventListener('scroll', () => {
                        if (Date.now() < programmaticUntil) {
                            return;
                        }

                        const nearNewest = distanceFromNewest() <= 64;

                        if (nearNewest) {
                            followNewest = true;
                        } else if (pointerActive) {
                            followNewest = false;
                        }
                    }, { passive: true });

                    if (form) {
                        form.addEventListener('submit', () => {
                            followNewest = true;
                            scheduleNewest();
                            guardNewest(3200);
                        }, true);
                    }

                    new MutationObserver(() => {
                        if (followNewest) {
                            scheduleNewest();
                        }
                    }).observe(stack, { childList: true, subtree: true });

                    if ('ResizeObserver' in window) {
                        new ResizeObserver(() => {
                            if (followNewest) {
                                goNewest();
                            }
                        }).observe(stack);
                    }

                    window.crmChatGoNewest = () => {
                        followNewest = true;
                        scheduleNewest();
                        guardNewest(2600);
                    };

                    followNewest = true;
                    scheduleNewest();
                    guardNewest(3600);
                    window.addEventListener('load', scheduleNewest, { once: true });
                    window.addEventListener('pageshow', scheduleNewest);
                    document.fonts?.ready?.then(scheduleNewest);
                };

                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', bootChatNewestV11, { once: true });
                } else {
                    bootChatNewestV11();
                }
            })();
        </script>
    @endif

BLADE;
}

function patchChatHotfix(string $source): string
{
    $source = repairCorruptedCurrentUserAttributeChatHotfix($source);
    $source = ensureChatShellHotfix($source);

    $source = replaceOpeningTagByIdChatHotfix(
        $source,
        'crm-chat-messages',
        [
            'class' => 'flex min-h-0 flex-1 flex-col overflow-y-auto bg-gray-100 p-5',
            'style' => 'min-height:0;overscroll-behavior:contain;scroll-behavior:auto;',
            'data-chat-newest-scroll-v1' => '1',
        ]
    );

    $source = replaceOpeningTagByIdChatHotfix(
        $source,
        'crm-chat-message-stack',
        [
            'class' => 'mt-auto flex w-full shrink-0 flex-col',
            'style' => 'flex-shrink:0;',
            'data-chat-newest-stack-v1' => '1',
        ]
    );

    $source = removeNewestScriptsChatHotfix($source);
    $closing = strrpos($source, '</x-admin::layouts>');

    if ($closing === false) {
        throw new RuntimeException('Penutup layout Internal Chat tidak ditemukan.');
    }

    $source = substr_replace($source, newestScriptChatHotfix(), $closing, 0);

    [$rootStart, $rootLength, $rootTag] = openingTagByIdChatHotfix(
        $source,
        'crm-chat-messages'
    );

    if (! str_contains($rootTag, 'data-current-user-id="{{ $currentUser->id }}"')
        || ! str_contains($rootTag, 'data-read-up-to-id=')
        || ! str_contains($rootTag, 'data-sync-at=')
        || ! str_contains($rootTag, 'data-action-base=')) {
        throw new RuntimeException(
            "Opening tag chat tidak utuh setelah patch (offset {$rootStart}, length {$rootLength})."
        );
    }

    return $source;
}

echo "INTERNAL CHAT NEWEST HOTFIX V1.1\n";
echo "=================================\n\n";

foreach ([$chatPath, $controllerPath] as $path) {
    if (! is_file($path)) {
        failChatHotfix("File wajib tidak ditemukan: {$path}");
    }
}

$originalChat = file_get_contents($chatPath);
$controller = (string) file_get_contents($controllerPath);

if ($originalChat === false) {
    failChatHotfix('Gagal membaca chat.blade.php.');
}

if (! preg_match(
    '~->orderByDesc\s*\(\s*[\'"]id[\'"]\s*\)[\s\S]*?->limit\s*\(\s*50\s*\)[\s\S]*?->get\s*\(\s*\)[\s\S]*?->sortBy\s*\(\s*[\'"]id[\'"]\s*\)~',
    $controller
)) {
    failChatHotfix('Preflight gagal: backend latest-50 ascending tidak ditemukan.');
}

$backupPath = $chatPath.$suffix;
$manifest = [
    'version' => 'internal-chat-newest-hotfix-v1_1',
    'created_at' => date(DATE_ATOM),
    'files' => [
        ['path' => $chatPath, 'backup' => $backupPath],
    ],
];

try {
    $updatedChat = patchChatHotfix($originalChat);

    if (! copy($chatPath, $backupPath)) {
        throw new RuntimeException("Gagal membuat backup: {$backupPath}");
    }

    atomicWriteChatHotfix($chatPath, $updatedChat);
    atomicWriteChatHotfix(
        $manifestPath,
        json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL
    );

    chdir($root);

    [$clearCode, $clearOutput] = commandChatHotfix(
        escapeshellarg(PHP_BINARY).' '.escapeshellarg($root.'/artisan').' view:clear'
    );

    if ($clearCode !== 0) {
        throw new RuntimeException("view:clear gagal:\n{$clearOutput}");
    }

    [$viewCode, $viewOutput] = commandChatHotfix(
        escapeshellarg(PHP_BINARY).' '.escapeshellarg($root.'/artisan').' view:cache'
    );

    if ($viewCode !== 0) {
        throw new RuntimeException("Blade compile gagal:\n{$viewOutput}");
    }

    /* view:cache compiles Blade but does not PHP-lint the generated file. */
    [$compiledLintCode, $compiledLintOutput] = lintCompiledChatViewHotfix($root);

    if ($compiledLintCode !== 0) {
        throw new RuntimeException(
            "Compiled Internal Chat PHP lint gagal:\n{$compiledLintOutput}"
        );
    }

    [$optimizeCode, $optimizeOutput] = commandChatHotfix(
        escapeshellarg(PHP_BINARY).' '.escapeshellarg($root.'/artisan').' optimize:clear'
    );

    echo "[OK] Syntax chat Blade yang rusak diperbaiki.\n";
    echo "[OK] Opening tag chat diproses tanpa salah membaca operator ->.\n";
    echo "[OK] Open conversation otomatis menuju pesan terbaru.\n";
    echo "[OK] Setelah send otomatis mengikuti pesan terbaru.\n";
    echo "[OK] Blade Internal Chat berhasil dikompilasi dan PHP-lint.\n";

    if ($optimizeCode !== 0) {
        echo "[WARN] optimize:clear gagal; jalankan manual bila perlu:\n{$optimizeOutput}\n";
    }

    echo "\nHOTFIX BERHASIL.\n";
    echo "Jalankan checker:\n";
    echo "php tools/check_internal_chat_newest_hotfix_v1_1.php\n";
} catch (Throwable $e) {
    if (is_file($backupPath)) {
        @copy($backupPath, $chatPath);
    }

    if (is_file($root.'/artisan')) {
        chdir($root);
        commandChatHotfix(
            escapeshellarg(PHP_BINARY).' '.escapeshellarg($root.'/artisan').' view:clear'
        );
    }

    fwrite(STDERR, "\nHOTFIX GAGAL: {$e->getMessage()}\n");
    fwrite(STDERR, "chat.blade.php dipulihkan dari backup.\n");
    exit(1);
}
