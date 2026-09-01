<?php

$projectRoot = realpath(__DIR__.'/..');

if (! $projectRoot) {
    fwrite(STDERR, "Project root tidak ditemukan.\n");
    exit(1);
}

function backupOnce(string $path): void
{
    $backup =
        $path
        .'.before-hardening-phase1.bak';

    if (
        is_file($path)
        && ! is_file($backup)
    ) {
        copy($path, $backup);
    }
}

/*
|--------------------------------------------------------------------------
| Provider registration
|--------------------------------------------------------------------------
*/

$providerPath =
    $projectRoot
    .'/bootstrap/providers.php';

if (! is_file($providerPath)) {
    fwrite(STDERR, "bootstrap/providers.php tidak ditemukan.\n");
    exit(2);
}

$source =
    file_get_contents($providerPath);

$provider =
    '\\Webkul\\Admin\\Providers\\CrmHardeningCoreServiceProvider::class';

if (! str_contains($source, $provider)) {
    $end =
        strrpos(
            $source,
            '];'
        );

    if ($end === false) {
        fwrite(STDERR, "providers.php format tidak dikenali.\n");
        exit(3);
    }

    backupOnce($providerPath);

    $source =
        substr_replace(
            $source,
            "    {$provider},\n",
            $end,
            0
        );

    file_put_contents(
        $providerPath,
        $source
    );

    echo "[PASS] CrmHardeningCoreServiceProvider registered.\n";
} else {
    echo "[SKIP] Core provider already registered.\n";
}

/*
|--------------------------------------------------------------------------
| ACL
|--------------------------------------------------------------------------
*/

$aclPath =
    $projectRoot
    .'/packages/Webkul/Admin/src/Config/acl.php';

if (! is_file($aclPath)) {
    fwrite(STDERR, "ACL file tidak ditemukan.\n");
    exit(4);
}

$acl =
    file_get_contents($aclPath);

if (
    ! str_contains(
        $acl,
        "'key'   => 'system-control'"
    )
    && ! str_contains(
        $acl,
        "'key' => 'system-control'"
    )
) {
    $end =
        strrpos(
            $acl,
            '];'
        );

    if ($end === false) {
        fwrite(STDERR, "ACL array tidak dikenali.\n");
        exit(5);
    }

    $block = <<<'PHP'

    [
        'key'   => 'system-control',
        'name'  => 'System Control',
        'route' => [
            'admin.system-control.index',
            'admin.system-control.audit-logs',
            'admin.system-control.incidents',
            'admin.system-control.incidents.resolve',
        ],
        'sort'  => 99,
    ],
PHP;

    backupOnce($aclPath);

    $acl =
        substr_replace(
            $acl,
            $block,
            $end,
            0
        );

    file_put_contents(
        $aclPath,
        $acl
    );

    echo "[PASS] System Control ACL added.\n";
} else {
    echo "[SKIP] System Control ACL already exists.\n";
}

echo "\n";
echo "CRM Production Hardening Phase 1 installer selesai.\n";
echo "Next: php artisan migrate\n";
