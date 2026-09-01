<?php

$projectRoot = realpath(__DIR__.'/..');

if (! $projectRoot) {
    fwrite(STDERR, "Project root tidak ditemukan.\n");
    exit(1);
}

function backupOnce(
    string $path,
    string $suffix = '.before-contact-identity-v2-1.bak'
): void {
    $backup = $path.$suffix;

    if (
        is_file($path)
        && ! is_file($backup)
    ) {
        copy($path, $backup);
    }
}

function recursivePhpFiles(string $root): array
{
    $files = [];

    if (! is_dir($root)) {
        return $files;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(
            $root,
            FilesystemIterator::SKIP_DOTS
        )
    );

    foreach ($iterator as $file) {
        if (
            $file->isFile()
            && str_ends_with(
                strtolower($file->getFilename()),
                '.php'
            )
        ) {
            $files[] = $file->getPathname();
        }
    }

    return $files;
}

function addManageLinkAfterForm(
    string $path,
    string $routeName,
    string $entityVariable,
    string $label,
    string $marker
): void {
    if (! is_file($path)) {
        fwrite(STDERR, "Edit Blade tidak ditemukan: {$path}\n");
        exit(10);
    }

    $source = file_get_contents($path);

    if ($source === false) {
        fwrite(STDERR, "Tidak dapat membaca: {$path}\n");
        exit(11);
    }

    if (str_contains($source, $marker)) {
        echo "[SKIP] {$label} link sudah ada.\n";
        return;
    }

    backupOnce($path);

    $closingTag = '</x-admin::form>';
    $closingPos = strrpos($source, $closingTag);

    if ($closingPos === false) {
        $closingTag = '</form>';
        $closingPos = strrpos($source, $closingTag);
    }

    if ($closingPos === false) {
        fwrite(STDERR, "{$label}: closing form tag tidak ditemukan.\n");
        exit(12);
    }

    $insertAt = $closingPos + strlen($closingTag);

    $html =
        "\n\n"
        ."    <!-- {$marker} -->\n"
        ."    <div class=\"mt-4 flex justify-end\">\n"
        ."        <a\n"
        ."            href=\"{{ route('"
        .$routeName
        ."', $"
        .$entityVariable
        ."->id) }}\"\n"
        ."            class=\"secondary-button\"\n"
        ."        >\n"
        ."            "
        .$label
        ."\n"
        ."        </a>\n"
        ."    </div>";

    $source = substr_replace(
        $source,
        $html,
        $insertAt,
        0
    );

    file_put_contents($path, $source);

    echo "[PASS] {$label} link ditambahkan setelah Contact form.\n";
}

function findContactRouteFile(string $root): string
{
    $matches = [];

    foreach (recursivePhpFiles($root) as $path) {
        $source = file_get_contents($path);

        if (
            $source !== false
            && str_contains(
                $source,
                'admin.contacts.persons.edit'
            )
            && str_contains(
                $source,
                'admin.contacts.organizations.edit'
            )
        ) {
            $matches[] = $path;
        }
    }

    if (count($matches) !== 1) {
        fwrite(
            STDERR,
            "Contact route file: expected 1, found "
                .count($matches)
                .".\n"
        );

        foreach ($matches as $match) {
            fwrite(STDERR, " - {$match}\n");
        }

        exit(13);
    }

    return $matches[0];
}

function insertRouteBlockAfterNamedRoute(
    string $source,
    string $anchorRouteName,
    string $newRouteName,
    string $routeBlock
): string {
    if (str_contains($source, $newRouteName)) {
        return $source;
    }

    $anchorPos = strpos($source, $anchorRouteName);

    if ($anchorPos === false) {
        fwrite(
            STDERR,
            "Route anchor {$anchorRouteName} tidak ditemukan.\n"
        );
        exit(14);
    }

    $statementEnd = strpos(
        $source,
        ';',
        $anchorPos
    );

    if ($statementEnd === false) {
        fwrite(
            STDERR,
            "Akhir statement {$anchorRouteName} tidak ditemukan.\n"
        );
        exit(15);
    }

    return substr_replace(
        $source,
        "\n\n".$routeBlock,
        $statementEnd + 1,
        0
    );
}

function addAclRoutes(
    string $source,
    string $key,
    array $routeNames
): string {
    $missing = array_values(
        array_filter(
            $routeNames,
            fn ($routeName) =>
                ! str_contains(
                    $source,
                    "'".$routeName."'"
                )
        )
    );

    if (! $missing) {
        return $source;
    }

    if (
        preg_match(
            "/'key'\\s*=>\\s*'"
                .preg_quote($key, '/')
                ."'/",
            $source,
            $keyMatch,
            PREG_OFFSET_CAPTURE
        ) !== 1
    ) {
        fwrite(STDERR, "ACL key {$key} tidak ditemukan.\n");
        exit(16);
    }

    $keyPos = $keyMatch[0][1];
    $chunk = substr($source, $keyPos, 2600);

    if (
        preg_match(
            "/'route'\\s*=>\\s*'([^']+)'/",
            $chunk,
            $singleMatch,
            PREG_OFFSET_CAPTURE
        ) === 1
    ) {
        $existingRoute = $singleMatch[1][0];
        $routes = [$existingRoute, ...$missing];

        $replacement = "'route' => [\n";

        foreach ($routes as $route) {
            $replacement .=
                "            '".$route."',\n";
        }

        $replacement .= '        ]';

        return substr_replace(
            $source,
            $replacement,
            $keyPos + $singleMatch[0][1],
            strlen($singleMatch[0][0])
        );
    }

    if (
        preg_match(
            "/'route'\\s*=>\\s*\\[(.*?)\\]/s",
            $chunk,
            $arrayMatch,
            PREG_OFFSET_CAPTURE
        ) === 1
    ) {
        $old = $arrayMatch[0][0];
        $insert = '';

        foreach ($missing as $route) {
            $insert .=
                "\n            '".$route."',";
        }

        $new = preg_replace(
            "/('route'\\s*=>\\s*\\[)/",
            "$1".$insert,
            $old,
            1
        );

        return substr_replace(
            $source,
            $new,
            $keyPos + $arrayMatch[0][1],
            strlen($old)
        );
    }

    fwrite(
        STDERR,
        "Format ACL route {$key} tidak dikenali.\n"
    );

    exit(17);
}

