<?php

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

function backupOnce(
    string $path,
    string $suffix
): void {
    $backup =
        $path
        .$suffix;

    if (
        is_file(
            $path
        )
        && ! is_file(
            $backup
        )
    ) {
        copy(
            $path,
            $backup
        );
    }
}

function recursiveFiles(
    string $root,
    string $suffix
): array {
    $files = [];

    if (! is_dir($root)) {
        return $files;
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
            $files[] =
                $file->getPathname();
        }
    }

    return $files;
}

function exactlyOne(
    array $matches,
    string $label
): string {
    if (count($matches) !== 1) {
        fwrite(
            STDERR,
            "{$label}: expected 1 file, found "
            .count(
                $matches
            )
            .".\n"
        );

        foreach ($matches as $match) {
            fwrite(
                STDERR,
                " - {$match}\n"
            );
        }

        exit(10);
    }

    return $matches[0];
}

function patchProviders(
    string $projectRoot
): void {
    $path =
        $projectRoot
        .'/bootstrap/providers.php';

    if (! is_file($path)) {
        fwrite(
            STDERR,
            "bootstrap/providers.php tidak ditemukan.\n"
        );

        exit(11);
    }

    $source =
        file_get_contents(
            $path
        );

    $provider =
        '\\Webkul\\Admin\\Providers\\GoogleCalendarIntegrationServiceProvider::class';

    if (
        str_contains(
            $source,
            $provider
        )
    ) {
        echo "[SKIP] Google Calendar provider sudah terdaftar.\n";

        return;
    }

    $closing =
        strrpos(
            $source,
            '];'
        );

    if ($closing === false) {
        fwrite(
            STDERR,
            "providers.php format tidak dikenali.\n"
        );

        exit(12);
    }

    backupOnce(
        $path,
        '.before-google-calendar-v1.bak'
    );

    $source =
        substr_replace(
            $source,
            "    {$provider},\n",
            $closing,
            0
        );

    file_put_contents(
        $path,
        $source
    );

    echo "[PASS] Google Calendar service provider registered.\n";
}

function findLeadRouteFile(
    string $projectRoot
): string {
    $matches = [];

    foreach (
        recursiveFiles(
            $projectRoot
            .'/packages/Webkul/Admin/src/Routes',
            '.php'
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
                'admin.leads.view'
            )
        ) {
            $matches[] =
                $path;
        }
    }

    return exactlyOne(
        $matches,
        'Lead routes'
    );
}

function patchRoutes(
    string $path
): void {
    $source =
        file_get_contents(
            $path
        );

    $marker =
        'CRM GOOGLE CALENDAR EVENTS V1 ROUTES';

    if (
        str_contains(
            $source,
            $marker
        )
    ) {
        echo "[SKIP] Google Calendar routes sudah ada.\n";

        return;
    }

    backupOnce(
        $path,
        '.before-google-calendar-v1.bak'
    );

    $block = <<<'PHP'

/*
|--------------------------------------------------------------------------
| CRM GOOGLE CALENDAR EVENTS V1 ROUTES
|--------------------------------------------------------------------------
*/

Route::prefix('google-calendar')
    ->group(function () {
        Route::get(
            'lead/{leadId}',
            [\Webkul\Admin\Http\Controllers\GoogleCalendar\LeadCalendarController::class, 'edit']
        )->name('admin.google-calendar.leads.edit');

        Route::post(
            'lead/{leadId}',
            [\Webkul\Admin\Http\Controllers\GoogleCalendar\LeadCalendarController::class, 'update']
        )->name('admin.google-calendar.leads.update');

        Route::post(
            'lead/{leadId}/sync',
            [\Webkul\Admin\Http\Controllers\GoogleCalendar\LeadCalendarController::class, 'sync']
        )->name('admin.google-calendar.leads.sync');
    });
PHP;

    $source .=
        $block
        ."\n";

    file_put_contents(
        $path,
        $source
    );

    echo "[PASS] Google Calendar Lead routes added.\n";
}

