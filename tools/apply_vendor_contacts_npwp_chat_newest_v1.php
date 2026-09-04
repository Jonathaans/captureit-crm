<?php

declare(strict_types=1);

/**
 * VENDOR CONTACTS + NPWP IMAGE + CHAT NEWEST V1
 *
 * - Moves Vendor Master navigation to Contacts > Vendor Master.
 * - Removes the Vendor Master shortcut from Operations Dashboard.
 * - Adds private NPWP image upload/view support.
 * - Makes Internal Chat a bounded scroll panel and opens/follows newest.
 *
 * Run from the Laravel project root:
 * php tools/apply_vendor_contacts_npwp_chat_newest_v1.php
 */

$root = dirname(__DIR__);
$stamp = date('Ymd-His');
$suffix = '.bak-vendor-contacts-npwp-chat-newest-v1-'.$stamp;

$paths = [
    'menu' => $root.'/packages/Webkul/Admin/src/Config/menu.php',
    'acl' => $root.'/packages/Webkul/Admin/src/Config/acl.php',
    'operations_service' => $root.'/packages/Webkul/Admin/src/Services/OperationsDashboardService.php',
    'vendor_controller' => $root.'/packages/Webkul/Admin/src/Http/Controllers/Vendor/VendorController.php',
    'operations_provider' => $root.'/packages/Webkul/Admin/src/Providers/CrmOperationsServiceProvider.php',
    'vendor_form' => $root.'/packages/Webkul/Admin/src/Resources/views/vendors/form.blade.php',
    'vendor_index' => $root.'/packages/Webkul/Admin/src/Resources/views/vendors/index.blade.php',
    'chat' => $root.'/packages/Webkul/Admin/src/Resources/views/internal-communication/chat.blade.php',
    'chat_controller' => $root.'/packages/Webkul/Admin/src/Http/Controllers/InternalCommunication/InternalChatController.php',
    'npwp_service' => $root.'/packages/Webkul/Admin/src/Services/VendorNpwpImageService.php',
    'migration' => $root.'/database/migrations/2026_09_03_230000_add_npwp_image_to_vendors_and_contacts_acl.php',
];

function failPatch(string $message, int $code = 1): never
{
    fwrite(STDERR, $message.PHP_EOL);
    exit($code);
}

function atomicWritePatch(string $path, string $contents): void
{
    $directory = dirname($path);

    if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
        throw new RuntimeException("Gagal membuat directory: {$directory}");
    }

    $temporary = $path.'.tmp-'.bin2hex(random_bytes(4));

    if (file_put_contents($temporary, $contents) === false) {
        @unlink($temporary);
        throw new RuntimeException("Gagal menulis temporary file: {$temporary}");
    }

    if (! rename($temporary, $path)) {
        @unlink($temporary);
        throw new RuntimeException("Gagal mengganti file: {$path}");
    }
}

function lintPhpPatch(string $path): array
{
    exec(
        escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($path).' 2>&1',
        $output,
        $code
    );

    return [$code, implode(PHP_EOL, $output)];
}

function exactKeyCountPatch(string $source, string $key): int
{
    return preg_match_all(
        "~['\"]key['\"]\\s*=>\\s*['\"]".preg_quote($key, '~')."['\"]~",
        $source
    );
}

/**
 * Locate one top-level config array element by its key.
 * Returns byte offsets [start, length, end] or null.
 */
function configBlockPatch(string $source, string $key): ?array
{
    $lines = preg_split('/(?<=\\n)/', $source);

    if (! is_array($lines)) {
        return null;
    }

    $keyLine = null;

    foreach ($lines as $index => $line) {
        if (preg_match(
            "~['\"]key['\"]\\s*=>\\s*['\"]".preg_quote($key, '~')."['\"]~",
            $line
        )) {
            if ($keyLine !== null) {
                throw new RuntimeException("Config key {$key} ditemukan lebih dari sekali.");
            }

            $keyLine = $index;
        }
    }

    if ($keyLine === null) {
        return null;
    }

    $startLine = $keyLine;

    while ($startLine >= 0 && ! preg_match('/^(\\s*)\\[\\s*(?:\\r?\\n)?$/', $lines[$startLine], $match)) {
        $startLine--;
    }

    if ($startLine < 0) {
        throw new RuntimeException("Pembuka block {$key} tidak ditemukan.");
    }

    $indent = $match[1] ?? '';
    $endLine = null;

    for ($index = $keyLine + 1; $index < count($lines); $index++) {
        if (preg_match('/^'.preg_quote($indent, '/').'\\],\\s*(?:\\r?\\n)?$/', $lines[$index])) {
            $endLine = $index;
            break;
        }
    }

    if ($endLine === null) {
        throw new RuntimeException("Penutup block {$key} tidak ditemukan.");
    }

    $start = 0;

    for ($index = 0; $index < $startLine; $index++) {
        $start += strlen($lines[$index]);
    }

    $length = 0;

    for ($index = $startLine; $index <= $endLine; $index++) {
        $length += strlen($lines[$index]);
    }

    return [$start, $length, $start + $length];
}

