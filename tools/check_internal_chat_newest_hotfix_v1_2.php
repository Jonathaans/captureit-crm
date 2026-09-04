<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$chatPath = $root.'/packages/Webkul/Admin/src/Resources/views/internal-communication/chat.blade.php';
$controllerPath = $root.'/packages/Webkul/Admin/src/Http/Controllers/InternalCommunication/InternalChatController.php';
$failed = 0;

function checkChatHotfix(bool $ok, string $label): void
{
    global $failed;
    echo ($ok ? '[OK]   ' : '[FAIL] ').$label.PHP_EOL;

    if (! $ok) {
        $failed++;
    }
}

function commandCheckChatHotfix(string $command): array
{
    exec($command.' 2>&1', $output, $code);

    return [$code, implode(PHP_EOL, $output)];
}

function compileAndLintChatViewCheck(string $root, string $bladeSource): array
{
    if (! is_file($root.'/vendor/autoload.php') || ! is_file($root.'/bootstrap/app.php')) {
        return [1, 'vendor/autoload.php atau bootstrap/app.php tidak ditemukan.'];
    }

    require_once $root.'/vendor/autoload.php';

    $app = require $root.'/bootstrap/app.php';
    $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
    $kernel->bootstrap();
    $compiler = $app->make('blade.compiler');
    $compiled = $compiler->compileString($bladeSource);
    $temporary = $root.'/storage/app/internal-chat-check-lint-'.bin2hex(random_bytes(4)).'.php';

    if (file_put_contents($temporary, $compiled) === false) {
        return [1, "Gagal menulis compiled Blade sementara: {$temporary}"];
    }

    try {
        return commandCheckChatHotfix(
            escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($temporary)
        );
    } finally {
        @unlink($temporary);
    }
}

function openingTagCheckChatHotfix(string $source, string $id): ?string
{
    $needle = 'id="'.$id.'"';

    if (substr_count($source, $needle) !== 1) {
        return null;
    }

    $position = strpos($source, $needle);
    $start = strrpos(substr($source, 0, $position), '<');

    if ($start === false) {
        return null;
    }

    $quote = null;

    for ($index = $start + 1, $length = strlen($source); $index < $length; $index++) {
        $character = $source[$index];

        if ($quote !== null) {
            if ($character === $quote && $source[$index - 1] !== '\\') {
                $quote = null;
            }

            continue;
        }

        if ($character === '"' || $character === "'") {
            $quote = $character;
        } elseif ($character === '>') {
            return substr($source, $start, $index - $start + 1);
        }
    }

    return null;
}

echo "CHECK INTERNAL CHAT NEWEST HOTFIX V1.2\n";
echo "=======================================\n\n";
echo "VALIDATION MODE: DIRECT PACKAGE BLADE COMPILE\n\n";

checkChatHotfix(is_file($chatPath), 'Chat View tersedia');
checkChatHotfix(is_file($controllerPath), 'Chat Controller tersedia');

$chat = is_file($chatPath) ? (string) file_get_contents($chatPath) : '';
$controller = is_file($controllerPath) ? (string) file_get_contents($controllerPath) : '';
$rootTag = openingTagCheckChatHotfix($chat, 'crm-chat-messages');
$stackTag = openingTagCheckChatHotfix($chat, 'crm-chat-message-stack');

checkChatHotfix(
    ! preg_match(
        '~data-current-user-id="\{\{\s*\$currentUser-\s*style=~s',
        $chat
    ),
    'Korupsi currentUser- style sudah tidak ada'
);

checkChatHotfix(
    is_string($rootTag)
        && str_contains($rootTag, 'data-current-user-id="{{ $currentUser->id }}"')
        && str_contains($rootTag, 'data-read-up-to-id=')
        && str_contains($rootTag, 'data-sync-at=')
        && str_contains($rootTag, 'data-action-base=')
        && str_contains($rootTag, 'data-search-url=')
        && str_contains($rootTag, 'data-typing-url='),
    'Opening tag pesan dan seluruh data attribute tetap utuh'
);

checkChatHotfix(
    is_string($rootTag)
        && str_contains($rootTag, 'min-h-0 flex-1 flex-col overflow-y-auto')
        && str_contains($rootTag, 'data-chat-newest-scroll-v1="1"')
        && str_contains($rootTag, 'overscroll-behavior:contain'),
    'Panel pesan memakai viewport scroll yang benar'
);

checkChatHotfix(
    is_string($stackTag)
        && str_contains($stackTag, 'mt-auto flex w-full shrink-0 flex-col')
        && str_contains($stackTag, 'data-chat-newest-stack-v1="1"'),
    'Stack pesan menempel ke bagian bawah'
);

checkChatHotfix(
    str_contains($chat, 'INTERNAL CHAT NEWEST PANEL V1.2 HOTFIX')
        && substr_count($chat, 'INTERNAL CHAT NEWEST PANEL V1.2 HOTFIX') === 1
        && str_contains($chat, 'window.crmChatGoNewest')
        && str_contains($chat, "form.addEventListener('submit'")
        && str_contains($chat, 'guardNewest(3600)')
        && str_contains($chat, 'new MutationObserver'),
    'Open chat dan send message otomatis menuju newest'
);

checkChatHotfix(
    ! preg_match(
        '~\{\{--\s*INTERNAL CHAT NEWEST PANEL (?:V1|V1\.1 HOTFIX)\s*--\}\}~',
        $chat
    ),
    'Script newest V1/V1.1 lama sudah diganti'
);

checkChatHotfix(
    preg_match(
        '~->orderByDesc\s*\(\s*[\'"]id[\'"]\s*\)[\s\S]*?->limit\s*\(\s*50\s*\)[\s\S]*?->get\s*\(\s*\)[\s\S]*?->sortBy\s*\(\s*[\'"]id[\'"]\s*\)~',
        $controller
    ) === 1,
    'Backend memuat 50 pesan terbaru lalu merender old-to-new'
);

[$lintCode, $lintOutput] = compileAndLintChatViewCheck($root, $chat);

checkChatHotfix(
    $lintCode === 0,
    $lintCode === 0
        ? 'Source package Internal Chat dikompilasi langsung dan lolos PHP lint'
        : 'Compiled Internal Chat lint gagal: '.$lintOutput
);

if (is_file($root.'/artisan')) {
    chdir($root);
    commandCheckChatHotfix(
        escapeshellarg(PHP_BINARY).' '.escapeshellarg($root.'/artisan').' view:clear'
    );
}

echo PHP_EOL;

if ($failed > 0) {
    echo "[FAIL] Checker menemukan {$failed} masalah.\n";
    exit(1);
}

echo "[PASS] Internal Chat sudah valid dan mengikuti pesan terbaru.\n";
