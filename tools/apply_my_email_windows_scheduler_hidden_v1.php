<?php

declare(strict_types=1);

/**
 * MY EMAIL WINDOWS SCHEDULER HIDDEN V1
 *
 * Menghilangkan blink CMD setiap menit.
 *
 * Penyebab blink:
 * Windows Scheduled Task sebelumnya menjalankan:
 *   cmd.exe /c tools\run_captureit_laravel_scheduler.cmd
 *
 * V1 ini mengganti action menjadi:
 *   powershell.exe -WindowStyle Hidden -File <hidden runner>
 *
 * Jadwal dan task name tetap:
 *   CaptureIT Laravel Scheduler
 *
 * Tidak menyentuh:
 * - My Email controller/service
 * - routes/console.php cadence
 * - database
 * - views
 */

$root = dirname(__DIR__);

$taskName =
    'CaptureIT Laravel Scheduler';

$hiddenRunner =
    $root
    . '/tools/run_captureit_laravel_scheduler_hidden.ps1';

function fail(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

function atomicWrite(string $path, string $contents): void
{
    $tmp =
        $path
        . '.tmp-'
        . bin2hex(random_bytes(4));

    if (file_put_contents($tmp, $contents) === false) {
        @unlink($tmp);
        throw new RuntimeException("Gagal menulis temporary file: {$tmp}");
    }

    if (!rename($tmp, $path)) {
        @unlink($tmp);
        throw new RuntimeException("Gagal mengganti file: {$path}");
    }
}

echo "MY EMAIL WINDOWS SCHEDULER HIDDEN V1\n";
echo "====================================\n\n";

if (PHP_OS_FAMILY !== 'Windows') {
    fail('Patch ini khusus Windows.');
}

$phpExe =
    str_replace(
        "'",
        "''",
        PHP_BINARY
    );

$projectRoot =
    str_replace(
        "'",
        "''",
        $root
    );

$logPath =
    str_replace(
        "'",
        "''",
        $root
        . '/storage/logs/laravel-scheduler.log'
    );

$runner = <<<POWERSHELL
\$ErrorActionPreference = 'SilentlyContinue'

Set-Location '{$projectRoot}'

& '{$phpExe}' `
    '{$projectRoot}/artisan' `
    schedule:run `
    *>> '{$logPath}'
POWERSHELL;

atomicWrite(
    $hiddenRunner,
    $runner
);

echo "[OK] Hidden PowerShell runner dibuat:\n";
echo "     {$hiddenRunner}\n\n";

/*
|--------------------------------------------------------------------------
| Replace Windows Scheduled Task action only
|--------------------------------------------------------------------------
*/

$escapedRunner =
    str_replace(
        "'",
        "''",
        $hiddenRunner
    );

$escapedTaskName =
    str_replace(
        "'",
        "''",
        $taskName
    );

$command = <<<PS
\$ErrorActionPreference = 'Stop'

\$taskName = '{$escapedTaskName}'
\$runner = '{$escapedRunner}'

\$task =
    Get-ScheduledTask `
        -TaskName \$taskName `
        -ErrorAction Stop

\$action =
    New-ScheduledTaskAction `
        -Execute 'powershell.exe' `
        -Argument ('-NoProfile -WindowStyle Hidden -ExecutionPolicy Bypass -File "' + \$runner + '"')

Set-ScheduledTask `
    -TaskName \$taskName `
    -Action \$action `
    | Out-Null

Write-Output 'UPDATED'
PS;

$fullCommand =
    'powershell.exe -NoProfile -ExecutionPolicy Bypass -Command '
    . escapeshellarg($command)
    . ' 2>&1';

exec(
    $fullCommand,
    $output,
    $code
);

if ($code !== 0) {
    echo "[WARN] Update task otomatis gagal.\n";
    echo implode(PHP_EOL, $output) . "\n\n";
    echo "Kemungkinan PowerShell perlu Run as Administrator.\n";
    echo "Jalankan file installer manual yang ikut ZIP.\n";
    exit(2);
}

echo "[OK] Scheduled Task action diganti ke PowerShell Hidden.\n";
echo "     Task: {$taskName}\n\n";

echo "SELESAI.\n";
echo "Checker:\n";
echo "php tools/check_my_email_windows_scheduler_hidden_v1.php\n";
