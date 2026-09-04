<?php

declare(strict_types=1);

/**
 * Read-only checker for Purchase Order PAID + private PDF proof.
 *
 * Run from project root:
 * php tools/check_purchase_order_paid_transfer_proof_v1.php
 */

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Webkul\Invoice\Models\Expense;

$root = dirname(__DIR__);
$errors = [];

function poPaidPdfV1Check(bool $condition, string $success, string $failure): void
{
    global $errors;

    if ($condition) {
        echo "[OK]   {$success}\n";

        return;
    }

    echo "[FAIL] {$failure}\n";
    $errors[] = $failure;
}

echo "CHECK PURCHASE ORDER PAID + PDF PROOF V1\n";
echo "=========================================\n\n";

foreach ([$root.'/vendor/autoload.php', $root.'/bootstrap/app.php'] as $path) {
    if (! is_file($path)) {
        fwrite(STDERR, "File tidak ditemukan: {$path}\n");
        exit(1);
    }
}

require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$paths = [
    'PO Controller' => $root.'/packages/Webkul/Admin/src/Http/Controllers/PurchaseOrder/PurchaseOrderController.php',
    'PO Model' => $root.'/packages/Webkul/Invoice/src/Models/PurchaseOrder.php',
    'PO Expense Service' => $root.'/packages/Webkul/Invoice/src/Services/PurchaseOrderExpenseService.php',
    'Payment Proof Service' => $root.'/packages/Webkul/Invoice/src/Services/PurchaseOrderPaymentProofService.php',
    'PO Routes' => $root.'/packages/Webkul/Admin/src/Routes/Admin/invoice-routes.php',
    'PO ACL' => $root.'/packages/Webkul/Admin/src/Config/acl.php',
    'PO Show' => $root.'/packages/Webkul/Admin/src/Resources/views/purchase-orders/show.blade.php',
    'PO Index' => $root.'/packages/Webkul/Admin/src/Resources/views/purchase-orders/index.blade.php',
    'Invoice Show' => $root.'/packages/Webkul/Admin/src/Resources/views/invoices/show.blade.php',
    'Invoice Controller' => $root.'/packages/Webkul/Admin/src/Http/Controllers/Invoice/InvoiceController.php',
    'Migration' => $root.'/database/migrations/2026_09_03_160000_add_paid_status_and_payment_proof_to_purchase_orders_table.php',
    'Pest Test' => $root.'/tests/Unit/PurchaseOrderPaymentProofServiceTest.php',
];

$contents = [];

foreach ($paths as $label => $path) {
    $exists = is_file($path);
    poPaidPdfV1Check($exists, "{$label} tersedia.", "{$label} tidak ditemukan: {$path}");
    $contents[$label] = $exists ? (string) file_get_contents($path) : '';
}

