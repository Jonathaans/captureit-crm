<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$files = [
    'Menu' => $root.'/packages/Webkul/Admin/src/Config/menu.php',
    'ACL' => $root.'/packages/Webkul/Admin/src/Config/acl.php',
    'Operations Service' => $root.'/packages/Webkul/Admin/src/Services/OperationsDashboardService.php',
    'Vendor Controller' => $root.'/packages/Webkul/Admin/src/Http/Controllers/Vendor/VendorController.php',
    'Vendor Provider' => $root.'/packages/Webkul/Admin/src/Providers/CrmOperationsServiceProvider.php',
    'Vendor Form' => $root.'/packages/Webkul/Admin/src/Resources/views/vendors/form.blade.php',
    'Vendor Index' => $root.'/packages/Webkul/Admin/src/Resources/views/vendors/index.blade.php',
    'NPWP Service' => $root.'/packages/Webkul/Admin/src/Services/VendorNpwpImageService.php',
    'Chat View' => $root.'/packages/Webkul/Admin/src/Resources/views/internal-communication/chat.blade.php',
    'Chat Controller' => $root.'/packages/Webkul/Admin/src/Http/Controllers/InternalCommunication/InternalChatController.php',
    'Migration' => $root.'/database/migrations/2026_09_03_230000_add_npwp_image_to_vendors_and_contacts_acl.php',
];

$failed = 0;

function checkV1(bool $ok, string $label): void
{
    global $failed;
    echo ($ok ? '[OK]   ' : '[FAIL] ').$label.PHP_EOL;

    if (! $ok) {
        $failed++;
    }
}

function sourceV1(string $path): string
{
    return is_file($path) ? (string) file_get_contents($path) : '';
}

echo "CHECK VENDOR CONTACTS + NPWP IMAGE + CHAT NEWEST V1\n";
echo "=====================================================\n\n";

foreach ($files as $label => $path) {
    checkV1(is_file($path), "{$label} tersedia");
}

$menu = sourceV1($files['Menu']);
$acl = sourceV1($files['ACL']);
$operations = sourceV1($files['Operations Service']);
$controller = sourceV1($files['Vendor Controller']);
$provider = sourceV1($files['Vendor Provider']);
$form = sourceV1($files['Vendor Form']);
$index = sourceV1($files['Vendor Index']);
$service = sourceV1($files['NPWP Service']);
$chat = sourceV1($files['Chat View']);
$chatController = sourceV1($files['Chat Controller']);

checkV1(
    str_contains($menu, "'key'        => 'contacts.vendors'")
        && str_contains($menu, "'route'      => 'admin.vendors.index'"),
    'Vendor Master berada di submenu Contacts'
);

checkV1(
    ! preg_match("~['\"]key['\"]\\s*=>\\s*['\"]vendors['\"]~", $menu),
    'Tidak ada menu Vendor Master top-level lama'
);

checkV1(
    str_contains($acl, "'key'   => 'contacts.vendors'")
        && str_contains($acl, "'admin.vendors.npwp-image'"),
    'ACL Contacts Vendor + view NPWP tersedia'
);

checkV1(
    ! (str_contains($operations, "'label' => 'Vendor Master'")
        && str_contains($operations, "'admin.vendors.index'")),
    'Vendor Master dihapus dari Quick Controls Operations Dashboard'
);

checkV1(
    str_contains($provider, "'admin.vendors.npwp-image'")
        && str_contains($provider, "'npwpImage'"),
    'Route privat view image NPWP tersedia'
);

checkV1(
    str_contains($controller, "'npwp_image' => [")
        && str_contains($controller, "'mimes:jpg,jpeg,png,webp'")
        && substr_count($controller, 'VENDOR NPWP NON COLUMN FIELDS V1') === 2
        && str_contains($controller, 'VendorNpwpImageService::store')
        && str_contains($controller, 'public function npwpImage('),
    'Controller validasi, membersihkan field upload, menyimpan, dan menampilkan image NPWP'
);

