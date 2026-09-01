<?php

/*
|--------------------------------------------------------------------------
| Contact Identity Documents Installer
|--------------------------------------------------------------------------
|
| Scope:
| - Contacts > Persons       : KTP image
| - Contacts > Organizations : NPWP image
|
| Intentionally NOT added to Quote/Invoice "Add New Client" / quick client.
|
| This tool patches CURRENT customized files in-place and creates backups.
| It never replaces the entire PersonController / OrganizationController /
| contact routes / ACL / existing contact Blade views with guessed copies.
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

function backupFile(
    string $path,
    string $suffix
): void {
    $backup =
        $path
        .$suffix;

    if (! is_file($backup)) {
        if (! copy(
            $path,
            $backup
        )) {
            throw new RuntimeException(
                "Gagal membuat backup: {$backup}"
            );
        }
    }
}

function recursiveFiles(
    string $root,
    string $suffix
): array {
    $result = [];

    if (! is_dir($root)) {
        return $result;
    }

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
                strtolower(
                    $suffix
                )
            )
        ) {
            $result[] =
                $file->getPathname();
        }
    }

    return $result;
}

function filesContaining(
    string $root,
    string $needle,
    string $suffix
): array {
    $matches = [];

    foreach (
        recursiveFiles(
            $root,
            $suffix
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
                $needle
            )
        ) {
            $matches[] =
                $path;
        }
    }

    return $matches;
}

function exactlyOne(
    array $candidates,
    string $label
): string {
    if (
        count(
            $candidates
        ) !== 1
    ) {
        fwrite(
            STDERR,
            "{$label}: expected 1 file, found "
                .count(
                    $candidates
                )
                .".\n"
        );

        foreach (
            $candidates
            as $candidate
        ) {
            fwrite(
                STDERR,
                " - {$candidate}\n"
            );
        }

        exit(10);
    }

    return $candidates[0];
}

function patchRepositorySave(
    string $source,
    string $entityVariable,
    string $repositoryVariable,
    string $operation,
    string $fieldName,
    string $serviceMethod,
    string $marker
): string {
    if (
        str_contains(
            $source,
            $marker
        )
    ) {
        return $source;
    }

    $pattern =
        '/(\\$'
        .preg_quote(
            $entityVariable,
            '/'
        )
        .'\\s*=\\s*\\$this->'
        .preg_quote(
            $repositoryVariable,
            '/'
        )
        .'->'
        .preg_quote(
            $operation,
            '/'
        )
        .'\\s*\\(.*?\\);)/s';

    if (
        preg_match(
            $pattern,
            $source
        ) !== 1
    ) {
        fwrite(
            STDERR,
            "Tidak dapat menemukan "
                .$repositoryVariable
                .'->'
                .$operation
                ."() untuk {$entityVariable}.\n"
        );

        exit(11);
    }

    $validation = sprintf(
        "\n        /* %s VALIDATION */\n"
        ."        request()->validate([\n"
        ."            '%s' => [\n"
        ."                'nullable',\n"
        ."                'image',\n"
        ."                'mimes:jpg,jpeg,png,webp',\n"
        ."                'max:5120',\n"
        ."            ],\n"
        ."        ]);\n\n",
        $marker,
        $fieldName
    );

    $upload = sprintf(
        "\n\n        /* %s */\n"
        ."        if (request()->hasFile('%s')) {\n"
        ."            app(\\Webkul\\Admin\\Services\\ContactIdentityDocumentService::class)\n"
        ."                ->%s(\n"
        ."                    (int) $%s->id,\n"
        ."                    request()->file('%s')\n"
        ."                );\n"
        ."        }\n",
        $marker,
        $fieldName,
        $serviceMethod,
        $entityVariable,
        $fieldName
    );

    return preg_replace(
        $pattern,
        $validation
            .'$1'
            .$upload,
        $source,
        1
    );
}

