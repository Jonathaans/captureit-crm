<?php

declare(strict_types=1);

const PATCH_MARKER = 'CRM_QA_AUTO_BACKUP_HOTFIX_V1';

echo "CRM QA + SENDGRID + AUTO BACKUP HOTFIX V1\n";
echo "==========================================\n\n";

$root = realpath(__DIR__.DIRECTORY_SEPARATOR.'..');

if ($root === false || ! is_file($root.DIRECTORY_SEPARATOR.'artisan')) {
    fwrite(STDERR, "HOTFIX GAGAL: Simpan file ini di folder tools lalu jalankan dari root project Laravel.\n");
    exit(1);
}

$qaRelative = 'packages/Webkul/Admin/src/Services/CrmFlowQualityAssuranceService.php';
$consoleRelative = 'routes/console.php';
$required = [$qaRelative, $consoleRelative, 'packages/Webkul/Admin/src/Console/Commands/CrmBackupCommand.php'];

function hotfixPath(string $root, string $relative): string
{
    return $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
}

function hotfixNormalize(string $contents): string
{
    return str_replace(["\r\n", "\r"], "\n", $contents);
}

function hotfixRun(string $root, array $arguments): array
{
    if (! function_exists('exec')) {
        return [null, ['PHP exec() tidak tersedia; validasi CLI dilewati.']];
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

foreach ($required as $relative) {
    if (! is_file(hotfixPath($root, $relative))) {
        fwrite(STDERR, "HOTFIX GAGAL: File wajib tidak ditemukan: {$relative}\n");
        exit(1);
    }
}

$qaPath = hotfixPath($root, $qaRelative);
$consolePath = hotfixPath($root, $consoleRelative);
$qa = hotfixNormalize((string) file_get_contents($qaPath));
$console = hotfixNormalize((string) file_get_contents($consolePath));

/*
 * The installed QA V1 used admin.inventory.index, while inventory-routes.php
 * defines admin.inventory.dashboard. Replace only the obsolete route name.
 */
if (str_contains($qa, "'admin.inventory.index'")) {
    $qa = str_replace(
        "'admin.inventory.index'",
        "'admin.inventory.dashboard'",
        $qa
    );
}

if (! str_contains($qa, "'admin.inventory.dashboard'")) {
    fwrite(STDERR, "HOTFIX GAGAL: Preflight route QA Inventory tidak cocok. Tidak ada file yang diubah.\n");
    exit(1);
}

if (! str_contains($qa, PATCH_MARKER)) {
    $classNeedle = "class CrmFlowQualityAssuranceService\n{";

    if (substr_count($qa, $classNeedle) !== 1) {
        fwrite(STDERR, "HOTFIX GAGAL: Class QA tidak cocok. Tidak ada file yang diubah.\n");
        exit(1);
    }

    $qa = str_replace(
        $classNeedle,
        $classNeedle."\n    /* ".PATCH_MARKER." */",
        $qa
    );
}

/*
 * SendGrid is webhook-driven and its processor intentionally has no bulk pull.
 * Preserve bulk scheduling for other receivers (for example webklex-imap).
 */
if (! str_contains($console, 'CRM_SENDGRID_BULK_SCHEDULER_GUARD_V1')) {
    $pattern = "~^\\s*Schedule::command\\(\\s*['\"]inbound-emails:process['\"]\\s*\\)"
        ."\\s*->everyFiveMinutes\\(\\)"
        ."\\s*->withoutOverlapping\\(\\s*10\\s*\\)\\s*;[^\\n]*$~m";

    $guard = <<<'PHP'
/* CRM_SENDGRID_BULK_SCHEDULER_GUARD_V1
 * SendGrid receives inbound messages through its webhook and does not support
 * a bulk mailbox pull. Do not run the bulk command for that receiver.
 */
if (config('mail-receiver.default', 'sendgrid') !== 'sendgrid') {
    Schedule::command('inbound-emails:process')
        ->everyFiveMinutes()
        ->withoutOverlapping(10);
}
PHP;

    $replacements = 0;
    $console = preg_replace($pattern, $guard, $console, 1, $replacements);

    if (! is_string($console) || $replacements !== 1) {
        fwrite(STDERR, "HOTFIX GAGAL: Jadwal inbound-emails:process tidak ditemukan tepat satu kali. Tidak ada file yang diubah.\n");
        exit(1);
    }
}

/*
 * This registers the daily application schedule. Windows Task Scheduler still
 * needs to invoke `artisan schedule:run`; a companion PowerShell installer is
 * included in this package.
 */
if (! str_contains($console, 'CRM_DAILY_FULL_BACKUP_SCHEDULE_V1')) {
    $backupSchedule = <<<'PHP'

/* CRM_DAILY_FULL_BACKUP_SCHEDULE_V1
 * Full database + storage backup every day at 02:00 application time.
 */
Schedule::command('crm:backup')
    ->dailyAt('02:00')
    ->timezone((string) config('app.timezone', 'Asia/Jakarta'))
    ->withoutOverlapping(360)
    ->appendOutputTo(storage_path('logs/crm-backup-schedule.log'));
PHP;

    $console = rtrim($console)."\n\n".$backupSchedule."\n";
}

$targets = [
    $qaRelative => $qa,
    $consoleRelative => $console,
];
$originals = [];
$backups = [];
$suffix = '.before-crm-qa-auto-backup-hotfix-v1-'.date('Ymd-His').'.bak';

try {
    foreach ($targets as $relative => $contents) {
        $path = hotfixPath($root, $relative);
        $originals[$relative] = (string) file_get_contents($path);
        $backup = $path.$suffix;

        if (file_put_contents($backup, $originals[$relative], LOCK_EX) === false) {
            throw new RuntimeException('Gagal membuat backup file: '.$backup);
        }

        $backups[] = $backup;

        if (file_put_contents($path, $contents, LOCK_EX) === false) {
            throw new RuntimeException('Gagal menulis file: '.$relative);
        }

        echo "[WRITE] {$relative}\n";
    }

    foreach (array_keys($targets) as $relative) {
        [$lintCode, $lintOutput] = hotfixRun(
            $root,
            ['-l', hotfixPath($root, $relative)]
        );

        if ($lintCode !== null && $lintCode !== 0) {
            throw new RuntimeException("PHP lint gagal: {$relative}\n".implode("\n", $lintOutput));
        }

        echo "[OK]    PHP lint {$relative}\n";
    }

    foreach ([['artisan', 'config:clear'], ['artisan', 'cache:clear']] as $arguments) {
        [$code, $output] = hotfixRun($root, $arguments);

        if ($code !== null && $code !== 0) {
            throw new RuntimeException(implode(' ', $arguments)." gagal:\n".implode("\n", $output));
        }
    }

    [$scheduleCode, $scheduleOutput] = hotfixRun($root, ['artisan', 'schedule:list']);

    if (
        $scheduleCode !== null
        && (
            $scheduleCode !== 0
            || ! str_contains(implode("\n", $scheduleOutput), 'crm:backup')
        )
    ) {
        throw new RuntimeException("Jadwal crm:backup belum terdaftar:\n".implode("\n", $scheduleOutput));
    }
} catch (Throwable $exception) {
    foreach ($originals as $relative => $contents) {
        @file_put_contents(hotfixPath($root, $relative), $contents, LOCK_EX);
    }

    hotfixRun($root, ['artisan', 'config:clear']);
    hotfixRun($root, ['artisan', 'cache:clear']);

    fwrite(STDERR, "\nHOTFIX GAGAL: ".$exception->getMessage()."\nSemua file target dipulihkan.\n");
    exit(1);
}

echo "\nHOTFIX BERHASIL.\n";
echo "- False FAIL route Inventory sudah diperbaiki.\n";
echo "- Bulk inbound SendGrid tidak akan dijadwalkan lagi.\n";
echo "- Backup penuh terjadwal setiap hari pukul 02:00.\n\n";
echo "WAJIB SATU KALI (PowerShell Run as Administrator):\n";
echo "powershell -ExecutionPolicy Bypass -File tools/install_windows_laravel_scheduler_v1.ps1\n\n";
echo "Lalu periksa dengan:\n";
echo "php tools/check_crm_qa_auto_backup_hotfix_v1.php\n";

