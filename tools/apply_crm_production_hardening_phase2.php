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
        .'.before-hardening-phase2.bak';

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

$providerSource =
    file_get_contents($providerPath);

$provider =
    '\\Webkul\\Admin\\Providers\\CrmOperationsServiceProvider::class';

if (! str_contains($providerSource, $provider)) {
    $end =
        strrpos(
            $providerSource,
            '];'
        );

    if ($end === false) {
        fwrite(STDERR, "providers.php format tidak dikenali.\n");
        exit(2);
    }

    backupOnce($providerPath);

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

    echo "[PASS] CrmOperationsServiceProvider registered.\n";
} else {
    echo "[SKIP] Operations provider already registered.\n";
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
    file_get_contents($aclPath);

$entries = '';

if (
    ! str_contains(
        $acl,
        "'key'   => 'vendors'"
    )
    && ! str_contains(
        $acl,
        "'key' => 'vendors'"
    )
) {
    $entries .= <<<'PHP'

    [
        'key'   => 'vendors',
        'name'  => 'Vendor Master',
        'route' => [
            'admin.vendors.index',
            'admin.vendors.create',
            'admin.vendors.store',
            'admin.vendors.edit',
            'admin.vendors.update',
        ],
        'sort'  => 90,
    ],
PHP;
}

if (
    ! str_contains(
        $acl,
        "'key'   => 'crm-notifications'"
    )
    && ! str_contains(
        $acl,
        "'key' => 'crm-notifications'"
    )
) {
    $entries .= <<<'PHP'

    [
        'key'   => 'crm-notifications',
        'name'  => 'CRM Notifications',
        'route' => [
            'admin.crm-notifications.index',
            'admin.crm-notifications.read',
            'admin.crm-notifications.read-all',
        ],
        'sort'  => 91,
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

    echo "[PASS] Vendor + Notification ACL added.\n";
} else {
    echo "[SKIP] Vendor + Notification ACL already exists.\n";
}

/*
|--------------------------------------------------------------------------
| Google Calendar: synchronous -> queued
|--------------------------------------------------------------------------
*/

$googleController =
    $projectRoot
    .'/packages/Webkul/Admin/src/Http/Controllers/GoogleCalendar/LeadCalendarController.php';

if (! is_file($googleController)) {
    echo "[WARN] Google Calendar controller tidak ditemukan. Queue patch dilewati.\n";
} else {
    $source =
        file_get_contents(
            $googleController
        );

    $marker =
        'GOOGLE CALENDAR QUEUE HARDENING V1';

    if (str_contains($source, $marker)) {
        echo "[SKIP] Google Calendar queue hardening already applied.\n";
    } else {
        $oldUpdate = <<<'PHP'
        try {
            $googleCalendar->sync(
                $event
            );

            session()->flash(
                'success',
                'Confirmed Event tersimpan dan Google Calendar berhasil disinkronkan.'
            );
        } catch (Throwable $exception) {
            $event->update([
                'sync_status' =>
                    'error',

                'sync_error' =>
                    $exception->getMessage(),
            ]);

            session()->flash(
                'warning',
                'Event tersimpan, tetapi Google Calendar sync gagal: '
                .$exception->getMessage()
            );
        }
PHP;

        $newUpdate = <<<'PHP'
        /*
         * GOOGLE CALENDAR QUEUE HARDENING V1
         *
         * Save first, sync through queue with retry/backoff.
         */
        $event->update([
            'sync_status' => 'pending',
            'sync_error' => null,
        ]);

        \Webkul\Admin\Jobs\SyncGoogleCalendarEventJob::dispatch(
            (int) $event->id
        );

        session()->flash(
            'success',
            'Confirmed Event tersimpan. Google Calendar sync masuk queue.'
        );
PHP;

        $oldSync = <<<'PHP'
        try {
            $googleCalendar->sync(
                $event
            );

            session()->flash(
                'success',
                'Google Calendar berhasil disinkronkan.'
            );
        } catch (Throwable $exception) {
            $event->update([
                'sync_status' =>
                    'error',

                'sync_error' =>
                    $exception->getMessage(),
            ]);

            session()->flash(
                'warning',
                'Google Calendar sync gagal: '
                .$exception->getMessage()
            );
        }
PHP;

        $newSync = <<<'PHP'
        /*
         * GOOGLE CALENDAR QUEUE HARDENING V1 - MANUAL RETRY
         */
        $event->update([
            'sync_status' => 'pending',
            'sync_error' => null,
        ]);

        \Webkul\Admin\Jobs\SyncGoogleCalendarEventJob::dispatch(
            (int) $event->id
        );

        session()->flash(
            'success',
            'Google Calendar retry masuk queue.'
        );
PHP;

        if (
            substr_count(
                $source,
                $oldUpdate
            ) !== 1
            || substr_count(
                $source,
                $oldSync
            ) !== 1
        ) {
            fwrite(
                STDERR,
                "Google Calendar controller berbeda dari V1 yang diketahui. Queue patch dihentikan tanpa mengubah controller.\n"
            );

            exit(4);
        }

        backupOnce($googleController);

        $source =
            str_replace(
                $oldUpdate,
                $newUpdate,
                $source,
                $countUpdate
            );

        $source =
            str_replace(
                $oldSync,
                $newSync,
                $source,
                $countSync
            );

        file_put_contents(
            $googleController,
            $source
        );

        echo "[PASS] Google Calendar sync converted to queue + retry.\n";
    }
}

echo "\n";
echo "CRM Production Hardening Phase 2 installer selesai.\n";
echo "Next: php artisan migrate\n";