function patchFormTag(
    string $source,
    string $routeName
): string {
    /*
     * Normal <form ...> first.
     */
    $pattern =
        '/<form\\b(?=[^>]*'
        .preg_quote(
            $routeName,
            '/'
        )
        .')[^>]*>/s';

    if (
        preg_match(
            $pattern,
            $source,
            $match
        ) === 1
    ) {
        $tag =
            $match[0];

        if (
            ! str_contains(
                strtolower(
                    $tag
                ),
                'enctype='
            )
        ) {
            $newTag =
                substr(
                    $tag,
                    0,
                    -1
                )
                .' enctype="multipart/form-data">';

            $source =
                str_replace(
                    $tag,
                    $newTag,
                    $source
                );
        }

        return $source;
    }

    /*
     * Support x-admin form component if this project uses it.
     */
    $componentPattern =
        '/<x-admin::form\\b(?=[^>]*'
        .preg_quote(
            $routeName,
            '/'
        )
        .')[^>]*>/s';

    if (
        preg_match(
            $componentPattern,
            $source,
            $match
        ) === 1
    ) {
        $tag =
            $match[0];

        if (
            ! str_contains(
                strtolower(
                    $tag
                ),
                'enctype='
            )
        ) {
            $newTag =
                substr(
                    $tag,
                    0,
                    -1
                )
                .' enctype="multipart/form-data">';

            return str_replace(
                $tag,
                $newTag,
                $source
            );
        }

        return $source;
    }

    fwrite(
        STDERR,
        "Form dengan route {$routeName} tidak ditemukan.\n"
    );

    exit(12);
}

function injectAfterCsrf(
    string $source,
    string $routeName,
    string $html,
    string $marker
): string {
    if (
        str_contains(
            $source,
            $marker
        )
    ) {
        return $source;
    }

    $routePosition =
        strpos(
            $source,
            $routeName
        );

    if (
        $routePosition === false
    ) {
        fwrite(
            STDERR,
            "Route {$routeName} tidak ditemukan pada Blade.\n"
        );

        exit(13);
    }

    $csrfPosition =
        strpos(
            $source,
            '@csrf',
            $routePosition
        );

    if (
        $csrfPosition === false
    ) {
        fwrite(
            STDERR,
            "@csrf setelah {$routeName} tidak ditemukan.\n"
        );

        exit(14);
    }

    $insertPosition =
        $csrfPosition
        + strlen(
            '@csrf'
        );

    /*
     * On edit forms, keep @method('PUT') before our UI if present nearby.
     */
    $nearby =
        substr(
            $source,
            $insertPosition,
            250
        );

    if (
        preg_match(
            "/\\s*@method\\([^\\n]+\\)/",
            $nearby,
            $methodMatch
        ) === 1
    ) {
        $relative =
            strpos(
                $nearby,
                $methodMatch[0]
            );

        if (
            $relative !== false
        ) {
            $insertPosition +=
                $relative
                + strlen(
                    $methodMatch[0]
                );
        }
    }

    return substr_replace(
        $source,
        "\n\n"
            .$html
            ."\n",
        $insertPosition,
        0
    );
}

/*
|--------------------------------------------------------------------------
| 1. CURRENT PersonController
|--------------------------------------------------------------------------
*/

$controllerRoot =
    $projectRoot
    .'/packages/Webkul/Admin/src/Http/Controllers';

$personControllerPath =
    exactlyOne(
        filesContaining(
            $controllerRoot,
            'class PersonController',
            '.php'
        ),
        'PersonController'
    );

$personSource =
    file_get_contents(
        $personControllerPath
    );

backupFile(
    $personControllerPath,
    '.before-identity-documents.bak'
);

$personSource =
    patchRepositorySave(
        source: $personSource,
        entityVariable: 'person',
        repositoryVariable: 'personRepository',
        operation: 'create',
        fieldName: 'ktp_image',
        serviceMethod: 'storePersonKtp',
        marker: 'CONTACT IDENTITY PERSON KTP STORE'
    );

