<?php

declare(strict_types=1);

/**
 * MY EMAIL AUTO SYNC V2
 *
 * Fixes two confirmed issues:
 *
 * 1) V1 scheduled the wrong subsystem:
 *      inbound-emails:process
 *
 *    But My Email "Sync Now" actually uses:
 *      Webkul\Admin\Services\UserEmailSyncService
 *      ->sync(UserEmailAccount $account, 100)
 *
 *    V2 registers a dedicated Artisan command:
 *      my-email:sync
 *
 *    It uses the SAME service as the button, for all sync_enabled accounts.
 *
 * 2) The Windows task action used cmd.exe, causing a 1-second blink.
 *
 *    V2 changes the task action to:
 *      wscript.exe <hidden runner.vbs>
 *
 *    The VBS launches the Laravel scheduler invisibly.
 *
 * Safe scope:
 * - does NOT touch My Email views
 * - does NOT touch controllers
 * - does NOT change DB schema
 * - keeps existing inbound-emails:process schedule untouched
 */

$root = dirname(__DIR__);

$consolePath =
    $root . '/routes/console.php';

$cmdRunner =
    $root . '/tools/run_captureit_laravel_scheduler.cmd';

$vbsRunner =
    $root . '/tools/run_captureit_laravel_scheduler_hidden.vbs';

$taskInstaller =
    $root . '/tools/install_captureit_windows_scheduler_hidden_v2.ps1';

$taskName =
    'CaptureIT Laravel Scheduler';

$markerStart =
    '// MY EMAIL PERSONAL AUTO SYNC V2 START';

$markerEnd =
    '// MY EMAIL PERSONAL AUTO SYNC V2 END';

