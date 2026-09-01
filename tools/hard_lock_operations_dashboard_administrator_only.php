<?php

/*
|--------------------------------------------------------------------------
| Operations Dashboard - Administrator Only V1
|--------------------------------------------------------------------------
|
| Hard-lock /admin/operations-dashboard to role:
|
| Administrator
|
| This is a backend restriction. ACL is still checked as a second layer.
| No other dashboard/business module is modified.
|
*/

$projectRoot = realpath(__DIR__.'/..');

if (! $projectRoot) {
    fwrite(STDERR, "Project root tidak ditemukan.\n");
    exit(1);
}

$path =
    $projectRoot
    .'/packages/Webkul/Admin/src/Http/Controllers/Dashboard/OperationsDashboardController.php';

if (! is_file($path)) {
    fwrite(
        STDERR,
        "OperationsDashboardController.php tidak ditemukan.\n"
    );
    exit(2);
}

$source = file_get_contents($path);

if ($source === false) {
    fwrite(
        STDERR,
        "OperationsDashboardController.php tidak dapat dibaca.\n"
    );
    exit(3);
}

$marker =
    'OPERATIONS DASHBOARD ADMINISTRATOR ONLY V1';

if (str_contains($source, $marker)) {
    echo "[SKIP] Administrator-only hard lock sudah terpasang.\n";
    exit(0);
}

$old = <<<'PHP'
        abort_unless(
            auth()->guard('user')->check(),
            403
        );

        if (
            function_exists('bouncer')
            && ! bouncer()->hasPermission(
                'operations-dashboard'
            )
        ) {
            abort(403);
        }

        $user =
            auth()->guard('user')->user();

        $user->loadMissing('role');
PHP;

$new = <<<'PHP'
        abort_unless(
            auth()->guard('user')->check(),
            403
        );

        $user =
            auth()->guard('user')->user();

        $user->loadMissing('role');

        /*
         * OPERATIONS DASHBOARD ADMINISTRATOR ONLY V1
         *
         * Backend hard lock:
         * even if another role accidentally receives the
         * "operations-dashboard" ACL permission, access is still denied.
         */
        abort_unless(
            strtolower(
                trim(
                    (string) (
                        $user->role?->name
                        ?? ''
                    )
                )
            ) === 'administrator',
            403
        );

        /*
         * Keep the existing ACL as a second security layer.
         */
        if (
            function_exists('bouncer')
            && ! bouncer()->hasPermission(
                'operations-dashboard'
            )
        ) {
            abort(403);
        }
PHP;

$count = substr_count($source, $old);

if ($count !== 1) {
    fwrite(
        STDERR,
        "Controller berbeda dari Phase 3 yang diketahui. "
        ."Expected 1 target block, found {$count}. "
        ."Patch dihentikan tanpa mengubah file.\n"
    );
    exit(4);
}

$backup =
    $path
    .'.before-operations-dashboard-admin-only-v1.bak';

if (! is_file($backup)) {
    copy($path, $backup);
}

$source = str_replace(
    $old,
    $new,
    $source,
    $replaced
);

if ($replaced !== 1) {
    fwrite(
        STDERR,
        "Patch gagal. File tidak ditulis.\n"
    );
    exit(5);
}

file_put_contents(
    $path,
    $source
);

echo "[PASS] Operations Dashboard hard-locked to Administrator role.\n";
echo "[PASS] Existing operations-dashboard ACL tetap aktif sebagai layer kedua.\n";
echo "[PASS] Role lain akan menerima HTTP 403.\n";