checkV1(
    str_contains($controller, "'contacts.vendors'")
        && str_contains($controller, "'vendors'"),
    'Controller menerima ACL baru dan ACL legacy'
);

checkV1(
    str_contains($form, 'enctype="multipart/form-data"')
        && str_contains($form, 'name="npwp_image"')
        && str_contains($form, 'accept="image/jpeg,image/png,image/webp"'),
    'Form Vendor memiliki input image NPWP multipart'
);

checkV1(
    str_contains($index, 'VENDOR NPWP INDEX ACTION V1')
        && str_contains($index, "route('admin.vendors.npwp-image'"),
    'Vendor index memiliki View NPWP'
);

checkV1(
    str_contains($service, 'VENDOR NPWP PRIVATE SERVICE V1')
        && str_contains($service, "'local'")
        && str_contains($service, "'inline'"),
    'Image NPWP disimpan privat dan dapat dilihat inline'
);

checkV1(
    str_contains($chat, 'data-chat-shell-newest-v1="1"')
        && str_contains($chat, 'height:clamp(520px,calc(100dvh - 260px),760px)'),
    'Chat memakai panel dengan tinggi responsif'
);

checkV1(
    str_contains($chat, 'data-chat-newest-scroll-v1="1"')
        && str_contains($chat, 'min-h-0 flex-1 flex-col overflow-y-auto'),
    'Daftar pesan menjadi scroll viewport yang benar'
);

checkV1(
    str_contains($chat, 'INTERNAL CHAT NEWEST PANEL V1')
        && str_contains($chat, 'window.crmChatGoNewest')
        && str_contains($chat, "form.addEventListener('submit'")
        && str_contains($chat, 'new MutationObserver'),
    'Open chat dan send message mengikuti newest'
);

checkV1(
    ! str_contains($chat, 'INTERNAL CHAT BOTTOM STICKY V1.6')
        && ! str_contains($chat, 'INTERNAL CHAT LATEST50 BOTTOM V1.5')
        && ! str_contains($chat, 'INTERNAL CHAT SCROLL GUARD LATEST50 V1.7')
        && ! str_contains($chat, 'flex-col-reverse'),
    'Eksperimen scroll lama yang bertumpuk sudah dibersihkan'
);

checkV1(
    preg_match(
        '~->orderByDesc\\s*\\(\\s*[\'\"]id[\'\"]\\s*\\)[\\s\\S]*?->limit\\s*\\(\\s*50\\s*\\)[\\s\\S]*?->get\\s*\\(\\s*\\)[\\s\\S]*?->sortBy\\s*\\(\\s*[\'\"]id[\'\"]\\s*\\)~',
        $chatController
    ) === 1,
    'Backend memuat 50 pesan terbaru lalu merender old-to-new'
);

if (is_file($root.'/vendor/autoload.php') && is_file($root.'/bootstrap/app.php')) {
    try {
        require_once $root.'/vendor/autoload.php';
        $app = require $root.'/bootstrap/app.php';
        $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
        $kernel->bootstrap();

        checkV1(
            Illuminate\Support\Facades\Schema::hasTable('vendors'),
            'Tabel vendors tersedia'
        );
        checkV1(
            Illuminate\Support\Facades\Schema::hasColumn('vendors', 'npwp_image_path'),
            'Kolom vendors.npwp_image_path tersedia'
        );
    } catch (Throwable $e) {
        checkV1(false, 'Bootstrap/database check gagal: '.$e->getMessage());
    }
}

echo PHP_EOL;

if ($failed > 0) {
    echo "[FAIL] Checker menemukan {$failed} masalah.\n";
    exit(1);
}

echo "[PASS] Semua pemeriksaan berhasil.\n";
echo "Lakukan Ctrl+Shift+R lalu tes Vendor Master dan Internal Chat.\n";
