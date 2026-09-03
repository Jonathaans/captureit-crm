<?php

declare(strict_types=1);

/**
 * MY EMAIL AUTO SYNC 5 MINUTES V1
 *
 * Target:
 * - Existing command: inbound-emails:process
 * - Laravel Scheduler: every 5 minutes
 * - Prevent overlapping runs
 * - Windows Task Scheduler calls `php artisan schedule:run` every minute
 * - Browser / CRM page does not need to stay open
 *
 * Safety:
 * - Does NOT touch My Email views, controllers, database schema, menu, ACL
 * - Aborts if the inbound email command does not exist
 * - Backs up routes/console.php before modifying
 */

$root = dirname(__DIR__);

$consolePath =
    $root . '/routes/console.php';

$runnerPath =
    $root . '/tools/run_captureit_laravel_scheduler.cmd';

$psInstallerPath =
    $root . '/tools/install_captureit_windows_scheduler.ps1';

$taskName =
    'CaptureIT Laravel Scheduler';

$marker =
    'MY EMAIL AUTO SYNC 5 MINUTES V1';

function fail(string $message, int $code = 1): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit($code);
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

function runCommand(string $command): array
{
    exec(
        $command . ' 2>&1',
        $output,
        $code
    );

    return [
        $code,
        implode(PHP_EOL, $output),
    ];
}

echo "MY EMAIL AUTO SYNC 5 MINUTES V1\n";
echo "===============================\n\n";

if (!is_file($consolePath)) {
    fail(
        "routes/console.php tidak ditemukan:\n{$consolePath}"
    );
}

/*
|--------------------------------------------------------------------------
| 1. Verify the existing inbound email command
|--------------------------------------------------------------------------
*/

[$listCode, $listOutput] =
    runCommand(
        escapeshellarg(PHP_BINARY)
        . ' '
        . escapeshellarg($root . '/artisan')
        . ' list --raw'
    );

if ($listCode !== 0) {
    fail(
        "Gagal membaca Artisan command list:\n{$listOutput}"
    );
}

if (
    !preg_match(
        '/^inbound-emails:process(?:\s|$)/m',
        $listOutput
    )
) {
    fail(
        "Command inbound-emails:process TIDAK ditemukan.\n"
        . "Auto-sync tidak dipasang agar tidak menebak mekanisme email."
    );
}

echo "[OK] Command inbound-emails:process tersedia.\n";

/*
|--------------------------------------------------------------------------
| 2. Patch Laravel schedule safely
|--------------------------------------------------------------------------
*/

$console =
    file_get_contents($consolePath);

if ($console === false) {
    fail('Gagal membaca routes/console.php.');
}

if (
    !str_contains(
        $console,
        'Illuminate\Support\Facades\Schedule'
    )
) {
    fail(
        "Facade Schedule tidak ditemukan di routes/console.php.\n"
        . "Source tidak diubah."
    );
}

$stamp =
    date('Ymd-His');

$backup =
    $consolePath
    . '.bak-my-email-auto-sync-5min-v1-'
    . $stamp;

if (!copy($consolePath, $backup)) {
    fail("Gagal membuat backup:\n{$backup}");
}

echo "[OK] Backup routes/console.php dibuat.\n";

