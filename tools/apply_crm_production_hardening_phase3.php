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
        .'.before-hardening-phase3.bak';

    if (
        is_file($path)
        && ! is_file($backup)
    ) {
        copy($path, $backup);
    }
}

/*
|--------------------------------------------------------------------------
| Provider
|--------------------------------------------------------------------------
*/

$providerPath =
    $projectRoot
    .'/bootstrap/providers.php';

$source =
    file_get_contents(
        $providerPath
    );

$provider =
    '\\Webkul\\Admin\\Providers\\CrmGovernanceServiceProvider::class';

if (! str_contains($source, $provider)) {
    $end =
        strrpos(
            $source,
            '];'
        );

    if ($end === false) {
        fwrite(STDERR, "providers.php format tidak dikenali.\n");
        exit(2);
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

    echo "[PASS] CrmGovernanceServiceProvider registered.\n";
} else {
    echo "[SKIP] Governance provider already registered.\n";
}

/*
|--------------------------------------------------------------------------
| ACL
|--------------------------------------------------------------------------
*/

$aclPath =
    $projectRoot
    .'/packages/Webkul/Admin/src/Config/acl.php';

$acl =
    file_get_contents(
        $aclPath
    );

$entries = '';

if (
    ! str_contains(
        $acl,
        "'key'   => 'operations-dashboard'"
    )
    && ! str_contains(
        $acl,
        "'key' => 'operations-dashboard'"
    )
) {
    $entries .= <<<'PHP'

    [
        'key'   => 'operations-dashboard',
        'name'  => 'Operations Dashboard',
        'route' => 'admin.operations-dashboard.index',
        'sort'  => 92,
    ],
PHP;
}

if (
    ! str_contains(
        $acl,
        "'key'   => 'financial-periods'"
    )
    && ! str_contains(
        $acl,
        "'key' => 'financial-periods'"
    )
) {
    $entries .= <<<'PHP'

    [
        'key'   => 'financial-periods',
        'name'  => 'Financial Period Lock',
        'route' => [
            'admin.financial-periods.index',
            'admin.financial-periods.store',
            'admin.financial-periods.destroy',
        ],
        'sort'  => 93,
    ],
PHP;
}

if ($entries !== '') {
    $end =
        strrpos(
            $acl,
            '];'
        );

    if ($end === false) {
        fwrite(STDERR, "ACL format tidak dikenali.\n");
        exit(3);
    }

    backupOnce($aclPath);

    $acl =
        substr_replace(
            $acl,
            $entries,
            $end,
            0
        );

    file_put_contents(
        $aclPath,
        $acl
    );

    echo "[PASS] Operations Dashboard + Financial Period ACL added.\n";
}

/*
|--------------------------------------------------------------------------
| Menu
|--------------------------------------------------------------------------
*/

$menuPath =
    $projectRoot
    .'/packages/Webkul/Admin/src/Config/menu.php';

if (! is_file($menuPath)) {
    echo "[WARN] menu.php tidak ditemukan. Operations Dashboard tetap dapat diakses via URL.\n";
} else {
    $menu =
        file_get_contents(
            $menuPath
        );

    if (
        str_contains(
            $menu,
            "'key'        => 'operations-dashboard'"
        )
        || str_contains(
            $menu,
            "'key' => 'operations-dashboard'"
        )
        || str_contains(
            $menu,
            "'key'   => 'operations-dashboard'"
        )
    ) {
        echo "[SKIP] Operations Dashboard menu already exists.\n";
    } else {
        $end =
            strrpos(
                $menu,
                '];'
            );

        if ($end === false) {
            echo "[WARN] menu.php format tidak dikenali. Skip menu patch.\n";
        } else {
            $entry = <<<'PHP'

    [
        'key'        => 'operations-dashboard',
        'name'       => 'Operations Dashboard',
        'route'      => 'admin.operations-dashboard.index',
        'sort'       => 2,
        'icon-class' => 'icon-dashboard',
    ],
PHP;

            backupOnce($menuPath);

            $menu =
                substr_replace(
                    $menu,
                    $entry,
                    $end,
                    0
                );

            file_put_contents(
                $menuPath,
                $menu
            );

            echo "[PASS] Operations Dashboard menu added.\n";
        }
    }
}

echo "\n";
echo "CRM Production Hardening Phase 3 installer selesai.\n";
echo "Next: php artisan migrate\n";
