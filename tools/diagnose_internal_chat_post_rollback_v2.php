<?php

declare(strict_types=1);

/**
 * INTERNAL CHAT POST-ROLLBACK DIAGNOSTIC V2
 *
 * READ-ONLY.
 * Tidak mengubah source, database, route, menu, ACL, atau cache.
 *
 * Tujuan:
 * - memastikan kondisi setelah rollback sehat;
 * - menangkap exact query initial messages;
 * - menangkap exact struktur message viewport + scroll logic;
 * - melihat patch marker lama yang masih tertinggal.
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

function printWindow(
    string $label,
    array $lines,
    int $center,
    int $before = 12,
    int $after = 24
): void {
    echo "\n{$label}\n";
    echo str_repeat('-', strlen($label)) . "\n";

    $start = max(0, $center - $before);
    $end = min(count($lines) - 1, $center + $after);

    for ($i = $start; $i <= $end; $i++) {
        echo
            str_pad(
                (string) ($i + 1),
                5,
                ' ',
                STR_PAD_LEFT
            )
            . ': '
            . $lines[$i]
            . PHP_EOL;
    }
}

function firstLineContaining(
    array $lines,
    string $needle
): ?int {
    foreach ($lines as $i => $line) {
        if (str_contains($line, $needle)) {
            return $i;
        }
    }

    return null;
}

function allLinesContaining(
    array $lines,
    string $needle
): array {
    $result = [];

    foreach ($lines as $i => $line) {
        if (str_contains($line, $needle)) {
            $result[] = $i;
        }
    }

    return $result;
}

echo "INTERNAL CHAT POST-ROLLBACK DIAGNOSTIC V2\n";
echo "=========================================\n";

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

$controllerLines =
    preg_split(
        '/\r\n|\n|\r/',
        $controller
    );

$chatLines =
    preg_split(
        '/\r\n|\n|\r/',
        $chat
    );

if (!is_array($controllerLines) || !is_array($chatLines)) {
    fail('Gagal memecah source menjadi lines.');
}

echo "\nSOURCE STATUS\n";
echo "-------------\n";
echo "[OK] Controller ditemukan\n";
echo "[OK] chat.blade.php ditemukan\n";

$legacyMarkers = [
    'INTERNAL CHAT OPEN LATEST V1.1',
    'INTERNAL CHAT INITIAL LATEST V1.1',
    'INTERNAL CHAT FORCE OPEN LATEST V1.2',
    'INTERNAL CHAT WHATSAPP BOTTOM STACK V1.3',
    'INTERNAL CHAT WHATSAPP INITIAL BOTTOM V1.3',
    'INTERNAL CHAT WHATSAPP FINAL SCROLL V1.4',
    'INTERNAL CHAT LATEST 50 V1.5',
    'INTERNAL CHAT LATEST50 BOTTOM V1.5',
    'INTERNAL CHAT STICKY LATEST 50 V1.6',
    'INTERNAL CHAT STICKY BOTTOM V1.6',
];

echo "\nPATCH MARKERS\n";
echo "-------------\n";

foreach ($legacyMarkers as $marker) {
    $count =
        substr_count(
            $controller,
            $marker
        )
        + substr_count(
            $chat,
            $marker
        );

    echo
        str_pad(
            $marker,
            48
        )
        . ' : '
        . $count
        . PHP_EOL;
}

/*
|--------------------------------------------------------------------------
| Controller exact initial-message area
|--------------------------------------------------------------------------
*/

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

echo "\nCONTROLLER INDEX BOUNDARY\n";
echo "-------------------------\n";

echo
    'index() found      : '
    . ($indexStart !== false ? 'YES' : 'NO')
    . PHP_EOL;

echo
    'startDirect found  : '
    . ($indexEnd !== false ? 'YES' : 'NO')
    . PHP_EOL;

if (
    $indexStart !== false
    && $indexEnd !== false
    && $indexEnd > $indexStart
) {
    $indexMethod =
        substr(
            $controller,
            $indexStart,
            $indexEnd - $indexStart
        );

    preg_match(
        '~\$messages\s*=\s*InternalMessage::query\(\)[\s\S]*?;~',
        $indexMethod,
        $queryMatch
    );

    echo "\nINITIAL MESSAGE QUERY (exact current source)\n";
    echo "--------------------------------------------\n";

    if (!empty($queryMatch[0])) {
        echo trim($queryMatch[0]) . PHP_EOL;
    } else {
        echo "[NOT FOUND]\n";
    }
}

/*
|--------------------------------------------------------------------------
| Blade relevant windows
|--------------------------------------------------------------------------
*/

$targets = [
    'MESSAGE ROOT' =>
        'id="crm-chat-messages"',

    'MESSAGE STACK' =>
        'id="crm-chat-message-stack"',

    'BOTTOM SENTINEL' =>
        'id="crm-chat-bottom"',

    'DYNAMIC APPEND' =>
        'messageStack.appendChild(',

    'ROOT APPEND' =>
        'messagesRoot.appendChild(',

    'INITIAL / APPEND SCROLL' =>
        'messagesRoot.scrollTop',

    'READ RECEIPT BOUNDARY' =>
        'updateReadReceipts(',

    'SEND FORM' =>
        'id="crm-chat-send-form"',
];

foreach ($targets as $label => $needle) {
    $matches =
        allLinesContaining(
            $chatLines,
            $needle
        );

    echo "\n{$label} MATCH COUNT: " . count($matches) . "\n";

    foreach ($matches as $n => $lineNo) {
        printWindow(
            $label . ' #' . ($n + 1),
            $chatLines,
            $lineNo,
            10,
            22
        );
    }
}

/*
|--------------------------------------------------------------------------
| Structural quick checks
|--------------------------------------------------------------------------
*/

echo "\nSTRUCTURAL QUICK CHECK\n";
echo "----------------------\n";

$checks = [
    'crm-chat-messages ada' =>
        str_contains(
            $chat,
            'id="crm-chat-messages"'
        ),

    'send form ada' =>
        str_contains(
            $chat,
            'id="crm-chat-send-form"'
        ),

    'message stack ada' =>
        str_contains(
            $chat,
            'id="crm-chat-message-stack"'
        ),

    'bottom sentinel ada' =>
        str_contains(
            $chat,
            'id="crm-chat-bottom"'
        ),

    'dynamic append to stack ada' =>
        str_contains(
            $chat,
            'messageStack.appendChild('
        ),
];

foreach ($checks as $label => $ok) {
    echo
        ($ok ? '[OK]   ' : '[INFO] ')
        . $label
        . PHP_EOL;
}

echo "\nHASIL: READ-ONLY COMPLETE\n";
echo "Kirim seluruh output ini ke chat.\n";

exit(0);