try {
    /*
     * Match any existing scheduler chain for the known command, from
     * Schedule::command(...) through its semicolon.
     */
    $schedulePattern =
        '~Schedule::command\(\s*[\'"]inbound-emails:process[\'"]\s*\)'
        . '[^;]*;~s';

    preg_match_all(
        $schedulePattern,
        $console,
        $existingMatches
    );

    $existingCount =
        count(
            $existingMatches[0]
            ?? []
        );

    if ($existingCount > 1) {
        throw new RuntimeException(
            "Schedule inbound-emails:process ditemukan {$existingCount} kali. "
            . "Patch dibatalkan agar tidak membuat duplicate sync."
        );
    }

    $newSchedule =
        "Schedule::command('inbound-emails:process')"
        . "->everyFiveMinutes()"
        . "->withoutOverlapping(10);"
        . " // {$marker}";

    if ($existingCount === 1) {
        $console =
            preg_replace(
                $schedulePattern,
                $newSchedule,
                $console,
                1,
                $replaceCount
            );

        if (!is_string($console) || $replaceCount !== 1) {
            throw new RuntimeException(
                'Gagal mengganti scheduler existing.'
            );
        }

        echo "[OK] Existing inbound schedule diperkuat.\n";
    } else {
        $console =
            rtrim($console)
            . PHP_EOL
            . PHP_EOL
            . $newSchedule
            . PHP_EOL;

        echo "[OK] Inbound schedule ditambahkan.\n";
    }

    /*
     * Make sure only one schedule remains.
     */
    if (
        preg_match_all(
            $schedulePattern,
            $console
        ) !== 1
    ) {
        throw new RuntimeException(
            'Post-patch inbound schedule tidak tepat satu.'
        );
    }

    atomicWrite(
        $consolePath,
        $console
    );

    /*
    |--------------------------------------------------------------------------
    | 3. Create a stable Windows runner
    |--------------------------------------------------------------------------
    */

    $phpExe =
        PHP_BINARY;

    $logPath =
        $root . '/storage/logs/laravel-scheduler.log';

    $runner =
        "@echo off\r\n"
        . "cd /d \"{$root}\"\r\n"
        . "\"{$phpExe}\" \"{$root}\\artisan\" schedule:run"
        . " >> \"{$logPath}\" 2>&1\r\n";

    atomicWrite(
        $runnerPath,
        $runner
    );

    echo "[OK] Scheduler runner dibuat:\n     {$runnerPath}\n";

    /*
    |--------------------------------------------------------------------------
    | 4. Generate and attempt Windows Scheduled Task registration
    |--------------------------------------------------------------------------
    */

    $psRoot =
        str_replace(
            "'",
            "''",
            $root
        );

    $psRunner =
        str_replace(
            "'",
            "''",
            $runnerPath
        );

    $psTaskName =
        str_replace(
            "'",
            "''",
            $taskName
        );

    $powershell = <<<POWERSHELL
\$ErrorActionPreference = 'Stop'

\$taskName = '{$psTaskName}'
\$runner = '{$psRunner}'

\$action = New-ScheduledTaskAction `
    -Execute 'cmd.exe' `
    -Argument ('/c "' + \$runner + '"')

\$trigger = New-ScheduledTaskTrigger `
    -Once `
    -At (Get-Date).AddMinutes(1) `
    -RepetitionInterval (New-TimeSpan -Minutes 1)

\$settings = New-ScheduledTaskSettingsSet `
    -MultipleInstances IgnoreNew `
    -StartWhenAvailable `
    -ExecutionTimeLimit (New-TimeSpan -Minutes 30)

Register-ScheduledTask `
    -TaskName \$taskName `
    -Action \$action `
    -Trigger \$trigger `
    -Settings \$settings `
    -Description 'Runs Laravel schedule:run every minute. My Email inbound sync is due every 5 minutes.' `
    -Force | Out-Null

Write-Host "WINDOWS TASK INSTALLED: \$taskName"
POWERSHELL;

    atomicWrite(
        $psInstallerPath,
        $powershell
    );

    echo "[OK] Windows Task installer dibuat.\n";

    if (PHP_OS_FAMILY === 'Windows') {
        $psCommand =
            'powershell.exe -NoProfile -ExecutionPolicy Bypass -File '
            . escapeshellarg($psInstallerPath);

        [$taskCode, $taskOutput] =
            runCommand(
                $psCommand
            );

        if ($taskCode === 0) {
            echo "[OK] Windows Scheduled Task berhasil diregistrasikan.\n";
            echo "     {$taskName}\n";
        } else {
            echo "[WARN] Registrasi Windows Task otomatis gagal.\n";
            echo "       Kemungkinan PowerShell perlu Run as Administrator.\n";
            echo "       Output:\n{$taskOutput}\n";
            echo "       Jalankan manual:\n";
            echo "       powershell -ExecutionPolicy Bypass -File tools\\install_captureit_windows_scheduler.ps1\n";
        }
    } else {
        echo "[INFO] Bukan Windows. OS scheduler tidak diregistrasikan.\n";
    }

    /*
    |--------------------------------------------------------------------------
    | 5. Clear scheduler cache + verify Laravel schedule
    |--------------------------------------------------------------------------
    */

    chdir($root);

    passthru(
        escapeshellarg(PHP_BINARY)
        . ' '
        . escapeshellarg($root . '/artisan')
        . ' optimize:clear'
    );

    echo "\nLaravel schedule verification:\n";

    [$scheduleListCode, $scheduleListOutput] =
        runCommand(
            escapeshellarg(PHP_BINARY)
            . ' '
            . escapeshellarg($root . '/artisan')
            . ' schedule:list'
        );

    if ($scheduleListCode === 0) {
        echo $scheduleListOutput . "\n";
    } else {
        echo "[WARN] artisan schedule:list gagal:\n";
        echo $scheduleListOutput . "\n";
    }

    /*
    |--------------------------------------------------------------------------
    | 6. One immediate sync test
    |--------------------------------------------------------------------------
    */

    echo "\nMenjalankan satu immediate inbound sync test...\n";

    passthru(
        escapeshellarg(PHP_BINARY)
        . ' '
        . escapeshellarg($root . '/artisan')
        . ' inbound-emails:process',
        $syncCode
    );

    if ($syncCode === 0) {
        echo "\n[OK] Immediate inbound sync selesai tanpa command-level error.\n";
    } else {
        echo "\n[WARN] Immediate sync exit code {$syncCode}.\n";
        echo "       Scheduler tetap terpasang; cek konfigurasi koneksi email/log.\n";
    }

    echo "\nPATCH SELESAI.\n";
    echo "Checker:\n";
    echo "php tools/check_my_email_auto_sync_5min_v1.php\n";
} catch (Throwable $e) {
    copy(
        $backup,
        $consolePath
    );

    fwrite(
        STDERR,
        "\nPATCH GAGAL: "
        . $e->getMessage()
        . "\n"
    );

    fwrite(
        STDERR,
        "routes/console.php dipulihkan dari backup otomatis.\n"
    );

    exit(1);
}