function ensureContactsVendorMenuPatch(string $source): string
{
    if (exactKeyCountPatch($source, 'contacts.vendors') === 0) {
        $anchor = configBlockPatch($source, 'contacts.organizations');

        if (! $anchor) {
            throw new RuntimeException('Menu Contacts > Organizations tidak ditemukan.');
        }

        $entry = <<<'PHP'

    [
        'key'        => 'contacts.vendors',
        'name'       => 'Vendor Master',
        'route'      => 'admin.vendors.index',
        'sort'       => 3,
        'icon-class' => '',
    ],
PHP;

        $source = substr_replace($source, $entry, $anchor[2], 0);
    }

    $legacy = configBlockPatch($source, 'vendors');

    if ($legacy) {
        $source = substr_replace($source, '', $legacy[0], $legacy[1]);
    }

    return $source;
}

function ensureContactsVendorAclPatch(string $source): string
{
    if (exactKeyCountPatch($source, 'contacts.vendors') > 0) {
        return $source;
    }

    $anchor = configBlockPatch($source, 'contacts.organizations.delete')
        ?: configBlockPatch($source, 'contacts.organizations');

    if (! $anchor) {
        throw new RuntimeException('ACL Contacts > Organizations tidak ditemukan.');
    }

    $entry = <<<'PHP'

    [
        'key'   => 'contacts.vendors',
        'name'  => 'Vendor Master',
        'route' => [
            'admin.vendors.index',
            'admin.vendors.create',
            'admin.vendors.store',
            'admin.vendors.edit',
            'admin.vendors.update',
            'admin.vendors.npwp-image',
        ],
        'sort'  => 3,
    ],
PHP;

    return substr_replace($source, $entry, $anchor[2], 0);
}

