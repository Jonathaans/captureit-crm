<?php

$projectRoot = realpath(__DIR__.'/..');

if (! $projectRoot) {
    fwrite(STDERR, "Project root tidak ditemukan.\n");
    exit(1);
}

function backupOnce(string $path): void
{
    $backup = $path.'.before-identity-documents-v1-1.bak';

    if (! is_file($backup)) {
        copy($path, $backup);
    }
}

function allFiles(string $root, string $suffix): array
{
    $result = [];

    if (! is_dir($root)) {
        return $result;
    }

    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(
            $root,
            FilesystemIterator::SKIP_DOTS
        )
    );

    foreach ($it as $file) {
        if (
            $file->isFile()
            && str_ends_with(
                strtolower($file->getFilename()),
                strtolower($suffix)
            )
        ) {
            $result[] = $file->getPathname();
        }
    }

    return $result;
}

function findDedicatedBlade(
    string $root,
    string $relative,
    string $routeName,
    string $label
): string {
    $preferred =
        $root
        .DIRECTORY_SEPARATOR
        .str_replace('/', DIRECTORY_SEPARATOR, $relative);

    if (is_file($preferred)) {
        return $preferred;
    }

    $matches = [];

    foreach (allFiles($root, '.blade.php') as $path) {
        $source = file_get_contents($path);

        if (
            $source !== false
            && str_contains($source, $routeName)
        ) {
            $matches[] = $path;
        }
    }

    if (count($matches) !== 1) {
        fwrite(
            STDERR,
            "{$label}: expected 1 Blade, found "
                .count($matches)
                .".\n"
        );

        foreach ($matches as $match) {
            fwrite(STDERR, " - {$match}\n");
        }

        exit(10);
    }

    return $matches[0];
}

function patchDedicatedForm(
    string $path,
    string $marker,
    string $html,
    string $label
): void {
    $source = file_get_contents($path);

    if ($source === false) {
        fwrite(STDERR, "{$label}: cannot read file.\n");
        exit(11);
    }

    backupOnce($path);

    if (str_contains($source, $marker)) {
        echo "[SKIP] {$label} already patched.\n";
        return;
    }

    /*
     * Dedicated Contact Create/Edit views only.
     * No dependency on a CSRF directive.
     */
    $patterns = [
        '/<form\b[^>]*>/s',
        '/<x-admin::form\b[^>]*>/s',
    ];

    foreach ($patterns as $pattern) {
        if (
            preg_match(
                $pattern,
                $source,
                $match,
                PREG_OFFSET_CAPTURE
            ) === 1
        ) {
            $tag = $match[0][0];
            $offset = $match[0][1];

            if (
                ! str_contains(
                    strtolower($tag),
                    'enctype='
                )
            ) {
                $newTag =
                    substr($tag, 0, -1)
                    .' enctype="multipart/form-data">';

                $source = substr_replace(
                    $source,
                    $newTag,
                    $offset,
                    strlen($tag)
                );

                $tag = $newTag;
            }

            $insertAt =
                $offset
                + strlen($tag);

            $source = substr_replace(
                $source,
                "\n\n".$html."\n",
                $insertAt,
                0
            );

            file_put_contents($path, $source);

            echo "[PASS] {$label} document field.\n";

            return;
        }
    }

    fwrite(
        STDERR,
        "{$label}: opening form tag tidak ditemukan.\n"
    );

    exit(12);
}

