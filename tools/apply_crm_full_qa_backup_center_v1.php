<?php

declare(strict_types=1);

const PATCH_MARKER = 'CRM_FULL_QA_BACKUP_CENTER_V1';

echo "CRM FULL QA + BACKUP CENTER V1\n";
echo "================================\n\n";

$root = realpath(__DIR__.DIRECTORY_SEPARATOR.'..');

if ($root === false || ! is_file($root.DIRECTORY_SEPARATOR.'artisan')) {
    fwrite(STDERR, "PATCH GAGAL: Jalankan file ini dari folder tools di root project Laravel.\n");
    exit(1);
}

if (! function_exists('gzdecode')) {
    fwrite(STDERR, "PATCH GAGAL: PHP zlib/gzdecode belum aktif.\n");
    exit(1);
}

$payload = [
    'packages/Webkul/Admin/src/Services/CrmBackupStatusService.php' =>
        gzdecode(base64_decode('H4sICPNAmmoCA0NybUJhY2t1cFN0YXR1c1NlcnZpY2UucGhwAJ1XWW/bRhB+16+YAEZIOrqcBkEhxzJk1WmCXK6VtEhtg1iRK2kjXt1dOnYK//fOHpRIipSR6kXk7jffzM61w1en2SrrdBISU5GRgMJfdL7Oo+tJGLPkekb5LQuoOO50ckHhbRTluEwkvZ7lWZZyef2aBCSk4voyzSU91qi/WTbhwYrd4nsniIgQMOXxGQnWeTaTRObC0nb+7QD+Bocwvfzgv/7y/r3/x8Q/m0zffbnwp+cfP59f+n8eweGgo3FZPo9YAIs8CSRLExB5HBN+73ojIJyTew0ylOp3EDJOA5nyeziBIE0WbOluNtXPCXjcWxEe0oQly/5cG9jfSDndClrgGllSPyNy5TokywYZZ7foioGiMcLC8TYy3nFna8qCRVSgGcsonVeN2FrZ/+3t5fn086fLr/7s/GJyOcHHvrMl7x32f7DM2SqA0xFc3ZTU5AIjUqPXmqsnWSTgCsnxzHAQ0YXsQvHG2XIl0Z0skXAyrghpQeSKJYupa5Hw6mRcXlVsLR6I0FMCWa1FV8MbOD2FJI+i41LEqCQsUiALP4UDuWKiN2aJyNBNrt3wYGRlN8KcypwncFUNMblFQjKPqIMH2tA+OTnR4t1aPqR5IjVQP7nGVK+GQkU0UQnoh+ReaHhM7twdbx11d5ZcdKxX5GJz+tXYu3D0wqvw1M0xRzLHM/6rATYJ5n/nTG58wYSPO+42/7wdc08VqBDaixxVkAhU/aQsUbL6xgT8wRa1KaJtVReRLnJS1Vtbhav4KE2YMXMiqFGqBUpJJdgPBUADFdzu49lMMHSIEVGsj2BYkg04RePCidxPYNO/ieGWRCxE6QWJBC2tsySIcmycM9NWGhDYkIXZcWw7BclCsoaQoCIIcYGzBVsTwfpOqRDYAtwnoPuuT++YkMLdduTRSG94XsmNdW0Xby5UDwd6h6koVEzmFPs+kLVkC6WqEHoAiibXmbBJIUtCv5cuArcUEA1KM5pQ5RcF743Vq/FfF8rGTt+cT99NP32clXtJccgNCVaz5Dmtn8m0FII5ibnxNgnp3UZflAYY148qX5wC0Rf/RE7NTusaSRSonaJA9L+JNGnkKJTMTDLWzFL9SId/R9CUock01ygWeIVqMbfK4l05KpEd3ViHXiOVzs3dvp5yJGf2eMNjsM+v7FGTPH6tyqTYePasydX6nLYabfV6lmBJpXKUtVpzeA12FIFFYR9PySX2FIa3rWbtgmOv4J66fB2vzYaW8lIZctwqMMdCXzdvP3R2V3YDXNT5T0X26dN6dj2Or2TSuDGepWI2drWk1aavGONDkkC5GEBSLmjISN9pSaZaY8JmvFyTDJAjrzKtUyyMZaVNlftFbxxEqdjpElvPlzx+gAebUbxDQzVQqZt32AXdfz3olTq29+hsUNwe5u4s3mq3p3aNQejH2rb1tAHYlxqkSEXfpq/B1hK0JqMK2Z/f481u0Oq9CYKjDY0MxMxJWMoxkWdK0tVS9XHB+scndmTY3nCYSw0TAIZR9Uj4AF/hzYjhQFJyccMc4PRqc7OjhuZVmnNzFI6TVeiWgziAX14OMYZHXoNgywEnS1rmqEuG6fckSkno59wI66+T0WhFxO6c5hD1qdPHy4QTNYGIXkjEap7iZGaHMtEvCKuF0DQxcaXJbayXn1LUbaS4aknamx10U2yqI+8jc1g5k9T3wIFOR5zETGuvjWL6PtYIGJ/A0fD5Czgs/9Wbta1IvFnmlPtGV0EwMIKVvy489/oO/H5WHj86j6v/33qtwg8tCh/nsQTvCoL9flYJrb0sbEbv87PF4P2sKqfliKozHnWLITVKU74VHMDLoaesi/HbQ0JEonyPX7fqfn35olVfiyJtIWr6RuI9evZzGLVIgl9LrMzy0Hno/AcwNT9DwRAAAA==', true) ?: ''),
    'packages/Webkul/Admin/src/Services/CrmFlowQualityAssuranceService.php' =>
        gzdecode(base64_decode('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', true) ?: ''),
    'packages/Webkul/Admin/src/Http/Controllers/System/CrmBackupController.php' =>
        gzdecode(base64_decode('H4sICPRAmmoCA0NybUJhY2t1cENvbnRyb2xsZXIucGhwAK1XW2/bNhR+969ggKyUC0tx0aIYnNmZ4yZYsV4yJ90FcSZQEmWzoUiBpHJpmv++Q0mRJVlOE2B6sC3p8JzvfOfqXw7SVdrrCZJQnZKQor9ocJnxxTRKmFj8Zky6mElhlOScKr04vdWGJvu9XqYpes95BlLE0EJwTiOmaGjmoEoKTffbUqdZmkplFsckJBHVi6kyTBPxQ7kZCVelttPbJJbiFkAlYIMKk5s+lpmIiGFSLA5Bhbo9Zpw2YTzu1/p3h/QpVVcstDhUckjCyyw9NcRkunwObIScaI2q12ttiN4YKiJ4Vz3q3fUQXHsv0Wz+0T/+8uGD/8fUP5zOfv9y4s+OPp0dzf0/X6GXe71cLs0CzkIUZyK0/iFtpKJO/spe3ZDQrq7f5tL9EWoHCN1VenbNiml3QjKzkop9o9MQHNZOf7+3FuEyvERjlEdjNLJ3axz2wqFK3Djj3A1ySK7KhGBiiQcNsddvh8PqQd0Ai5GzU5hxJ0tqnH6/htBeGjABCU7fncRA+appP8dwTVSHzfxVQRTihAmUEM1WKKDqK+FEeOgsE8tlhlIlwQYykBM0yAwY5FQT5uGGtjpoeylqMiWQ9doy9vD4fi1l1G3LFevsQ1B9esO00Q7W1PiGJdTnLGEGb/hvr1+bQs6wZrFlNY8a6DYzGVGIXFlvo1FIOHdsuEZFpHBLx67MTArujwE4S5zqXPEY4tIiwDqzNrQzHqNhF3SzUvIaCXqNFvNMWB+ObkKaWgo2I1nHYTVi3ClirwOUBL7OAm2UUx4ZoOEAvRoOh/2tp0YIn1DFhCErVNCAEiqWNAkIZ5fElpqtIbQkS8K9Tes/4J1DJ9OWwmYtuhOdJQm0KKd/jgsZfIEODpCAwumgdafS9P07qm7O8RWAjPDF/0Pzg1IYAposaQEI2yb6wExAVIYMi8gl4pJLja6Auhho0uzZ1DyhinWW959HqljTJCMImj55KFKI54a0V7kWgzN2yuGLTSGd2jSIHYycn3Tfw4M1Ixp6oc9JQDlw3dvi4z0KiQlXyFmcWepJALxBOZSct0OkqB1uTk1g/9n8UKWkeoSdPGeBEK9WGJW9vLl+LELt9LdUSsO9GIYq5+0WVnZqBfQTTbd0vrI3qnL0WKcUlChtjQ5iJ60nU6ryMa7dCBwPJFGRx0REb3CvBey+ezxG8lpwSSIHXIYxgHYf4g7jb3M1yFU8bwhqEtNPoA8KOwCvrWpnbaQmSQKIsp8JblU0nE0VXfqJzZiOyO79a6doOUAX0d3P9/bz7f3C+8bS3b2OmFeQmvFDY2iar5rib4ZvOifvbhEcCVNqjEIpYrbsGO0riAa1w9Ur4HnVqRYqu6NAbvkpMSsHkzTdgwK7goraW/umcb8biqKEv6vBsfe5ojXIWqbl4ifwui7ZzNLqmPfu/fxodvZ5/o9/enQynU/hp7fJ3tOC2MJpJ1RMuG4G4cWLGsCtIkz7NoGcSrTfloBk9mGKKKP9a2Y60qY6Ouh+9e4xDpp587SMqcq6KCRb1lXp9Z6ArKK9+fh8syDyddMt92eMxhOEy2waICHdfB8ewD5340LKjYddTfHv/Dj8WXDPblPqfs67oC50CakFi+PWsYtt7aawvO43G71ihK4ki1qNZXsa2fOWvGUGxeVg+O+hMNyCy3aXbMfidXfJ2FOQ/1t02e96c87F3YkN1UcGk0YsHQzUUvy0xIdcNLABXIPSfDksO20fNsBCsdV14E5sP8zXCFhjN5cw25yKpg+bL/T8jWm2zVm7EzUEN5boAP4Mhtb1dhXtoPKVJWlFNGx/CStGLe6aPPUO1Rp9OT0OYGwMveLzvvcfPhs+bFUPAAA=', true) ?: ''),
];