function removeVendorQuickLinkPatch(string $source): string
{
    $pattern = <<<'REGEX'
~\s*\[\s*['"]label['"]\s*=>\s*['"]Vendor Master['"]\s*,\s*['"]url['"]\s*=>\s*\$this->routeUrl\s*\(\s*['"]admin\.vendors\.index['"]\s*\)\s*,\s*\]\s*,~s
REGEX;

    $updated = preg_replace($pattern, '', $source, -1, $count);

    if (! is_string($updated)) {
        throw new RuntimeException('Regex Operations Dashboard gagal.');
    }

    if ($count > 1) {
        throw new RuntimeException("Vendor Master quick link ditemukan {$count} kali.");
    }

    return $updated;
}

function ensureProviderRoutePatch(string $source): string
{
    if (str_contains($source, "'admin.vendors.npwp-image'")) {
        return $source;
    }

    $anchor = "function () {";
    $position = strpos($source, $anchor);

    if ($position === false) {
        throw new RuntimeException('Route group CrmOperationsServiceProvider tidak ditemukan.');
    }

    $position += strlen($anchor);
    $route = <<<'PHP'

                    /* VENDOR NPWP PRIVATE IMAGE V1 */
                    Route::get(
                        'vendors/{id}/npwp-image',
                        [
                            VendorController::class,
                            'npwpImage',
                        ]
                    )->name(
                        'admin.vendors.npwp-image'
                    );

PHP;

    return substr_replace($source, $route, $position, 0);
}

function ensureVendorControllerPatch(string $source): string
{
    if (! str_contains($source, 'use Webkul\\Admin\\Services\\VendorNpwpImageService;')) {
        $anchor = 'use Webkul\\Admin\\Services\\VendorSyncService;';

        if (! str_contains($source, $anchor)) {
            throw new RuntimeException('Import VendorSyncService tidak ditemukan.');
        }

        $source = str_replace(
            $anchor,
            $anchor.PHP_EOL.'use Webkul\\Admin\\Services\\VendorNpwpImageService;',
            $source,
            $count
        );

        if ($count !== 1) {
            throw new RuntimeException('Import VendorNpwpImageService gagal.');
        }
    }

    if (! str_contains($source, 'VENDOR NPWP NON COLUMN FIELDS V1')) {
        $pattern = '~(\\$data\\s*=\\s*\\$this->validated\\(\\$request\\);)~';
        $replacement = <<<'PHP'
$1

        /* VENDOR NPWP NON COLUMN FIELDS V1 */
        unset(
            $data['npwp_image'],
            $data['remove_npwp_image']
        );
PHP;

        $source = preg_replace($pattern, $replacement, $source, -1, $count);

        if (! is_string($source) || $count !== 2) {
            throw new RuntimeException(
                "Pembersihan field upload Vendor harus terpasang di store dan update; ditemukan {$count}."
            );
        }
    }

    if (! str_contains($source, 'VENDOR NPWP STORE V1')) {
        if (! str_contains($source, 'Vendor::query()->create($data);')) {
            throw new RuntimeException('Vendor create statement tidak ditemukan.');
        }

        $replacement = <<<'PHP'
$vendor = Vendor::query()->create($data);

        /* VENDOR NPWP STORE V1 */
        if ($request->hasFile('npwp_image')) {
            VendorNpwpImageService::store(
                $vendor,
                $request->file('npwp_image')
            );
        }
PHP;

        $source = str_replace(
            'Vendor::query()->create($data);',
            $replacement,
            $source,
            $count
        );

        if ($count !== 1) {
            throw new RuntimeException('Vendor store patch count tidak valid.');
        }
    }

    if (! str_contains($source, 'VENDOR NPWP UPDATE V1')) {
        $anchor = '$vendor->update($data);';

        if (! str_contains($source, $anchor)) {
            throw new RuntimeException('Vendor update statement tidak ditemukan.');
        }

        $replacement = <<<'PHP'
$vendor->update($data);

        /* VENDOR NPWP UPDATE V1 */
        if ($request->boolean('remove_npwp_image')) {
            VendorNpwpImageService::delete($vendor);
        }

        if ($request->hasFile('npwp_image')) {
            VendorNpwpImageService::store(
                $vendor,
                $request->file('npwp_image')
            );
        }
PHP;

        $source = str_replace($anchor, $replacement, $source, $count);

        if ($count !== 1) {
            throw new RuntimeException('Vendor update patch count tidak valid.');
        }
    }

    if (! str_contains($source, "'npwp_image' => [")) {
        $anchor = "            'pic_name' => [";

        if (! str_contains($source, $anchor)) {
            throw new RuntimeException('Validation anchor pic_name tidak ditemukan.');
        }

        $rules = <<<'PHP'
            'npwp_image' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
            ],
            'remove_npwp_image' => [
                'nullable',
                'boolean',
            ],
PHP;

        $source = str_replace($anchor, $rules.$anchor, $source, $count);

        if ($count !== 1) {
            throw new RuntimeException('Validation NPWP image gagal.');
        }
    }

    if (! str_contains($source, 'public function npwpImage(')) {
        $anchor = '    private function validated(';
        $position = strpos($source, $anchor);

        if ($position === false) {
            throw new RuntimeException('Method validated() tidak ditemukan.');
        }

        $method = <<<'PHP'
    /** VENDOR NPWP PRIVATE VIEW V1 */
    public function npwpImage(int $id)
    {
        $this->authorizeAccess();

        $vendor = Vendor::query()->findOrFail($id);

        return VendorNpwpImageService::response($vendor);
    }

PHP;

        $source = substr_replace($source, $method, $position, 0);
    }

    $legacyCondition = <<<'PHP'
            && ! bouncer()->hasPermission(
                'vendors'
            )
PHP;

    if (str_contains($source, $legacyCondition)
        && ! str_contains($source, "'contacts.vendors'")) {
        $dualCondition = <<<'PHP'
            && ! bouncer()->hasPermission(
                'vendors'
            )
            && ! bouncer()->hasPermission(
                'contacts.vendors'
            )
PHP;

        $source = str_replace($legacyCondition, $dualCondition, $source, $count);

        if ($count !== 1) {
            throw new RuntimeException('Vendor dual ACL patch gagal.');
        }
    }

    return $source;
}

function ensureMultipartPatch(string $source): string
{
    if (str_contains($source, 'name="npwp_image"')) {
        return $source;
    }

    if (! str_contains($source, 'enctype="multipart/form-data"')) {
        $source = preg_replace(
            '~(<form\\b[\\s\\S]*?method="POST"[\\s\\S]*?)(>)~',
            '$1'.PHP_EOL.'            enctype="multipart/form-data"$2',
            $source,
            1,
            $count
        );

        if (! is_string($source) || $count !== 1) {
            throw new RuntimeException('Form Vendor multipart patch gagal.');
        }
    }

    $pattern = '~(\\s*<div style="grid-column:1/-1;">\\s*<label[^>]*>\\s*Address\\s*</label>)~s';
    $block = <<<'BLADE'

                {{-- VENDOR NPWP IMAGE CONTACTS V1 --}}
                <div style="grid-column:1/-1;">
                    <div class="rounded-lg border p-4">
                        <label class="mb-1.5 block text-sm font-semibold">Image NPWP</label>

                        <p class="mb-3 text-xs text-gray-500">
                            JPG, JPEG, PNG, atau WebP. Maksimal 5 MB. File disimpan privat.
                        </p>

                        @if ($vendor->exists && ! empty($vendor->npwp_image_path))
                            <div class="mb-3 flex flex-wrap items-center gap-3">
                                <a
                                    href="{{ route('admin.vendors.npwp-image', $vendor->id) }}"
                                    target="_blank"
                                    rel="noopener"
                                    class="secondary-button"
                                >
                                    View Image NPWP
                                </a>

                                <label class="flex items-center gap-2 text-xs text-gray-600">
                                    <input type="checkbox" name="remove_npwp_image" value="1">
                                    Hapus image NPWP saat ini
                                </label>
                            </div>
                        @endif

                        <input
                            type="file"
                            name="npwp_image"
                            accept="image/jpeg,image/png,image/webp"
                            class="w-full rounded-md border px-3 py-2"
                        >

                        @error('npwp_image')
                            <p class="mt-2 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
BLADE;

    $source = preg_replace($pattern, $block.'$1', $source, 1, $count);

    if (! is_string($source) || $count !== 1) {
        throw new RuntimeException('Anchor Address pada Vendor form tidak ditemukan.');
    }

    return $source;
}

function ensureVendorIndexPatch(string $source): string
{
    if (str_contains($source, 'VENDOR NPWP INDEX ACTION V1')) {
        return $source;
    }

    $anchor = '<a href="{{ route(\'admin.vendors.edit\', $vendor->id) }}" class="secondary-button">Edit</a>';

    if (! str_contains($source, $anchor)) {
        throw new RuntimeException('Tombol Edit Vendor pada index tidak ditemukan.');
    }

    $replacement = <<<'BLADE'
                                {{-- VENDOR NPWP INDEX ACTION V1 --}}
                                @if (! empty($vendor->npwp_image_path))
                                    <a
                                        href="{{ route('admin.vendors.npwp-image', $vendor->id) }}"
                                        target="_blank"
                                        rel="noopener"
                                        class="secondary-button"
                                    >View NPWP</a>
                                @endif

                                <a href="{{ route('admin.vendors.edit', $vendor->id) }}" class="secondary-button">Edit</a>
BLADE;

    return str_replace($anchor, $replacement, $source, $count);
}

function removeStandaloneScrollPatch(string $source, string $marker): string
{
    while (($markerPosition = strpos($source, $marker)) !== false) {
        $before = substr($source, 0, $markerPosition);
        $openBefore = strrpos($before, '<script>');
        $closeBefore = strrpos($before, '</script>');

        if ($openBefore !== false && ($closeBefore === false || $openBefore > $closeBefore)) {
            $scriptOpen = $openBefore;
        } else {
            $scriptOpen = strpos($source, '<script>', $markerPosition);
        }

        if ($scriptOpen === false || abs($scriptOpen - $markerPosition) > 500) {
            throw new RuntimeException("Boundary script marker {$marker} tidak ditemukan.");
        }

        $scriptClose = strpos($source, '</script>', max($scriptOpen, $markerPosition));

        if ($scriptClose === false) {
            throw new RuntimeException("Penutup script marker {$marker} tidak ditemukan.");
        }

        $start = $scriptOpen;
        $commentStart = strrpos(substr($source, 0, $markerPosition), '{{--');

        if ($commentStart !== false && $markerPosition - $commentStart < 250) {
            $start = $commentStart;
        }

        $ifStart = strrpos(substr($source, 0, $start), '@if ($conversation)');

        if ($ifStart !== false && $start - $ifStart < 180) {
            $start = $ifStart;
        }

        $end = $scriptClose + strlen('</script>');
        $endif = strpos($source, '@endif', $end);

        if ($endif !== false && $endif - $end < 180) {
            $end = $endif + strlen('@endif');
        }

        $source = substr($source, 0, $start).substr($source, $end);
    }

    return $source;
}

function replaceTagByIdPatch(
    string $source,
    string $id,
    string $class,
    string $style,
    string $marker
): string {
    $pattern = '~<([a-zA-Z0-9]+)\\b(?=[^>]*\\bid="'.preg_quote($id, '~').'")[^>]*>~s';

    $source = preg_replace_callback(
        $pattern,
        function (array $match) use ($class, $style, $marker): string {
            $tag = $match[0];

            if (preg_match('/\\bclass="[^"]*"/', $tag)) {
                $tag = preg_replace('/\\bclass="[^"]*"/', 'class="'.$class.'"', $tag, 1);
            } else {
                $tag = substr($tag, 0, -1).' class="'.$class.'">';
            }

            if (preg_match('/\\bstyle="([^"]*)"/', $tag, $styleMatch)) {
                $newStyle = rtrim($styleMatch[1], ';').';'.$style;
                $tag = preg_replace('/\\bstyle="[^"]*"/', 'style="'.$newStyle.'"', $tag, 1);
            } else {
                $tag = substr($tag, 0, -1).' style="'.$style.'">';
            }

            if (! str_contains($tag, $marker)) {
                $tag = substr($tag, 0, -1).' '.$marker.'="1">';
            }

            return $tag;
        },
        $source,
        1,
        $count
    );

    if (! is_string($source) || $count !== 1) {
        throw new RuntimeException("Tag #{$id} tidak ditemukan tepat satu kali.");
    }

    return $source;
}

function ensureChatNewestPatch(string $source): string
{
    foreach ([
        'INTERNAL CHAT BOTTOM STICKY V1.6',
        'INTERNAL CHAT LATEST50 BOTTOM V1.5',
        'INTERNAL CHAT SCROLL GUARD LATEST50 V1.7',
        'INTERNAL CHAT WHATSAPP FINAL SCROLL V1.4.1',
        'INTERNAL CHAT WHATSAPP FINAL SCROLL V1.4',
        'INTERNAL CHAT WHATSAPP INITIAL BOTTOM V1.3',
        'INTERNAL CHAT FORCE OPEN LATEST V1.2 START',
        'INTERNAL CHAT OPEN LATEST V1.1 SCROLL',
        'INTERNAL CHAT OPEN LATEST V1 SCROLL',
    ] as $marker) {
        if (str_contains($source, $marker)) {
            $source = removeStandaloneScrollPatch($source, $marker);
        }
    }

    $shellPattern = '~<div\\s+class="flex(?:\\s+min-h-screen|\\s+min-h-0)?\\s+overflow-hidden\\s+rounded-xl\\s+border\\s+bg-white\\s+shadow-sm"(?:\\s+style="[^"]*")?(?:\\s+data-chat-shell-newest-v1="1")?>~';
    $shell = '<div class="flex min-h-0 overflow-hidden rounded-xl border bg-white shadow-sm" style="height:clamp(520px,calc(100dvh - 260px),760px);min-height:0;" data-chat-shell-newest-v1="1">';
    $source = preg_replace($shellPattern, $shell, $source, 1, $count);

    if (! is_string($source) || $count !== 1) {
        throw new RuntimeException('Shell Internal Chat tidak ditemukan tepat satu kali.');
    }

    $source = str_replace(
        '<aside class="{{ $conversation ? \'hidden lg:flex\' : \'flex\' }} w-full flex-col border-r bg-white lg:w-96 lg:flex-none">',
        '<aside class="{{ $conversation ? \'hidden lg:flex\' : \'flex\' }} min-h-0 w-full flex-col overflow-hidden border-r bg-white lg:w-96 lg:flex-none">',
        $source
    );

    $source = str_replace(
        '<section class="{{ $conversation ? \'flex\' : \'hidden lg:flex\' }} w-full min-w-0 flex-1 flex-col bg-gray-50">',
        '<section class="{{ $conversation ? \'flex\' : \'hidden lg:flex\' }} min-h-0 w-full min-w-0 flex-1 flex-col overflow-hidden bg-gray-50">',
        $source
    );

    $source = replaceTagByIdPatch(
        $source,
        'crm-chat-messages',
        'flex min-h-0 flex-1 flex-col overflow-y-auto bg-gray-100 p-5',
        'min-height:0;overscroll-behavior:contain;scroll-behavior:auto;',
        'data-chat-newest-scroll-v1'
    );

    $source = replaceTagByIdPatch(
        $source,
        'crm-chat-message-stack',
        'mt-auto flex w-full shrink-0 flex-col',
        'flex-shrink:0;',
        'data-chat-newest-stack-v1'
    );

    if (! str_contains($source, 'INTERNAL CHAT NEWEST PANEL V1')) {
        $closing = strrpos($source, '</x-admin::layouts>');

        if ($closing === false) {
            throw new RuntimeException('Penutup layout Internal Chat tidak ditemukan.');
        }

        $script = <<<'BLADE'

    {{-- INTERNAL CHAT NEWEST PANEL V1 --}}
    @if ($conversation)
        <script>
            (() => {
                const bootNewestPanelV1 = () => {
                    const root = document.getElementById('crm-chat-messages');
                    const stack = document.getElementById('crm-chat-message-stack');
                    const form = document.getElementById('crm-chat-send-form');

                    if (! root || ! stack || root.dataset.newestPanelV1 === '1') {
                        return;
                    }

                    root.dataset.newestPanelV1 = '1';

                    let followNewest = true;
                    let programmatic = false;

                    const distanceFromNewest = () => Math.max(
                        0,
                        root.scrollHeight - root.clientHeight - root.scrollTop
                    );

                    const goNewest = () => {
                        if (! followNewest) {
                            return;
                        }

                        programmatic = true;
                        root.scrollTop = Math.max(0, root.scrollHeight - root.clientHeight);

                        window.requestAnimationFrame(() => {
                            root.scrollTop = Math.max(0, root.scrollHeight - root.clientHeight);
                            programmatic = false;
                        });
                    };

                    const scheduleNewest = () => {
                        goNewest();
                        window.requestAnimationFrame(() => window.requestAnimationFrame(goNewest));
                        [0, 80, 220, 500].forEach((delay) => window.setTimeout(goNewest, delay));
                    };

                    root.addEventListener('wheel', (event) => {
                        if (event.deltaY < 0) {
                            followNewest = false;
                        }
                    }, { passive: true });

                    let touchY = null;

                    root.addEventListener('touchstart', (event) => {
                        touchY = event.touches?.[0]?.clientY ?? null;
                    }, { passive: true });

                    root.addEventListener('touchmove', (event) => {
                        const currentY = event.touches?.[0]?.clientY ?? null;

                        if (touchY !== null && currentY !== null && currentY > touchY + 4) {
                            followNewest = false;
                        }
                    }, { passive: true });

                    root.addEventListener('scroll', () => {
                        if (! programmatic && distanceFromNewest() <= 32) {
                            followNewest = true;
                        }
                    }, { passive: true });

                    if (form) {
                        form.addEventListener('submit', () => {
                            followNewest = true;
                            scheduleNewest();
                        }, true);
                    }

                    new MutationObserver(() => {
                        if (followNewest) {
                            scheduleNewest();
                        }
                    }).observe(stack, { childList: true, subtree: true });

                    if ('ResizeObserver' in window) {
                        new ResizeObserver(() => {
                            if (followNewest) {
                                goNewest();
                            }
                        }).observe(stack);
                    }

                    window.crmChatGoNewest = () => {
                        followNewest = true;
                        scheduleNewest();
                    };

                    scheduleNewest();
                    window.addEventListener('load', scheduleNewest, { once: true });
                    window.addEventListener('pageshow', scheduleNewest, { once: true });
                    document.fonts?.ready?.then(scheduleNewest);
                };

                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', bootNewestPanelV1, { once: true });
                } else {
                    bootNewestPanelV1();
                }
            })();
        </script>
    @endif

BLADE;

        $source = substr_replace($source, $script, $closing, 0);
    }

    return $source;
}

$npwpService = <<<'PHP'
<?php

namespace Webkul\Admin\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Webkul\Admin\Models\Vendor;

/** VENDOR NPWP PRIVATE SERVICE V1 */
class VendorNpwpImageService
{
    public static function store(Vendor $vendor, UploadedFile $file): string
    {
        $extension = strtolower((string) ($file->extension() ?: 'jpg'));
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];

        if (! in_array($extension, $allowed, true)) {
            throw new \RuntimeException('Format image NPWP tidak didukung.');
        }

        $directory = 'vendor-npwp/'.(int) $vendor->id;
        $filename = 'npwp-'.(int) $vendor->id.'-'.Str::uuid().'.'.$extension;
        $oldPath = trim((string) ($vendor->npwp_image_path ?? ''));
        $path = $file->storeAs($directory, $filename, 'local');

        if (! $path) {
            throw new \RuntimeException('Gagal menyimpan image NPWP.');
        }

        $vendor->forceFill(['npwp_image_path' => $path])->save();
        self::deletePath($oldPath);

        return $path;
    }

    public static function delete(Vendor $vendor): void
    {
        $path = trim((string) ($vendor->npwp_image_path ?? ''));
        $vendor->forceFill(['npwp_image_path' => null])->save();
        self::deletePath($path);
    }

    public static function response(Vendor $vendor)
    {
        $path = trim((string) ($vendor->npwp_image_path ?? ''));

        if ($path === '') {
            abort(404, 'Image NPWP belum tersedia.');
        }

        $name = 'npwp-vendor-'.(int) $vendor->id.'.'.pathinfo($path, PATHINFO_EXTENSION);

        if (Storage::disk('local')->exists($path)) {
            return Storage::disk('local')->response($path, $name, [], 'inline');
        }

        // Backward-compatible viewer for files created by older public-storage patches.
        if (Storage::disk('public')->exists($path)) {
            return Storage::disk('public')->response($path, $name, [], 'inline');
        }

        abort(404, 'File image NPWP tidak ditemukan.');
    }

    private static function deletePath(string $path): void
    {
        if ($path === '') {
            return;
        }

        if (str_starts_with($path, 'vendor-npwp/')) {
            Storage::disk('local')->delete($path);
        }

        if (str_starts_with($path, 'vendors/')) {
            Storage::disk('public')->delete($path);
        }
    }
}
PHP;

