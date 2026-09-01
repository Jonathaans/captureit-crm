<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

require __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';

$app->make(
    Kernel::class
)->bootstrap();

echo "CONTACT IDENTITY DOCUMENTS V2 CHECK\n";
echo "===================================\n\n";

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

foreach (
    [
        'admin.contacts.persons.identity',
        'admin.contacts.persons.identity.update',
        'admin.contacts.persons.ktp',
        'admin.contacts.organizations.identity',
        'admin.contacts.organizations.identity.update',
        'admin.contacts.organizations.npwp',
    ]
    as $routeName
) {
    if (! Route::has($routeName)) {
        $errors[] =
            'Route '
            .$routeName
            .' belum terdaftar.';
    }
}

$personEdit = base_path(
    'packages/Webkul/Admin/src/Resources/views/contacts/persons/edit.blade.php'
);

$organizationEdit = base_path(
    'packages/Webkul/Admin/src/Resources/views/contacts/organizations/edit.blade.php'
);

if (
    ! is_file($personEdit)
    || ! str_contains(
        file_get_contents($personEdit),
        'CONTACT IDENTITY V2.1 PERSON LINK'
    )
) {
    $errors[] =
        'Manage KTP link belum terpasang.';
}

if (
    ! is_file($organizationEdit)
    || ! str_contains(
        file_get_contents($organizationEdit),
        'CONTACT IDENTITY V2.1 ORGANIZATION LINK'
    )
) {
    $errors[] =
        'Manage NPWP link belum terpasang.';
}

if ($errors) {
    echo "FAIL\n";

    foreach ($errors as $error) {
        echo " - {$error}\n";
    }

    exit(1);
}

echo "PASS\n";
echo " - database columns ready\n";
echo " - Person identity routes ready\n";
echo " - Organization identity routes ready\n";
echo " - Manage KTP link ready\n";
echo " - Manage NPWP link ready\n";
