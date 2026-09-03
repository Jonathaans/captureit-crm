<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$taskName =
    'CaptureIT Laravel Scheduler';

$hiddenRunner =
    $root
    . '/tools/run_captureit_laravel_scheduler_hidden.ps1';

echo "CHECK MY EMAIL WINDOWS SCHEDULER HIDDEN V1\n";
echo "==========================================\n\n";

$checks = [];

$checks['Hidden runner tersedia'] =
    is_file($hiddenRunner);

if (PHP_OS_FAMILY === 'Windows') {
    $escapedTask =
        str_replace(
            "'",
            "''",
            $taskName
        );

    $ps =
        'powershell.exe -NoProfile -Command '
        . escapeshellarg(
            '$t=Get-ScheduledTask -TaskName '
            . "'{$escapedTask}'"
            . ' -ErrorAction SilentlyContinue; '
            . 'if($null -eq $t){exit 2}; '
            . '$a=$t.Actions | Select-Object -First 1; '
            . 'Write-Output ("Execute="+$a.Execute); '
            . 'Write-Output ("Arguments="+$a.Arguments);'
        )
        . ' 2>&1';

    exec(
        $ps,
        $output,
        $code
    );

    $text =
        implode(
            PHP_EOL,
            $output
        );

    $checks['Scheduled Task tersedia'] =
        $code === 0;

    $checks['Task memakai powershell.exe'] =
        stripos(
            $text,
            'Execute=powershell.exe'
        ) !== false;

    $checks['WindowStyle Hidden aktif'] =
        stripos(
            $text,
            '-WindowStyle Hidden'
        ) !== false;

    $checks['Hidden runner dipakai'] =
        stripos(
            $text,
            'run_captureit_laravel_scheduler_hidden.ps1'
        ) !== false;

    echo "Task action:\n";
    echo $text . "\n\n";
} else {
    $checks['Scheduled Task tersedia'] = false;
    $checks['Task memakai powershell.exe'] = false;
    $checks['WindowStyle Hidden aktif'] = false;
    $checks['Hidden runner dipakai'] = false;
}

$failed = [];

foreach ($checks as $label => $ok) {
    echo
        ($ok ? '[OK]   ' : '[FAIL] ')
        . $label
        . PHP_EOL;

    if (!$ok) {
        $failed[] = $label;
    }
}

echo PHP_EOL;

if ($failed) {
    echo "HASIL: FAIL\n";
    exit(1);
}

echo "HASIL: PASS\n";
echo "CMD blink seharusnya sudah hilang.\n";

exit(0);