$migration = <<<'PHP'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('vendors')) {
            throw new RuntimeException('Tabel vendors tidak ditemukan.');
        }

        if (! Schema::hasColumn('vendors', 'npwp_image_path')) {
            Schema::table('vendors', function (Blueprint $table) {
                $table->string('npwp_image_path', 500)->nullable();
            });
        }

        if (! Schema::hasTable('roles') || ! Schema::hasColumn('roles', 'permissions')) {
            return;
        }

        DB::table('roles')->orderBy('id')->chunkById(100, function ($roles) {
            foreach ($roles as $role) {
                $permissions = is_string($role->permissions ?? null)
                    ? json_decode((string) $role->permissions, true)
                    : ($role->permissions ?? null);

                if (! is_array($permissions) || ! in_array('vendors', $permissions, true)) {
                    continue;
                }

                if (! in_array('contacts.vendors', $permissions, true)) {
                    $permissions[] = 'contacts.vendors';

                    DB::table('roles')->where('id', $role->id)->update([
                        'permissions' => json_encode(array_values($permissions)),
                    ]);
                }
            }
        });
    }

    public function down(): void
    {
        // Intentionally preserve the NPWP file reference and role aliases.
    }
};
PHP;

echo "VENDOR CONTACTS + NPWP IMAGE + CHAT NEWEST V1\n";
echo "================================================\n\n";

