param(
    [string]$ProjectRoot = $PSScriptRoot
)

$ErrorActionPreference = 'Stop'

function Read-Utf8File([string]$Path) {
    $content = [System.IO.File]::ReadAllText(
        $Path,
        [System.Text.Encoding]::UTF8
    )

    # Normalisasi line ending agar anchor patch konsisten di Windows/Linux.
    return $content.Replace("`r`n", "`n")
}

function Write-Utf8NoBom([string]$Path, [string]$Content) {
    $encoding = New-Object System.Text.UTF8Encoding($false)
    [System.IO.File]::WriteAllText($Path, $Content, $encoding)
}

function Backup-File([string]$Path) {
    $backupRoot = Join-Path $ProjectRoot 'storage\app\phase3a-backup'

    if (-not (Test-Path $backupRoot)) {
        New-Item -ItemType Directory -Path $backupRoot -Force | Out-Null
    }

    $relative = $Path.Substring($ProjectRoot.Length).TrimStart('\', '/')
    $safeName = $relative -replace '[\\/:*?"<>|]', '__'
    $backupPath = Join-Path $backupRoot $safeName

    Copy-Item $Path $backupPath -Force
    Write-Host "Backup: $relative"
}

function Replace-Once(
    [string]$Content,
    [string]$Old,
    [string]$New,
    [string]$Label
) {
    if (-not $Content.Contains($Old)) {
        throw "Anchor tidak ditemukan: $Label"
    }

    return $Content.Replace($Old, $New)
}

function Replace-BetweenMarkers(
    [string]$Content,
    [string]$StartMarker,
    [string]$EndMarker,
    [string]$Replacement,
    [string]$Label
) {
    $start = $Content.IndexOf($StartMarker)

    if ($start -lt 0) {
        throw "Start marker tidak ditemukan: $Label"
    }

    $end = $Content.IndexOf($EndMarker, $start)

    if ($end -lt 0) {
        throw "End marker tidak ditemukan: $Label"
    }

    return $Content.Substring(0, $start) +
        $Replacement +
        $Content.Substring($end)
}

$projectRootFull = [System.IO.Path]::GetFullPath($ProjectRoot)
$ProjectRoot = $projectRootFull.TrimEnd('\', '/')

$productController = Join-Path $ProjectRoot 'packages\Webkul\Admin\src\Http\Controllers\Products\ProductController.php'
$productEdit = Join-Path $ProjectRoot 'packages\Webkul\Admin\src\Resources\views\products\edit.blade.php'
$deliveryController = Join-Path $ProjectRoot 'packages\Webkul\Admin\src\Http\Controllers\DeliveryOrder\DeliveryOrderController.php'
$deliveryEdit = Join-Path $ProjectRoot 'packages\Webkul\Admin\src\Resources\views\delivery-orders\edit.blade.php'
$deliveryShow = Join-Path $ProjectRoot 'packages\Webkul\Admin\src\Resources\views\delivery-orders\show.blade.php'

$required = @(
    $productController,
    $productEdit,
    $deliveryController,
    $deliveryEdit,
    $deliveryShow
)

foreach ($file in $required) {
    if (-not (Test-Path $file)) {
        throw "File tidak ditemukan: $file"
    }
}

Write-Host ""
Write-Host "Inventory Phase 3A installer"
Write-Host "Project: $ProjectRoot"
Write-Host ""

# -------------------------------------------------------------------------
# ProductController
# -------------------------------------------------------------------------
$content = Read-Utf8File $productController

if (-not $content.Contains("'equipment_items.*.inventory_item_id'")) {
    Backup-File $productController

    $anchor = @"
        'equipment_items.*.name' => [
"@

    $replacement = @"
        'equipment_items.*.inventory_item_id' => [
            'nullable',
            'integer',
            'exists:inventory_items,id',
        ],

        'equipment_items.*.name' => [
"@

    $content = Replace-Once $content $anchor $replacement 'ProductController validation'

    $anchor = @"
                    'template_id' =>
                        `$template->id,

                    'name' =>
"@

    $replacement = @"
                    'template_id' =>
                        `$template->id,

                    'inventory_item_id' =>
                        ! empty(`$item['inventory_item_id'])
                            ? (int) `$item['inventory_item_id']
                            : null,

                    'name' =>
"@

    $content = Replace-Once $content $anchor $replacement 'ProductController save inventory mapping'

    Write-Utf8NoBom $productController $content
    Write-Host "Patched: ProductController.php"
} else {
    Write-Host "Skip: ProductController.php sudah Phase 3A"
}

# -------------------------------------------------------------------------
# Product edit Blade -> dedicated partial
# -------------------------------------------------------------------------
$content = Read-Utf8File $productEdit

if (-not $content.Contains("admin::products.partials.equipment-template")) {
    Backup-File $productEdit

    $startMarker = "{{-- EQUIPMENT TEMPLATE --}}"
    $endMarker = "                <!-- Right sub-component -->"

    $replacement = @"
{{-- EQUIPMENT TEMPLATE --}}
@include('admin::products.partials.equipment-template')
                </div>

"@

    $content = Replace-BetweenMarkers `
        $content `
        $startMarker `
        $endMarker `
        $replacement `
        'Product edit Equipment Template'

    Write-Utf8NoBom $productEdit $content
    Write-Host "Patched: products\edit.blade.php"
} else {
    Write-Host "Skip: products\edit.blade.php sudah Phase 3A"
}

# -------------------------------------------------------------------------
# DeliveryOrderController
# -------------------------------------------------------------------------
$content = Read-Utf8File $deliveryController

if (-not $content.Contains("'items.*.inventory_item_id'")) {
    Backup-File $deliveryController

    $anchor = @"
            'items.*.name' => [
"@

    $replacement = @"
            'items.*.inventory_item_id' => [
                'nullable',
                'integer',
                'exists:inventory_items,id',
            ],

            'items.*.name' => [
"@

    $content = Replace-Once $content $anchor $replacement 'DeliveryOrderController validation'

    $anchor = @"
                        'delivery_order_id' =>
                            `$deliveryOrder->id,

                        'name' =>
"@

    $replacement = @"
                        'delivery_order_id' =>
                            `$deliveryOrder->id,

                        'inventory_item_id' =>
                            ! empty(`$item['inventory_item_id'])
                                ? (int) `$item['inventory_item_id']
                                : null,

                        'name' =>
"@

    $content = Replace-Once $content $anchor $replacement 'DeliveryOrderController save inventory mapping'

    Write-Utf8NoBom $deliveryController $content
    Write-Host "Patched: DeliveryOrderController.php"
} else {
    Write-Host "Skip: DeliveryOrderController.php sudah Phase 3A"
}

# -------------------------------------------------------------------------
# Delivery Order edit Blade -> dedicated partial
# -------------------------------------------------------------------------
$content = Read-Utf8File $deliveryEdit

if (-not $content.Contains("admin::delivery-orders.partials.equipment-edit")) {
    Backup-File $deliveryEdit

    $startMarker = "{{-- EQUIPMENT / ITEMS - MANUAL FIXED ROWS --}}"
    $endMarker = "{{-- NOTES --}}"

    $replacement = @"
{{-- EQUIPMENT / ITEMS - INVENTORY REQUIREMENT --}}
        @include('admin::delivery-orders.partials.equipment-edit')


        {{-- ===================================================== --}}
"@

    $content = Replace-BetweenMarkers `
        $content `
        $startMarker `
        $endMarker `
        $replacement `
        'Delivery Order edit equipment'

    Write-Utf8NoBom $deliveryEdit $content
    Write-Host "Patched: delivery-orders\edit.blade.php"
} else {
    Write-Host "Skip: delivery-orders\edit.blade.php sudah Phase 3A"
}

# -------------------------------------------------------------------------
# Delivery Order show Blade -> dedicated partial
# -------------------------------------------------------------------------
$content = Read-Utf8File $deliveryShow

if (-not $content.Contains("admin::delivery-orders.partials.equipment-show")) {
    Backup-File $deliveryShow

    $startMarker = "{{-- EQUIPMENT --}}"
    $endMarker = "{{-- NOTES --}}"

    $replacement = @"
{{-- EQUIPMENT / INVENTORY REQUIREMENT --}}
        @include('admin::delivery-orders.partials.equipment-show')


        {{-- ===================================================== --}}
"@

    $content = Replace-BetweenMarkers `
        $content `
        $startMarker `
        $endMarker `
        $replacement `
        'Delivery Order show equipment'

    Write-Utf8NoBom $deliveryShow $content
    Write-Host "Patched: delivery-orders\show.blade.php"
} else {
    Write-Host "Skip: delivery-orders\show.blade.php sudah Phase 3A"
}

Write-Host ""
Write-Host "Patch Phase 3A selesai."
Write-Host "Backup ada di storage\app\phase3a-backup"
Write-Host ""
Write-Host "Lanjutkan:"
Write-Host "  php artisan migrate"
Write-Host "  php artisan optimize:clear"
Write-Host ""