$personSource =
    patchRepositorySave(
        source: $personSource,
        entityVariable: 'person',
        repositoryVariable: 'personRepository',
        operation: 'update',
        fieldName: 'ktp_image',
        serviceMethod: 'storePersonKtp',
        marker: 'CONTACT IDENTITY PERSON KTP UPDATE'
    );

file_put_contents(
    $personControllerPath,
    $personSource
);

echo "[PASS] PersonController KTP upload.\n";

/*
|--------------------------------------------------------------------------
| 2. CURRENT OrganizationController
|--------------------------------------------------------------------------
*/

$organizationControllerPath =
    exactlyOne(
        filesContaining(
            $controllerRoot,
            'class OrganizationController',
            '.php'
        ),
        'OrganizationController'
    );

$organizationSource =
    file_get_contents(
        $organizationControllerPath
    );

backupFile(
    $organizationControllerPath,
    '.before-identity-documents.bak'
);

$organizationSource =
    patchRepositorySave(
        source: $organizationSource,
        entityVariable: 'organization',
        repositoryVariable: 'organizationRepository',
        operation: 'create',
        fieldName: 'npwp_image',
        serviceMethod: 'storeOrganizationNpwp',
        marker: 'CONTACT IDENTITY ORGANIZATION NPWP STORE'
    );

$organizationSource =
    patchRepositorySave(
        source: $organizationSource,
        entityVariable: 'organization',
        repositoryVariable: 'organizationRepository',
        operation: 'update',
        fieldName: 'npwp_image',
        serviceMethod: 'storeOrganizationNpwp',
        marker: 'CONTACT IDENTITY ORGANIZATION NPWP UPDATE'
    );

file_put_contents(
    $organizationControllerPath,
    $organizationSource
);

echo "[PASS] OrganizationController NPWP upload.\n";

/*
|--------------------------------------------------------------------------
| 3. Dedicated Contact menu Create/Edit Blade views
|--------------------------------------------------------------------------
|
| We prefer the canonical Krayin paths so Quote/Invoice quick-client UI is
| intentionally untouched.
|
*/

$viewRoot =
    $projectRoot
    .'/packages/Webkul/Admin/src/Resources/views';

