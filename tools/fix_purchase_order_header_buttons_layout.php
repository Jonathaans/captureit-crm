<?php

/*
|--------------------------------------------------------------------------
| Purchase Order Header Buttons Layout V1
|--------------------------------------------------------------------------
|
| Goal:
| Put:
|   + Create Purchase Order
|   Export Expense CSV
|
| next to each other in one compact flex group.
|
| This patch ONLY touches:
| packages/Webkul/Admin/src/Resources/views/purchase-orders/index.blade.php
|
*/

$projectRoot = realpath(__DIR__.'/..');

if (! $projectRoot) {
    fwrite(STDERR, "Project root tidak ditemukan.\n");
    exit(1);
}

$path =
    $projectRoot
    .'/packages/Webkul/Admin/src/Resources/views/purchase-orders/index.blade.php';

if (! is_file($path)) {
    fwrite(STDERR, "PO index Blade tidak ditemukan.\n");
    exit(2);
}

$source = file_get_contents($path);

if ($source === false) {
    fwrite(STDERR, "PO index Blade tidak dapat dibaca.\n");
    exit(3);
}

$marker = 'PO HEADER ACTIONS GROUP V1';

if (str_contains($source, $marker)) {
    echo "[SKIP] Header action buttons sudah dirapikan.\n";
    exit(0);
}

$createNeedles = [
    "route('admin.purchase-orders.create')",
    'route("admin.purchase-orders.create")',
];

$createRoutePos = false;

foreach ($createNeedles as $needle) {
    $pos = strpos($source, $needle);

    if ($pos !== false) {
        $createRoutePos = $pos;
        break;
    }
}

if ($createRoutePos === false) {
    fwrite(STDERR, "Create Purchase Order route anchor tidak ditemukan.\n");
    exit(4);
}

$exportMarkerPos = strpos(
    $source,
    'PO EXPENSE CSV EXPORT V1 BUTTON'
);

if ($exportMarkerPos === false) {
    fwrite(
        STDERR,
        "Export Expense CSV marker tidak ditemukan. Pastikan V1 export sudah terpasang.\n"
    );
    exit(5);
}

/*
 * Find Create button permission block.
 * We deliberately keep the existing @if / @endif intact.
 */
$createIfPos = strrpos(
    substr(
        $source,
        0,
        $createRoutePos
    ),
    '@if'
);

if ($createIfPos === false) {
    fwrite(STDERR, "Opening @if Create PO tidak ditemukan.\n");
    exit(6);
}

$createEndIfPos = strpos(
    $source,
    '@endif',
    $createRoutePos
);

if ($createEndIfPos === false) {
    fwrite(STDERR, "Closing @endif Create PO tidak ditemukan.\n");
    exit(7);
}

$createBlockEnd =
    $createEndIfPos
    + strlen('@endif');

/*
 * Find Export button permission block starting immediately after its marker.
 */
$exportIfPos = strpos(
    $source,
    '@if',
    $exportMarkerPos
);

if ($exportIfPos === false) {
    fwrite(STDERR, "Opening @if Export Expense CSV tidak ditemukan.\n");
    exit(8);
}

$exportEndIfPos = strpos(
    $source,
    '@endif',
    $exportIfPos
);

if ($exportEndIfPos === false) {
    fwrite(STDERR, "Closing @endif Export Expense CSV tidak ditemukan.\n");
    exit(9);
}

$exportBlockEnd =
    $exportEndIfPos
    + strlen('@endif');

/*
 * Ensure Create appears before Export.
 */
if ($createIfPos >= $exportBlockEnd) {
    fwrite(STDERR, "Urutan button block tidak dikenali.\n");
    exit(10);
}

/*
 * Preserve the exact customized source of both permission blocks.
 */
$createBlock = substr(
    $source,
    $createIfPos,
    $createBlockEnd - $createIfPos
);

$exportCommentStart = strrpos(
    substr(
        $source,
        0,
        $exportMarkerPos
    ),
    '<!--'
);

if (
    $exportCommentStart === false
    || $exportCommentStart < $createBlockEnd
) {
    $exportCommentStart = $exportIfPos;
}

$exportBlock = substr(
    $source,
    $exportCommentStart,
    $exportBlockEnd - $exportCommentStart
);

$replacement = <<<'BLADE'
                <!-- PO HEADER ACTIONS GROUP V1 -->
                <div class="flex items-center gap-2">
__CREATE_BLOCK__

__EXPORT_BLOCK__
                </div>
BLADE;

$replacement = str_replace(
    [
        '__CREATE_BLOCK__',
        '__EXPORT_BLOCK__',
    ],
    [
        $createBlock,
        $exportBlock,
    ],
    $replacement
);

/*
 * Replace everything from the Create @if through Export @endif.
 * That removes the whitespace that previously let the outer header layout
 * spread both buttons across the page.
 */
$replaceStart = $createIfPos;
$replaceLength = $exportBlockEnd - $createIfPos;

$backup =
    $path
    .'.before-po-header-actions-v1.bak';

if (! is_file($backup)) {
    copy($path, $backup);
}

$source = substr_replace(
    $source,
    $replacement,
    $replaceStart,
    $replaceLength
);

file_put_contents(
    $path,
    $source
);

echo "[PASS] Create PO + Export Expense CSV sekarang berada dalam satu action group.\n";
echo "[PASS] Gap antar tombol: 0.5rem (gap-2).\n";
echo "[PASS] Logic/permission tombol tidak diubah.\n";
