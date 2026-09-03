<?php

declare(strict_types=1);

/**
 * MY EMAIL BODY BINDING DIAGNOSTIC V3
 *
 * READ-ONLY.
 * Tidak mengubah source/database/cache.
 *
 * Tujuan:
 * - melihat exact Blade expression yang menampilkan body email;
 * - melihat tombol Reply/Reply All/Back;
 * - melihat apakah body memakai {{ ... }} / {!! ... !!} / <pre> / textarea;
 * - melihat renderer V1/V2 yang masih terpasang;
 * - melihat controller action untuk /admin/my-email/messages/{id}.
 */

$root = dirname(__DIR__);

$blade =
    $root . '/packages/Webkul/Admin/src/Resources/views/user-email/message.blade.php';

$controllersRoot =
    $root . '/packages/Webkul/Admin/src/Http/Controllers';

function fail(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

function lines(string $content): array
{
    return preg_split(
        '/\r\n|\n|\r/',
        $content
    ) ?: [];
}

function printWindow(
    array $lines,
    int $center,
    int $before = 12,
    int $after = 18
): void {
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

function allMatchingLines(
    array $lines,
    array $needles
): array {
    $matches = [];

    foreach ($lines as $i => $line) {
        foreach ($needles as $needle) {
            if (stripos($line, $needle) !== false) {
                $matches[$i] = true;
                break;
            }
        }
    }

    return array_keys($matches);
}

echo "MY EMAIL BODY BINDING DIAGNOSTIC V3\n";
echo "===================================\n\n";

if (!is_file($blade)) {
    fail("Blade tidak ditemukan:\n{$blade}");
}

$content = file_get_contents($blade);

if ($content === false) {
    fail('Gagal membaca message.blade.php.');
}

$bladeLines = lines($content);

echo "BLADE TARGET\n";
echo "------------\n";
echo "packages/Webkul/Admin/src/Resources/views/user-email/message.blade.php\n";
echo "Total lines: " . count($bladeLines) . "\n\n";

echo "PATCH MARKERS\n";
echo "-------------\n";

foreach (
    [
        'MY EMAIL SAFE HTML RENDER V1',
        'MY EMAIL NORMAL READER V2',
    ]
    as $marker
) {
    echo
        str_pad($marker, 34)
        . ': '
        . substr_count($content, $marker)
        . PHP_EOL;
}

/*
|--------------------------------------------------------------------------
| Blade echo expressions
|--------------------------------------------------------------------------
*/

echo "\nBLADE ECHO EXPRESSIONS\n";
echo "----------------------\n";

foreach ($bladeLines as $i => $line) {
    if (
        str_contains($line, '{{')
        || str_contains($line, '{!!')
    ) {
        echo
            str_pad((string) ($i + 1), 5, ' ', STR_PAD_LEFT)
            . ': '
            . trim($line)
            . PHP_EOL;
    }
}

/*
|--------------------------------------------------------------------------
| Relevant source windows
|--------------------------------------------------------------------------
*/

$needles = [
    'body',
    'html',
    'content',
    'message',
    'raw',
    'Reply',
    'Reply All',
    'Move to Trash',
    'Received',
    'From:',
    '<pre',
    '<textarea',
    'white-space',
    'overflow',
];

$matches =
    allMatchingLines(
        $bladeLines,
        $needles
    );

echo "\nRELEVANT SOURCE WINDOWS\n";
echo "-----------------------\n";

$printed = [];
$windowNo = 0;

foreach ($matches as $lineNo) {
    /*
     * Avoid printing heavily overlapping windows.
     */
    $skip = false;

    foreach ($printed as $prior) {
        if (abs($prior - $lineNo) < 10) {
            $skip = true;
            break;
        }
    }

    if ($skip) {
        continue;
    }

    $printed[] = $lineNo;
    $windowNo++;

    echo "\n--- WINDOW {$windowNo} around line " . ($lineNo + 1) . " ---\n";
    printWindow(
        $bladeLines,
        $lineNo,
        12,
        20
    );
}

/*
|--------------------------------------------------------------------------
| Search route/controller definitions
|--------------------------------------------------------------------------
*/

echo "\nROUTE / CONTROLLER SEARCH\n";
echo "-------------------------\n";

$searchRoots = [
    $root . '/packages/Webkul/Admin/src',
];

$routeHits = [];

foreach ($searchRoots as $searchRoot) {
    if (!is_dir($searchRoot)) {
        continue;
    }

    $iterator =
        new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $searchRoot,
                FilesystemIterator::SKIP_DOTS
            )
        );

    foreach ($iterator as $file) {
        if (!$file->isFile()) {
            continue;
        }

        $path = $file->getPathname();

        if (
            !preg_match(
                '/\.(?:php|blade\.php)$/i',
                $path
            )
        ) {
            continue;
        }

        $text = file_get_contents($path);

        if ($text === false) {
            continue;
        }

        if (
            stripos($text, 'my-email/messages') !== false
            || stripos($text, 'admin.my-email') !== false
        ) {
            $routeHits[] = $path;
        }
    }
}

$routeHits = array_values(array_unique($routeHits));

foreach ($routeHits as $path) {
    $relative =
        str_replace('\\', '/', $path);

    $rootNormalized =
        rtrim(
            str_replace('\\', '/', $root),
            '/'
        );

    if (str_starts_with($relative, $rootNormalized . '/')) {
        $relative =
            substr(
                $relative,
                strlen($rootNormalized) + 1
            );
    }

    echo "- {$relative}\n";
}

/*
|--------------------------------------------------------------------------
| Controller likely message-detail methods
|--------------------------------------------------------------------------
*/

echo "\nCONTROLLER CANDIDATES\n";
echo "---------------------\n";

if (is_dir($controllersRoot)) {
    $iterator =
        new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $controllersRoot,
                FilesystemIterator::SKIP_DOTS
            )
        );

    foreach ($iterator as $file) {
        if (
            !$file->isFile()
            || !str_ends_with(
                strtolower($file->getFilename()),
                '.php'
            )
        ) {
            continue;
        }

        $path = $file->getPathname();
        $text = file_get_contents($path);

        if ($text === false) {
            continue;
        }

        if (
            stripos($text, 'user-email.message') === false
            && stripos($text, 'my-email') === false
        ) {
            continue;
        }

        $relative =
            str_replace('\\', '/', $path);

        $rootNormalized =
            rtrim(
                str_replace('\\', '/', $root),
                '/'
            );

        if (str_starts_with($relative, $rootNormalized . '/')) {
            $relative =
                substr(
                    $relative,
                    strlen($rootNormalized) + 1
                );
        }

        echo "\n### {$relative}\n";

        $controllerLines = lines($text);

        $controllerMatches =
            allMatchingLines(
                $controllerLines,
                [
                    'user-email.message',
                    'my-email',
                    'function show',
                    'function message',
                    'return view',
                ]
            );

        $shown = [];

        foreach ($controllerMatches as $lineNo) {
            $skip = false;

            foreach ($shown as $prior) {
                if (abs($prior - $lineNo) < 10) {
                    $skip = true;
                    break;
                }
            }

            if ($skip) {
                continue;
            }

            $shown[] = $lineNo;

            printWindow(
                $controllerLines,
                $lineNo,
                8,
                14
            );

            echo "\n";
        }
    }
}

echo "\nHASIL: READ-ONLY COMPLETE\n";
echo "Kirim seluruh output ini.\n";

exit(0);
