<?php

declare(strict_types=1);

/**
 * CHECK MY EMAIL AUTO SYNC 5 MINUTES V1
 */

$root = dirname(__DIR__);

$consolePath =
    $root . '/routes/console.php';

$runnerPath =
    $root . '/tools/run_captureit_laravel_scheduler.cmd';

$taskName =
    'CaptureIT Laravel Scheduler';

echo "CHECK MY EMAIL AUTO SYNC 5 MINUTES V1\n";
echo "=====================================\n\n";

$console =
    is_file($consolePath)
        ? file_get_contents($consolePath)
        : '';

$checks = [];

$checks['routes/console.php tersedia'] =
    $console !== '';

$checks['Schedule command tepat satu'] =
    preg_match_all(
        '~Schedule::command\(\s*[\'"]inbound-emails:process[\'"]\s*\)[^;]*;~s',
        $console
    ) === 1;

$checks['Cadence everyFiveMinutes aktif'] =
    preg_match(
        '~Schedule::command\(\s*[\'"]inbound-emails:process[\'"]\s*\)'
        . '[^;]*->everyFiveMinutes\(\)[^;]*;~s',
        $console
    ) === 1;

$checks['withoutOverlapping aktif'] =
    preg_match(
        '~Schedule::command\(\s*[\'"]inbound-emails:process[\'"]\s*\)'
        . '[^;]*->withoutOverlapping\(\s*10\s*\)[^;]*;~s',
        $console
    ) === 1;

$checks['V1 marker tersedia'] =
    str_contains(
        $console,
        'MY EMAIL AUTO SYNC 5 MINUTES V1'
    );

$checks['Windows runner tersedia'] =
    is_file($runnerPath);

$listCommand =
    escapeshellarg(PHP_BINARY)
    . ' '
    . escapeshellarg($root . '/artisan')
    . ' list --raw 2>&1';

exec(
    $listCommand,
    $listOutput,
    $listCode
);

$listText =
    implode(
        PHP_EOL,
        $listOutput
    );

$checks['inbound-emails:process command tersedia'] =
    $listCode === 0
    && preg_match(
        '/^inbound-emails:process(?:\s|$)/m',
        $listText
    ) === 1;

$scheduleCommand =
    escapeshellarg(PHP_BINARY)
    . ' '
    . escapeshellarg($root . '/artisan')
    . ' schedule:list 2>&1';

exec(
    $scheduleCommand,
    $scheduleOutput,
    $scheduleCode
);

$scheduleText =
    implode(
        PHP_EOL,
        $scheduleOutput
    );

$checks['Laravel schedule:list melihat inbound sync'] =
    $scheduleCode === 0
    && str_contains(
        $scheduleText,
        'inbound-emails:process'
    );

$windowsTaskStatus =
    null;

if (PHP_OS_FAMILY === 'Windows') {
    $ps =
        'powershell.exe -NoProfile -Command '
        . escapeshellarg(
            '$t = Get-ScheduledTask -TaskName '
            . "'"
            . str_replace(
                "'",
                "''",
                $taskName
            )
            . "' -ErrorAction SilentlyContinue; "
            . 'if ($null -eq $t) { exit 2 } else { Write-Output $t.State; exit 0 }'
        );

    exec(
        $ps . ' 2>&1',
        $taskOutput,
        $taskCode
    );

    $windowsTaskStatus =
        $taskCode === 0;

    $checks['Windows Scheduled Task terpasang'] =
        $windowsTaskStatus;
}

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
echo
    (
        $scheduleText !== ''
            ? $scheduleText
            : '(no output)'
    )
    . "\n\n";

if ($failed) {
    echo "HASIL: FAIL\n";

    if (
        PHP_OS_FAMILY === 'Windows'
        && $windowsTaskStatus === false
    ) {
        echo "\nJika hanya Windows Scheduled Task yang FAIL:\n";
        echo "Buka PowerShell as Administrator lalu jalankan:\n";
        echo "powershell -ExecutionPolicy Bypass -File tools\\install_captureit_windows_scheduler.ps1\n";
    }

    exit(1);
}

echo "HASIL: PASS\n";
echo "\nBehavior:\n";
echo "- Windows menjalankan Laravel scheduler setiap 1 menit.\n";
echo "- Laravel menjalankan inbound-emails:process setiap 5 menit.\n";
echo "- withoutOverlapping(10) mencegah sync bertumpuk.\n";
echo "- Browser CRM boleh ditutup.\n";
echo "- Log scheduler: storage\\logs\\laravel-scheduler.log\n";

exit(0);
