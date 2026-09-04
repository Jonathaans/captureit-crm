<?php

declare(strict_types=1);

/**
 * SIDEBAR + LEAD FLOATING ACTION LAYOUT V1
 *
 * - Orders the primary sidebar according to the CRM workflow requested by the user.
 * - Keeps extra operational/admin menus accessible after the primary workflow.
 * - Allows long sidebar labels to wrap inside the 200px sidebar.
 * - Separates Quotation, Google Calendar, and Chat floating controls vertically.
 */

$root = dirname(__DIR__);

$paths = [
    'menu' => $root.'/packages/Webkul/Admin/src/Config/menu.php',
    'sidebar' => $root.'/packages/Webkul/Admin/src/Resources/views/components/layouts/sidebar/desktop/index.blade.php',
    'lead' => $root.'/packages/Webkul/Admin/src/Resources/views/leads/view.blade.php',
    'quotation' => $root.'/packages/Webkul/Admin/src/Resources/views/lead-commercial-workflow/action-widget.blade.php',
];

function abortPatch(string $message, int $code = 1): never
{
    fwrite(STDERR, "\nPATCH GAGAL: {$message}\n");
    exit($code);
}

function loadText(string $path): string
{
    $content = file_get_contents($path);

    if ($content === false) {
        throw new RuntimeException("Gagal membaca file: {$path}");
    }

    return $content;
}

function atomicWrite(string $path, string $content): void
{
    $tmp = $path.'.tmp-'.bin2hex(random_bytes(4));

    if (file_put_contents($tmp, $content) === false) {
        @unlink($tmp);
        throw new RuntimeException("Gagal menulis file temporary: {$tmp}");
    }

    if (! rename($tmp, $path)) {
        @unlink($tmp);
        throw new RuntimeException("Gagal mengganti file: {$path}");
    }
}

/**
 * @return array{0:int,1:int,2:string}
 */
function findMenuBlock(array $lines, string $key): array
{
    $keyLine = null;

    foreach ($lines as $index => $line) {
        if (preg_match(
            "~['\"]key['\"]\\s*=>\\s*['\"]".preg_quote($key, '~')."['\"]~",
            $line
        )) {
            if ($keyLine !== null) {
                throw new RuntimeException("Menu key '{$key}' ditemukan lebih dari sekali.");
            }

            $keyLine = $index;
        }
    }

    if ($keyLine === null) {
        throw new RuntimeException("Menu key '{$key}' tidak ditemukan.");
    }

    $start = $keyLine;

    while ($start >= 0 && ! preg_match('/^(\\s*)\\[\\s*$/', $lines[$start], $match)) {
        $start--;
    }

    if ($start < 0) {
        throw new RuntimeException("Pembuka block menu '{$key}' tidak ditemukan.");
    }

    $indent = $match[1] ?? '';
    $end = null;

    for ($index = $keyLine + 1, $count = count($lines); $index < $count; $index++) {
        if (preg_match('/^'.preg_quote($indent, '/').'\\],\\s*$/', $lines[$index])) {
            $end = $index;
            break;
        }
    }

    if ($end === null) {
        throw new RuntimeException("Penutup block menu '{$key}' tidak ditemukan.");
    }

    return [$start, $end, $indent];
}

function setMenuSort(array &$lines, string $key, int $sort): void
{
    [$start, $end] = findMenuBlock($lines, $key);
    $sortLine = null;

    for ($index = $start; $index <= $end; $index++) {
        if (preg_match("~['\"]sort['\"]\\s*=>~", $lines[$index])) {
            if ($sortLine !== null) {
                throw new RuntimeException("Block menu '{$key}' memiliki lebih dari satu sort.");
            }

            $sortLine = $index;
        }
    }

    if ($sortLine === null) {
        throw new RuntimeException("Sort menu '{$key}' tidak ditemukan.");
    }

    if (! preg_match('/^(\\s*)/', $lines[$sortLine], $match)) {
        throw new RuntimeException("Indentasi sort menu '{$key}' tidak terbaca.");
    }

    $lines[$sortLine] = ($match[1] ?? '')."'sort'       => {$sort},";
}

function setMenuName(array &$lines, string $key, string $name): void
{
    [$start, $end] = findMenuBlock($lines, $key);
    $nameLine = null;

    for ($index = $start; $index <= $end; $index++) {
        if (preg_match("~['\"]name['\"]\\s*=>~", $lines[$index])) {
            if ($nameLine !== null) {
                throw new RuntimeException("Block menu '{$key}' memiliki lebih dari satu name.");
            }

            $nameLine = $index;
        }
    }

    if ($nameLine === null || ! preg_match('/^(\\s*)/', $lines[$nameLine], $match)) {
        throw new RuntimeException("Name menu '{$key}' tidak ditemukan.");
    }

    $lines[$nameLine] = ($match[1] ?? '')."'name'       => '".str_replace("'", "\\'", $name)."',";
}