function findOneRouteFile(string $root): string
{
    $matches = [];

    foreach (allFiles($root, '.php') as $path) {
        $source = file_get_contents($path);

        if (
            $source !== false
            && str_contains(
                $source,
                'admin.contacts.persons.index'
            )
            && str_contains(
                $source,
                'admin.contacts.organizations.index'
            )
        ) {
            $matches[] = $path;
        }
    }

    if (count($matches) !== 1) {
        fwrite(
            STDERR,
            "Contact routes: expected 1 file, found "
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

function insertRouteAfterName(
    string $source,
    string $anchorName,
    string $newName,
    string $uri,
    string $method
): string {
    if (str_contains($source, $newName)) {
        return $source;
    }

    $namePos = strpos($source, $anchorName);

    if ($namePos === false) {
        fwrite(
            STDERR,
            "Route anchor {$anchorName} tidak ditemukan.\n"
        );

        exit(14);
    }

    $routeStart = strrpos(
        substr($source, 0, $namePos),
        'Route::'
    );

    $statementEnd = strpos(
        $source,
        ';',
        $namePos
    );

    if (
        $routeStart === false
        || $statementEnd === false
    ) {
        fwrite(
            STDERR,
            "Statement route {$anchorName} tidak dapat dibaca.\n"
        );

        exit(15);
    }

    $lineStart = strrpos(
        substr($source, 0, $routeStart),
        "\n"
    );

    $lineStart =
        $lineStart === false
            ? 0
            : $lineStart + 1;

    $indent = substr(
        $source,
        $lineStart,
        $routeStart - $lineStart
    );

    $route =
        "\n\n"
        .$indent
        ."Route::get(\n"
        .$indent
        ."    '".$uri."',\n"
        .$indent
        ."    [\\Webkul\\Admin\\Http\\Controllers\\Contact\\ContactIdentityDocumentController::class, '".$method."']\n"
        .$indent
        .")\n"
        .$indent
        ."    ->name('".$newName."');";

    return substr_replace(
        $source,
        $route,
        $statementEnd + 1,
        0
    );
}

function patchAclRoute(
    string $source,
    string $key,
    string $existingRoute,
    string $newRoute
): string {
    if (str_contains($source, "'".$newRoute."'")) {
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
    $chunk = substr($source, $keyPos, 2200);

    $singlePattern =
        "/'route'\\s*=>\\s*'"
        .preg_quote($existingRoute, '/')
        ."'\\s*,?/";

    if (
        preg_match(
            $singlePattern,
            $chunk,
            $routeMatch,
            PREG_OFFSET_CAPTURE
        ) === 1
    ) {
        $old = $routeMatch[0][0];

        $new =
            "'route' => [\n"
            ."            '".$existingRoute."',\n"
            ."            '".$newRoute."',\n"
            ."        ],";

        return substr_replace(
            $source,
            $new,
            $keyPos + $routeMatch[0][1],
            strlen($old)
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

        $new = preg_replace(
            "/('route'\\s*=>\\s*\\[)/",
            "$1\n"
                ."            '".$newRoute."',",
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
        "ACL route {$key} tidak dapat dipatch.\n"
    );

    exit(17);
}

/*
|--------------------------------------------------------------------------
| Dedicated Contact forms
|--------------------------------------------------------------------------
*/

$viewRoot =
    $projectRoot
    .'/packages/Webkul/Admin/src/Resources/views';

$specs = [
    [
        'label' => 'Person Create',
        'relative' => 'contacts/persons/create.blade.php',
        'route' => 'admin.contacts.persons.store',
        'marker' => 'CONTACT IDENTITY PERSON KTP CREATE',
        'html' => <<<'BLADE'
        <!-- CONTACT IDENTITY PERSON KTP CREATE -->
        <div class="mb-4 rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
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
                JPG, JPEG, PNG, atau WEBP. Maksimal 5 MB. Private document.
            </p>
        </div>
BLADE,
    ],
    [
        'label' => 'Person Edit',
        'relative' => 'contacts/persons/edit.blade.php',
        'route' => 'admin.contacts.persons.update',
        'marker' => 'CONTACT IDENTITY PERSON KTP EDIT',
        'html' => <<<'BLADE'
        <!-- CONTACT IDENTITY PERSON KTP EDIT -->
        <div class="mb-4 rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;">
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
    ],
    [
        'label' => 'Organization Create',
        'relative' => 'contacts/organizations/create.blade.php',
        'route' => 'admin.contacts.organizations.store',
        'marker' => 'CONTACT IDENTITY ORGANIZATION NPWP CREATE',
        'html' => <<<'BLADE'
        <!-- CONTACT IDENTITY ORGANIZATION NPWP CREATE -->
        <div class="mb-4 rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
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
                JPG, JPEG, PNG, atau WEBP. Maksimal 5 MB. Private document.
            </p>
        </div>
BLADE,
    ],
    [
        'label' => 'Organization Edit',
        'relative' => 'contacts/organizations/edit.blade.php',
        'route' => 'admin.contacts.organizations.update',
        'marker' => 'CONTACT IDENTITY ORGANIZATION NPWP EDIT',
        'html' => <<<'BLADE'
        <!-- CONTACT IDENTITY ORGANIZATION NPWP EDIT -->
        <div class="mb-4 rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;">
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

foreach ($specs as $spec) {
    $path = findDedicatedBlade(
        $viewRoot,
        $spec['relative'],
        $spec['route'],
        $spec['label']
    );

    patchDedicatedForm(
        $path,
        $spec['marker'],
        $spec['html'],
        $spec['label']
    );
}

/*
|--------------------------------------------------------------------------
| Preview routes
|--------------------------------------------------------------------------
*/

$routePath = findOneRouteFile(
    $projectRoot
        .'/packages/Webkul/Admin/src/Routes'
);

$routeSource = file_get_contents($routePath);

if ($routeSource === false) {
    fwrite(STDERR, "Contact route file tidak dapat dibaca.\n");
    exit(18);
}

backupOnce($routePath);

$personAnchor =
    str_contains(
        $routeSource,
        'admin.contacts.persons.view'
    )
        ? 'admin.contacts.persons.view'
        : 'admin.contacts.persons.edit';

$routeSource = insertRouteAfterName(
    $routeSource,
    $personAnchor,
    'admin.contacts.persons.ktp',
    '{id}/ktp-document',
    'personKtp'
);

$routeSource = insertRouteAfterName(
    $routeSource,
    'admin.contacts.organizations.edit',
    'admin.contacts.organizations.npwp',
    '{id}/npwp-document',
    'organizationNpwp'
);

file_put_contents(
    $routePath,
    $routeSource
);

echo "[PASS] Private KTP/NPWP routes.\n";

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
    exit(19);
}

$aclSource = file_get_contents($aclPath);

if ($aclSource === false) {
    fwrite(STDERR, "ACL tidak dapat dibaca.\n");
    exit(20);
}

backupOnce($aclPath);

$personAclKey =
    preg_match(
        "/'key'\\s*=>\\s*'contacts\\.persons\\.view'/",
        $aclSource
    ) === 1
        ? 'contacts.persons.view'
        : 'contacts.persons.edit';

$personExistingRoute =
    $personAclKey === 'contacts.persons.view'
        ? 'admin.contacts.persons.view'
        : 'admin.contacts.persons.edit';

$aclSource = patchAclRoute(
    $aclSource,
    $personAclKey,
    $personExistingRoute,
    'admin.contacts.persons.ktp'
);

$aclSource = patchAclRoute(
    $aclSource,
    'contacts.organizations.edit',
    'admin.contacts.organizations.edit',
    'admin.contacts.organizations.npwp'
);

file_put_contents(
    $aclPath,
    $aclSource
);

echo "[PASS] KTP/NPWP ACL routes.\n";

echo "\n";
echo "Contact Identity Documents V1.1 hotfix selesai.\n";
echo "Routes: {$routePath}\n";
echo "ACL   : {$aclPath}\n";
echo "Quote/Invoice Quick Add Client tidak dipatch.\n";