$fragment = gzdecode(
    base64_decode('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', true) ?: ''
);

foreach ($payload as $relative => $contents) {
    if (! is_string($contents) || $contents === '') {
        fwrite(STDERR, "PATCH GAGAL: Payload rusak untuk {$relative}.\n");
        exit(1);
    }
}

if (! is_string($fragment) || $fragment === '') {
    fwrite(STDERR, "PATCH GAGAL: Payload view rusak.\n");
    exit(1);
}

$operationsServiceRelative =
    'packages/Webkul/Admin/src/Services/OperationsDashboardService.php';

$hardeningProviderRelative =
    'packages/Webkul/Admin/src/Providers/CrmHardeningCoreServiceProvider.php';

$dashboardViewRelative =
    'packages/Webkul/Admin/src/Resources/views/operations-dashboard/index.blade.php';

$required = [
    $operationsServiceRelative,
    $hardeningProviderRelative,
    $dashboardViewRelative,
    'packages/Webkul/Admin/src/Console/Commands/CrmBackupCommand.php',
];

foreach ($required as $relative) {
    if (! is_file($root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative))) {
        fwrite(STDERR, "PATCH GAGAL: File wajib tidak ditemukan: {$relative}\n");
        exit(1);
    }
}

function normalized(string $contents): string
{
    return str_replace(["\r\n", "\r"], "\n", $contents);
}

