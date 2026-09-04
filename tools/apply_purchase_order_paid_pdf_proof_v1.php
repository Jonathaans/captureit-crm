<?php

declare(strict_types=1);

/**
 * PURCHASE ORDER PAID PDF PROOF V1
 *
 * Upgrade an installed Purchase Order PAID V1/V1.1 workflow:
 * - new PAID proofs accept PDF only;
 * - proof stays in private local storage;
 * - Expense receipt links use the private PO route, not /storage;
 * - existing linked PAID Expense rows are normalized to relative routes.
 *
 * Run from project root:
 * php tools/apply_purchase_order_paid_pdf_proof_v1.php
 */

$root = dirname(__DIR__);

$paths = [
    'controller' => $root.'/packages/Webkul/Admin/src/Http/Controllers/PurchaseOrder/PurchaseOrderController.php',
    'expense_service' => $root.'/packages/Webkul/Invoice/src/Services/PurchaseOrderExpenseService.php',
    'proof_service' => $root.'/packages/Webkul/Invoice/src/Services/PurchaseOrderPaymentProofService.php',
    'po_show' => $root.'/packages/Webkul/Admin/src/Resources/views/purchase-orders/show.blade.php',
    'invoice_show' => $root.'/packages/Webkul/Admin/src/Resources/views/invoices/show.blade.php',
    'test' => $root.'/tests/Unit/PurchaseOrderPaymentProofServiceTest.php',
];

function poPaidPdfV1Fail(string $message): never
{
    fwrite(STDERR, $message.PHP_EOL);
    exit(1);
}

function poPaidPdfV1Read(string $path): string
{
    $contents = file_get_contents($path);

    if ($contents === false) {
        throw new RuntimeException("Gagal membaca {$path}");
    }

    return str_replace(["\r\n", "\r"], "\n", $contents);
}

function poPaidPdfV1AtomicWrite(string $path, string $contents): void
{
    $temporary = $path.'.tmp-po-paid-pdf-'.bin2hex(random_bytes(4));

    if (file_put_contents($temporary, $contents) === false) {
        @unlink($temporary);
        throw new RuntimeException("Gagal menulis temporary file {$temporary}");
    }

    if (! rename($temporary, $path)) {
        @unlink($temporary);
        throw new RuntimeException("Gagal mengganti {$path}");
    }
}

function poPaidPdfV1ReplaceOnce(
    string $source,
    string $search,
    string $replacement,
    string $label
): string {
    $count = substr_count($source, $search);

    if ($count !== 1) {
        throw new RuntimeException(
            "Preflight {$label} gagal: anchor ditemukan {$count} kali."
        );
    }

    return str_replace($search, $replacement, $source);
}

function poPaidPdfV1MethodRange(string $source, string $method): array
{
    $pattern = '/\bpublic\s+function\s+'.preg_quote($method, '/').'\s*\(/';

    if (preg_match($pattern, $source, $match, PREG_OFFSET_CAPTURE) !== 1) {
        throw new RuntimeException("Method public {$method} tidak ditemukan.");
    }

    $methodStart = (int) $match[0][1];
    $brace = strpos($source, '{', $methodStart);

    if ($brace === false) {
        throw new RuntimeException("Opening brace method {$method} tidak ditemukan.");
    }

    $length = strlen($source);
    $depth = 0;
    $state = 'code';
    $escaped = false;
    $end = null;

    for ($position = $brace; $position < $length; $position++) {
        $character = $source[$position];
        $next = $position + 1 < $length ? $source[$position + 1] : '';

        if ($state === 'single' || $state === 'double') {
            if ($escaped) {
                $escaped = false;
                continue;
            }

            if ($character === '\\') {
                $escaped = true;
                continue;
            }

            if (
                ($state === 'single' && $character === "'")
                || ($state === 'double' && $character === '"')
            ) {
                $state = 'code';
            }

            continue;
        }

        if ($state === 'line_comment') {
            if ($character === "\n") {
                $state = 'code';
            }

            continue;
        }

        if ($state === 'block_comment') {
            if ($character === '*' && $next === '/') {
                $state = 'code';
                $position++;
            }

            continue;
        }

        if ($character === "'") {
            $state = 'single';
            continue;
        }

        if ($character === '"') {
            $state = 'double';
            continue;
        }

        if (($character === '/' && $next === '/') || $character === '#') {
            $state = 'line_comment';
            $position += $character === '/' ? 1 : 0;
            continue;
        }

        if ($character === '/' && $next === '*') {
            $state = 'block_comment';
            $position++;
            continue;
        }

        if ($character === '{') {
            $depth++;
        } elseif ($character === '}') {
            $depth--;

            if ($depth === 0) {
                $end = $position + 1;
                break;
            }
        }
    }

    if ($end === null) {
        throw new RuntimeException("Closing brace method {$method} tidak ditemukan.");
    }

    $beforeMethod = substr($source, 0, $methodStart);
    $docStart = strrpos($beforeMethod, '/**');
    $docEnd = strrpos($beforeMethod, '*/');

    if (
        $docStart !== false
        && $docEnd !== false
        && trim(substr($source, $docEnd + 2, $methodStart - ($docEnd + 2))) === ''
    ) {
        $methodStart = $docStart;
    }

    return [$methodStart, $end];
}