$sourceChecks = [
    'Release belum membuat Expense' =>
        str_contains($contents['PO Controller'], 'DRAFT -> RELEASED only')
        && str_contains(
            $contents['PO Controller'],
            'RELEASED dan sedang menunggu pembayaran. Expense belum dibuat.'
        ),

    'PAID hanya menerima PDF maksimal 10 MB' =>
        str_contains($contents['PO Controller'], 'PURCHASE ORDER PAID PDF PROOF V1')
        && str_contains($contents['PO Controller'], "'mimes:pdf'")
        && str_contains($contents['PO Controller'], "'mimetypes:application/pdf'")
        && str_contains($contents['PO Controller'], "'max:10240'"),

    'PAID membuat Expense satu kali' =>
        str_contains($contents['PO Controller'], 'lockForUpdate()')
        && str_contains($contents['PO Controller'], 'createForPaidPurchaseOrder'),

    'PAID final dan tidak dapat cancel' =>
        str_contains(
            $contents['PO Controller'],
            '$purchaseOrder->isPaid() || $purchaseOrder->isCompleted()'
        ),

    'PDF diperiksa dan disimpan privat' =>
        str_contains($contents['Payment Proof Service'], 'PURCHASE ORDER PAID PDF PROOF V1')
        && str_contains($contents['Payment Proof Service'], "header !== '%PDF-'")
        && str_contains($contents['Payment Proof Service'], "Storage::disk('local')")
        && ! str_contains($contents['Payment Proof Service'], "Storage::disk('public')"),

    'Service PDF tidak mengubah upload gambar modul lain' =>
        ! str_contains($contents['Payment Proof Service'], 'imagejpeg(')
        && ! str_contains($contents['Payment Proof Service'], 'imagecreatefromstring('),

    'Form Pay hanya menerima PDF' =>
        str_contains($contents['PO Show'], 'PURCHASE ORDER PAID PDF PROOF V1 FORM')
        && str_contains($contents['PO Show'], 'accept="application/pdf,.pdf"')
        && str_contains($contents['PO Show'], 'name="payment_proof"')
        && str_contains($contents['PO Show'], 'required'),

    'Expense menyimpan route bukti tanpa hostname' =>
        str_contains($contents['PO Expense Service'], 'PURCHASE ORDER PAID PDF RECEIPT V1')
        && str_contains($contents['PO Expense Service'], 'absolute: false'),

    'Invoice membuka route bukti PO tanpa prefix storage' =>
        str_contains($contents['Invoice Show'], 'PURCHASE ORDER PAID PDF RECEIPT V1')
        && str_contains($contents['Invoice Show'], 'href="{{ $receiptUrl }}"')
        && ! str_contains(
            $contents['Invoice Show'],
            "href=\"{{ asset('storage/'.\$expense->receipt_path) }}\""
        ),

    'Route bukti privat mendukung PDF dan gambar lama' =>
        str_contains($contents['PO Controller'], "'pdf' => 'application/pdf'")
        && str_contains($contents['PO Controller'], "'X-Content-Type-Options' => 'nosniff'"),

    'Expense PAID dikunci dari edit/delete manual' =>
        str_contains($contents['Invoice Controller'], 'Expense dari PAID/legacy PO dikunci')
        && substr_count($contents['Invoice Controller'], "'paid',") >= 2,
];

foreach ($sourceChecks as $label => $ok) {
    poPaidPdfV1Check($ok, $label, $label.' belum terpasang dengan benar.');
}

$paidRoute = Route::getRoutes()->getByName('admin.purchase-orders.paid');
$proofRoute = Route::getRoutes()->getByName('admin.purchase-orders.payment-proof');

poPaidPdfV1Check(
    $paidRoute && in_array('POST', $paidRoute->methods(), true),
    'Route PAID menggunakan POST.',
    'Route admin.purchase-orders.paid POST tidak ditemukan.'
);
poPaidPdfV1Check(
    $proofRoute && in_array('GET', $proofRoute->methods(), true),
    'Route bukti privat menggunakan GET.',
    'Route admin.purchase-orders.payment-proof GET tidak ditemukan.'
);

$schemaColumns = [
    'status',
    'expense_id',
    'payment_proof_path',
    'paid_by',
    'paid_by_name',
    'paid_at',
];

$hasPoTable = Schema::hasTable('purchase_orders');
poPaidPdfV1Check($hasPoTable, 'Tabel purchase_orders tersedia.', 'Tabel purchase_orders tidak ditemukan.');

if ($hasPoTable) {
    $missingColumns = array_values(array_diff(
        $schemaColumns,
        Schema::getColumnListing('purchase_orders')
    ));

    poPaidPdfV1Check(
        $missingColumns === [],
        'Kolom workflow PAID lengkap.',
        'Kolom workflow PAID kurang: '.implode(', ', $missingColumns)
    );

    if (DB::connection()->getDriverName() === 'mysql') {
        try {
            $statusColumn = DB::selectOne("SHOW COLUMNS FROM `purchase_orders` LIKE 'status'");
            $type = strtolower((string) ($statusColumn->Type ?? ''));

            poPaidPdfV1Check(
                str_contains($type, "'paid'"),
                'ENUM purchase_orders.status menerima paid.',
                'ENUM purchase_orders.status belum menerima paid.'
            );
        } catch (Throwable $exception) {
            poPaidPdfV1Check(false, '', 'Gagal membaca ENUM status: '.$exception->getMessage());
        }
    }
}