function absolutePath(string $root, string $relative): string
{
    return $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
}

function writePatchedFile(string $path, string $contents): void
{
    $directory = dirname($path);

    if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
        throw new RuntimeException('Tidak dapat membuat folder: '.$directory);
    }

    if (file_put_contents($path, $contents, LOCK_EX) === false) {
        throw new RuntimeException('Tidak dapat menulis file: '.$path);
    }
}

function runCommand(string $root, array $arguments): array
{
    if (! function_exists('exec')) {
        return [null, ['PHP exec() tidak tersedia; validasi CLI dilewati.']];
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

$operationsServicePath = absolutePath($root, $operationsServiceRelative);
$hardeningProviderPath = absolutePath($root, $hardeningProviderRelative);
$dashboardViewPath = absolutePath($root, $dashboardViewRelative);

$operationsService = normalized(
    (string) file_get_contents($operationsServicePath)
);

$hardeningProvider = normalized(
    (string) file_get_contents($hardeningProviderPath)
);

$dashboardView = normalized(
    (string) file_get_contents($dashboardViewPath)
);

if (! str_contains($operationsService, PATCH_MARKER)) {
    $needle =
        "        return [\n"
        ."            'role' => \$role,\n";

    if (substr_count($operationsService, $needle) !== 1) {
        fwrite(
            STDERR,
            "PATCH GAGAL: Preflight OperationsDashboardService tidak cocok. "
            ."Tidak ada file yang diubah.\n"
        );
        exit(1);
    }

    $replacement =
        "        /* ".PATCH_MARKER." */\n"
        ."        \$qa = app(CrmFlowQualityAssuranceService::class)->run(request()->boolean('refresh_qa'));\n"
        ."        \$backup = app(CrmBackupStatusService::class)->summary();\n\n"
        ."        return [\n"
        ."            'role' => \$role,\n"
        ."            'qa' => \$qa,\n"
        ."            'backup' => \$backup,\n";

    $operationsService = str_replace(
        $needle,
        $replacement,
        $operationsService
    );
}

if (! str_contains($hardeningProvider, 'use Webkul\\Admin\\Http\\Controllers\\System\\CrmBackupController;')) {
    $importNeedle =
        'use Webkul\\Admin\\Http\\Controllers\\System\\SystemControlController;';

    if (substr_count($hardeningProvider, $importNeedle) !== 1) {
        fwrite(
            STDERR,
            "PATCH GAGAL: Preflight import CrmHardeningCoreServiceProvider tidak cocok. "
            ."Tidak ada file yang diubah.\n"
        );
        exit(1);
    }

    $hardeningProvider = str_replace(
        $importNeedle,
        $importNeedle."\n"
        .'use Webkul\\Admin\\Http\\Controllers\\System\\CrmBackupController;',
        $hardeningProvider
    );
}

if (! str_contains($hardeningProvider, PATCH_MARKER)) {
    $consoleNeedle = '        if ($this->app->runningInConsole()) {';

    if (substr_count($hardeningProvider, $consoleNeedle) !== 1) {
        fwrite(
            STDERR,
            "PATCH GAGAL: Preflight route CrmHardeningCoreServiceProvider tidak cocok. "
            ."Tidak ada file yang diubah.\n"
        );
        exit(1);
    }

    $routeBlock = <<<'PHP'

        /*
         * CRM_FULL_QA_BACKUP_CENTER_V1
         * Backup is POST-only, CSRF protected by web middleware, and the
         * controller applies an additional Administrator + ACL hard lock.
         */
        Route::middleware('web')
            ->prefix('admin')
            ->group(
                function () {
                    Route::post(
                        'operations-dashboard/backups',
                        [
                            CrmBackupController::class,
                            'store',
                        ]
                    )->name(
                        'admin.operations-dashboard.backups.store'
                    );

                    Route::get(
                        'operations-dashboard/backups/{filename}',
                        [
                            CrmBackupController::class,
                            'download',
                        ]
                    )
                        ->where(
                            'filename',
                            'crm-backup-[0-9]{8}-[0-9]{6}\.zip'
                        )
                        ->name(
                            'admin.operations-dashboard.backups.download'
                        );
                }
            );

PHP;

    $hardeningProvider = str_replace(
        $consoleNeedle,
        $routeBlock.$consoleNeedle,
        $hardeningProvider
    );
}

if (! str_contains($dashboardView, PATCH_MARKER)) {
    $layoutPosition = strrpos($dashboardView, '</x-admin::layouts>');

    if ($layoutPosition === false) {
        fwrite(
            STDERR,
            "PATCH GAGAL: Penutup layout Operations Dashboard tidak ditemukan. "
            ."Tidak ada file yang diubah.\n"
        );
        exit(1);
    }

    $beforeLayout = substr($dashboardView, 0, $layoutPosition);
    $outerClosePosition = strrpos($beforeLayout, '    </div>');

    if ($outerClosePosition === false) {
        fwrite(
            STDERR,
            "PATCH GAGAL: Penutup container Operations Dashboard tidak ditemukan. "
            ."Tidak ada file yang diubah.\n"
        );
        exit(1);
    }

    $dashboardView =
        substr($dashboardView, 0, $outerClosePosition)
        .rtrim($fragment)."\n"
        .substr($dashboardView, $outerClosePosition);
}

$writeSet = $payload + [
    $operationsServiceRelative => $operationsService,
    $hardeningProviderRelative => $hardeningProvider,
    $dashboardViewRelative => $dashboardView,
];

$original = [];
$created = [];
$backupSuffix = '.before-crm-full-qa-backup-center-v1-'.date('Ymd-His').'.bak';

try {
    foreach ($writeSet as $relative => $contents) {
        $path = absolutePath($root, $relative);

        if (is_file($path)) {
            $current = (string) file_get_contents($path);

            if (
                array_key_exists($relative, $payload)
                && ! str_contains($current, PATCH_MARKER)
            ) {
                throw new RuntimeException(
                    'Target baru sudah ada dan bukan milik patch ini: '.$relative
                );
            }

            $original[$relative] = $current;

            if (
                in_array(
                    $relative,
                    [
                        $operationsServiceRelative,
                        $hardeningProviderRelative,
                        $dashboardViewRelative,
                    ],
                    true
                )
            ) {
                $backupPath = $path.$backupSuffix;

                if (file_put_contents($backupPath, $current, LOCK_EX) === false) {
                    throw new RuntimeException(
                        'Tidak dapat membuat backup file: '.$backupPath
                    );
                }
            }
        } else {
            $created[] = $relative;
        }

        writePatchedFile($path, $contents);
        echo "[WRITE] {$relative}\n";
    }

    $phpFiles = array_values(array_filter(
        array_keys($writeSet),
        fn (string $relative): bool => str_ends_with($relative, '.php')
            && ! str_ends_with($relative, '.blade.php')
    ));

    foreach ($phpFiles as $relative) {
        [$exitCode, $output] = runCommand(
            $root,
            ['-l', absolutePath($root, $relative)]
        );

        if ($exitCode !== null && $exitCode !== 0) {
            throw new RuntimeException(
                "PHP lint gagal: {$relative}\n".implode("\n", $output)
            );
        }

        echo "[OK]    PHP lint {$relative}\n";
    }

    [$viewExitCode, $viewOutput] = runCommand(
        $root,
        ['artisan', 'view:clear']
    );

    if ($viewExitCode !== null && $viewExitCode !== 0) {
        throw new RuntimeException(
            "view:clear gagal:\n".implode("\n", $viewOutput)
        );
    }

    [$cacheExitCode, $cacheOutput] = runCommand(
        $root,
        ['artisan', 'view:cache']
    );

    if ($cacheExitCode !== null && $cacheExitCode !== 0) {
        throw new RuntimeException(
            "Blade compile gagal:\n".implode("\n", $cacheOutput)
        );
    }

    [$routeExitCode, $routeOutput] = runCommand(
        $root,
        [
            'artisan',
            'route:list',
            '--name=admin.operations-dashboard.backups',
        ]
    );

    if (
        $routeExitCode !== null
        && (
            $routeExitCode !== 0
            || ! str_contains(
                implode("\n", $routeOutput),
                'admin.operations-dashboard.backups.store'
            )
            || ! str_contains(
                implode("\n", $routeOutput),
                'admin.operations-dashboard.backups.download'
            )
        )
    ) {
        throw new RuntimeException(
            "Route backup belum terdaftar:\n".implode("\n", $routeOutput)
        );
    }
} catch (Throwable $exception) {
    foreach ($original as $relative => $contents) {
        @file_put_contents(
            absolutePath($root, $relative),
            $contents,
            LOCK_EX
        );
    }

    foreach ($created as $relative) {
        @unlink(absolutePath($root, $relative));
    }

    runCommand($root, ['artisan', 'view:clear']);

    fwrite(
        STDERR,
        "\nPATCH GAGAL: ".$exception->getMessage()."\n"
        ."Semua file target dipulihkan.\n"
    );
    exit(1);
}

echo "\nPATCH BERHASIL.\n";
echo "Buka Admin > Operations Dashboard untuk melihat Full QA Flow.\n";
echo "Klik Backup Semua Data untuk membuat database + storage archive.\n";
echo "Lanjutkan dengan:\n";
echo "php tools/check_crm_full_qa_backup_center_v1.php\n";