function poPaidPdfV1ReplaceMethod(
    string $source,
    string $method,
    string $replacement
): string {
    [$start, $end] = poPaidPdfV1MethodRange($source, $method);

    return substr_replace($source, rtrim($replacement), $start, $end - $start);
}

function poPaidPdfV1Lint(string $path): array
{
    exec(
        escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($path).' 2>&1',
        $output,
        $code
    );

    return [$code, implode(PHP_EOL, $output)];
}

set_exception_handler(
    static function (Throwable $exception): void {
        poPaidPdfV1Fail('PATCH GAGAL: '.$exception->getMessage());
    }
);

echo "PURCHASE ORDER PAID PDF PROOF V1\n";
echo "=================================\n\n";

foreach ($paths as $label => $path) {
    if (! is_file($path)) {
        poPaidPdfV1Fail("File {$label} tidak ditemukan: {$path}");
    }
}

$sources = [];

foreach ($paths as $key => $path) {
    $sources[$key] = poPaidPdfV1Read($path);
}

if (! str_contains($sources['controller'], 'PURCHASE ORDER PAID WORKFLOW V1')) {
    throw new RuntimeException(
        'Workflow PAID V1/V1.1 belum terpasang. Jalankan installer PAID V1.1 terlebih dahulu.'
    );
}

$upgradeChecks = [
    str_contains($sources['controller'], 'PURCHASE ORDER PAID PDF PROOF V1'),
    str_contains($sources['proof_service'], 'PURCHASE ORDER PAID PDF PROOF V1'),
    str_contains($sources['po_show'], 'PURCHASE ORDER PAID PDF PROOF V1 FORM'),
    str_contains($sources['invoice_show'], 'PURCHASE ORDER PAID PDF RECEIPT V1'),
    str_contains($sources['expense_service'], 'absolute: false'),
];

$upgradeInstalled = ! in_array(false, $upgradeChecks, true);

if (in_array(true, $upgradeChecks, true) && ! $upgradeInstalled) {
    throw new RuntimeException(
        'Upgrade PDF terdeteksi hanya terpasang sebagian. Pulihkan backup .bak-po-paid-pdf-v1 lalu jalankan ulang.'
    );
}

$backups = [];

