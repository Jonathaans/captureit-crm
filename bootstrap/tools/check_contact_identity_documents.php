<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

require __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';

$app->make(
    Kernel::class
)->bootstrap();

echo "CONTACT IDENTITY DOCUMENT CHECK\n";
echo "===============================\n\n";

$errors = [];

if (
    ! Schema::hasColumn(
        'persons',
        'ktp_image_path'
    )
) {
    $errors[] =
        'persons.ktp_image_path belum ada.';
}

if (
    ! Schema::hasColumn(
        'organizations',
        'npwp_image_path'
    )
) {
    $errors[] =
        'organizations.npwp_image_path belum ada.';
}

if (
    ! Route::has(
        'admin.contacts.persons.ktp'
    )
) {
    $errors[] =
        'Route admin.contacts.persons.ktp belum terdaftar.';
}

if (
    ! Route::has(
        'admin.contacts.organizations.npwp'
    )
) {
    $errors[] =
        'Route admin.contacts.organizations.npwp belum terdaftar.';
}

$localRoot =
    config(
        'filesystems.disks.local.root'
    );

$publicRoot =
    config(
        'filesystems.disks.public.root'
    );

echo "Local disk root : "
    .($localRoot ?: '-')
    ."\n";

echo "Public disk root: "
    .($publicRoot ?: '-')
    ."\n\n";

if (
    $localRoot
    && $publicRoot
    && realpath(
        dirname(
            $localRoot
        )
    ) === realpath(
        dirname(
            $publicRoot
        )
    )
    && realpath(
        $localRoot
    ) === realpath(
        $publicRoot
    )
) {
    $errors[] =
        'Disk local dan public menunjuk lokasi yang sama. Dokumen identitas harus private.';
}

if ($errors) {
    echo "FAIL\n";

    foreach (
        $errors
        as $error
    ) {
        echo " - {$error}\n";
    }

    exit(1);
}

echo "PASS\n";
echo " - Person KTP column ready\n";
echo " - Organization NPWP column ready\n";
echo " - Private preview routes ready\n";
echo " - Storage disk local configured separately from public\n";
