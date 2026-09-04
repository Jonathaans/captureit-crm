<?php

declare(strict_types=1);

echo "CHECK CRM FULL QA + BACKUP CENTER V1\n";
echo "=====================================\n\n";

$root = realpath(__DIR__.DIRECTORY_SEPARATOR.'..');

if ($root === false || ! is_file($root.DIRECTORY_SEPARATOR.'artisan')) {
    fwrite(
        STDERR,
        "CHECK GAGAL: Simpan checker di folder tools lalu jalankan dari root project.\n"
    );
    exit(1);
}

$failures = 0;

function checkResult(bool $ok, string $label, string $detail = ''): void
{
    global $failures;

    echo ($ok ? '[OK]   ' : '[FAIL] ').$label;

    if ($detail !== '') {
        echo ': '.$detail;
    }

    echo PHP_EOL;

    if (! $ok) {
        $failures++;
    }
}

function projectPath(string $root, string $relative): string
{
    return $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
}

function readProjectFile(string $root, string $relative): string
{
    $path = projectPath($root, $relative);

    return is_file($path) ? (string) file_get_contents($path) : '';
}

function runPhp(string $root, array $arguments): array
{
    if (! function_exists('exec')) {
        return [null, ['PHP exec() tidak tersedia.']];
    }

    $parts = [escapeshellarg(PHP_BINARY)];

    foreach ($arguments as $argument) {
        $parts[] = escapeshellarg($argument);
    }

    $output = [];
    $exitCode = 0;
    $previous = getcwd();

    chdir($root);

    try {
        exec(implode(' ', $parts).' 2>&1', $output, $exitCode);
    } finally {
        if ($previous !== false) {
            chdir($previous);
        }
    }

    return [$exitCode, $output];
}

$files = [
    'packages/Webkul/Admin/src/Services/CrmFlowQualityAssuranceService.php',
    'packages/Webkul/Admin/src/Services/CrmBackupStatusService.php',
    'packages/Webkul/Admin/src/Http/Controllers/System/CrmBackupController.php',
    'packages/Webkul/Admin/src/Services/OperationsDashboardService.php',
    'packages/Webkul/Admin/src/Providers/CrmHardeningCoreServiceProvider.php',
    'packages/Webkul/Admin/src/Resources/views/operations-dashboard/index.blade.php',
    'packages/Webkul/Admin/src/Console/Commands/CrmBackupCommand.php',
];

foreach ($files as $relative) {
    checkResult(
        is_file(projectPath($root, $relative)),
        'File tersedia',
        $relative
    );
}

$operationsService = readProjectFile(
    $root,
    'packages/Webkul/Admin/src/Services/OperationsDashboardService.php'
);

$provider = readProjectFile(
    $root,
    'packages/Webkul/Admin/src/Providers/CrmHardeningCoreServiceProvider.php'
);

$view = readProjectFile(
    $root,
    'packages/Webkul/Admin/src/Resources/views/operations-dashboard/index.blade.php'
);

checkResult(
    str_contains($operationsService, 'CRM_FULL_QA_BACKUP_CENTER_V1')
    && str_contains($operationsService, "'qa' => \$qa")
    && str_contains($operationsService, "'backup' => \$backup"),
    'Operations Dashboard menerima data QA dan backup'
);

checkResult(
    str_contains($provider, 'CrmBackupController')
    && str_contains($provider, 'admin.operations-dashboard.backups.store')
    && str_contains($provider, 'admin.operations-dashboard.backups.download'),
    'Route provider backup terpasang'
);

checkResult(
    str_contains($view, 'CRM_FULL_QA_BACKUP_CENTER_V1')
    && str_contains($view, 'Full QA CRM Flow')
    && str_contains($view, 'Backup Semua Data')
    && str_contains($view, '@csrf'),
    'UI Full QA dan tombol backup terpasang'
);

checkResult(
    str_contains($view, 'database dan seluruh file')
    && str_contains($view, 'object storage, NAS, atau cloud drive'),
    'UI menjelaskan cakupan dan recovery copy'
);