/*
|--------------------------------------------------------------------------
| 1. Add links after restored Contact forms
|--------------------------------------------------------------------------
*/

$viewRoot =
    $projectRoot
    .'/packages/Webkul/Admin/src/Resources/views/contacts';

addManageLinkAfterForm(
    path: $viewRoot.'/persons/edit.blade.php',
    routeName: 'admin.contacts.persons.identity',
    entityVariable: 'person',
    label: 'Manage KTP',
    marker: 'CONTACT IDENTITY V2.1 PERSON LINK'
);

addManageLinkAfterForm(
    path: $viewRoot.'/organizations/edit.blade.php',
    routeName: 'admin.contacts.organizations.identity',
    entityVariable: 'organization',
    label: 'Manage NPWP',
    marker: 'CONTACT IDENTITY V2.1 ORGANIZATION LINK'
);

/*
|--------------------------------------------------------------------------
| 2. Install routes because V2 stopped before this stage
|--------------------------------------------------------------------------
*/

$routePath = findContactRouteFile(
    $projectRoot.'/packages/Webkul/Admin/src/Routes'
);

$routeSource = file_get_contents($routePath);

if ($routeSource === false) {
    fwrite(STDERR, "Contact routes tidak dapat dibaca.\n");
    exit(18);
}

backupOnce($routePath);

$personBlock = <<<'PHP'
        Route::get(
            'identity-document/{id}',
            [\Webkul\Admin\Http\Controllers\Contact\ContactIdentityDocumentController::class, 'editPerson']
        )->name('admin.contacts.persons.identity');

        Route::post(
            'identity-document/{id}',
            [\Webkul\Admin\Http\Controllers\Contact\ContactIdentityDocumentController::class, 'updatePerson']
        )->name('admin.contacts.persons.identity.update');

        Route::get(
            'identity-document/{id}/file',
            [\Webkul\Admin\Http\Controllers\Contact\ContactIdentityDocumentController::class, 'personKtp']
        )->name('admin.contacts.persons.ktp');
PHP;

$routeSource = insertRouteBlockAfterNamedRoute(
    source: $routeSource,
    anchorRouteName: 'admin.contacts.persons.edit',
    newRouteName: 'admin.contacts.persons.identity',
    routeBlock: $personBlock
);

$organizationBlock = <<<'PHP'
        Route::get(
            'identity-document/{id}',
            [\Webkul\Admin\Http\Controllers\Contact\ContactIdentityDocumentController::class, 'editOrganization']
        )->name('admin.contacts.organizations.identity');

        Route::post(
            'identity-document/{id}',
            [\Webkul\Admin\Http\Controllers\Contact\ContactIdentityDocumentController::class, 'updateOrganization']
        )->name('admin.contacts.organizations.identity.update');

        Route::get(
            'identity-document/{id}/file',
            [\Webkul\Admin\Http\Controllers\Contact\ContactIdentityDocumentController::class, 'organizationNpwp']
        )->name('admin.contacts.organizations.npwp');
PHP;

$routeSource = insertRouteBlockAfterNamedRoute(
    source: $routeSource,
    anchorRouteName: 'admin.contacts.organizations.edit',
    newRouteName: 'admin.contacts.organizations.identity',
    routeBlock: $organizationBlock
);

file_put_contents(
    $routePath,
    $routeSource
);

echo "[PASS] Dedicated KTP/NPWP routes ditambahkan.\n";

/*
|--------------------------------------------------------------------------
| 3. ACL
|--------------------------------------------------------------------------
*/

$aclPath =
    $projectRoot
    .'/packages/Webkul/Admin/src/Config/acl.php';

if (! is_file($aclPath)) {
    fwrite(STDERR, "ACL file tidak ditemukan.\n");
    exit(19);
}

$aclSource = file_get_contents($aclPath);

if ($aclSource === false) {
    fwrite(STDERR, "ACL tidak dapat dibaca.\n");
    exit(20);
}

backupOnce($aclPath);

$aclSource = addAclRoutes(
    source: $aclSource,
    key: 'contacts.persons.edit',
    routeNames: [
        'admin.contacts.persons.identity',
        'admin.contacts.persons.identity.update',
        'admin.contacts.persons.ktp',
    ]
);

$aclSource = addAclRoutes(
    source: $aclSource,
    key: 'contacts.organizations.edit',
    routeNames: [
        'admin.contacts.organizations.identity',
        'admin.contacts.organizations.identity.update',
        'admin.contacts.organizations.npwp',
    ]
);

file_put_contents(
    $aclPath,
    $aclSource
);

echo "[PASS] Identity document ACL ditambahkan.\n";

echo "\n";
echo "Contact Identity Documents V2.1 selesai.\n";
echo "Link dipasang setelah closing Contact form.\n";
echo "Quick Add Client tidak disentuh.\n";
