<?php

/*
|--------------------------------------------------------------------------
| Contact Identity Documents V2 - Safe Rebuild
|--------------------------------------------------------------------------
|
| The earlier V1/V1.1/V1.2 injected upload markup into Krayin's complex
| Contact edit component and caused compiled Blade "unexpected endif".
|
| V2 deliberately changes architecture:
|
| Person Edit
|   -> Manage KTP button
|   -> dedicated standalone KTP page
|
| Organization Edit
|   -> Manage NPWP button
|   -> dedicated standalone NPWP page
|
| Existing Person/Organization forms are restored EXACTLY from the user's
| checkpoint commit made before this feature:
|
| f5e49bd
|
| Therefore the original Contact form/component structure is not modified
| except for one plain link inserted OUTSIDE the form.
|
*/

$projectRoot = realpath(
    __DIR__.'/..'
);

if (! $projectRoot) {
    fwrite(
        STDERR,
        "Project root tidak ditemukan.\n"
    );

    exit(1);
}

$checkpoint =
    'f5e49bd';

function runCommand(
    string $command,
    string $label
): void {
    exec(
        $command
        .' 2>&1',
        $output,
        $exitCode
    );

    if ($exitCode !== 0) {
        fwrite(
            STDERR,
            "{$label} gagal.\n"
        );

        foreach ($output as $line) {
            fwrite(
                STDERR,
                $line."\n"
            );
        }

        exit(10);
    }
}

function relativePath(
    string $projectRoot,
    string $absolute
): string {
    $root =
        rtrim(
            str_replace(
                '\\',
                '/',
                $projectRoot
            ),
            '/'
        );

    $path =
        str_replace(
            '\\',
            '/',
            $absolute
        );

    if (
        ! str_starts_with(
            $path,
            $root.'/'
        )
    ) {
        throw new RuntimeException(
            "Path berada di luar project root: {$absolute}"
        );
    }

    return substr(
        $path,
        strlen($root) + 1
    );
}

function backupCurrent(
    string $path
): void {
    if (! is_file($path)) {
        return;
    }

    $backup =
        $path
        .'.before-contact-identity-v2-rebuild.bak';

    if (! is_file($backup)) {
        copy(
            $path,
            $backup
        );
    }
}

function restoreFromCheckpoint(
    string $projectRoot,
    string $checkpoint,
    string $absolutePath
): void {
    backupCurrent(
        $absolutePath
    );

    $relative =
        relativePath(
            $projectRoot,
            $absolutePath
        );

    $command =
        'git -C '
        .escapeshellarg(
            $projectRoot
        )
        .' checkout '
        .escapeshellarg(
            $checkpoint
        )
        .' -- '
        .escapeshellarg(
            $relative
        );

    runCommand(
        $command,
        'Restore '.$relative
    );

    echo "[PASS] Restored from {$checkpoint}: {$relative}\n";
}

function recursivePhpFiles(
    string $root
): array {
    $result = [];

    $iterator =
        new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $root,
                FilesystemIterator::SKIP_DOTS
            )
        );

    foreach ($iterator as $file) {
        if (
            $file->isFile()
            && str_ends_with(
                strtolower(
                    $file->getFilename()
                ),
                '.php'
            )
        ) {
            $result[] =
                $file->getPathname();
        }
    }

    return $result;
}

function findController(
    string $root,
    string $className
): string {
    $matches = [];

    foreach (
        recursivePhpFiles(
            $root
        )
        as $path
    ) {
        $source =
            file_get_contents(
                $path
            );

        if (
            $source !== false
            && str_contains(
                $source,
                'class '.$className
            )
        ) {
            $matches[] =
                $path;
        }
    }

    if (count($matches) !== 1) {
        fwrite(
            STDERR,
            "{$className}: expected 1 controller, found "
                .count($matches)
                .".\n"
        );

        exit(11);
    }

    return $matches[0];
}

