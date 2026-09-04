<?php

declare(strict_types=1);

echo "CHECK CRM QA + SENDGRID + AUTO BACKUP HOTFIX V1\n";
echo "================================================\n\n";

$root = realpath(__DIR__.DIRECTORY_SEPARATOR.'..');

if ($root === false || ! is_file($root.DIRECTORY_SEPARATOR.'artisan')) {
    fwrite(STDERR, "CHECK GAGAL: Jalankan dari root project; file checker harus berada di folder tools.\n");
    exit(1);
}

function checkPath(string $root, string $relative): string
{
    return $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
}

function checkRun(string $root, array $arguments): array
{
    if (! function_exists('exec')) {
        return [null, ['exec() tidak tersedia']];
    }

    $parts = [escapeshellarg(PHP_BINARY)];

    foreach ($arguments as $argument) {
        $parts[] = escapeshellarg((string) $argument);
    }

    $output = [];
    $code = 0;
    $previous = getcwd();
    chdir($root);

    try {
        exec(implode(' ', $parts).' 2>&1', $output, $code);
    } finally {
        if ($previous !== false) {
            chdir($previous);
        }
    }

    return [$code, $output];
}

$fails = 0;
$warnings = 0;

function result(bool $ok, string $message): void
{
    global $fails;

    echo ($ok ? '[OK]   ' : '[FAIL] ').$message."\n";

    if (! $ok) {
        $fails++;
    }
}

function warning(string $message): void
{
    global $warnings;
    $warnings++;
    echo '[WARN] '.$message."\n";
}

$qaRelative = 'packages/Webkul/Admin/src/Services/CrmFlowQualityAssuranceService.php';
$consoleRelative = 'routes/console.php';
$backupRelative = 'packages/Webkul/Admin/src/Console/Commands/CrmBackupCommand.php';
$qaPath = checkPath($root, $qaRelative);
$consolePath = checkPath($root, $consoleRelative);
$backupPath = checkPath($root, $backupRelative);

result(is_file($qaPath), 'QA Service tersedia');
result(is_file($consolePath), 'Console schedule tersedia');
result(is_file($backupPath), 'Perintah crm:backup tersedia');

$qa = is_file($qaPath) ? (string) file_get_contents($qaPath) : '';
$console = is_file($consolePath) ? (string) file_get_contents($consolePath) : '';

result(
    str_contains($qa, 'CRM_QA_AUTO_BACKUP_HOTFIX_V1')
        && str_contains($qa, "'admin.inventory.dashboard'")
        && ! str_contains($qa, "'admin.inventory.index'"),
    'QA memakai route Inventory yang benar'
);
result(
    str_contains($console, 'CRM_SENDGRID_BULK_SCHEDULER_GUARD_V1')
        && str_contains($console, "config('mail-receiver.default', 'sendgrid') !== 'sendgrid'"),
    'Bulk inbound tidak dijalankan untuk SendGrid'
);
result(
    str_contains($console, 'CRM_DAILY_FULL_BACKUP_SCHEDULE_V1')
        && str_contains($console, "Schedule::command('crm:backup')")
        && str_contains($console, "->dailyAt('02:00')")
        && str_contains($console, '->withoutOverlapping(360)'),
    'Backup penuh harian pukul 02:00 terdaftar'
);

foreach ([$qaRelative, $consoleRelative, $backupRelative] as $relative) {
    [$code, $output] = checkRun($root, ['-l', checkPath($root, $relative)]);

    result(
        $code === null || $code === 0,
        'PHP lint: '.$relative.($code === null ? ' (dilewati)' : '')
    );
}

[$scheduleCode, $scheduleOutput] = checkRun($root, ['artisan', 'schedule:list']);
$scheduleText = implode("\n", $scheduleOutput);
result(
    $scheduleCode === null
        || ($scheduleCode === 0 && str_contains($scheduleText, 'crm:backup')),
    'Laravel schedule:list menampilkan crm:backup'
);

if (PHP_OS_FAMILY === 'Windows' && function_exists('exec')) {
    $taskOutput = [];
    $taskCode = 0;
    exec('schtasks /Query /TN "Laravel CRM Scheduler" 2>&1', $taskOutput, $taskCode);

    if ($taskCode === 0) {
        echo "[OK]   Windows Task Scheduler Laravel CRM Scheduler aktif\n";
    } else {
        warning('Windows Task Scheduler belum terpasang. Jalankan PowerShell installer sebagai Administrator.');
    }
} else {
    warning('Status Windows Task Scheduler tidak diperiksa pada OS ini.');
}

echo "\n";

if ($fails > 0) {
    echo "[FAIL] Checker menemukan {$fails} masalah instalasi".($warnings > 0 ? " dan {$warnings} peringatan" : '').".\n";
    exit(1);
}

echo "[PASS] Hotfix terpasang".($warnings > 0 ? " dengan {$warnings} peringatan" : '').".\n";
exit(0);