$viewSpecs = [
    [
        'label' =>
            'Person Create',

        'route' =>
            'admin.contacts.persons.store',

        'preferred_suffix' =>
            'contacts'
            .DIRECTORY_SEPARATOR
            .'persons'
            .DIRECTORY_SEPARATOR
            .'create.blade.php',

        'marker' =>
            'CONTACT IDENTITY PERSON KTP CREATE',

        'html' =>
<<<'BLADE'
        <!-- CONTACT IDENTITY PERSON KTP CREATE -->
        <div
            class="mb-4 rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900"
        >
            <label class="mb-1.5 block text-sm font-semibold text-gray-800 dark:text-white">
                KTP Image
            </label>

            <input
                type="file"
                name="ktp_image"
                accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-950"
            >

            <p class="mt-1.5 text-xs text-gray-500">
                JPG, JPEG, PNG, atau WEBP. Maksimal 5 MB. Disimpan private dan tidak tampil pada Quick Add Client.
            </p>
        </div>
BLADE,
    ], [
        'label' =>
            'Person Edit',

        'route' =>
            'admin.contacts.persons.update',

        'preferred_suffix' =>
            'contacts'
            .DIRECTORY_SEPARATOR
            .'persons'
            .DIRECTORY_SEPARATOR
            .'edit.blade.php',

        'marker' =>
            'CONTACT IDENTITY PERSON KTP EDIT',

        'html' =>
<<<'BLADE'
        <!-- CONTACT IDENTITY PERSON KTP EDIT -->
        <div
            class="mb-4 rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900"
        >
            <div
                style="
                    display:flex;
                    justify-content:space-between;
                    align-items:center;
                    gap:10px;
                    flex-wrap:wrap;
                "
            >
                <label class="block text-sm font-semibold text-gray-800 dark:text-white">
                    KTP Image
                </label>

                <?php if (! empty($person->ktp_image_path)): ?>
                    <a
                        href="{{ route('admin.contacts.persons.ktp', $person->id) }}"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="text-sm font-semibold text-blue-600 hover:underline dark:text-blue-400"
                    >
                        View Current KTP
                    </a>
                <?php endif; ?>
            </div>

            <input
                type="file"
                name="ktp_image"
                accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                class="mt-2 w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-950"
            >

            <p class="mt-1.5 text-xs text-gray-500">
                Upload file baru untuk mengganti KTP saat ini. Maksimal 5 MB.
            </p>
        </div>
BLADE,
    ], [
        'label' =>
            'Organization Create',

        'route' =>
            'admin.contacts.organizations.store',

        'preferred_suffix' =>
            'contacts'
            .DIRECTORY_SEPARATOR
            .'organizations'
            .DIRECTORY_SEPARATOR
            .'create.blade.php',

        'marker' =>
            'CONTACT IDENTITY ORGANIZATION NPWP CREATE',

        'html' =>
<<<'BLADE'
        <!-- CONTACT IDENTITY ORGANIZATION NPWP CREATE -->
        <div
            class="mb-4 rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900"
        >
            <label class="mb-1.5 block text-sm font-semibold text-gray-800 dark:text-white">
                NPWP Image
            </label>

            <input
                type="file"
                name="npwp_image"
                accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-950"
            >

            <p class="mt-1.5 text-xs text-gray-500">
                JPG, JPEG, PNG, atau WEBP. Maksimal 5 MB. Disimpan private dan tidak tampil pada Quick Add Client.
            </p>
        </div>
BLADE,
    ], [
        'label' =>
            'Organization Edit',

        'route' =>
            'admin.contacts.organizations.update',

        'preferred_suffix' =>
            'contacts'
            .DIRECTORY_SEPARATOR
            .'organizations'
            .DIRECTORY_SEPARATOR
            .'edit.blade.php',

        'marker' =>
            'CONTACT IDENTITY ORGANIZATION NPWP EDIT',

        'html' =>
<<<'BLADE'
        <!-- CONTACT IDENTITY ORGANIZATION NPWP EDIT -->
        <div
            class="mb-4 rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900"
        >
            <div
                style="
                    display:flex;
                    justify-content:space-between;
                    align-items:center;
                    gap:10px;
                    flex-wrap:wrap;
                "
            >
                <label class="block text-sm font-semibold text-gray-800 dark:text-white">
                    NPWP Image
                </label>

                <?php if (! empty($organization->npwp_image_path)): ?>
                    <a
                        href="{{ route('admin.contacts.organizations.npwp', $organization->id) }}"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="text-sm font-semibold text-blue-600 hover:underline dark:text-blue-400"
                    >
                        View Current NPWP
                    </a>
                <?php endif; ?>
            </div>

            <input
                type="file"
                name="npwp_image"
                accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                class="mt-2 w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-950"
            >

            <p class="mt-1.5 text-xs text-gray-500">
                Upload file baru untuk mengganti NPWP saat ini. Maksimal 5 MB.
            </p>
        </div>
BLADE,
    ],
];

foreach (
    $viewSpecs
    as $spec
) {
    $preferred = null;

    foreach (
        recursiveFiles(
            $viewRoot,
            '.blade.php'
        )
        as $candidate
    ) {
        if (
            str_ends_with(
                $candidate,
                $spec['preferred_suffix']
            )
        ) {
            $preferred =
                $candidate;

            break;
        }
    }

    if (! $preferred) {
        $preferred =
            exactlyOne(
                filesContaining(
                    $viewRoot,
                    $spec['route'],
                    '.blade.php'
                ),
                $spec['label']
            );
    }

    $source =
        file_get_contents(
            $preferred
        );

    backupFile(
        $preferred,
        '.before-identity-documents.bak'
    );

    $source =
        patchFormTag(
            $source,
            $spec['route']
        );

    $source =
        injectAfterCsrf(
            source: $source,
            routeName: $spec['route'],
            html: $spec['html'],
            marker: $spec['marker']
        );

    file_put_contents(
        $preferred,
        $source
    );

    echo "[PASS] "
        .$spec['label']
        ." document field.\n";
}