function patchAcl(
    string $projectRoot
): void {
    $path =
        $projectRoot
        .'/packages/Webkul/Admin/src/Config/acl.php';

    if (! is_file($path)) {
        fwrite(
            STDERR,
            "ACL file tidak ditemukan.\n"
        );

        exit(13);
    }

    $source =
        file_get_contents(
            $path
        );

    if (
        str_contains(
            $source,
            "'admin.google-calendar.leads.edit'"
        )
    ) {
        echo "[SKIP] Google Calendar ACL sudah ada.\n";

        return;
    }

    if (
        preg_match(
            "/'key'\\s*=>\\s*'leads\\.view'/",
            $source,
            $keyMatch,
            PREG_OFFSET_CAPTURE
        ) !== 1
    ) {
        fwrite(
            STDERR,
            "ACL key leads.view tidak ditemukan.\n"
        );

        exit(14);
    }

    $keyPos =
        $keyMatch[0][1];

    $chunk =
        substr(
            $source,
            $keyPos,
            2600
        );

    $newRoutes = [
        'admin.google-calendar.leads.edit',
        'admin.google-calendar.leads.update',
        'admin.google-calendar.leads.sync',
    ];

    if (
        preg_match(
            "/'route'\\s*=>\\s*'admin\\.leads\\.view'/",
            $chunk,
            $routeMatch,
            PREG_OFFSET_CAPTURE
        ) === 1
    ) {
        $replacement =
            "'route' => [\n"
            ."            'admin.leads.view',\n";

        foreach ($newRoutes as $route) {
            $replacement .=
                "            '"
                .$route
                ."',\n";
        }

        $replacement .=
            '        ]';

        backupOnce(
            $path,
            '.before-google-calendar-v1.bak'
        );

        $source =
            substr_replace(
                $source,
                $replacement,
                $keyPos
                + $routeMatch[0][1],
                strlen(
                    $routeMatch[0][0]
                )
            );

        file_put_contents(
            $path,
            $source
        );

        echo "[PASS] Google Calendar routes added to leads.view ACL.\n";

        return;
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

        foreach ($newRoutes as $route) {
            $insert .=
                "\n            '"
                .$route
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

        backupOnce(
            $path,
            '.before-google-calendar-v1.bak'
        );

        $source =
            substr_replace(
                $source,
                $new,
                $keyPos
                + $arrayMatch[0][1],
                strlen(
                    $old
                )
            );

        file_put_contents(
            $path,
            $source
        );

        echo "[PASS] Google Calendar routes added to leads.view ACL.\n";

        return;
    }

    fwrite(
        STDERR,
        "Format route ACL leads.view tidak dikenali.\n"
    );

    exit(15);
}

function findLeadView(
    string $projectRoot
): ?string {
    $preferred = [
        $projectRoot
            .'/packages/Webkul/Admin/src/Resources/views/leads/view.blade.php',

        $projectRoot
            .'/packages/Webkul/Admin/src/Resources/views/leads/view/index.blade.php',
    ];

    foreach ($preferred as $path) {
        if (is_file($path)) {
            return $path;
        }
    }

    $matches = [];

    foreach (
        recursiveFiles(
            $projectRoot
            .'/packages/Webkul/Admin/src/Resources/views/leads',
            '.blade.php'
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
                '$lead'
            )
            && (
                str_contains(
                    $source,
                    'admin.leads.view'
                )
                || str_contains(
                    $source,
                    'leads.view'
                )
            )
        ) {
            $matches[] =
                $path;
        }
    }

    if (count($matches) === 1) {
        return $matches[0];
    }

    return null;
}

function patchLeadView(
    string $projectRoot
): void {
    $path =
        findLeadView(
            $projectRoot
        );

    if (! $path) {
        echo "[WARN] Lead View Blade tidak ditemukan unik. Route tetap aktif, tetapi tombol otomatis dilewati.\n";

        return;
    }

    $source =
        file_get_contents(
            $path
        );

    $marker =
        'CRM GOOGLE CALENDAR LEAD BUTTON V1';

    if (
        str_contains(
            $source,
            $marker
        )
    ) {
        echo "[SKIP] Lead Google Calendar button sudah ada.\n";

        return;
    }

    $closing =
        strrpos(
            $source,
            '</x-admin::layouts>'
        );

    if ($closing === false) {
        echo "[WARN] Closing x-admin::layouts tidak ditemukan. Tombol Lead dilewati.\n";

        return;
    }

    $html = <<<'BLADE'

    <!-- CRM GOOGLE CALENDAR LEAD BUTTON V1 -->
    <a
        href="{{ route(
            'admin.google-calendar.leads.edit',
            $lead->id
        ) }}"
        class="primary-button"
        style="
            position:fixed;
            right:24px;
            bottom:24px;
            z-index:40;
            box-shadow:0 8px 24px rgba(0,0,0,.18);
        "
    >
        Confirm Event / Google Calendar
    </a>

BLADE;

    backupOnce(
        $path,
        '.before-google-calendar-v1.bak'
    );

    $source =
        substr_replace(
            $source,
            $html,
            $closing,
            0
        );

    file_put_contents(
        $path,
        $source
    );

    echo "[PASS] Lead View Google Calendar button added.\n";
}

function patchEnvExample(
    string $projectRoot
): void {
    $path =
        $projectRoot
        .'/.env.example';

    if (! is_file($path)) {
        echo "[WARN] .env.example tidak ditemukan. Skip env template patch.\n";

        return;
    }

    $source =
        file_get_contents(
            $path
        );

    if (
        str_contains(
            $source,
            'GOOGLE_CALENDAR_ENABLED='
        )
    ) {
        echo "[SKIP] .env.example Google Calendar variables sudah ada.\n";

        return;
    }

    backupOnce(
        $path,
        '.before-google-calendar-v1.bak'
    );

    $source .= <<<'ENV'


# CRM Google Calendar
GOOGLE_CALENDAR_ENABLED=false
GOOGLE_CALENDAR_ID=
GOOGLE_CALENDAR_CREDENTIALS_PATH=
GOOGLE_CALENDAR_TIMEZONE=Asia/Jakarta
ENV;

    $source .=
        "\n";

    file_put_contents(
        $path,
        $source
    );

    echo "[PASS] .env.example Google Calendar variables added.\n";
}

patchProviders(
    $projectRoot
);

$routePath =
    findLeadRouteFile(
        $projectRoot
    );

patchRoutes(
    $routePath
);

patchAcl(
    $projectRoot
);

patchLeadView(
    $projectRoot
);

patchEnvExample(
    $projectRoot
);

echo "\n";
echo "CRM Google Calendar Events V1 installer selesai.\n";
echo "Lead routes: {$routePath}\n";
