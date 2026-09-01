<?php

$projectRoot = realpath(__DIR__.'/..');

if (! $projectRoot) {
    fwrite(STDERR, "Project root tidak ditemukan.\n");
    exit(1);
}

function backupOnce(string $path, string $suffix): void
{
    $backup = $path.$suffix;

    if (! is_file($backup)) {
        copy($path, $backup);
    }
}

function restoreOriginalEdit(string $path): void
{
    $candidates = [
        $path.'.before-identity-documents-v1-1.bak',
        $path.'.before-identity-documents.bak',
    ];

    foreach ($candidates as $backup) {
        if (is_file($backup)) {
            copy($backup, $path);

            echo "[PASS] Restored original Edit Blade from:\n";
            echo "       {$backup}\n";

            return;
        }
    }

    fwrite(
        STDERR,
        "Backup original tidak ditemukan untuk {$path}\n"
    );

    exit(2);
}

function ensureMultipart(string $source, string $label): string
{
    foreach (
        [
            '/<form\b[^>]*>/s',
            '/<x-admin::form\b[^>]*>/s',
        ]
        as $pattern
    ) {
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
                str_contains(
                    strtolower($tag),
                    'enctype='
                )
            ) {
                return $source;
            }

            $newTag =
                substr($tag, 0, -1)
                .' enctype="multipart/form-data">';

            return substr_replace(
                $source,
                $newTag,
                $offset,
                strlen($tag)
            );
        }
    }

    fwrite(
        STDERR,
        "{$label}: opening form tag tidak ditemukan.\n"
    );

    exit(3);
}

function injectBeforeEvent(
    string $source,
    string $eventName,
    string $marker,
    string $html,
    string $label
): string {
    if (str_contains($source, $marker)) {
        return $source;
    }

    $eventPos = strpos(
        $source,
        "view_render_event('".$eventName."'"
    );

    if ($eventPos === false) {
        $eventPos = strpos(
            $source,
            'view_render_event("'.$eventName.'"'
        );
    }

    if ($eventPos === false) {
        fwrite(
            STDERR,
            "{$label}: render event {$eventName} tidak ditemukan.\n"
        );

        exit(4);
    }

    $lineStart = strrpos(
        substr($source, 0, $eventPos),
        "\n"
    );

    $lineStart =
        $lineStart === false
            ? 0
            : $lineStart + 1;

    return substr_replace(
        $source,
        $html."\n\n",
        $lineStart,
        0
    );
}

function patchEditBlade(
    string $path,
    string $eventName,
    string $marker,
    string $html,
    string $label
): void {
    if (! is_file($path)) {
        fwrite(
            STDERR,
            "{$label} tidak ditemukan: {$path}\n"
        );

        exit(5);
    }

    backupOnce(
        $path,
        '.before-v1-2-hotfix.bak'
    );

    restoreOriginalEdit($path);

    $source = file_get_contents($path);

    if ($source === false) {
        fwrite(
            STDERR,
            "{$label} tidak dapat dibaca setelah restore.\n"
        );

        exit(6);
    }

    $source = ensureMultipart(
        $source,
        $label
    );

    $source = injectBeforeEvent(
        $source,
        $eventName,
        $marker,
        $html,
        $label
    );

    file_put_contents(
        $path,
        $source
    );

    echo "[PASS] {$label} identity field reinstalled safely.\n";
}

$viewRoot =
    $projectRoot
    .'/packages/Webkul/Admin/src/Resources/views/contacts';

$personEdit =
    $viewRoot
    .'/persons/edit.blade.php';

$organizationEdit =
    $viewRoot
    .'/organizations/edit.blade.php';

$personHtml = <<<'BLADE'
                <!-- CONTACT IDENTITY PERSON KTP EDIT SAFE -->
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

                        <a
                            href="{{ route('admin.contacts.persons.ktp', $person->id) }}"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="text-sm font-semibold text-blue-600 hover:underline dark:text-blue-400"
                        >
                            View Current KTP
                        </a>
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
BLADE;

$organizationHtml = <<<'BLADE'
                <!-- CONTACT IDENTITY ORGANIZATION NPWP EDIT SAFE -->
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

                        <a
                            href="{{ route('admin.contacts.organizations.npwp', $organization->id) }}"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="text-sm font-semibold text-blue-600 hover:underline dark:text-blue-400"
                        >
                            View Current NPWP
                        </a>
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
BLADE;

patchEditBlade(
    path: $personEdit,
    eventName:
        'admin.contacts.persons.edit.form_controls.after',
    marker:
        'CONTACT IDENTITY PERSON KTP EDIT SAFE',
    html:
        $personHtml,
    label:
        'Person Edit'
);

patchEditBlade(
    path: $organizationEdit,
    eventName:
        'admin.contacts.organizations.edit.form_controls.after',
    marker:
        'CONTACT IDENTITY ORGANIZATION NPWP EDIT SAFE',
    html:
        $organizationHtml,
    label:
        'Organization Edit'
);

echo "\n";
echo "V1.2 Edit Blade hotfix selesai.\n";
echo "Controller, route, ACL, migration tidak diubah.\n";