if (! $upgradeInstalled) {
    $paidMethod = <<<'PHP'
    /**
     * PURCHASE ORDER PAID PDF PROOF V1
     *
     * RELEASED -> PAID. A private PDF transfer proof is mandatory and
     * Expense is created exactly once in the same database transaction.
     */
    public function paid(Request $request, int $id): RedirectResponse
    {
        $request->validate([
            'payment_proof' => [
                'required',
                'file',
                'mimes:pdf',
                'mimetypes:application/pdf',
                'max:10240',
            ],
        ], [
            'payment_proof.required' => 'PDF bukti transfer wajib diunggah.',
            'payment_proof.file' => 'Bukti transfer harus berupa file PDF.',
            'payment_proof.mimes' => 'Format bukti transfer harus PDF.',
            'payment_proof.mimetypes' => 'Isi bukti transfer harus berupa PDF yang valid.',
            'payment_proof.max' => 'Ukuran PDF bukti transfer maksimal 10 MB.',
        ]);

        $user = auth()->guard('user')->user();
        $proofPath = null;
        $alreadyPaid = false;

        try {
            DB::transaction(function () use (
                $request,
                $id,
                $user,
                &$proofPath,
                &$alreadyPaid
            ) {
                $purchaseOrder = PurchaseOrder::query()
                    ->lockForUpdate()
                    ->findOrFail($id);

                if ($purchaseOrder->isPaid()) {
                    $alreadyPaid = true;

                    return;
                }

                if (! $purchaseOrder->isReleased()) {
                    throw ValidationException::withMessages([
                        'status' => 'Hanya Purchase Order RELEASED yang dapat diubah menjadi PAID.',
                    ]);
                }

                if ((float) $purchaseOrder->grand_total <= 0) {
                    throw ValidationException::withMessages([
                        'grand_total' => 'Grand Total PO harus lebih besar dari 0 sebelum dibayar.',
                    ]);
                }

                $proof = $request->file('payment_proof');

                if (! $proof) {
                    throw ValidationException::withMessages([
                        'payment_proof' => 'PDF bukti transfer wajib diunggah.',
                    ]);
                }

                $proofService = app(
                    \Webkul\Invoice\Services\PurchaseOrderPaymentProofService::class
                );

                $proofPath = $proofService->store($proof, (int) $purchaseOrder->id);

                $purchaseOrder->update([
                    'status' => 'paid',
                    'payment_proof_path' => $proofPath,
                    'paid_by' => $user?->id,
                    'paid_by_name' => $user?->name,
                    'paid_at' => now(),
                ]);

                $purchaseOrder->refresh();

                $expenseId = $this->expenseService->createForPaidPurchaseOrder(
                    $purchaseOrder,
                    $user?->id,
                    $user?->name
                );

                $purchaseOrder->update(['expense_id' => $expenseId]);
            });
        } catch (\Throwable $exception) {
            if ($proofPath) {
                app(\Webkul\Invoice\Services\PurchaseOrderPaymentProofService::class)
                    ->delete($proofPath);
            }

            throw $exception;
        }

        session()->flash(
            $alreadyPaid ? 'warning' : 'success',
            $alreadyPaid
                ? 'Purchase Order ini sudah PAID.'
                : 'Purchase Order berhasil PAID. PDF bukti transfer tersimpan privat dan Expense dibuat.'
        );

        return redirect()->route('admin.purchase-orders.show', $id);
    }
PHP;

    $paymentProofMethod = <<<'PHP'
    /**
     * Serve a PAID proof from private storage. PDF is used for new records;
     * legacy JPEG proofs remain readable.
     */
    public function paymentProof(
        int $id
    ): \Symfony\Component\HttpFoundation\BinaryFileResponse {
        $purchaseOrder = PurchaseOrder::query()->findOrFail($id);

        abort_if(
            ! $purchaseOrder->payment_proof_path,
            404,
            'Bukti transfer tidak ditemukan.'
        );

        $proofPath = (string) $purchaseOrder->payment_proof_path;
        $absolutePath = app(
            \Webkul\Invoice\Services\PurchaseOrderPaymentProofService::class
        )->absolutePath($proofPath);

        $extension = strtolower(pathinfo($proofPath, PATHINFO_EXTENSION));
        $contentType = match ($extension) {
            'pdf' => 'application/pdf',
            'png' => 'image/png',
            'webp' => 'image/webp',
            default => 'image/jpeg',
        };
        $downloadExtension = in_array($extension, ['pdf', 'png', 'webp', 'jpg', 'jpeg'], true)
            ? $extension
            : 'bin';
        $safeNumber = preg_replace('/[^A-Za-z0-9_-]+/', '-', $purchaseOrder->po_number);

        return response()->file($absolutePath, [
            'Content-Type' => $contentType,
            'Content-Disposition' => 'inline; filename="bukti-transfer-'.$safeNumber.'.'.$downloadExtension.'"',
            'Cache-Control' => 'private, no-store, no-cache, must-revalidate',
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
        ]);
    }
PHP;

    $sources['controller'] = poPaidPdfV1ReplaceMethod(
        $sources['controller'],
        'paid',
        $paidMethod
    );
    $sources['controller'] = poPaidPdfV1ReplaceMethod(
        $sources['controller'],
        'paymentProof',
        $paymentProofMethod
    );

    $oldReceiptRoute = <<<'PHP'
        $receiptUrl = route(
            'admin.purchase-orders.payment-proof',
            $purchaseOrder->id
        );
PHP;
    $newReceiptRoute = <<<'PHP'
        /* PURCHASE ORDER PAID PDF RECEIPT V1: keep a host-independent route. */
        $receiptUrl = route(
            'admin.purchase-orders.payment-proof',
            $purchaseOrder->id,
            absolute: false
        );
PHP;
    $sources['expense_service'] = poPaidPdfV1ReplaceOnce(
        $sources['expense_service'],
        $oldReceiptRoute,
        $newReceiptRoute,
        'Expense receipt relative route'
    );

    $oldPaymentForm = <<<'BLADE'
        <!-- PURCHASE ORDER PAID WORKFLOW V1 FORM -->
        @if ($purchaseOrder->status === 'released' && bouncer()->hasPermission('purchase-orders.paid'))
            <section id="po-payment" class="rounded-xl border border-blue-200 bg-blue-50 p-5 text-sm text-blue-900">
                <form
                    method="POST"
                    action="{{ route('admin.purchase-orders.paid', $purchaseOrder->id) }}"
                    enctype="multipart/form-data"
                    onsubmit="return confirm('Konfirmasi pembayaran PO ini? Setelah PAID, Expense akan dibuat dan status tidak dapat dibatalkan.');"
                    style="display:grid;grid-template-columns:minmax(260px,1fr) auto;gap:12px;align-items:end;"
                >
                    @csrf

                    <div>
                        <label for="payment_proof" class="mb-1.5 block font-bold">
                            Gambar Bukti Transfer <span class="text-red-600">*</span>
                        </label>
                        <input
                            id="payment_proof"
                            name="payment_proof"
                            type="file"
                            accept="image/jpeg,image/png,image/webp"
                            required
                            class="w-full rounded-md border border-blue-200 bg-white px-3 py-2"
                        >
                        <p class="mt-1 text-xs text-blue-700">
                            JPG, PNG, atau WebP; maksimum 10 MB. Hanya bukti transfer ini yang dikompres otomatis menjadi JPEG, maksimal 2000 px.
                        </p>
                        @error('payment_proof')
                            <p class="mt-1 text-xs font-semibold text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <button type="submit" class="primary-button">Mark as PAID</button>
                </form>
            </section>
        @endif
BLADE;
    $newPaymentForm = <<<'BLADE'
        <!-- PURCHASE ORDER PAID PDF PROOF V1 FORM -->
        @if ($purchaseOrder->status === 'released' && bouncer()->hasPermission('purchase-orders.paid'))
            <section id="po-payment" class="rounded-xl border border-blue-200 bg-blue-50 p-5 text-sm text-blue-900">
                <form
                    method="POST"
                    action="{{ route('admin.purchase-orders.paid', $purchaseOrder->id) }}"
                    enctype="multipart/form-data"
                    onsubmit="return confirm('Konfirmasi pembayaran PO ini? Setelah PAID, Expense akan dibuat dan status tidak dapat dibatalkan.');"
                    style="display:grid;grid-template-columns:minmax(260px,1fr) auto;gap:12px;align-items:end;"
                >
                    @csrf

                    <div>
                        <label for="payment_proof" class="mb-1.5 block font-bold">
                            PDF Bukti Transfer <span class="text-red-600">*</span>
                        </label>
                        <input
                            id="payment_proof"
                            name="payment_proof"
                            type="file"
                            accept="application/pdf,.pdf"
                            required
                            class="w-full rounded-md border border-blue-200 bg-white px-3 py-2"
                        >
                        <p class="mt-1 text-xs text-blue-700">
                            Hanya PDF; maksimum 10 MB. File disimpan privat dan dapat dilihat dari PO maupun Expense Invoice.
                        </p>
                        @error('payment_proof')
                            <p class="mt-1 text-xs font-semibold text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <button type="submit" class="primary-button">Mark as PAID</button>
                </form>
            </section>
        @endif
BLADE;
    $sources['po_show'] = poPaidPdfV1ReplaceOnce(
        $sources['po_show'],
        $oldPaymentForm,
        $newPaymentForm,
        'PO PDF payment form'
    );

    $oldProofDisplay = <<<'BLADE'
        @if ($purchaseOrder->payment_proof_path)
            <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <div style="display:flex;align-items:flex-start;gap:16px;flex-wrap:wrap;">
                    <a
                        href="{{ route('admin.purchase-orders.payment-proof', $purchaseOrder->id) }}"
                        target="_blank"
                        rel="noopener"
                    >
                        <img
                            src="{{ route('admin.purchase-orders.payment-proof', $purchaseOrder->id) }}"
                            alt="Bukti transfer {{ $purchaseOrder->po_number }}"
                            style="width:180px;max-height:220px;object-fit:contain;border:1px solid #e5e7eb;border-radius:10px;"
                        >
                    </a>
                    <div>
                        <p class="text-[11px] font-bold uppercase tracking-wide text-gray-500">Bukti Transfer</p>
                        <p class="mt-2 text-sm text-gray-600">Gambar tersimpan privat dan sudah dikompres khusus untuk bukti pembayaran PO.</p>
                        <a
                            href="{{ route('admin.purchase-orders.payment-proof', $purchaseOrder->id) }}"
                            target="_blank"
                            rel="noopener"
                            class="mt-3 inline-flex font-bold text-blue-600 hover:underline"
                        >
                            Lihat gambar penuh
                        </a>
                    </div>
                </div>
            </section>
        @endif
BLADE;
    $newProofDisplay = <<<'BLADE'
        @if ($purchaseOrder->payment_proof_path)
            <!-- PURCHASE ORDER PAID PDF PROOF V1 DISPLAY -->
            <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <div style="display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;">
                    <div>
                        <p class="text-[11px] font-bold uppercase tracking-wide text-gray-500">Bukti Pembayaran</p>
                        <p class="mt-2 text-sm text-gray-600">
                            Bukti pembayaran tersimpan privat. Upload baru menggunakan format PDF.
                        </p>
                    </div>
                    <a
                        href="{{ route('admin.purchase-orders.payment-proof', $purchaseOrder->id) }}"
                        target="_blank"
                        rel="noopener"
                        class="primary-button"
                    >
                        View PDF / Bukti Transfer
                    </a>
                </div>
            </section>
        @endif
BLADE;
    $sources['po_show'] = poPaidPdfV1ReplaceOnce(
        $sources['po_show'],
        $oldProofDisplay,
        $newProofDisplay,
        'PO proof display'
    );

    $oldInvoiceReceipt = <<<'BLADE'
                            @if ($expense->receipt_path)
                                <a
                                    href="{{ asset('storage/'.$expense->receipt_path) }}"
BLADE;
    $newInvoiceReceipt = <<<'BLADE'
                            @if ($expense->receipt_path)
                                @php
                                    /* PURCHASE ORDER PAID PDF RECEIPT V1 */
                                    $receiptPath = trim((string) $expense->receipt_path);
                                    $isPoPaymentProof = preg_match(
                                        '~/admin/purchase-orders/\d+/payment-proof(?:\?.*)?$~i',
                                        $receiptPath
                                    ) === 1;
                                    $receiptUrl = $isPoPaymentProof
                                        ? (parse_url($receiptPath, PHP_URL_PATH) ?: $receiptPath)
                                        : asset('storage/'.ltrim($receiptPath, '/'));
                                @endphp
                                <a
                                    href="{{ $receiptUrl }}"
BLADE;
    $sources['invoice_show'] = poPaidPdfV1ReplaceOnce(
        $sources['invoice_show'],
        $oldInvoiceReceipt,
        $newInvoiceReceipt,
        'Invoice Expense receipt route'
    );

    $sources['proof_service'] = <<<'PHP'
<?php

declare(strict_types=1);

namespace Webkul\Invoice\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * PURCHASE ORDER PAID PDF PROOF V1
 *
 * Dedicated private storage for PO payment proofs. New uploads accept PDF
 * only. This service does not alter uploads in any other CRM module.
 */
final class PurchaseOrderPaymentProofService
{
    public const MAX_BYTES = 10 * 1024 * 1024;

    public function store(UploadedFile $file, int $purchaseOrderId): string
    {
        $realPath = $file->getRealPath();

        if (! is_string($realPath) || $realPath === '' || ! is_file($realPath)) {
            throw ValidationException::withMessages([
                'payment_proof' => 'PDF bukti transfer tidak dapat dibaca.',
            ]);
        }

        $size = filesize($realPath);

        if ($size === false || $size <= 0 || $size > self::MAX_BYTES) {
            throw ValidationException::withMessages([
                'payment_proof' => 'Ukuran PDF bukti transfer harus antara 1 byte dan 10 MB.',
            ]);
        }

        $mimeType = (new \finfo(FILEINFO_MIME_TYPE))->file($realPath);
        $handle = fopen($realPath, 'rb');

        if ($handle === false) {
            throw ValidationException::withMessages([
                'payment_proof' => 'PDF bukti transfer tidak dapat dibuka.',
            ]);
        }

        try {
            $header = fread($handle, 5);
            rewind($handle);

            if ($mimeType !== 'application/pdf' || $header !== '%PDF-') {
                throw ValidationException::withMessages([
                    'payment_proof' => 'Isi bukti transfer harus berupa PDF yang valid.',
                ]);
            }

            $relativePath = sprintf(
                'purchase-orders/payment-proofs/%s/po-%d-%s.pdf',
                now()->format('Y/m'),
                $purchaseOrderId,
                bin2hex(random_bytes(16))
            );

            if (! Storage::disk('local')->put($relativePath, $handle)) {
                throw new RuntimeException('Gagal menyimpan PDF bukti transfer.');
            }
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }
        }

        return $relativePath;
    }

    public function delete(?string $path): void
    {
        $path = trim((string) ($path ?? ''));

        if ($path !== '') {
            Storage::disk('local')->delete($path);
        }
    }

    public function absolutePath(string $path): string
    {
        if (! Storage::disk('local')->exists($path)) {
            throw new RuntimeException('File bukti transfer tidak ditemukan.');
        }

        return Storage::disk('local')->path($path);
    }
}
PHP;

    $sources['test'] = <<<'PHP'