foreach ($files as $relative) {
    if (
        ! str_ends_with($relative, '.php')
        || str_ends_with($relative, '.blade.php')
        || ! is_file(projectPath($root, $relative))
    ) {
        continue;
    }

    [$exitCode, $output] = runPhp(
        $root,
        ['-l', projectPath($root, $relative)]
    );

    checkResult(
        $exitCode === null || $exitCode === 0,
        'PHP lint',
        $relative.($exitCode === 0 ? '' : ' '.implode(' ', $output))
    );
}

try {
    $app = require $root.DIRECTORY_SEPARATOR.'bootstrap'.DIRECTORY_SEPARATOR.'app.php';

    $kernel = $app->make(
        Illuminate\Contracts\Console\Kernel::class
    );

    $kernel->bootstrap();

    checkResult(
        Illuminate\Support\Facades\Route::has(
            'admin.operations-dashboard.backups.store'
        ),
        'Route POST backup aktif'
    );

    checkResult(
        Illuminate\Support\Facades\Route::has(
            'admin.operations-dashboard.backups.download'
        ),
        'Route download backup aktif'
    );

    $commands = Illuminate\Support\Facades\Artisan::all();

    checkResult(
        array_key_exists('crm:backup', $commands),
        'Command crm:backup aktif'
    );

    try {
        Illuminate\Support\Facades\Artisan::call('view:clear');
        $viewExit = Illuminate\Support\Facades\Artisan::call('view:cache');

        checkResult(
            $viewExit === 0,
            'Semua Blade berhasil dikompilasi',
            trim(Illuminate\Support\Facades\Artisan::output())
        );
    } catch (Throwable $exception) {
        checkResult(
            false,
            'Semua Blade berhasil dikompilasi',
            $exception->getMessage()
        );
    }

    try {
        $qa = $app->make(
            Webkul\Admin\Services\CrmFlowQualityAssuranceService::class
        )->run(true);

        checkResult(
            count($qa['flow'] ?? []) === 10,
            'QA menjalankan 10 tahap flow',
            'score '.($qa['score'] ?? 0).'%'
        );

        echo "\nLIVE QA RESULT\n";

        foreach ($qa['flow'] ?? [] as $stage) {
            echo sprintf(
                "[%s] %s %s\n",
                strtoupper((string) ($stage['status'] ?? 'unknown')),
                (string) ($stage['number'] ?? '--'),
                (string) ($stage['title'] ?? '-')
            );
        }

        echo sprintf(
            "Overall: %s · %d%% · %d pass · %d warning · %d fail\n",
            strtoupper((string) ($qa['status'] ?? 'unknown')),
            (int) ($qa['score'] ?? 0),
            (int) ($qa['counts']['pass'] ?? 0),
            (int) ($qa['counts']['warning'] ?? 0),
            (int) ($qa['counts']['fail'] ?? 0)
        );
    } catch (Throwable $exception) {
        checkResult(
            false,
            'QA service dapat dijalankan',
            $exception->getMessage()
        );
    }

    try {
        $backup = $app->make(
            Webkul\Admin\Services\CrmBackupStatusService::class
        )->summary();

        checkResult(
            array_key_exists('available', $backup)
            && array_key_exists('directory_writable', $backup),
            'Backup status service dapat dijalankan'
        );

        if ($backup['latest'] ?? null) {
            echo '[INFO] Latest backup: '
                .$backup['latest']['filename'].' · '
                .$backup['latest']['size_label'].' · '
                .($backup['latest']['valid'] ? 'VALID' : 'INVALID')
                .PHP_EOL;
        } else {
            echo "[INFO] Belum ada backup. Buat dari Operations Dashboard.\n";
        }
    } catch (Throwable $exception) {
        checkResult(
            false,
            'Backup status service dapat dijalankan',
            $exception->getMessage()
        );
    }
} catch (Throwable $exception) {
    checkResult(
        false,
        'Laravel bootstrap',
        $exception->getMessage()
    );
}

echo PHP_EOL;

if ($failures > 0) {
    echo "[FAIL] Checker menemukan {$failures} masalah instalasi.\n";
    exit(1);
}

echo "[PASS] CRM Full QA + Backup Center V1 terpasang dengan benar.\n";
echo "Catatan: WARNING/FAIL pada LIVE QA adalah temuan data atau konfigurasi yang harus ditindaklanjuti, bukan kegagalan installer.\n";