if ($hasPoTable && $errors === []) {
    $expenseTable = (new Expense())->getTable();
    $paidRows = DB::table('purchase_orders')
        ->where('status', 'paid')
        ->get(['id', 'expense_id', 'payment_proof_path']);

    $missingProofPaths = 0;
    $missingProofFiles = 0;
    $invalidPdfFiles = 0;
    $legacyImageFiles = 0;
    $invalidExpenseLinks = 0;

    foreach ($paidRows as $purchaseOrder) {
        $proofPath = trim((string) ($purchaseOrder->payment_proof_path ?? ''));

        if ($proofPath === '') {
            $missingProofPaths++;
        } elseif (! Storage::disk('local')->exists($proofPath)) {
            $missingProofFiles++;
        } elseif (strtolower(pathinfo($proofPath, PATHINFO_EXTENSION)) === 'pdf') {
            $absolutePath = Storage::disk('local')->path($proofPath);
            $handle = fopen($absolutePath, 'rb');
            $header = is_resource($handle) ? fread($handle, 5) : false;

            if (is_resource($handle)) {
                fclose($handle);
            }

            $mimeType = (new finfo(FILEINFO_MIME_TYPE))->file($absolutePath);

            if ($header !== '%PDF-' || $mimeType !== 'application/pdf') {
                $invalidPdfFiles++;
            }
        } else {
            $legacyImageFiles++;
        }

        $expense = $purchaseOrder->expense_id
            ? DB::table($expenseTable)->find($purchaseOrder->expense_id)
            : null;
        $expectedReceipt = route(
            'admin.purchase-orders.payment-proof',
            $purchaseOrder->id,
            absolute: false
        );

        if (! $expense || (string) ($expense->receipt_path ?? '') !== $expectedReceipt) {
            $invalidExpenseLinks++;
        }
    }

    echo "\nDATA DIAGNOSTIC (read-only)\n";
    echo '[INFO] PAID: '.$paidRows->count().PHP_EOL;
    echo "[INFO] Bukti gambar historis: {$legacyImageFiles}\n";

    if ($legacyImageFiles > 0) {
        echo "[WARN] Bukti gambar lama tetap didukung; upload Pay berikutnya hanya PDF.\n";
    }

    poPaidPdfV1Check(
        $missingProofPaths === 0,
        'Semua PO PAID memiliki path bukti.',
        "Ada {$missingProofPaths} PO PAID tanpa path bukti."
    );
    poPaidPdfV1Check(
        $missingProofFiles === 0,
        'Semua file bukti PAID ditemukan di private storage.',
        "Ada {$missingProofFiles} file bukti PAID yang hilang."
    );
    poPaidPdfV1Check(
        $invalidPdfFiles === 0,
        'Semua bukti ber-ekstensi PDF memiliki isi PDF yang valid.',
        "Ada {$invalidPdfFiles} file .pdf dengan isi/MIME tidak valid."
    );
    poPaidPdfV1Check(
        $invalidExpenseLinks === 0,
        'Semua Expense PAID menunjuk route bukti PO yang benar.',
        "Ada {$invalidExpenseLinks} Expense PAID dengan link receipt tidak valid."
    );
}

echo PHP_EOL;

if ($errors !== []) {
    echo '[FAIL] Checker menemukan '.count($errors)." masalah.\n";
    exit(1);
}

echo "[PASS] Workflow PAID PDF dan View Receipt Invoice siap digunakan.\n";
echo "Browser test: Release PO -> Pay dengan PDF -> buka Invoice -> View Receipt / Bon.\n";
