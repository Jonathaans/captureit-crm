<?php

/*
|--------------------------------------------------------------------------
| Per-User IMAP + SMTP V1 Installer
|--------------------------------------------------------------------------
|
| Additive only:
| - register provider
| - add ACL entries
| - add sidebar menu entry when menu.php format is recognized
|
| Existing global Configuration > IMAP Settings is NOT modified.
|
*/

$projectRoot =
    realpath(
        __DIR__.'/..'
    );

if (! $projectRoot) {
    fwrite(
        STDERR,
        "Project root tidak ditemukan.\n"
    );

    exit(1);
}

function backupOnce(
    string $path,
    string $suffix
): void {
    $backup =
        $path
        .$suffix;

    if (
        is_file($path)
        && ! is_file($backup)
    ) {
        copy(
            $path,
            $backup
        );
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

if (! is_file($providerPath)) {
    fwrite(
        STDERR,
        "bootstrap/providers.php tidak ditemukan.\n"
    );

    exit(2);
}

$providerSource =
    file_get_contents(
        $providerPath
    );

$provider =
    '\\Webkul\\Admin\\Providers\\UserEmailIntegrationServiceProvider::class';

if (
    str_contains(
        $providerSource,
        $provider
    )
) {
    echo "[SKIP] UserEmailIntegrationServiceProvider already registered.\n";
} else {
    $end =
        strrpos(
            $providerSource,
            '];'
        );

    if ($end === false) {
        fwrite(
            STDERR,
            "bootstrap/providers.php format tidak dikenali.\n"
        );

        exit(3);
    }

    backupOnce(
        $providerPath,
        '.before-user-email-v1.bak'
    );

    $providerSource =
        substr_replace(
            $providerSource,
            "    {$provider},\n",
            $end,
            0
        );

    file_put_contents(
        $providerPath,
        $providerSource
    );

    echo "[PASS] UserEmailIntegrationServiceProvider registered.\n";
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
    fwrite(
        STDERR,
        "ACL file tidak ditemukan.\n"
    );

    exit(4);
}

$acl =
    file_get_contents(
        $aclPath
    );

$entries = '';

if (
    ! str_contains(
        $acl,
        "'key'   => 'my-email'"
    )
    && ! str_contains(
        $acl,
        "'key' => 'my-email'"
    )
) {
    $entries .= <<<'PHP'

    [
        'key'   => 'my-email',
        'name'  => 'My Email',
        'route' => [
            'admin.my-email.inbox',
            'admin.my-email.sync',
            'admin.my-email.messages.show',
            'admin.my-email.settings',
            'admin.my-email.settings.update',
            'admin.my-email.test-imap',
            'admin.my-email.test-smtp',
        ],
        'sort'  => 94,
    ],
PHP;
}

if (
    ! str_contains(
        $acl,
        "'key'   => 'system-control.email-accounts'"
    )
    && ! str_contains(
        $acl,
        "'key' => 'system-control.email-accounts'"
    )
) {
    $entries .= <<<'PHP'

    [
        'key'   => 'system-control.email-accounts',
        'name'  => 'User Email Connections',
        'route' => 'admin.system-control.email-accounts',
        'sort'  => 100,
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
        fwrite(
            STDERR,
            "ACL array format tidak dikenali.\n"
        );

        exit(5);
    }

    backupOnce(
        $aclPath,
        '.before-user-email-v1.bak'
    );

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

    echo "[PASS] My Email + Admin connection status ACL added.\n";
} else {
    echo "[SKIP] Email ACL entries already exist.\n";
}

/*
|--------------------------------------------------------------------------
| Sidebar Menu
|--------------------------------------------------------------------------
*/

$menuPath =
    $projectRoot
    .'/packages/Webkul/Admin/src/Config/menu.php';

if (! is_file($menuPath)) {
    echo "[WARN] menu.php tidak ditemukan. My Email tetap bisa diakses via /admin/my-email.\n";
} else {
    $menu =
        file_get_contents(
            $menuPath
        );

    if (
        str_contains(
            $menu,
            "'key'        => 'my-email'"
        )
        || str_contains(
            $menu,
            "'key'   => 'my-email'"
        )
        || str_contains(
            $menu,
            "'key' => 'my-email'"
        )
    ) {
        echo "[SKIP] My Email sidebar menu already exists.\n";
    } else {
        $end =
            strrpos(
                $menu,
                '];'
            );

        if ($end === false) {
            echo "[WARN] menu.php format tidak dikenali. Sidebar patch dilewati.\n";
        } else {
            $entry = <<<'PHP'

    [
        'key'        => 'my-email',
        'name'       => 'My Email',
        'route'      => 'admin.my-email.inbox',
        'sort'       => 89,
        'icon-class' => 'icon-mail',
    ],
PHP;

            backupOnce(
                $menuPath,
                '.before-user-email-v1.bak'
            );

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

            echo "[PASS] My Email sidebar menu added.\n";
        }
    }
}

echo "\n";
echo "PER-USER IMAP + SMTP V1 installer selesai.\n";
echo "Next: php artisan migrate\n";