function replaceMenuField(string $content, string $key, string $field, string $literal): string
{
    $keyToken = "['\"]key['\"]\\s*=>\\s*['\"]".preg_quote($key, '~')."['\"]";
    $nextKeyToken = "['\"]key['\"]\\s*=>";
    $fieldToken = "['\"]".preg_quote($field, '~')."['\"]\\s*=>\\s*";
    $pattern = '~(('.$keyToken.')(?:(?!'.$nextKeyToken.').)*?'.$fieldToken.')[^,\\r\\n]+~s';

    $updated = preg_replace_callback(
        $pattern,
        static fn (array $match): string => $match[1].$literal,
        $content,
        1,
        $count
    );

    if ($updated === null || $count !== 1) {
        throw new RuntimeException("Field '{$field}' menu '{$key}' tidak ditemukan secara unik.");
    }

    return $updated;
}

function phpLint(string $path): array
{
    exec(
        escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($path).' 2>&1',
        $output,
        $code
    );

    return [$code, implode(PHP_EOL, $output)];
}

echo "SIDEBAR + LEAD FLOATING ACTION LAYOUT V1\n";
echo "========================================\n\n";

if (! is_file($root.'/artisan')) {
    abortPatch('Jalankan tool dari folder project Laravel: php tools/apply_sidebar_lead_action_layout_v1.php');
}

foreach ($paths as $label => $path) {
    if (! is_file($path)) {
        abortPatch("File {$label} tidak ditemukan: {$path}");
    }
}

$originals = [];

try {
    foreach ($paths as $label => $path) {
        $originals[$label] = loadText($path);
    }

    $stamp = date('Ymd-His');
    $backupRoot = $root.'/storage/app/crm-patch-backups/sidebar-lead-action-layout-v1/'.$stamp;

    foreach ($paths as $label => $path) {
        $relative = substr($path, strlen($root) + 1);
        $backupPath = $backupRoot.'/'.$relative;

        if (! is_dir(dirname($backupPath)) && ! mkdir(dirname($backupPath), 0775, true) && ! is_dir(dirname($backupPath))) {
            throw new RuntimeException("Gagal membuat folder backup: ".dirname($backupPath));
        }

        if (! copy($path, $backupPath)) {
            throw new RuntimeException("Gagal membuat backup: {$backupPath}");
        }
    }

    file_put_contents($backupRoot.'/README.txt', "Backup SIDEBAR + LEAD FLOATING ACTION LAYOUT V1\nCreated: {$stamp}\n");

    /*
     * Primary workflow requested by the user. Extra existing entries remain available
     * after this sequence so the patch never removes access to an existing module.
     */
    $sorts = [
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
        'purchase-orders' => 160,
        'financial-report' => 170,
        'operations-dashboard' => 180,
        'internal-chat-audit' => 190,
    ];

    $updatedMenu = $originals['menu'];

    foreach ($sorts as $key => $sort) {
        $updatedMenu = replaceMenuField($updatedMenu, $key, 'sort', (string) $sort);
    }

    $updatedMenu = replaceMenuField($updatedMenu, 'my-email', 'name', "'My Mail'");

    /* Long menu labels wrap inside the sidebar instead of overflowing its edge. */
    $updatedSidebar = $originals['sidebar'];

    if (! str_contains($updatedSidebar, 'SIDEBAR_LEAD_ACTION_LAYOUT_V1')) {
        $updatedSidebar = preg_replace_callback(
            '/class="([^"]*group-\\[\\.sidebar-collapsed\\]\\/container:hidden[^"]*)"/',
            static function (array $match): string {
                $classes = $match[1];

                if (! str_contains($classes, 'justify-between') || ! str_contains($classes, 'items-center')) {
                    return $match[0];
                }

                $classes = preg_replace('/(?:^|\\s)whitespace-nowrap(?=\\s|$)/', '', $classes) ?? $classes;
                $classes = preg_replace('/\\s+/', ' ', trim($classes)) ?? trim($classes);

                if (! str_contains(' '.$classes.' ', ' min-w-0 ')) {
                    $classes = 'min-w-0 '.$classes;
                }

                return 'class="'.$classes.'"';
            },
            $updatedSidebar,
            1,
            $sidebarClassCount
        );

        if ($sidebarClassCount !== 1) {
            throw new RuntimeException('Preflight sidebar label container gagal.');
        }

        $oldLabel = "<p>{{ core()->getConfigData('general.settings.menu.'.\$menuItem->getKey()) ?: \$menuItem->getName() }}</p>";
        $newLabel = "<p class=\"min-w-0 break-words whitespace-normal leading-5\">{{ core()->getConfigData('general.settings.menu.'.\$menuItem->getKey()) ?: \$menuItem->getName() }}</p>";

        if (! str_contains($updatedSidebar, $oldLabel)) {
            throw new RuntimeException('Preflight sidebar menu label gagal.');
        }

        $updatedSidebar = str_replace($oldLabel, $newLabel, $updatedSidebar, $labelCount);

        if ($labelCount !== 1) {
            throw new RuntimeException('Sidebar menu label tidak unik.');
        }

        $updatedSidebar = preg_replace(
            '/(<div\\s+ref="sidebar")/',
            "<!-- SIDEBAR_LEAD_ACTION_LAYOUT_V1 -->\n$1",
            $updatedSidebar,
            1,
            $markerCount
        );

        if ($markerCount !== 1) {
            throw new RuntimeException('Gagal memasang marker sidebar.');
        }
    }

    /* Calendar button sits above the native chat launcher. */
    $updatedLead = $originals['lead'];
    $calendarMarker = '<!-- CRM GOOGLE CALENDAR LEAD BUTTON V1 -->';
    $calendarOffset = strpos($updatedLead, $calendarMarker);

    if ($calendarOffset === false) {
        throw new RuntimeException('Preflight tombol Google Calendar gagal.');
    }

    $leadHead = substr($updatedLead, 0, $calendarOffset);
    $leadTail = substr($updatedLead, $calendarOffset);

    if (! str_contains($leadTail, 'LEAD_CALENDAR_ACTION_LAYOUT_V1')) {
        $leadTail = preg_replace(
            '/(<a\\s*\\n)/',
            "<!-- LEAD_CALENDAR_ACTION_LAYOUT_V1 -->\n    <style id=\"crm-lead-calendar-action-layout-v1\">\n        @media (max-width: 720px) {\n            #crm-lead-calendar-action-v1 {\n                right: 12px !important;\n                bottom: 82px !important;\n                max-width: calc(100vw - 24px);\n                white-space: normal;\n                text-align: center;\n            }\n        }\n    </style>\n    $1        id=\"crm-lead-calendar-action-v1\"\n",
            $leadTail,
            1,
            $calendarAnchorCount
        );

        if ($calendarAnchorCount !== 1) {
            throw new RuntimeException('Anchor tombol Google Calendar tidak ditemukan.');
        }
    }

    $leadTail = preg_replace_callback(
        '/(id="crm-lead-calendar-action-v1".*?style=".*?bottom:)\\s*\\d+px;/s',
        static fn (array $match): string => $match[1].'88px;',
        $leadTail,
        1,
        $calendarBottomCount
    );

    if ($calendarBottomCount !== 1) {
        throw new RuntimeException('Posisi bottom tombol Google Calendar tidak ditemukan.');
    }

    if (! str_contains($leadTail, 'max-width:calc(100vw - 48px);')) {
        $leadTail = preg_replace(
            '/(box-shadow:\\s*0 8px 24px rgba\\(0,0,0,\\.18\\);)/',
            "$1\n            max-width:calc(100vw - 48px);\n            white-space:normal;\n            text-align:center;",
            $leadTail,
            1,
            $calendarWidthCount
        );

        if ($calendarWidthCount !== 1) {
            throw new RuntimeException('Style tombol Google Calendar tidak cocok dengan source terbaru.');
        }
    }

    $updatedLead = $leadHead.$leadTail;

    /* Quotation action sits above Calendar, leaving the bottom row to Chat. */
    $updatedQuotation = $originals['quotation'];
    $quotationOverride = <<<'BLADE'

<!-- LEAD_QUOTATION_ACTION_LAYOUT_V1 -->
<style id="crm-lead-quotation-action-layout-v1">
    #crm-lead-commercial-action {
        top: auto !important;
        right: 24px !important;
        bottom: 150px !important;
        z-index: 39 !important;
        max-width: calc(100vw - 48px);
    }

    @media (max-width: 720px) {
        #crm-lead-commercial-action {
            right: 12px !important;
            bottom: 142px !important;
            max-width: calc(100vw - 24px);
            gap: 6px;
            padding: 8px 10px;
        }
    }