function fail(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

function atomicWrite(string $path, string $content): void
{
    $tmp =
        $path . '.tmp-' . bin2hex(random_bytes(4));

    if (file_put_contents($tmp, $content) === false) {
        @unlink($tmp);
        throw new RuntimeException("Gagal menulis temporary file: {$tmp}");
    }

    if (!rename($tmp, $path)) {
        @unlink($tmp);
        throw new RuntimeException("Gagal mengganti file: {$path}");
    }
}

function run(string $command): array
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

function removeMarkedBlock(
    string $content,
    string $start,
    string $end
): string {
    $startPos =
        strpos($content, $start);

    if ($startPos === false) {
        return $content;
    }

    $endPos =
        strpos($content, $end, $startPos);

    if ($endPos === false) {
        throw new RuntimeException(
            'Marker V2 lama tidak lengkap.'
        );
    }

    $endPos += strlen($end);

    return
        rtrim(
            substr($content, 0, $startPos)
        )
        . PHP_EOL
        . PHP_EOL
        . ltrim(
            substr($content, $endPos)
        );
}

echo "MY EMAIL AUTO SYNC V2\n";
echo "=====================\n\n";

if (!is_file($consolePath)) {
    fail("routes/console.php tidak ditemukan:\n{$consolePath}");
}

/*
|--------------------------------------------------------------------------
| Preflight: current local classes must exist
|--------------------------------------------------------------------------
*/

require $root . '/vendor/autoload.php';

$app =
    require $root . '/bootstrap/app.php';

$app
    ->make(
        \Illuminate\Contracts\Console\Kernel::class
    )
    ->bootstrap();

$serviceClass =
    \Webkul\Admin\Services\UserEmailSyncService::class;

$accountClass =
    \Webkul\Admin\Models\UserEmailAccount::class;

if (!class_exists($serviceClass)) {
    fail("UserEmailSyncService tidak ditemukan: {$serviceClass}");
}

if (!class_exists($accountClass)) {
    fail("UserEmailAccount tidak ditemukan: {$accountClass}");
}

$syncMethod =
    new ReflectionMethod(
        $serviceClass,
        'sync'
    );

$params =
    $syncMethod->getParameters();

if (count($params) < 2) {
    fail(
        'Signature UserEmailSyncService::sync() tidak sesuai diagnostic.'
    );
}

echo "[OK] UserEmailSyncService::sync() tersedia.\n";
echo "[OK] UserEmailAccount model tersedia.\n";

/*
|--------------------------------------------------------------------------
| Patch routes/console.php
|--------------------------------------------------------------------------
*/

$console =
    file_get_contents($consolePath);

if ($console === false) {
    fail('Gagal membaca routes/console.php.');
}

$backup =
    $consolePath
    . '.bak-my-email-auto-sync-v2-'
    . date('Ymd-His');

if (!copy($consolePath, $backup)) {
    fail("Gagal membuat backup:\n{$backup}");
}

echo "[OK] Backup routes/console.php dibuat.\n";

try {
    $console =
        removeMarkedBlock(
            $console,
            $markerStart,
            $markerEnd
        );

    if (
        !str_contains(
            $console,
            'Illuminate\Support\Facades\Artisan'
        )
        || !str_contains(
            $console,
            'Illuminate\Support\Facades\Schedule'
        )
    ) {
        throw new RuntimeException(
            'routes/console.php tidak memiliki Artisan/Schedule facade imports.'
        );
    }

    /*
     * If an older copy of my-email:sync exists outside our V2 marker,
     * abort instead of creating a duplicate command.
     */
    if (
        preg_match(
            '~Artisan::command\(\s*[\'"]my-email:sync[\'"]~',
            $console
        ) === 1
    ) {
        throw new RuntimeException(
            'Command my-email:sync sudah ada di luar marker V2. '
            . 'Patch dibatalkan agar tidak duplicate.'
        );
    }

    if (
        preg_match(
            '~Schedule::command\(\s*[\'"]my-email:sync[\'"]~',
            $console
        ) === 1
    ) {
        throw new RuntimeException(
            'Schedule my-email:sync sudah ada di luar marker V2.'
        );
    }

    $block = <<<'PHP'

// MY EMAIL PERSONAL AUTO SYNC V2 START
Artisan::command('my-email:sync', function () {
    $sync =
        app(
            \Webkul\Admin\Services\UserEmailSyncService::class
        );

    $accounts =
        \Webkul\Admin\Models\UserEmailAccount::query()
            ->where(
                'sync_enabled',
                true
            )
            ->orderBy('id')
            ->get();

    if ($accounts->isEmpty()) {
        $this->warn(
            'Tidak ada My Email account dengan sync_enabled=1.'
        );

        return 0;
    }

    $totalNew =
        0;

    $failed =
        0;

    foreach ($accounts as $account) {
        try {
            $count =
                $sync->sync(
                    $account,
                    100
                );

            $totalNew +=
                $count;

            $this->info(
                sprintf(
                    '[OK] %s | new=%d | last_synced_at=%s',
                    $account->email_address,
                    $count,
                    (string) (
                        $account
                            ->fresh()
                            ?->last_synced_at
                        ?? '-'
                    )
                )
            );
        } catch (\Throwable $exception) {
            $failed++;

            $this->error(
                sprintf(
                    '[FAIL] %s | %s',
                    $account->email_address,
                    $exception->getMessage()
                )
            );
        }
    }

    $this->line(
        sprintf(
            'My Email sync complete: accounts=%d new=%d failed=%d',
            $accounts->count(),
            $totalNew,
            $failed
        )
    );

    return $failed > 0
        ? 1
        : 0;
})
    ->purpose(
        'Sync Personal My Email accounts using the same service as Sync Now'
    );

Schedule::command('my-email:sync')
    ->everyFiveMinutes()
    ->withoutOverlapping(10);
// MY EMAIL PERSONAL AUTO SYNC V2 END
PHP;

    $console =
        rtrim($console)
        . PHP_EOL
        . $block
        . PHP_EOL;

    atomicWrite(
        $consolePath,
        $console
    );

    echo "[OK] Command my-email:sync didaftarkan.\n";
    echo "[OK] Schedule every 5 minutes ditambahkan.\n";
    echo "[INFO] Existing inbound-emails:process schedule tidak dihapus.\n";

    /*
    |--------------------------------------------------------------------------
    | Create hidden Windows runner
    |--------------------------------------------------------------------------
    */

    $phpExe =
        PHP_BINARY;

    $logPath =
        $root . '/storage/logs/laravel-scheduler.log';

    $cmd =
        "@echo off\r\n"
        . "cd /d \"{$root}\"\r\n"
        . "\"{$phpExe}\" \"{$root}\\artisan\" schedule:run"
        . " >> \"{$logPath}\" 2>&1\r\n";

    atomicWrite(
        $cmdRunner,
        $cmd
    );

    /*
     * VBS runs the CMD runner hidden, so no black console flashes.
     */
    $vbsCmd =
        str_replace(
            '"',
            '""',
            $cmdRunner
        );

    $vbs =
        "Set shell = CreateObject(\"WScript.Shell\")\r\n"
        . "shell.Run \"cmd.exe /c \"\"\" & \"{$vbsCmd}\" & \"\"\"\", 0, True\r\n";

    atomicWrite(
        $vbsRunner,
        $vbs
    );

    echo "[OK] Hidden VBS scheduler runner dibuat.\n";

    /*
    |--------------------------------------------------------------------------
    | Register/update Windows Scheduled Task using a PS1 file
    |--------------------------------------------------------------------------
    */

    $psVbs =
        str_replace(
            "'",
            "''",
            $vbsRunner
        );

    $psTask =
        str_replace(
            "'",
            "''",
            $taskName
        );

    $ps = <<<POWERSHELL
\$ErrorActionPreference = 'Stop'

\$taskName = '{$psTask}'
\$vbs = '{$psVbs}'
\$wscript = Join-Path \$env:SystemRoot 'System32\wscript.exe'

\$action =
    New-ScheduledTaskAction `
        -Execute \$wscript `
        -Argument ('"' + \$vbs + '"')

\$trigger =
    New-ScheduledTaskTrigger `
        -Once `
        -At (Get-Date).AddMinutes(1) `
        -RepetitionInterval (New-TimeSpan -Minutes 1)

\$settings =
    New-ScheduledTaskSettingsSet `
        -MultipleInstances IgnoreNew `
        -StartWhenAvailable `
        -ExecutionTimeLimit (New-TimeSpan -Minutes 30)

Register-ScheduledTask `
    -TaskName \$taskName `
    -Action \$action `
    -Trigger \$trigger `
    -Settings \$settings `
    -Description 'Runs Laravel schedule:run invisibly every minute. Personal My Email sync is due every 5 minutes.' `
    -Force `
    | Out-Null

Write-Host "INSTALLED: \$taskName"
POWERSHELL;

    atomicWrite(
        $taskInstaller,
        $ps
    );

    if (PHP_OS_FAMILY === 'Windows') {
        [$taskCode, $taskOutput] =
            run(
                'powershell.exe -NoProfile -ExecutionPolicy Bypass -File '
                . escapeshellarg($taskInstaller)
            );

        if ($taskCode === 0) {
            echo "[OK] Windows Scheduled Task diubah ke hidden wscript runner.\n";
        } else {
            echo "[WARN] Registrasi Scheduled Task otomatis gagal.\n";
            echo $taskOutput . "\n";
            echo "       Jalankan PowerShell as Administrator:\n";
            echo "       powershell -ExecutionPolicy Bypass -File tools\\install_captureit_windows_scheduler_hidden_v2.ps1\n";
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Clear cache and verify command/schedule
    |--------------------------------------------------------------------------
    */

    chdir($root);

    passthru(
        escapeshellarg(PHP_BINARY)
        . ' '
        . escapeshellarg($root . '/artisan')
        . ' optimize:clear'
    );

    [$listCode, $listOutput] =
        run(
            escapeshellarg(PHP_BINARY)
            . ' '
            . escapeshellarg($root . '/artisan')
            . ' list --raw'
        );

    if (
        $listCode !== 0
        || !preg_match(
            '/^my-email:sync(?:\s|$)/m',
            $listOutput
        )
    ) {
        throw new RuntimeException(
            "Artisan belum melihat my-email:sync.\n{$listOutput}"
        );
    }

    echo "\n[OK] Artisan command my-email:sync aktif.\n";

    [$scheduleCode, $scheduleOutput] =
        run(
            escapeshellarg(PHP_BINARY)
            . ' '
            . escapeshellarg($root . '/artisan')
            . ' schedule:list'
        );

    echo "\nLaravel schedule:list:\n";
    echo "----------------------\n";
    echo $scheduleOutput . "\n";

    if (
        $scheduleCode !== 0
        || !str_contains(
            $scheduleOutput,
            'my-email:sync'
        )
    ) {
        throw new RuntimeException(
            'Laravel schedule:list belum melihat my-email:sync.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Immediate test, same path as Sync Now
    |--------------------------------------------------------------------------
    */

    echo "\nImmediate My Email sync test:\n";
    echo "-----------------------------\n";

    passthru(
        escapeshellarg(PHP_BINARY)
        . ' '
        . escapeshellarg($root . '/artisan')
        . ' my-email:sync',
        $syncExit
    );

    echo "\nAccount state setelah test:\n";
    echo "---------------------------\n";

    $accounts =
        \Webkul\Admin\Models\UserEmailAccount::query()
            ->orderBy('id')
            ->get();

    foreach ($accounts as $account) {
        echo
            $account->email_address
            . ' | sync_enabled='
            . ($account->sync_enabled ? '1' : '0')
            . ' | last_synced_at='
            . (
                $account->last_synced_at
                ?? '-'
            )
            . ' | last_sync_error='
            . (
                $account->last_sync_error
                ?: '-'
            )
            . PHP_EOL;
    }

    echo "\nSELESAI.\n";
    echo "Checker:\n";
    echo "php tools/check_my_email_auto_sync_v2.php\n";
} catch (Throwable $exception) {
    copy(
        $backup,
        $consolePath
    );

    fwrite(
        STDERR,
        "\nPATCH GAGAL: "
        . $exception->getMessage()
        . "\n"
    );

    fwrite(
        STDERR,
        "routes/console.php dipulihkan dari backup.\n"
    );

    exit(1);
}