/*
|--------------------------------------------------------------------------
| 4. Contact routes
|--------------------------------------------------------------------------
*/

$routeRoot =
    $projectRoot
    .'/packages/Webkul/Admin/src/Routes';

$routePath =
    exactlyOne(
        filesContaining(
            $routeRoot,
            'admin.contacts.persons.index',
            '.php'
        ),
        'Contact routes'
    );

$routeSource =
    file_get_contents(
        $routePath
    );

backupFile(
    $routePath,
    '.before-identity-documents.bak'
);

if (
    ! str_contains(
        $routeSource,
        'admin.contacts.persons.ktp'
    )
) {
    $personNamePosition =
        strpos(
            $routeSource,
            'admin.contacts.persons.view'
        );

    if (
        $personNamePosition === false
    ) {
        /*
         * Fallback to edit route if this project removed Person View.
         */
        $personNamePosition =
            strpos(
                $routeSource,
                'admin.contacts.persons.edit'
            );
    }

    if (
        $personNamePosition === false
    ) {
        fwrite(
            STDERR,
            "Person route anchor tidak ditemukan.\n"
        );

        exit(15);
    }

    $statementEnd =
        strpos(
            $routeSource,
            ';',
            $personNamePosition
        );

    if (
        $statementEnd === false
    ) {
        fwrite(
            STDERR,
            "Akhir Person route statement tidak ditemukan.\n"
        );

        exit(16);
    }

    $insertPosition =
        $statementEnd
        + 1;

    $routeSource =
        substr_replace(
            $routeSource,
            "\n\n"
            ."        Route::get(\n"
            ."            '{id}/ktp-document',\n"
            ."            [\\Webkul\\Admin\\Http\\Controllers\\Contact\\ContactIdentityDocumentController::class, 'personKtp']\n"
            ."        )->name('admin.contacts.persons.ktp');",
            $insertPosition,
            0
        );
}

if (
    ! str_contains(
        $routeSource,
        'admin.contacts.organizations.npwp'
    )
) {
    $organizationNamePosition =
        strpos(
            $routeSource,
            'admin.contacts.organizations.edit'
        );

    if (
        $organizationNamePosition === false
    ) {
        fwrite(
            STDERR,
            "Organization route anchor tidak ditemukan.\n"
        );

        exit(17);
    }

    $statementEnd =
        strpos(
            $routeSource,
            ';',
            $organizationNamePosition
        );

    if (
        $statementEnd === false
    ) {
        fwrite(
            STDERR,
            "Akhir Organization route statement tidak ditemukan.\n"
        );

        exit(18);
    }

    $insertPosition =
        $statementEnd
        + 1;

    $routeSource =
        substr_replace(
            $routeSource,
            "\n\n"
            ."        Route::get(\n"
            ."            '{id}/npwp-document',\n"
            ."            [\\Webkul\\Admin\\Http\\Controllers\\Contact\\ContactIdentityDocumentController::class, 'organizationNpwp']\n"
            ."        )->name('admin.contacts.organizations.npwp');",
            $insertPosition,
            0
        );
}

file_put_contents(
    $routePath,
    $routeSource
);

echo "[PASS] Private KTP/NPWP preview routes.\n";

/*
|--------------------------------------------------------------------------
| 5. ACL
|--------------------------------------------------------------------------
|
| Person KTP preview inherits Person VIEW permission.
| Organization NPWP preview inherits Organization EDIT permission.
|
*/

$aclPath =
    $projectRoot
    .'/packages/Webkul/Admin/src/Config/acl.php';

