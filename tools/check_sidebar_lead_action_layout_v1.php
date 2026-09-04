<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$paths = [
    'menu' => $root.'/packages/Webkul/Admin/src/Config/menu.php',
    'sidebar' => $root.'/packages/Webkul/Admin/src/Resources/views/components/layouts/sidebar/desktop/index.blade.php',
    'lead' => $root.'/packages/Webkul/Admin/src/Resources/views/leads/view.blade.php',
    'quotation' => $root.'/packages/Webkul/Admin/src/Resources/views/lead-commercial-workflow/action-widget.blade.php',
];

$failures = 0;

function reportCheck(bool $ok, string $message): void
{
    global $failures;

    echo ($ok ? '[OK]   ' : '[FAIL] ').$message.PHP_EOL;

    if (! $ok) {
        $failures++;
    }
}

function menuBlock(string $content, string $key): ?string
{
    $pattern = "~['\"]key['\"]\\s*=>\\s*['\"]".preg_quote($key, '~')."['\"]~";

    if (! preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE) || count($matches[0]) !== 1) {
        return null;
    }

    $start = $matches[0][0][1];
    $tail = substr($content, $start + strlen($matches[0][0][0]));

    if (preg_match("~['\"]key['\"]\\s*=>~", $tail, $next, PREG_OFFSET_CAPTURE)) {
        return substr($content, $start, strlen($matches[0][0][0]) + $next[0][1]);
    }

    return substr($content, $start);
}

function menuSort(string $content, string $key): ?int
{
    $block = menuBlock($content, $key);

    if ($block && preg_match("~['\"]sort['\"]\\s*=>\\s*([0-9]+)~", $block, $match)) {
        return (int) $match[1];
    }

    return null;
}

echo "CHECK SIDEBAR + LEAD FLOATING ACTION LAYOUT V1\n";
echo "================================================\n\n";

foreach ($paths as $label => $path) {
    reportCheck(is_file($path), ucfirst($label).' file tersedia.');
}

if ($failures > 0) {
    echo "\n[FAIL] File source wajib belum lengkap.\n";
    exit(1);
}

$menu = file_get_contents($paths['menu']) ?: '';
$sidebar = file_get_contents($paths['sidebar']) ?: '';
$lead = file_get_contents($paths['lead']) ?: '';
$quotation = file_get_contents($paths['quotation']) ?: '';

$primary = [
    'dashboard' => 10,
    'leads' => 20,
    'quotes' => 30,
    'invoices' => 40,
    'work-orders' => 50,
    'delivery-orders' => 60,
    'inventory' => 70,
    'my-email' => 80,
    'mail' => 90,
    'activities' => 100,
    'contacts' => 110,
    'products' => 120,
    'settings' => 130,
    'configuration' => 140,
    'help' => 150,
];

foreach ($primary as $key => $expected) {
    reportCheck(menuSort($menu, $key) === $expected, "Menu {$key} berada pada urutan {$expected}.");
}

$extras = [
    'purchase-orders' => 160,
    'financial-report' => 170,
    'operations-dashboard' => 180,
    'internal-chat-audit' => 190,
];

foreach ($extras as $key => $expected) {
    reportCheck(menuSort($menu, $key) === $expected, "Menu tambahan {$key} tetap tersedia setelah workflow utama.");
}

$myMailBlock = menuBlock($menu, 'my-email') ?: '';
reportCheck(str_contains($myMailBlock, "'name'       => 'My Mail'"), 'Label My Mail sudah benar.');
reportCheck(str_contains($sidebar, 'SIDEBAR_LEAD_ACTION_LAYOUT_V1'), 'Marker perbaikan sidebar terpasang.');
reportCheck(str_contains($sidebar, 'break-words whitespace-normal leading-5'), 'Label sidebar dapat wrap.');
reportCheck(str_contains($sidebar, 'min-w-0'), 'Container label sidebar dibatasi dengan benar.');
reportCheck(str_contains($lead, 'LEAD_CALENDAR_ACTION_LAYOUT_V1'), 'Layout tombol Calendar terpasang.');
reportCheck(str_contains($lead, 'bottom:88px;'), 'Calendar berada di atas Chat pada desktop.');
reportCheck(str_contains($lead, 'bottom: 82px !important;'), 'Calendar aman pada layar kecil.');
reportCheck(str_contains($quotation, 'LEAD_QUOTATION_ACTION_LAYOUT_V1'), 'Layout tombol Quotation terpasang.');
reportCheck(str_contains($quotation, 'bottom: 150px !important;'), 'Quotation berada di atas Calendar pada desktop.');
reportCheck(str_contains($quotation, 'bottom: 142px !important;'), 'Quotation aman pada layar kecil.');

exec(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($paths['menu']).' 2>&1', $lintOutput, $lintCode);
reportCheck($lintCode === 0, 'menu.php lolos PHP lint.');

if ($failures > 0) {
    echo "\n[FAIL] Checker menemukan {$failures} masalah.\n";
    exit(1);
}

echo "\n[PASS] Sidebar dan floating action Lead sudah rapi.\n";
