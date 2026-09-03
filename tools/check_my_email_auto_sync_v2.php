<?php

declare(strict_types=1);

/**
 * CHECK MY EMAIL AUTO SYNC V2
 */

$root = dirname(__DIR__);

$consolePath =
    $root . '/routes/console.php';

$taskStatusPs =
    $root . '/tools/check_captureit_windows_scheduler_hidden_v2.ps1';

$taskName =
    'CaptureIT Laravel Scheduler';

echo "CHECK MY EMAIL AUTO SYNC V2\n";
echo "===========================\n\n";

$content =
    is_file($consolePath)
        ? file_get_contents($consolePath)
        : '';

$checks = [];

$checks['routes/console.php tersedia'] =
    $content !== '';

$checks['V2 marker tersedia'] =
    substr_count(
        $content,
        'MY EMAIL PERSONAL AUTO SYNC V2 START'
    ) === 1
    && substr_count(
        $content,
        'MY EMAIL PERSONAL AUTO SYNC V2 END'
    ) === 1;

$checks['my-email:sync command tepat satu'] =
    preg_match_all(
        '~Artisan::command\(\s*[\'"]my-email:sync[\'"]~',
        $content
    ) === 1;

$checks['my-email:sync schedule tepat satu'] =
    preg_match_all(
        '~Schedule::command\(\s*[\'"]my-email:sync[\'"]~',
        $content
    ) === 1;

$checks['everyFiveMinutes aktif'] =
    preg_match(
        '~Schedule::command\(\s*[\'"]my-email:sync[\'"]\s*\)'
        . '[\s\S]*?->everyFiveMinutes\(\)'
        . '[\s\S]*?;~',
        $content
    ) === 1;

$checks['withoutOverlapping aktif'] =
    preg_match(
        '~Schedule::command\(\s*[\'"]my-email:sync[\'"]\s*\)'
        . '[\s\S]*?->withoutOverlapping\(\s*10\s*\)'
        . '[\s\S]*?;~',
        $content
    ) === 1;

$checks['Service sama dengan Sync Now'] =
    str_contains(
        $content,
        '\Webkul\Admin\Services\UserEmailSyncService::class'
    )
    && str_contains(
        $content,
        '$sync->sync('
    );

$checks['Hanya account sync_enabled'] =
    str_contains(
        $content,
        "'sync_enabled'"
    )
    && str_contains(
        $content,
        'true'
    );

/*
|--------------------------------------------------------------------------
| Artisan + schedule verification
|--------------------------------------------------------------------------
*/

exec(
    escapeshellarg(PHP_BINARY)
    . ' '
    . escapeshellarg($root . '/artisan')
    . ' list --raw 2>&1',
    $listOut,
    $listCode
);

$listText =
    implode(PHP_EOL, $listOut);

$checks['Artisan command terdaftar'] =
    $listCode === 0
    && preg_match(
        '/^my-email:sync(?:\s|$)/m',
        $listText
    ) === 1;

exec(
    escapeshellarg(PHP_BINARY)
    . ' '
    . escapeshellarg($root . '/artisan')
    . ' schedule:list 2>&1',
    $scheduleOut,
    $scheduleCode
);

$scheduleText =
    implode(PHP_EOL, $scheduleOut);

$checks['schedule:list melihat my-email:sync'] =
    $scheduleCode === 0
    && str_contains(
        $scheduleText,
        'my-email:sync'
    );

/*
|--------------------------------------------------------------------------
| Windows task verification using a PS1 file to avoid quoting bugs
|--------------------------------------------------------------------------
*/

$taskOutput =
    '(not checked)';

if (PHP_OS_FAMILY === 'Windows') {
    $psTask =
        str_replace(
            "'",
            "''",
            $taskName
        );

    $ps = <<<POWERSHELL
\$ErrorActionPreference = 'Stop'

\$task =
    Get-ScheduledTask `
        -TaskName '{$psTask}' `
        -ErrorAction Stop

\$info =
    Get-ScheduledTaskInfo `
        -TaskName '{$psTask}'

\$action =
    \$task.Actions `
        | Select-Object -First 1

Write-Output ('STATE|' + \$task.State)
Write-Output ('EXECUTE|' + \$action.Execute)
Write-Output ('ARGUMENTS|' + \$action.Arguments)
Write-Output ('LAST_RUN|' + \$info.LastRunTime.ToString('yyyy-MM-dd HH:mm:ss'))
Write-Output ('LAST_RESULT|' + \$info.LastTaskResult)
Write-Output ('NEXT_RUN|' + \$info.NextRunTime.ToString('yyyy-MM-dd HH:mm:ss'))
POWERSHELL;

    file_put_contents(
        $taskStatusPs,
        $ps
    );

    exec(
        'powershell.exe -NoProfile -ExecutionPolicy Bypass -File '
        . escapeshellarg($taskStatusPs)
        . ' 2>&1',
        $psOut,
        $psCode
    );

    $taskOutput =
        implode(PHP_EOL, $psOut);

    $checks['Windows Scheduled Task tersedia'] =
        $psCode === 0;

    $checks['Task memakai wscript.exe hidden runner'] =
        $psCode === 0
        && stripos(
            $taskOutput,
            'EXECUTE|'
        ) !== false
        && stripos(
            $taskOutput,
            'wscript.exe'
        ) !== false
        && stripos(
            $taskOutput,
            'run_captureit_laravel_scheduler_hidden.vbs'
        ) !== false;
}

/*
|--------------------------------------------------------------------------
| DB state
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

$accounts =
    \Webkul\Admin\Models\UserEmailAccount::query()
        ->orderBy('id')
        ->get();

$checks['Minimal satu My Email account tersedia'] =
    $accounts->count() > 0;

$checks['Minimal satu account sync_enabled'] =
    $accounts
        ->where(
            'sync_enabled',
            true
        )
        ->count()
    > 0;

$failed = [];

foreach ($checks as $label => $ok) {
    echo
        ($ok ? '[OK]   ' : '[FAIL] ')
        . $label
        . PHP_EOL;

    if (!$ok) {
        $failed[] =
            $label;
    }
}

echo "\nLaravel schedule:list:\n";
echo "----------------------\n";
echo $scheduleText . "\n";

echo "\nWindows Task:\n";
echo "-------------\n";
echo $taskOutput . "\n";

echo "\nMy Email Accounts:\n";
echo "------------------\n";

foreach ($accounts as $account) {
    echo
        $account->email_address
        . ' | enabled='
        . ($account->sync_enabled ? '1' : '0')
        . ' | last_synced_at='
        . (
            $account->last_synced_at
            ?? '-'
        )
        . ' | error='
        . (
            $account->last_sync_error
            ?: '-'
        )
        . PHP_EOL;
}

echo PHP_EOL;

if ($failed) {
    echo "HASIL: FAIL\n";

    if (
        in_array(
            'Windows Scheduled Task tersedia',
            $failed,
            true
        )
        || in_array(
            'Task memakai wscript.exe hidden runner',
            $failed,
            true
        )
    ) {
        echo "\nJika hanya Windows Task yang FAIL:\n";
        echo "Run PowerShell as Administrator:\n";
        echo "powershell -ExecutionPolicy Bypass -File tools\\install_captureit_windows_scheduler_hidden_v2.ps1\n";
    }

    exit(1);
}

echo "HASIL: PASS\n";
echo "\nAuto-sync Personal My Email sekarang memakai jalur yang sama dengan tombol Sync Now.\n";
echo "CMD blink juga seharusnya hilang karena task memakai wscript.exe hidden runner.\n";

exit(0);
