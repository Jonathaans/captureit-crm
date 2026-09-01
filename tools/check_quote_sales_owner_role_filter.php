<?php

use Illuminate\Contracts\Console\Kernel;
use Webkul\Admin\Services\QuoteSalesOwnerService;

require __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';

$app->make(
    Kernel::class
)->bootstrap();

echo "QUOTE SALES OWNER ROLE FILTER CHECK\n";
echo "===================================\n\n";

$service = app(
    QuoteSalesOwnerService::class
);

$users =
    $service->roleSummary();

if ($users->isEmpty()) {
    echo "FAIL\n";
    echo "Tidak ada user dengan role Sales Admin atau Sales User.\n";
    echo "Pastikan nama Role di Settings > Roles sesuai requirement.\n";

    exit(1);
}

echo "Eligible Sales Owners:\n\n";

foreach ($users as $user) {
    echo sprintf(
        " - #%d %s [%s]\n",
        $user['id'],
        $user['name'],
        $user['role']
    );
}

$createPath =
    base_path(
        'packages/Webkul/Admin/src/Resources/views/quotes/create.blade.php'
    );

$editPath =
    base_path(
        'packages/Webkul/Admin/src/Resources/views/quotes/edit.blade.php'
    );

$controllerCandidates = [];

$root =
    base_path(
        'packages/Webkul/Admin/src/Http/Controllers'
    );

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
        && $file->getExtension() === 'php'
    ) {
        $source =
            file_get_contents(
                $file->getPathname()
            );

        if (
            $source !== false
            && str_contains(
                $source,
                'class QuoteController'
            )
        ) {
            $controllerCandidates[] =
                $file->getPathname();
        }
    }
}

$errors = [];

if (
    ! is_file($createPath)
    || ! str_contains(
        file_get_contents($createPath),
        'QUOTE SALES OWNER ROLE FILTER CREATE'
    )
) {
    $errors[] =
        'Quote Create Sales Owner filter belum terpasang.';
}

if (
    ! is_file($editPath)
    || ! str_contains(
        file_get_contents($editPath),
        'QUOTE SALES OWNER ROLE FILTER EDIT'
    )
) {
    $errors[] =
        'Quote Edit Sales Owner filter belum terpasang.';
}

if (
    count(
        $controllerCandidates
    ) !== 1
    || ! str_contains(
        file_get_contents(
            $controllerCandidates[0]
        ),
        'QUOTE SALES OWNER ROLE VALIDATION'
    )
) {
    $errors[] =
        'QuoteController Sales Owner validation belum terpasang.';
}

if ($errors) {
    echo "\nFAIL\n";

    foreach ($errors as $error) {
        echo " - {$error}\n";
    }

    exit(2);
}

echo "\nPASS\n";
echo "Create Quote filter ready.\n";
echo "Edit Quote filter ready.\n";
echo "Server validation ready.\n";