if (! is_file($aclPath)) {
    fwrite(
        STDERR,
        "ACL tidak ditemukan.\n"
    );

    exit(19);
}

$aclSource =
    file_get_contents(
        $aclPath
    );

backupFile(
    $aclPath,
    '.before-identity-documents.bak'
);

function addRouteToAclBlock(
    string $source,
    string $key,
    string $existingRoute,
    string $newRoute
): string {
    if (
        str_contains(
            $source,
            "'".$newRoute."'"
        )
    ) {
        return $source;
    }

    $keyPattern =
        "/'key'\\s*=>\\s*'"
        .preg_quote(
            $key,
            '/'
        )
        ."'/'";

    if (
        preg_match(
            $keyPattern,
            $source,
            $keyMatch,
            PREG_OFFSET_CAPTURE
        ) !== 1
    ) {
        fwrite(
            STDERR,
            "ACL key {$key} tidak ditemukan.\n"
        );

        exit(20);
    }

    $keyPosition =
        $keyMatch[0][1];

    $blockEnd =
        strpos(
            $source,
            '], [',
            $keyPosition
        );

    if (
        $blockEnd === false
    ) {
        $blockEnd =
            strpos(
                $source,
                "],\n    [",
                $keyPosition
            );
    }

    if (
        $blockEnd === false
    ) {
        fwrite(
            STDERR,
            "Akhir ACL block {$key} tidak ditemukan.\n"
        );

        exit(21);
    }

    $block =
        substr(
            $source,
            $keyPosition,
            $blockEnd
                - $keyPosition
        );

    $singlePattern =
        "/'route'\\s*=>\\s*'"
        .preg_quote(
            $existingRoute,
            '/'
        )
        ."'/";

    if (
        preg_match(
            $singlePattern,
            $block
        ) === 1
    ) {
        $block =
            preg_replace(
                $singlePattern,
                "'route' => [\n"
                ."            '"
                .$existingRoute
                ."',\n"
                ."            '"
                .$newRoute
                ."',\n"
                ."        ]",
                $block,
                1
            );
    } elseif (
        preg_match(
            "/'route'\\s*=>\\s*\\[(.*?)\\]/s",
            $block,
            $arrayMatch
        ) === 1
    ) {
        $routeArray =
            $arrayMatch[0];

        if (
            ! str_contains(
                $routeArray,
                "'".$newRoute."'"
            )
        ) {
            $newArray =
                preg_replace(
                    "/('route'\\s*=>\\s*\\[)/",
                    "$1\n"
                    ."            '"
                    .$newRoute
                    ."',",
                    $routeArray,
                    1
                );

            $block =
                str_replace(
                    $routeArray,
                    $newArray,
                    $block
                );
        }
    } else {
        fwrite(
            STDERR,
            "Format ACL route {$key} tidak dikenali.\n"
        );

        exit(22);
    }

    return substr_replace(
        $source,
        $block,
        $keyPosition,
        $blockEnd
            - $keyPosition
    );
}

$aclSource =
    addRouteToAclBlock(
        source: $aclSource,
        key: 'contacts.persons.view',
        existingRoute:
            'admin.contacts.persons.view',
        newRoute:
            'admin.contacts.persons.ktp'
    );

$aclSource =
    addRouteToAclBlock(
        source: $aclSource,
        key: 'contacts.organizations.edit',
        existingRoute:
            'admin.contacts.organizations.edit',
        newRoute:
            'admin.contacts.organizations.npwp'
    );

file_put_contents(
    $aclPath,
    $aclSource
);

echo "[PASS] KTP/NPWP preview ACL inheritance.\n";

echo "\n";
echo "Contact Identity Documents patch selesai.\n";
echo "Person Controller      : {$personControllerPath}\n";
echo "Organization Controller: {$organizationControllerPath}\n";
echo "Contact Routes         : {$routePath}\n";
echo "ACL                    : {$aclPath}\n";
echo "\n";
echo "Quick Add Client / Quote / Invoice tidak dipatch.\n";