foreach ([
    'menu',
    'acl',
    'operations_service',
    'vendor_controller',
    'operations_provider',
    'vendor_form',
    'vendor_index',
    'chat',
    'chat_controller',
] as $required) {
    if (! is_file($paths[$required])) {
        failPatch("File wajib tidak ditemukan: {$paths[$required]}");
    }
}

$chatController = (string) file_get_contents($paths['chat_controller']);

if (! preg_match(
    '~->orderByDesc\\s*\\(\\s*[\'\"]id[\'\"]\\s*\\)[\\s\\S]*?->limit\\s*\\(\\s*50\\s*\\)[\\s\\S]*?->get\\s*\\(\\s*\\)[\\s\\S]*?->sortBy\\s*\\(\\s*[\'\"]id[\'\"]\\s*\\)~',
    $chatController
)) {
    failPatch('Preflight chat gagal: backend latest-50 ascending tidak ditemukan.');
}

$originals = [];
$updated = [];
$manifest = [
    'version' => 'vendor-contacts-npwp-chat-newest-v1',
    'created_at' => date(DATE_ATOM),
    'files' => [],
];

try {
    foreach ([
        'menu',
        'acl',
        'operations_service',
        'vendor_controller',
        'operations_provider',
        'vendor_form',
        'vendor_index',
        'chat',
    ] as $key) {
        $contents = file_get_contents($paths[$key]);

        if ($contents === false) {
            throw new RuntimeException("Gagal membaca {$paths[$key]}");
        }

        $originals[$key] = $contents;
    }

    $updated['menu'] = ensureContactsVendorMenuPatch($originals['menu']);
    $updated['acl'] = ensureContactsVendorAclPatch($originals['acl']);
    $updated['operations_service'] = removeVendorQuickLinkPatch($originals['operations_service']);
    $updated['vendor_controller'] = ensureVendorControllerPatch($originals['vendor_controller']);
    $updated['operations_provider'] = ensureProviderRoutePatch($originals['operations_provider']);
    $updated['vendor_form'] = ensureMultipartPatch($originals['vendor_form']);
    $updated['vendor_index'] = ensureVendorIndexPatch($originals['vendor_index']);
    $updated['chat'] = ensureChatNewestPatch($originals['chat']);

    foreach ($updated as $key => $contents) {
        $backup = $paths[$key].$suffix;

        if (! copy($paths[$key], $backup)) {
            throw new RuntimeException("Gagal membuat backup {$backup}");
        }

        $manifest['files'][] = [
            'path' => $paths[$key],
            'backup' => $backup,
            'created' => false,
        ];

        atomicWritePatch($paths[$key], $contents);
        echo "[OK] {$key}\n";
    }

    foreach ([
        'npwp_service' => $npwpService,
        'migration' => $migration,
    ] as $key => $contents) {
        $created = ! is_file($paths[$key]);
        $backup = null;

        if (! $created) {
            $existing = (string) file_get_contents($paths[$key]);

            if (! str_contains($existing, $key === 'npwp_service'
                ? 'VENDOR NPWP PRIVATE SERVICE V1'
                : 'contacts.vendors')) {
                throw new RuntimeException("File sudah ada dan bukan milik patch ini: {$paths[$key]}");
            }

            $backup = $paths[$key].$suffix;

            if (! copy($paths[$key], $backup)) {
                throw new RuntimeException("Gagal membuat backup {$backup}");
            }
        }

        $manifest['files'][] = [
            'path' => $paths[$key],
            'backup' => $backup,
            'created' => $created,
        ];

        atomicWritePatch($paths[$key], $contents);
        echo "[OK] {$key}\n";
    }

    foreach ([
        $paths['menu'],
        $paths['acl'],
        $paths['operations_service'],
        $paths['vendor_controller'],
        $paths['operations_provider'],
        $paths['npwp_service'],
        $paths['migration'],
    ] as $phpPath) {
        [$code, $output] = lintPhpPatch($phpPath);

        if ($code !== 0) {
            throw new RuntimeException("PHP lint gagal: {$phpPath}\n{$output}");
        }
    }

    $manifestPath = $root.'/storage/app/vendor_contacts_npwp_chat_newest_v1_manifest.json';
    atomicWritePatch(
        $manifestPath,
        json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL
    );

    chdir($root);

    echo "\nMenjalankan migration...\n";
    passthru(
        escapeshellarg(PHP_BINARY)
            .' '.escapeshellarg($root.'/artisan')
            .' migrate --path='
            .escapeshellarg('database/migrations/'.basename($paths['migration']))
            .' --force',
        $migrateCode
    );

    if ($migrateCode !== 0) {
        throw new RuntimeException("Migration gagal dengan exit code {$migrateCode}.");
    }

    echo "\nMembersihkan cache...\n";
    passthru(
        escapeshellarg(PHP_BINARY).' '.escapeshellarg($root.'/artisan').' optimize:clear',
        $clearCode
    );

    if ($clearCode !== 0) {
        echo "[WARN] optimize:clear exit code {$clearCode}; jalankan manual bila perlu.\n";
    }

    echo "\nPATCH BERHASIL.\n";
    echo "Jalankan checker:\n";
    echo "php tools/check_vendor_contacts_npwp_chat_newest_v1.php\n";
} catch (Throwable $e) {
    foreach (array_reverse($manifest['files']) as $file) {
        if (! empty($file['backup']) && is_file($file['backup'])) {
            @copy($file['backup'], $file['path']);
        } elseif (! empty($file['created']) && is_file($file['path'])) {
            @unlink($file['path']);
        }
    }

    fwrite(STDERR, "\nPATCH GAGAL: {$e->getMessage()}\n");
    fwrite(STDERR, "File source dipulihkan otomatis.\n");
    exit(1);
}
