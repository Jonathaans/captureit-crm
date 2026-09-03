<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Route;

$root =
    dirname(
        __DIR__
    );

require
    $root
    . '/vendor/autoload.php';

$app =
    require_once
    $root
    . '/bootstrap/app.php';

$app->make(
    Kernel::class
)->bootstrap();

$menuPath =
    base_path(
        'packages/Webkul/Admin/src/Config/menu.php'
    );

$menu =
    require $menuPath;

function findMenu(array $menu, string $key): ?array
{
    foreach ($menu as $item) {
        if (
            is_array($item)
            && ($item['key'] ?? null) === $key
        ) {
            return $item;
        }
    }

    return null;
}

$operations =
    findMenu(
        $menu,
        'operations-dashboard'
    );

$audit =
    findMenu(
        $menu,
        'internal-chat-audit'
    );

$mail =
    findMenu(
        $menu,
        'mail'
    );

$myEmail =
    findMenu(
        $menu,
        'my-email'
    );

$checks = [
    'Operations Dashboard menu ada' =>
        $operations !== null,

    'Internal Chat Audit menu ada' =>
        $audit !== null,

    'Mail menu ada' =>
        $mail !== null,

    'My Email menu ada' =>
        $myEmail !== null,

    'Internal Chat Audit tepat setelah Operations sort' =>
        $operations
        && $audit
        && (float) $audit['sort']
            > (float) $operations['sort']
        && (float) $audit['sort']
            < (float) $operations['sort']
                + 1,

    'My Email tepat setelah Mail sort' =>
        $mail
        && $myEmail
        && (float) $myEmail['sort']
            > (float) $mail['sort']
        && (float) $myEmail['sort']
            < (float) $mail['sort']
                + 1,

    'Internal Chat Audit route tersedia' =>
        Route::has(
            'admin.operational-dashboard.internal-chat-audit.index'
        ),

    'My Email route tersedia' =>
        Route::has(
            'admin.my-email.inbox'
        ),
];

echo "CHECK SIDEBAR MENU POSITION FIX V1\n";
echo "==================================\n\n";

if ($operations && $audit) {
    echo
        '[INFO] Operations Dashboard sort = '
        . $operations['sort']
        . PHP_EOL;

    echo
        '[INFO] Internal Chat Audit sort  = '
        . $audit['sort']
        . PHP_EOL;
}

if ($mail && $myEmail) {
    echo
        '[INFO] Mail sort                 = '
        . $mail['sort']
        . PHP_EOL;

    echo
        '[INFO] My Email sort             = '
        . $myEmail['sort']
        . PHP_EOL;
}

echo PHP_EOL;

$failed = [];

foreach ($checks as $label => $ok) {
    echo
        ($ok ? '[OK]   ' : '[FAIL] ')
        . $label
        . PHP_EOL;

    if (!$ok) {
        $failed[] =
            $label;
    }
}

echo PHP_EOL;

if ($failed) {
    echo "HASIL: FAIL\n";
    exit(1);
}

echo "HASIL: PASS\n";
echo "\nSetelah PASS:\n";
echo "1. Logout/login jika permission baru saja diubah.\n";
echo "2. Ctrl + Shift + R.\n";
echo "3. Internal Chat Audit harus berada dekat Operations Dashboard.\n";
echo "4. My Email harus berada dekat Mail.\n";

exit(0);
