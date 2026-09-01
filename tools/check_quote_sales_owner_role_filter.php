<?php

use Illuminate\Contracts\Console\Kernel;
use Webkul\Admin\Services\QuoteSalesOwnerService;

require __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';

$app->make(Kernel::class)->bootstrap();

echo "QUOTE SALES OWNER ROLE FILTER CHECK\n";
echo "===================================\n\n";

$users = app(
    QuoteSalesOwnerService::class
)->roleSummary();

$allowed = [
    'administrator',
    'sales admin',
    'sales user',
];

$errors = [];

foreach ($users as $user) {
    echo sprintf(
        " - #%d %s [%s]\n",
        $user['id'],
        $user['name'],
        $user['role']
    );

    if (
        ! in_array(
            strtolower($user['role']),
            $allowed,
            true
        )
    ) {
        $errors[] =
            'Role tidak valid: '
            .$user['role'];
    }
}

$create = file_get_contents(
    base_path(
        'packages/Webkul/Admin/src/Resources/views/quotes/create.blade.php'
    )
);

$edit = file_get_contents(
    base_path(
        'packages/Webkul/Admin/src/Resources/views/quotes/edit.blade.php'
    )
);

if (
    $create === false
    || ! str_contains(
        $create,
        'QUOTE SALES OWNER ROLE FILTER CREATE V1.3'
    )
) {
    $errors[] =
        'Quote Create filter V1.3 belum terpasang.';
}

if (
    $edit === false
    || ! str_contains(
        $edit,
        'QUOTE SALES OWNER ROLE FILTER EDIT V1.3'
    )
) {
    $errors[] =
        'Quote Edit filter V1.3 belum terpasang.';
}

if ($errors) {
    echo "\nFAIL\n";

    foreach ($errors as $error) {
        echo " - {$error}\n";
    }

    exit(1);
}

echo "\nPASS\n";
echo "Allowed roles only: Administrator, Sales Admin, Sales User.\n";