</style>
BLADE;

    if (! str_contains($updatedQuotation, 'LEAD_QUOTATION_ACTION_LAYOUT_V1')) {
        $updatedQuotation = rtrim($updatedQuotation).$quotationOverride."\n";
    }

    atomicWrite($paths['menu'], $updatedMenu);
    atomicWrite($paths['sidebar'], $updatedSidebar);
    atomicWrite($paths['lead'], $updatedLead);
    atomicWrite($paths['quotation'], $updatedQuotation);

    [$lintCode, $lintOutput] = phpLint($paths['menu']);

    if ($lintCode !== 0) {
        throw new RuntimeException("menu.php gagal PHP lint:\n{$lintOutput}");
    }

    echo "[OK] Urutan menu utama diperbarui sesuai tahapan CRM.\n";
    echo "[OK] My Email diubah menjadi My Mail.\n";
    echo "[OK] Label panjang sidebar sekarang wrap dan tidak overflow.\n";
    echo "[OK] Quotation, Calendar, dan Chat dipisahkan vertikal.\n";
    echo "[OK] Backup: {$backupRoot}\n\n";

    chdir($root);
    passthru(escapeshellarg(PHP_BINARY).' artisan optimize:clear', $clearCode);

    if ($clearCode !== 0) {
        echo "\nPERINGATAN: optimize:clear exit code {$clearCode}. Patch tetap sudah terpasang.\n";
    }

    echo "\nSELESAI. Jalankan:\n";
    echo "php tools/check_sidebar_lead_action_layout_v1.php\n";
} catch (Throwable $exception) {
    foreach ($paths as $label => $path) {
        if (isset($originals[$label])) {
            @file_put_contents($path, $originals[$label]);
        }
    }

    abortPatch($exception->getMessage());
}
