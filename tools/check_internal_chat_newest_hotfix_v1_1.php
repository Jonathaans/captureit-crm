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

function lintCompiledChatViewCheck(string $root): array
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
            'Compiled Internal Chat view count='.count($matches),
        ];
    }

    return commandCheckChatHotfix(
        escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($matches[0])
    );
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

echo "CHECK INTERNAL CHAT NEWEST HOTFIX V1.1\n";
echo "=======================================\n\n";

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
    str_contains($chat, 'INTERNAL CHAT NEWEST PANEL V1.1 HOTFIX')
        && substr_count($chat, 'INTERNAL CHAT NEWEST PANEL V1.1 HOTFIX') === 1
        && str_contains($chat, 'window.crmChatGoNewest')
        && str_contains($chat, "form.addEventListener('submit'")
        && str_contains($chat, 'guardNewest(3600)')
        && str_contains($chat, 'new MutationObserver'),
    'Open chat dan send message otomatis menuju newest'
);

checkChatHotfix(
    ! preg_match(
        '~\{\{--\s*INTERNAL CHAT NEWEST PANEL V1\s*--\}\}~',
        $chat
    ),
    'Script newest V1 lama sudah diganti'
);

checkChatHotfix(
    preg_match(
        '~->orderByDesc\s*\(\s*[\'"]id[\'"]\s*\)[\s\S]*?->limit\s*\(\s*50\s*\)[\s\S]*?->get\s*\(\s*\)[\s\S]*?->sortBy\s*\(\s*[\'"]id[\'"]\s*\)~',
        $controller
    ) === 1,
    'Backend memuat 50 pesan terbaru lalu merender old-to-new'
);

if (is_file($root.'/artisan')) {
    chdir($root);
    commandCheckChatHotfix(
        escapeshellarg(PHP_BINARY).' '.escapeshellarg($root.'/artisan').' view:clear'
    );
    [$viewCode, $viewOutput] = commandCheckChatHotfix(
        escapeshellarg(PHP_BINARY).' '.escapeshellarg($root.'/artisan').' view:cache'
    );

    checkChatHotfix(
        $viewCode === 0,
        $viewCode === 0
            ? 'Semua Blade berhasil dikompilasi'
            : 'Blade compile gagal: '.$viewOutput
    );

    if ($viewCode === 0) {
        [$lintCode, $lintOutput] = lintCompiledChatViewCheck($root);

        checkChatHotfix(
            $lintCode === 0,
            $lintCode === 0
                ? 'Compiled Internal Chat lolos PHP lint'
                : 'Compiled Internal Chat lint gagal: '.$lintOutput
        );
    }

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
