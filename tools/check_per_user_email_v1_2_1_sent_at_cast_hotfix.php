<?php

use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';

$app->make(Kernel::class)->bootstrap();

$path =
    base_path(
        'packages/Webkul/Admin/src/Models/UserEmailMessage.php'
    );

echo "USER EMAIL V1.2.1 SENT_AT CAST CHECK\n";
echo "====================================\n\n";

if (! is_file($path)) {
    echo "FAIL\n";
    echo " - UserEmailMessage.php tidak ditemukan.\n";
    exit(1);
}

$source = file_get_contents($path);

if (
    ! str_contains(
        $source,
        "'sent_at' => 'datetime'"
    )
) {
    echo "FAIL\n";
    echo " - sent_at datetime cast belum terpasang.\n";
    exit(1);
}

echo "PASS\n";
echo " - sent_at cast = datetime\n";
echo " - sent detail format() safe\n";