<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Webkul\Invoice\Services\PurchaseOrderPaymentProofService;

uses(TestCase::class);

it('stores only a valid purchase order payment PDF in private storage', function (): void {
    Storage::fake('local');

    $temporary = tempnam(sys_get_temp_dir(), 'po-proof-');
    file_put_contents(
        $temporary,
        "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n"
    );

    try {
        $upload = new UploadedFile(
            $temporary,
            'bukti-transfer.pdf',
            'application/pdf',
            null,
            true
        );

        $path = app(PurchaseOrderPaymentProofService::class)->store($upload, 42);

        Storage::disk('local')->assertExists($path);

        expect($path)
            ->toStartWith('purchase-orders/payment-proofs/')
            ->toEndWith('.pdf');
    } finally {
        @unlink($temporary);
    }
});
PHP;

    $requiredMarkers = [
        'controller' => [
            'PURCHASE ORDER PAID PDF PROOF V1',
            "'mimes:pdf'",
            "'mimetypes:application/pdf'",
            "'pdf' => 'application/pdf'",
        ],
        'expense_service' => ['PURCHASE ORDER PAID PDF RECEIPT V1', 'absolute: false'],
        'proof_service' => ['PURCHASE ORDER PAID PDF PROOF V1', "header !== '%PDF-'", "disk('local')"],
        'po_show' => ['PURCHASE ORDER PAID PDF PROOF V1 FORM', 'accept="application/pdf,.pdf"'],
        'invoice_show' => ['PURCHASE ORDER PAID PDF RECEIPT V1', 'href="{{ $receiptUrl }}"'],
        'test' => ['payment PDF in private storage', "->toEndWith('.pdf')"],
    ];

    foreach ($requiredMarkers as $key => $markers) {
        foreach ($markers as $marker) {
            if (! str_contains($sources[$key], $marker)) {
                throw new RuntimeException("Validation {$key} gagal: {$marker}");
            }
        }
    }

    $stamp = date('Ymd-His');

    foreach ($paths as $key => $path) {
        $backup = $path.'.bak-po-paid-pdf-v1-'.$stamp;

        if (! copy($path, $backup)) {
            throw new RuntimeException("Gagal membuat backup {$backup}");
        }

        $backups[$key] = $backup;
    }

    try {
        foreach ($paths as $key => $path) {
            poPaidPdfV1AtomicWrite($path, rtrim($sources[$key]).PHP_EOL);
        }

        foreach (['controller', 'expense_service', 'proof_service', 'test'] as $key) {
            [$lintCode, $lintOutput] = poPaidPdfV1Lint($paths[$key]);

            if ($lintCode !== 0) {
                throw new RuntimeException(
                    "PHP lint gagal {$paths[$key]}:\n{$lintOutput}"
                );
            }
        }
    } catch (Throwable $exception) {
        foreach ($backups as $key => $backup) {
            @copy($backup, $paths[$key]);
        }

        throw $exception;
    }

    echo "[OK] Source upgrade PDF terpasang.\n";
    echo "[OK] Backup source: .bak-po-paid-pdf-v1-{$stamp}\n";
} else {
    echo "[SKIP] Source upgrade PDF sudah terpasang.\n";
}