function addManageLink(
    string $path,
    string $eventName,
    string $routeName,
    string $entityVariable,
    string $label,
    string $marker
): void {
    $source =
        file_get_contents(
            $path
        );

    if ($source === false) {
        fwrite(
            STDERR,
            "Cannot read {$path}\n"
        );

        exit(12);
    }

    if (
        str_contains(
            $source,
            $marker
        )
    ) {
        echo "[SKIP] {$label} link already installed.\n";
        return;
    }

    /*
     * Insert just before the existing form.after render event.
     * This location is OUTSIDE <x-admin::form>, so no nested forms,
     * no enctype mutations, no conditional block, and no component surgery.
     */
    $needle =
        "view_render_event('"
        .$eventName
        ."'";

    $eventPos =
        strpos(
            $source,
            $needle
        );

    if ($eventPos === false) {
        $needle =
            'view_render_event("'
            .$eventName
            .'"';

        $eventPos =
            strpos(
                $source,
                $needle
            );
    }

    if ($eventPos === false) {
        fwrite(
            STDERR,
            "Render event {$eventName} tidak ditemukan.\n"
        );

        exit(13);
    }

    $lineStart =
        strrpos(
            substr(
                $source,
                0,
                $eventPos
            ),
            "\n"
        );

    $lineStart =
        $lineStart === false
            ? 0
            : $lineStart + 1;

    $html =
        "    <!-- {$marker} -->\n"
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
        ."    </div>\n\n";

    $source =
        substr_replace(
            $source,
            $html,
            $lineStart,
            0
        );

    file_put_contents(
        $path,
        $source
    );

    echo "[PASS] {$label} link installed outside Contact form.\n";
}

function findContactRouteFile(
    string $root
): string {
    $matches = [];

    foreach (
        recursivePhpFiles(
            $root
        )
        as $path
    ) {
        $source =
            file_get_contents(
                $path
            );

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
            $matches[] =
                $path;
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
            fwrite(
                STDERR,
                " - {$match}\n"
            );
        }

        exit(14);
    }

    return $matches[0];
}

function insertRoutesAfterAnchor(
    string $source,
    string $anchorName,
    string $routeBlock,
    string $identityRouteName
): string {
    if (
        str_contains(
            $source,
            $identityRouteName
        )
    ) {
        return $source;
    }

    $anchorPos =
        strpos(
            $source,
            $anchorName
        );

    if ($anchorPos === false) {
        fwrite(
            STDERR,
            "Route anchor {$anchorName} tidak ditemukan.\n"
        );

        exit(15);
    }

    $statementEnd =
        strpos(
            $source,
            ';',
            $anchorPos
        );

    if ($statementEnd === false) {
        fwrite(
            STDERR,
            "Akhir route {$anchorName} tidak ditemukan.\n"
        );

        exit(16);
    }

    return substr_replace(
        $source,
        "\n\n"
            .$routeBlock,
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
                .preg_quote(
                    $key,
                    '/'
                )
                ."'/",
            $source,
            $keyMatch,
            PREG_OFFSET_CAPTURE
        ) !== 1
    ) {
        fwrite(
            STDERR,
            "ACL key {$key} tidak ditemukan.\n"
        );

        exit(17);
    }

    $keyPos =
        $keyMatch[0][1];

    $chunk =
        substr(
            $source,
            $keyPos,
            2400
        );

    if (
        preg_match(
            "/'route'\\s*=>\\s*'([^']+)'/",
            $chunk,
            $singleMatch,
            PREG_OFFSET_CAPTURE
        ) === 1
    ) {
        $existing =
            $singleMatch[1][0];

        $lines = [
            $existing,
            ...$missing,
        ];

        $replacement =
            "'route' => [\n";

        foreach ($lines as $line) {
            $replacement .=
                "            '"
                .$line
                ."',\n";
        }

        $replacement .=
            '        ]';

        return substr_replace(
            $source,
            $replacement,
            $keyPos
                + $singleMatch[0][1],
            strlen(
                $singleMatch[0][0]
            )
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
        $old =
            $arrayMatch[0][0];

        $insert = '';

        foreach ($missing as $routeName) {
            $insert .=
                "\n            '"
                .$routeName
                ."',";
        }

        $new =
            preg_replace(
                "/('route'\\s*=>\\s*\\[)/",
                "$1"
                .$insert,
                $old,
                1
            );

        return substr_replace(
            $source,
            $new,
            $keyPos
                + $arrayMatch[0][1],
            strlen(
                $old
            )
        );
    }

    fwrite(
        STDERR,
        "ACL route block {$key} tidak dikenali.\n"
    );

    exit(18);
}

/*
|--------------------------------------------------------------------------
| 1. Verify checkpoint exists
|--------------------------------------------------------------------------
*/

runCommand(
    'git -C '
        .escapeshellarg(
            $projectRoot
        )
        .' cat-file -e '
        .escapeshellarg(
            $checkpoint.'^{commit}'
        ),
    'Verify checkpoint '.$checkpoint
);

