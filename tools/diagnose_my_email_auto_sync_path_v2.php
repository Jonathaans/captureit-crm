<?php

declare(strict_types=1);

/**
 * MY EMAIL AUTO SYNC PATH DIAGNOSTIC V2
 *
 * READ-ONLY.
 * Tidak mengubah source, database, scheduler, atau Windows Task.
 *
 * Tujuan:
 * memastikan scheduled command yang berjalan memang memanggil jalur yang sama
 * dengan tombol "Sync Now" di My Email.
 */

use Illuminate\Contracts\Console\Kernel as ConsoleKernel;

$root = dirname(__DIR__);

$controllerPath =
    $root
    . '/packages/Webkul/Admin/src/Http/Controllers/UserEmail/MyEmailInboxController.php';

$consolePath =
    $root
    . '/routes/console.php';

function section(string $title): void
{
    echo "\n{$title}\n";
    echo str_repeat('=', strlen($title)) . "\n";
}

function fail(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

function classImports(string $source): array
{
    $imports = [];

    if (
        preg_match_all(
            '/^use\s+([^;]+);/mi',
            $source,
            $matches
        )
    ) {
        foreach ($matches[1] as $fqcn) {
            $fqcn = trim($fqcn);
            $short = basename(
                str_replace('\\', '/', $fqcn)
            );
            $imports[$short] = $fqcn;
        }
    }

    return $imports;
}

function methodSource(
    ReflectionMethod $method
): string {
    $file = $method->getFileName();

    if (!$file || !is_file($file)) {
        return '(source unavailable)';
    }

    $lines = file($file);

    if ($lines === false) {
        return '(source unreadable)';
    }

    $start = max(
        0,
        $method->getStartLine() - 1
    );

    $length =
        $method->getEndLine()
        - $method->getStartLine()
        + 1;

    return implode(
        '',
        array_slice(
            $lines,
            $start,
            $length
        )
    );
}

echo "MY EMAIL AUTO SYNC PATH DIAGNOSTIC V2\n";
echo "=====================================\n";

if (!is_file($controllerPath)) {
    fail(
        "Controller tidak ditemukan:\n{$controllerPath}"
    );
}

$controllerSource =
    file_get_contents($controllerPath);

if ($controllerSource === false) {
    fail('Gagal membaca MyEmailInboxController.php.');
}

$imports =
    classImports($controllerSource);

section('CONTROLLER IMPORTS');

foreach ($imports as $short => $fqcn) {
    if (
        stripos($short, 'Email') !== false
        || stripos($short, 'User') !== false
        || stripos($short, 'Account') !== false
    ) {
        echo "{$short} => {$fqcn}\n";
    }
}

/*
|--------------------------------------------------------------------------
| Bootstrap Laravel so reflection resolves current local code exactly.
|--------------------------------------------------------------------------
*/

require $root . '/vendor/autoload.php';

$app =
    require $root . '/bootstrap/app.php';

$app
    ->make(ConsoleKernel::class)
    ->bootstrap();

$controllerClass = null;

if (
    preg_match(
        '/^namespace\s+([^;]+);/mi',
        $controllerSource,
        $namespaceMatch
    )
    && preg_match(
        '/class\s+MyEmailInboxController\b/',
        $controllerSource
    )
) {
    $controllerClass =
        trim($namespaceMatch[1])
        . '\\MyEmailInboxController';
}

section('SYNC NOW CONTROLLER PATH');

if (
    !$controllerClass
    || !class_exists($controllerClass)
) {
    echo "Controller class tidak dapat direfleksikan.\n";
} else {
    echo "Class: {$controllerClass}\n";

    $syncMethod =
        new ReflectionMethod(
            $controllerClass,
            'sync'
        );

    echo "\nExact sync() source:\n";
    echo "--------------------\n";
    echo methodSource($syncMethod) . "\n";
}

$serviceClass =
    $imports['UserEmailSyncService']
    ?? null;

section('USER EMAIL SYNC SERVICE');

if (
    !$serviceClass
    || !class_exists($serviceClass)
) {
    echo "UserEmailSyncService import/class tidak ditemukan.\n";
} else {
    echo "Class: {$serviceClass}\n";

    $serviceReflection =
        new ReflectionClass(
            $serviceClass
        );

    echo
        "File : "
        . (
            $serviceReflection->getFileName()
            ?: '(unknown)'
        )
        . "\n";

    echo "\nPublic methods:\n";

    foreach (
        $serviceReflection->getMethods(
            ReflectionMethod::IS_PUBLIC
        )
        as $method
    ) {
        if (
            $method->getDeclaringClass()->getName()
            !== $serviceClass
        ) {
            continue;
        }

        $params = [];

        foreach ($method->getParameters() as $param) {
            $type =
                $param->getType();

            $typeText =
                $type
                    ? (string) $type
                    : 'mixed';

            $params[] =
                $typeText
                . ' $'
                . $param->getName();
        }

        $return =
            $method->getReturnType();

        echo
            '- '
            . $method->getName()
            . '('
            . implode(', ', $params)
            . ')'
            . (
                $return
                    ? ': ' . (string) $return
                    : ''
            )
            . "\n";
    }
}

/*
|--------------------------------------------------------------------------
| Candidate account model from imports
|--------------------------------------------------------------------------
*/

section('ACCOUNT MODEL CANDIDATES');

$accountCandidates = [];

foreach ($imports as $short => $fqcn) {
    if (
        stripos($short, 'Account') !== false
        && class_exists($fqcn)
    ) {
        $accountCandidates[$short] =
            $fqcn;
    }
}

if (!$accountCandidates) {
    echo "(none from controller imports)\n";
} else {
    foreach ($accountCandidates as $short => $fqcn) {
        echo "\n{$short} => {$fqcn}\n";

        try {
            $model =
                app($fqcn);

            if (
                $model
                instanceof \Illuminate\Database\Eloquent\Model
            ) {
                $table =
                    $model->getTable();

                echo "table: {$table}\n";

                try {
                    $columns =
                        \Illuminate\Support\Facades\Schema::getColumnListing(
                            $table
                        );

                    echo
                        "columns: "
                        . implode(', ', $columns)
                        . "\n";

                    echo
                        "rows: "
                        . $fqcn::query()->count()
                        . "\n";
                } catch (Throwable $e) {
                    echo
                        "DB inspect error: "
                        . $e->getMessage()
                        . "\n";
                }
            }
        } catch (Throwable $e) {
            echo
                "Model inspect error: "
                . $e->getMessage()
                . "\n";
        }
    }
}

/*
|--------------------------------------------------------------------------
| Current scheduler config
|--------------------------------------------------------------------------
*/

section('ROUTES CONSOLE SCHEDULE');

if (!is_file($consolePath)) {
    echo "routes/console.php tidak ditemukan.\n";
} else {
    $console =
        file_get_contents($consolePath);

    if ($console === false) {
        echo "routes/console.php tidak dapat dibaca.\n";
    } else {
        $lines =
            preg_split(
                '/\R/',
                $console
            ) ?: [];

        foreach ($lines as $i => $line) {
            if (
                stripos($line, 'Schedule::') !== false
                || stripos($line, 'inbound-emails') !== false
                || stripos($line, 'my-email') !== false
                || stripos($line, 'UserEmail') !== false
            ) {
                echo
                    str_pad(
                        (string) ($i + 1),
                        4,
                        ' ',
                        STR_PAD_LEFT
                    )
                    . ': '
                    . $line
                    . "\n";
            }
        }
    }
}

section('ARTISAN SCHEDULE LIST');

$command =
    escapeshellarg(PHP_BINARY)
    . ' '
    . escapeshellarg(
        $root . '/artisan'
    )
    . ' schedule:list 2>&1';

exec(
    $command,
    $scheduleOutput,
    $scheduleCode
);

echo
    "exit={$scheduleCode}\n"
    . implode(
        PHP_EOL,
        $scheduleOutput
    )
    . "\n";

section('WINDOWS TASK');

if (PHP_OS_FAMILY !== 'Windows') {
    echo "Not Windows.\n";
} else {
    $ps =
        'powershell.exe -NoProfile -Command '
        . escapeshellarg(
            '$t=Get-ScheduledTask -TaskName '
            . "'CaptureIT Laravel Scheduler'"
            . ' -ErrorAction SilentlyContinue; '
            . 'if($null -eq $t){Write-Output "NOT_FOUND"; exit 2}; '
            . '$i=Get-ScheduledTaskInfo -TaskName '
            . "'CaptureIT Laravel Scheduler'; "
            . 'Write-Output ("State="+$t.State); '
            . 'Write-Output ("LastRunTime="+$i.LastRunTime); '
            . 'Write-Output ("LastTaskResult="+$i.LastTaskResult); '
            . 'Write-Output ("NextRunTime="+$i.NextRunTime); '
            . '$t.Actions | Format-List *'
        )
        . ' 2>&1';

    exec(
        $ps,
        $taskOutput,
        $taskCode
    );

    echo
        "exit={$taskCode}\n"
        . implode(
            PHP_EOL,
            $taskOutput
        )
        . "\n";
}

section('CONCLUSION HINT');

echo
    "Jika Sync Now memakai UserEmailSyncService tetapi schedule hanya menjalankan\n"
    . "inbound-emails:process, maka itu dua jalur berbeda dan scheduler lama\n"
    . "belum menyinkronkan Personal My Email.\n\n";

echo "HASIL: READ-ONLY COMPLETE\n";
echo "Kirim seluruh output ini.\n";