try {
    require $root.'/vendor/autoload.php';
    $app = require $root.'/bootstrap/app.php';
    $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

    $expenseTable = (new \Webkul\Invoice\Models\Expense())->getTable();

    if (
        ! \Illuminate\Support\Facades\Schema::hasTable('purchase_orders')
        || ! \Illuminate\Support\Facades\Schema::hasTable($expenseTable)
        || ! \Illuminate\Support\Facades\Schema::hasColumn($expenseTable, 'receipt_path')
    ) {
        throw new RuntimeException('Tabel/kolom untuk normalisasi Expense tidak lengkap.');
    }

    $normalized = 0;

    \Illuminate\Support\Facades\DB::transaction(
        function () use ($expenseTable, &$normalized): void {
            $purchaseOrders = \Illuminate\Support\Facades\DB::table('purchase_orders')
                ->where('status', 'paid')
                ->whereNotNull('expense_id')
                ->orderBy('id')
                ->get(['id', 'expense_id']);

            foreach ($purchaseOrders as $purchaseOrder) {
                $receiptPath = route(
                    'admin.purchase-orders.payment-proof',
                    $purchaseOrder->id,
                    absolute: false
                );

                $normalized += \Illuminate\Support\Facades\DB::table($expenseTable)
                    ->where('id', $purchaseOrder->expense_id)
                    ->where(function ($query) use ($receiptPath): void {
                        $query->whereNull('receipt_path')
                            ->orWhere('receipt_path', '!=', $receiptPath);
                    })
                    ->update(['receipt_path' => $receiptPath]);
            }
        }
    );

    echo "[OK] {$normalized} receipt Expense PAID dinormalisasi.\n";
} catch (Throwable $exception) {
    if (! $upgradeInstalled) {
        foreach ($backups as $key => $backup) {
            @copy($backup, $paths[$key]);
        }
    }

    throw new RuntimeException(
        'Normalisasi database gagal; source lama dipulihkan. '.$exception->getMessage(),
        previous: $exception
    );
}

$php = escapeshellarg(PHP_BINARY);
$artisan = escapeshellarg($root.'/artisan');
passthru($php.' '.$artisan.' optimize:clear', $cacheCode);

if ($cacheCode !== 0) {
    echo "[WARN] Upgrade berhasil, tetapi optimize:clear gagal. Jalankan manual.\n";
}

echo "\n[PASS] Upgrade PAID PDF selesai.\n";
echo "- Upload baru pada menu Pay hanya menerima PDF maksimal 10 MB.\n";
echo "- PDF disimpan privat.\n";
echo "- View Receipt / Bon pada Invoice membuka route bukti PO yang benar.\n";
echo "- Bukti gambar lama tetap dapat dibuka sebagai data historis.\n\n";
echo "Jalankan checker:\n";
echo "php tools/check_purchase_order_paid_transfer_proof_v1.php\n";