echo "[PASS] Checkpoint {$checkpoint} tersedia.\n";

/*
|--------------------------------------------------------------------------
| 2. Restore Contact forms/controllers EXACTLY from the pre-feature checkpoint
|--------------------------------------------------------------------------
*/

$viewRoot =
    $projectRoot
    .'/packages/Webkul/Admin/src/Resources/views/contacts';

$personCreate =
    $viewRoot
    .'/persons/create.blade.php';

$personEdit =
    $viewRoot
    .'/persons/edit.blade.php';

$organizationCreate =
    $viewRoot
    .'/organizations/create.blade.php';

$organizationEdit =
    $viewRoot
    .'/organizations/edit.blade.php';

$controllerRoot =
    $projectRoot
    .'/packages/Webkul/Admin/src/Http/Controllers';

$personController =
    findController(
        $controllerRoot,
        'PersonController'
    );

$organizationController =
    findController(
        $controllerRoot,
        'OrganizationController'
    );

foreach (
    [
        $personCreate,
        $personEdit,
        $organizationCreate,
        $organizationEdit,
        $personController,
        $organizationController,
    ]
    as $path
) {
    restoreFromCheckpoint(
        $projectRoot,
        $checkpoint,
        $path
    );
}

/*
|--------------------------------------------------------------------------
| 3. Add plain links OUTSIDE the restored edit forms
|--------------------------------------------------------------------------
*/

addManageLink(
    path:
        $personEdit,
    eventName:
        'admin.contacts.persons.edit.form.after',
    routeName:
        'admin.contacts.persons.identity',
    entityVariable:
        'person',
    label:
        'Manage KTP',
    marker:
        'CONTACT IDENTITY V2 PERSON LINK'
);

addManageLink(
    path:
        $organizationEdit,
    eventName:
        'admin.contacts.organizations.edit.form.after',
    routeName:
        'admin.contacts.organizations.identity',
    entityVariable:
        'organization',
    label:
        'Manage NPWP',
    marker:
        'CONTACT IDENTITY V2 ORGANIZATION LINK'
);

/*
|--------------------------------------------------------------------------
| 4. Routes inside the existing Person/Organization route groups
|--------------------------------------------------------------------------
*/

$routePath =
    findContactRouteFile(
        $projectRoot
        .'/packages/Webkul/Admin/src/Routes'
    );

$routeSource =
    file_get_contents(
        $routePath
    );

backupCurrent(
    $routePath
);

$personRoutes = <<<'PHP'
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

$routeSource =
    insertRoutesAfterAnchor(
        source:
            $routeSource,
        anchorName:
            'admin.contacts.persons.edit',
        routeBlock:
            $personRoutes,
        identityRouteName:
            'admin.contacts.persons.identity'
    );

$organizationRoutes = <<<'PHP'
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

$routeSource =
    insertRoutesAfterAnchor(
        source:
            $routeSource,
        anchorName:
            'admin.contacts.organizations.edit',
        routeBlock:
            $organizationRoutes,
        identityRouteName:
            'admin.contacts.organizations.identity'
    );

file_put_contents(
    $routePath,
    $routeSource
);

echo "[PASS] Dedicated KTP/NPWP routes installed.\n";

/*
|--------------------------------------------------------------------------
| 5. ACL: inherit existing Edit permission
|--------------------------------------------------------------------------
*/

$aclPath =
    $projectRoot
    .'/packages/Webkul/Admin/src/Config/acl.php';

$aclSource =
    file_get_contents(
        $aclPath
    );

backupCurrent(
    $aclPath
);

$aclSource =
    addAclRoutes(
        source:
            $aclSource,
        key:
            'contacts.persons.edit',
        routeNames: [
            'admin.contacts.persons.identity',
            'admin.contacts.persons.identity.update',
            'admin.contacts.persons.ktp',
        ]
    );

$aclSource =
    addAclRoutes(
        source:
            $aclSource,
        key:
            'contacts.organizations.edit',
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

echo "[PASS] Identity document ACL added under Contact Edit permissions.\n";

echo "\n";
echo "Contact Identity Documents V2 rebuild selesai.\n";
echo "Person/Organization original forms restored from f5e49bd.\n";
echo "Identity upload moved to standalone pages.\n";
echo "Quick Add Client tetap tidak disentuh.\n";
