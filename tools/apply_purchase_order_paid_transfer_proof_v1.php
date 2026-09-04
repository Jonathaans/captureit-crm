<?php

declare(strict_types=1);

/**
 * PURCHASE ORDER PAID + COMPRESSED TRANSFER PROOF V1
 *
 * Run from project root:
 * php tools/apply_purchase_order_paid_transfer_proof_v1.php
 */

$root = dirname(__DIR__);

$paths = [
    'controller' => $root.'/packages/Webkul/Admin/src/Http/Controllers/PurchaseOrder/PurchaseOrderController.php',
    'model' => $root.'/packages/Webkul/Invoice/src/Models/PurchaseOrder.php',
    'expense_service' => $root.'/packages/Webkul/Invoice/src/Services/PurchaseOrderExpenseService.php',
    'proof_service' => $root.'/packages/Webkul/Invoice/src/Services/PurchaseOrderPaymentProofService.php',
    'routes' => $root.'/packages/Webkul/Admin/src/Routes/Admin/invoice-routes.php',
    'acl' => $root.'/packages/Webkul/Admin/src/Config/acl.php',
    'show' => $root.'/packages/Webkul/Admin/src/Resources/views/purchase-orders/show.blade.php',
    'index' => $root.'/packages/Webkul/Admin/src/Resources/views/purchase-orders/index.blade.php',
    'export' => $root.'/packages/Webkul/Admin/src/Http/Controllers/PurchaseOrder/PurchaseOrderExpenseExportController.php',
    'invoice_controller' => $root.'/packages/Webkul/Admin/src/Http/Controllers/Invoice/InvoiceController.php',
    'migration' => $root.'/database/migrations/2026_09_03_160000_add_paid_status_and_payment_proof_to_purchase_orders_table.php',
    'test' => $root.'/tests/Unit/PurchaseOrderPaymentProofServiceTest.php',
];

function poPaidV1Fail(string $message): never
{
    fwrite(STDERR, $message.PHP_EOL);
    exit(1);
}

function poPaidV1Read(string $path): string
{
    $contents = file_get_contents($path);

    if ($contents === false) {
        throw new RuntimeException("Gagal membaca {$path}");
    }

    return $contents;
}

function poPaidV1AtomicWrite(string $path, string $contents): void
{
    $temporary = $path.'.tmp-'.bin2hex(random_bytes(4));

    if (file_put_contents($temporary, $contents) === false) {
        @unlink($temporary);
        throw new RuntimeException("Gagal menulis temporary file {$temporary}");
    }

    if (! rename($temporary, $path)) {
        @unlink($temporary);
        throw new RuntimeException("Gagal mengganti {$path}");
    }
}

function poPaidV1ReplaceOnce(
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

/**
 * Returns the byte range of a public method, including its directly attached
 * docblock. Strings and comments are ignored while balancing braces.
 */
function poPaidV1MethodRange(string $source, string $method): array
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

function poPaidV1ReplaceMethod(
    string $source,
    string $method,
    string $replacement
): string {
    [$start, $end] = poPaidV1MethodRange($source, $method);

    return substr_replace($source, rtrim($replacement), $start, $end - $start);
}

/**
 * Removes one simple Blade @if ... @endif block by a unique inner marker.
 * This avoids depending on indentation or CRLF/LF line endings in customized
 * Purchase Order views.
 */
function poPaidV1RemoveBladeBlockContaining(
    string $source,
    array $markers,
    string $label
): string {
    $marker = null;
    $markerPosition = null;

    foreach ($markers as $candidate) {
        if (substr_count($source, $candidate) === 1) {
            $marker = $candidate;
            $markerPosition = strpos($source, $candidate);
            break;
        }
    }

    if (! is_string($marker) || $markerPosition === false || $markerPosition === null) {
        throw new RuntimeException(
            "Preflight {$label} gagal: marker blok tidak ditemukan secara unik."
        );
    }

    $beforeMarker = substr($source, 0, $markerPosition);
    $ifPosition = strrpos($beforeMarker, '@if');
    $endifPosition = strpos($source, '@endif', $markerPosition);

    if ($ifPosition === false || $endifPosition === false) {
        throw new RuntimeException(
            "Preflight {$label} gagal: batas @if/@endif tidak ditemukan."
        );
    }

    $block = substr(
        $source,
        $ifPosition,
        ($endifPosition + strlen('@endif')) - $ifPosition
    );

    if (
        substr_count($block, '@if') !== 1
        || substr_count($block, '@endif') !== 1
        || ! str_contains($block, $marker)
    ) {
        throw new RuntimeException(
            "Preflight {$label} gagal: struktur blok Blade tidak aman untuk diubah."
        );
    }

    $lineStart = strrpos(substr($source, 0, $ifPosition), "\n");
    $removeStart = $lineStart === false ? 0 : $lineStart + 1;
    $afterEndif = $endifPosition + strlen('@endif');
    $lineEnd = strpos($source, "\n", $afterEndif);
    $removeEnd = $lineEnd === false ? strlen($source) : $lineEnd + 1;

    return substr_replace(
        $source,
        '',
        $removeStart,
        $removeEnd - $removeStart
    );
}

function poPaidV1Lint(string $path): array
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
        poPaidV1Fail('PATCH GAGAL: '.$exception->getMessage());
    }
);

echo "PURCHASE ORDER PAID + TRANSFER PROOF V1.1\n";
echo "========================================\n\n";

foreach ($paths as $label => $path) {
    if (! is_file($path)) {
        poPaidV1Fail("File {$label} tidak ditemukan: {$path}");
    }
}

if (! extension_loaded('gd')) {
    poPaidV1Fail(
        'PHP GD belum aktif. Aktifkan extension=gd pada php.ini, restart web server, lalu jalankan installer lagi.'
    );
}

$sourceKeys = [
    'controller',
    'model',
    'expense_service',
    'routes',
    'acl',
    'show',
    'index',
    'export',
    'invoice_controller',
];

$sources = [];

foreach ($sourceKeys as $key) {
    $sources[$key] = poPaidV1Read($paths[$key]);
}

$alreadyInstalled = str_contains(
    $sources['controller'],
    'PURCHASE ORDER PAID WORKFLOW V1'
);
$backups = [];

if (! $alreadyInstalled) {
    foreach (['release', 'complete', 'cancel'] as $method) {
        poPaidV1MethodRange($sources['controller'], $method);
    }

    if (! str_contains($sources['expense_service'], 'createForReleasedPurchaseOrder')) {
        throw new RuntimeException(
            'Preflight gagal: PurchaseOrderExpenseService baseline tidak dikenali.'
        );
    }

    $releaseMethod = <<<'PHP'
    /**
     * PURCHASE ORDER PAID WORKFLOW V1
     *
     * DRAFT -> RELEASED only. Releasing a PO does not create an Expense.
     */
    public function release(int $id): RedirectResponse
    {
        $user = auth()->guard('user')->user();

        DB::transaction(function () use ($id, $user) {
            $purchaseOrder = PurchaseOrder::query()
                ->lockForUpdate()
                ->findOrFail($id);

            if (in_array($purchaseOrder->status, ['released', 'paid', 'completed'], true)) {
                return;
            }

            if (! $purchaseOrder->isDraft()) {
                throw ValidationException::withMessages([
                    'status' => 'Hanya Purchase Order DRAFT yang dapat di-Release.',
                ]);
            }

            if ((float) $purchaseOrder->grand_total <= 0) {
                throw ValidationException::withMessages([
                    'grand_total' => 'Grand Total PO harus lebih besar dari 0 sebelum Release.',
                ]);
            }

            if ($purchaseOrder->expense_id) {
                throw ValidationException::withMessages([
                    'status' => 'PO DRAFT ini sudah memiliki Expense. Audit data terlebih dahulu sebelum Release.',
                ]);
            }

            $purchaseOrder->update([
                'status' => 'released',
                'released_by' => $user?->id,
                'released_by_name' => $user?->name,
                'released_at' => now(),
            ]);
        });

        session()->flash(
            'success',
            'Purchase Order berhasil RELEASED dan sedang menunggu pembayaran. Expense belum dibuat.'
        );

        return redirect()->route('admin.purchase-orders.show', $id);
    }
PHP;

    $completeMethod = <<<'PHP'
    /**
     * COMPLETED is retained only for legacy route compatibility.
     * New workflow ends at PAID.
     */
    public function complete(int $id): RedirectResponse
    {
        throw ValidationException::withMessages([
            'status' => 'Status COMPLETED sudah digantikan oleh PAID. Upload bukti transfer dari halaman PO.',
        ]);
    }
PHP;

    $cancelMethod = <<<'PHP'
    public function cancel(int $id): RedirectResponse
    {
        $user = auth()->guard('user')->user();

        DB::transaction(function () use ($id, $user) {
            $purchaseOrder = PurchaseOrder::query()
                ->lockForUpdate()
                ->findOrFail($id);

            if ($purchaseOrder->isCancelled()) {
                return;
            }

            if ($purchaseOrder->isPaid() || $purchaseOrder->isCompleted()) {
                throw ValidationException::withMessages([
                    'status' => 'PO PAID bersifat final dan tidak dapat dibatalkan. Buat koreksi finansial terpisah jika diperlukan.',
                ]);
            }

            if (! in_array($purchaseOrder->status, ['draft', 'released'], true)) {
                throw ValidationException::withMessages([
                    'status' => 'Hanya PO DRAFT atau RELEASED yang dapat dibatalkan.',
                ]);
            }

            /* Remove only a legacy Expense that was posted by the old release flow. */
            $this->expenseService->removeForCancelledPurchaseOrder($purchaseOrder);

            if ($purchaseOrder->payment_proof_path) {
                app(\Webkul\Invoice\Services\PurchaseOrderPaymentProofService::class)
                    ->delete($purchaseOrder->payment_proof_path);
            }

            $purchaseOrder->update([
                'status' => 'cancelled',
                'expense_id' => null,
                'payment_proof_path' => null,
                'paid_by' => null,
                'paid_by_name' => null,
                'paid_at' => null,
                'cancelled_by' => $user?->id,
                'cancelled_by_name' => $user?->name,
                'cancelled_at' => now(),
            ]);
        });

        session()->flash('success', 'Purchase Order berhasil dibatalkan.');

        return redirect()->route('admin.purchase-orders.show', $id);
    }
PHP;

    $paidMethods = <<<'PHP'

    /**
     * RELEASED -> PAID. A compressed transfer-proof image is mandatory and
     * Expense is created exactly once inside the same database transaction.
     */
    public function paid(Request $request, int $id): RedirectResponse
    {
        $request->validate([
            'payment_proof' => [
                'required',
                'file',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:10240',
            ],
        ], [
            'payment_proof.required' => 'Gambar bukti transfer wajib diunggah.',
            'payment_proof.image' => 'Bukti transfer harus berupa gambar.',
            'payment_proof.mimes' => 'Format bukti transfer harus JPG, PNG, atau WebP.',
            'payment_proof.max' => 'Ukuran bukti transfer maksimal 10 MB.',
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
                        'payment_proof' => 'Gambar bukti transfer wajib diunggah.',
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
                : 'Purchase Order berhasil PAID. Bukti transfer telah dikompres dan Expense dibuat.'
        );

        return redirect()->route('admin.purchase-orders.show', $id);
    }

    public function paymentProof(
        int $id
    ): \Symfony\Component\HttpFoundation\BinaryFileResponse {
        $purchaseOrder = PurchaseOrder::query()->findOrFail($id);

        abort_if(
            ! $purchaseOrder->payment_proof_path,
            404,
            'Bukti transfer tidak ditemukan.'
        );

        $absolutePath = app(
            \Webkul\Invoice\Services\PurchaseOrderPaymentProofService::class
        )->absolutePath($purchaseOrder->payment_proof_path);

        $safeNumber = preg_replace('/[^A-Za-z0-9_-]+/', '-', $purchaseOrder->po_number);

        return response()->file($absolutePath, [
            'Content-Type' => 'image/jpeg',
            'Content-Disposition' => 'inline; filename="bukti-transfer-'.$safeNumber.'.jpg"',
            'Cache-Control' => 'private, no-store, no-cache, must-revalidate',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
PHP;

    $sources['controller'] = poPaidV1ReplaceMethod(
        $sources['controller'],
        'release',
        $releaseMethod
    );
    $sources['controller'] = poPaidV1ReplaceMethod(
        $sources['controller'],
        'complete',
        $completeMethod
    );
    $sources['controller'] = poPaidV1ReplaceMethod(
        $sources['controller'],
        'cancel',
        $cancelMethod
    );
    [$completeStart] = poPaidV1MethodRange($sources['controller'], 'complete');
    $sources['controller'] = substr_replace(
        $sources['controller'],
        rtrim($paidMethods).PHP_EOL.PHP_EOL,
        $completeStart,
        0
    );

    $sources['model'] = poPaidV1ReplaceOnce(
        $sources['model'],
        "        'released_at',\n",
        "        'released_at',\n"
            ."        // PURCHASE ORDER PAID WORKFLOW V1\n"
            ."        'payment_proof_path',\n"
            ."        'paid_by',\n"
            ."        'paid_by_name',\n"
            ."        'paid_at',\n",
        'model fillable'
    );
    $sources['model'] = poPaidV1ReplaceOnce(
        $sources['model'],
        "        'released_at' => 'datetime',\n",
        "        'released_at' => 'datetime',\n        'paid_at' => 'datetime',\n",
        'model cast paid_at'
    );
    $sources['model'] = poPaidV1ReplaceOnce(
        $sources['model'],
        "    public function isCompleted(): bool\n",
        "    public function isPaid(): bool\n"
            ."    {\n"
            ."        return \$this->status === 'paid';\n"
            ."    }\n\n"
            ."    public function isCompleted(): bool\n",
        'model isPaid'
    );

    $paidExpenseMethod = <<<'PHP'
    /**
     * PURCHASE ORDER PAID WORKFLOW V1
     *
     * PAID PO -> exactly one Expense on the related Invoice.
     */
    public function createForPaidPurchaseOrder(
        PurchaseOrder $purchaseOrder,
        ?int $userId,
        ?string $userName
    ): int {
        if (! $purchaseOrder->isPaid()) {
            throw new RuntimeException('Expense PO hanya dapat dibuat ketika status PAID.');
        }

        $expenseTable = (new Expense())->getTable();
        $columns = Schema::getColumnListing($expenseTable);

        foreach (['invoice_id', 'category', 'amount', 'expense_date'] as $requiredColumn) {
            if (! in_array($requiredColumn, $columns, true)) {
                throw new RuntimeException(
                    sprintf(
                        'Expense table %s tidak memiliki kolom wajib %s.',
                        $expenseTable,
                        $requiredColumn
                    )
                );
            }
        }

        $receiptUrl = route(
            'admin.purchase-orders.payment-proof',
            $purchaseOrder->id
        );

        if (
            $purchaseOrder->expense_id
            && DB::table($expenseTable)
                ->where('id', $purchaseOrder->expense_id)
                ->exists()
        ) {
            $updates = [];

            if (in_array('receipt_path', $columns, true)) {
                $updates['receipt_path'] = $receiptUrl;
            }

            if (in_array('updated_at', $columns, true)) {
                $updates['updated_at'] = now();
            }

            if ($updates !== []) {
                DB::table($expenseTable)
                    ->where('id', $purchaseOrder->expense_id)
                    ->update($updates);
            }

            return (int) $purchaseOrder->expense_id;
        }

        $description = sprintf(
            '%s - Vendor %s - %s',
            $purchaseOrder->po_number,
            $purchaseOrder->vendor_name,
            $purchaseOrder->project_name ?: $purchaseOrder->invoice_number
        );

        $payload = [
            'invoice_id' => $purchaseOrder->invoice_id,
            'category' => $this->resolveCategory($expenseTable),
            'amount' => (float) $purchaseOrder->grand_total,
            'expense_date' => ($purchaseOrder->paid_at ?? now())->toDateString(),
        ];

        $optionalValues = [
            'description' => $description,
            'notes' => $description,
            'receipt_path' => $receiptUrl,
            'reference_type' => 'purchase_order',
            'reference_id' => $purchaseOrder->id,
            'reference_number' => $purchaseOrder->po_number,
            'purchase_order_id' => $purchaseOrder->id,
            'user_id' => $userId,
            'created_by' => $userId,
            'created_by_name' => $userName,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        foreach ($optionalValues as $column => $value) {
            if (in_array($column, $columns, true)) {
                $payload[$column] = $value;
            }
        }

        return (int) DB::table($expenseTable)->insertGetId($payload);
    }

    /**
     * Old release posting entry point is intentionally blocked.
     */
    public function createForReleasedPurchaseOrder(
        PurchaseOrder $purchaseOrder,
        ?int $userId,
        ?string $userName
    ): int {
        throw new RuntimeException(
            'Posting Expense saat RELEASED sudah dinonaktifkan. Gunakan workflow PAID.'
        );
    }
PHP;

    $sources['expense_service'] = poPaidV1ReplaceMethod(
        $sources['expense_service'],
        'createForReleasedPurchaseOrder',
        $paidExpenseMethod
    );

    $routeAnchor = <<<'PHP'
        Route::post('{id}/complete', 'complete')
            ->name('admin.purchase-orders.complete');
PHP;
    $routePaid = <<<'PHP'
        /* PURCHASE ORDER PAID WORKFLOW V1 ROUTES */
        Route::post('{id}/paid', 'paid')
            ->name('admin.purchase-orders.paid');

        Route::get('{id}/payment-proof', 'paymentProof')
            ->name('admin.purchase-orders.payment-proof');

        Route::post('{id}/complete', 'complete')
            ->name('admin.purchase-orders.complete');
PHP;
    $sources['routes'] = poPaidV1ReplaceOnce(
        $sources['routes'],
        $routeAnchor,
        $routePaid,
        'route paid'
    );

    $viewAclOld = <<<'PHP'
        'key'   => 'purchase-orders.view',
        'name'  => 'View Purchase Order',
        'route' => 'admin.purchase-orders.show',
        'sort'  => 3,
PHP;
    $viewAclNew = <<<'PHP'
        'key'   => 'purchase-orders.view',
        'name'  => 'View Purchase Order',
        'route' => [
            'admin.purchase-orders.show',
            'admin.purchase-orders.payment-proof',
        ],
        'sort'  => 3,
PHP;
    $sources['acl'] = poPaidV1ReplaceOnce(
        $sources['acl'],
        $viewAclOld,
        $viewAclNew,
        'ACL payment proof view'
    );

    $completeAclAnchor = <<<'PHP'
    ], [
        'key'   => 'purchase-orders.complete',
PHP;
    $paidAcl = <<<'PHP'
    ], [
        /* PURCHASE ORDER PAID WORKFLOW V1 ACL */
        'key'   => 'purchase-orders.paid',
        'name'  => 'Mark Purchase Order Paid',
        'route' => 'admin.purchase-orders.paid',
        'sort'  => 5,
    ], [
        'key'   => 'purchase-orders.complete',
PHP;
    $sources['acl'] = poPaidV1ReplaceOnce(
        $sources['acl'],
        $completeAclAnchor,
        $paidAcl,
        'ACL paid'
    );

    $sources['show'] = poPaidV1ReplaceOnce(
        $sources['show'],
        "            'completed' => ['COMPLETED', '#dcfce7', '#15803d'],\n",
        "            'paid' => ['PAID', '#dcfce7', '#15803d'],\n"
            ."            'completed' => ['COMPLETED (LEGACY)', '#f3f4f6', '#4b5563'],\n",
        'show badge paid'
    );
    $sources['show'] = str_replace(
        'Release PO ini? Grand Total akan langsung menjadi Expense pada Invoice terkait.',
        'Release PO ini? Status menjadi RELEASED dan belum membuat Expense.',
        $sources['show'],
        $releaseConfirmCount
    );

    if ($releaseConfirmCount !== 1) {
        throw new RuntimeException('Preflight show release confirmation gagal.');
    }

    $sources['show'] = poPaidV1RemoveBladeBlockContaining(
        $sources['show'],
        [
            'Mark Completed',
            "route('admin.purchase-orders.complete'",
            'admin.purchase-orders.complete',
        ],
        'remove Mark Completed UI'
    );

    $sources['show'] = str_replace(
        'Cancel PO ini? Jika sudah RELEASED, Expense milik PO ini juga akan dihapus.',
        'Cancel PO ini? PO PAID tidak dapat dibatalkan.',
        $sources['show']
    );

    $paymentForm = <<<'BLADE'
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

    $statusAnchor = "        @if (\$purchaseOrder->status === 'draft')\n";
    $sources['show'] = poPaidV1ReplaceOnce(
        $sources['show'],
        $statusAnchor,
        rtrim($paymentForm)."\n\n".$statusAnchor,
        'show payment form'
    );

    $oldStatusNotice = <<<'BLADE'
        @if ($purchaseOrder->status === 'draft')
            <section class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
                <strong>DRAFT:</strong> PO belum memengaruhi Expense maupun Financial Report. Nominal baru diposting ketika Finance menekan Release PO.
            </section>
        @elseif ($purchaseOrder->expense_id)
            <section class="rounded-xl border border-green-200 bg-green-50 p-4 text-sm text-green-800">
                <strong>EXPENSE POSTED:</strong>
                {{ $money($purchaseOrder->grand_total) }}
                sudah menjadi Expense Invoice
                <strong>{{ $purchaseOrder->invoice_number }}</strong>
                dengan Expense ID #{{ $purchaseOrder->expense_id }}.
            </section>
        @endif
BLADE;
    $newStatusNotice = <<<'BLADE'
        @if ($purchaseOrder->status === 'draft')
            <section class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
                <strong>DRAFT:</strong> PO masih dapat diedit dan belum memengaruhi Expense.
            </section>
        @elseif ($purchaseOrder->status === 'released')
            <section class="rounded-xl border border-blue-200 bg-blue-50 p-4 text-sm text-blue-800">
                <strong>RELEASED:</strong> PO sudah dirilis dan sedang menunggu pembayaran. Expense belum dibuat.
            </section>
        @elseif ($purchaseOrder->status === 'paid' && $purchaseOrder->expense_id)
            <section class="rounded-xl border border-green-200 bg-green-50 p-4 text-sm text-green-800">
                <strong>PAID / EXPENSE POSTED:</strong>
                {{ $money($purchaseOrder->grand_total) }}
                sudah menjadi Expense Invoice
                <strong>{{ $purchaseOrder->invoice_number }}</strong>
                dengan Expense ID #{{ $purchaseOrder->expense_id }}.
            </section>
        @elseif ($purchaseOrder->status === 'completed' && $purchaseOrder->expense_id)
            <section class="rounded-xl border border-gray-200 bg-gray-50 p-4 text-sm text-gray-700">
                <strong>LEGACY COMPLETED:</strong> PO lama ini memiliki Expense ID #{{ $purchaseOrder->expense_id }}.
            </section>
        @endif

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
    $sources['show'] = poPaidV1ReplaceOnce(
        $sources['show'],
        $oldStatusNotice,
        $newStatusNotice,
        'show status notices'
    );
    $sources['show'] = poPaidV1ReplaceOnce(
        $sources['show'],
        '                <div><span class="text-gray-500">Completed At</span><strong class="ml-2">{{ $purchaseOrder->completed_at?->format(\'d M Y H:i\') ?: \'-\' }}</strong></div>',
        '                <div><span class="text-gray-500">Paid By</span><strong class="ml-2">{{ $purchaseOrder->paid_by_name ?: \'-\' }}</strong></div>'.PHP_EOL
            .'                <div><span class="text-gray-500">Paid At</span><strong class="ml-2">{{ $purchaseOrder->paid_at?->format(\'d M Y H:i\') ?: \'-\' }}</strong></div>'.PHP_EOL
            .'                @if ($purchaseOrder->completed_at)'.PHP_EOL
            .'                    <div><span class="text-gray-500">Legacy Completed At</span><strong class="ml-2">{{ $purchaseOrder->completed_at?->format(\'d M Y H:i\') }}</strong></div>'.PHP_EOL
            .'                @endif',
        'show paid audit'
    );

    $sources['index'] = poPaidV1ReplaceOnce(
        $sources['index'],
        "                'completed' => ['COMPLETED', '#dcfce7', '#15803d'],\n",
        "                'paid' => ['PAID', '#dcfce7', '#15803d'],\n"
            ."                'completed' => ['COMPLETED (LEGACY)', '#f3f4f6', '#4b5563'],\n",
        'index badge paid'
    );
    $sources['index'] = poPaidV1ReplaceOnce(
        $sources['index'],
        'Expense baru diposting ketika PO di-Release.',
        'Expense baru diposting setelah admin menandai PO sebagai PAID dan mengunggah bukti transfer.',
        'index description'
    );
    $sources['index'] = poPaidV1ReplaceOnce(
        $sources['index'],
        "@foreach (['draft' => 'Draft', 'released' => 'Released', 'completed' => 'Completed', 'cancelled' => 'Cancelled'] as \$status => \$label)",
        "@foreach (['draft' => 'Draft', 'released' => 'Released', 'paid' => 'Paid', 'cancelled' => 'Cancelled'] as \$status => \$label)",
        'index status cards'
    );
    $sources['index'] = poPaidV1ReplaceOnce(
        $sources['index'],
        "                        <option value=\"completed\" @selected(request('status') === 'completed')>Completed</option>\n",
        "                        <option value=\"paid\" @selected(request('status') === 'paid')>Paid</option>\n",
        'index status filter'
    );
    $indexPrintAnchor = "                                            @if (bouncer()->hasPermission('purchase-orders.print'))\n";
    $indexPayAction = <<<'BLADE'
                                            @if ($purchaseOrder->status === 'released' && bouncer()->hasPermission('purchase-orders.paid'))
                                                <a href="{{ route('admin.purchase-orders.show', $purchaseOrder->id) }}#po-payment" class="primary-button">Pay</a>
                                            @endif

BLADE;
    $sources['index'] = poPaidV1ReplaceOnce(
        $sources['index'],
        $indexPrintAnchor,
        $indexPayAction.$indexPrintAnchor,
        'index paid action'
    );

    $defaultScopeOld = <<<'PHP'
            $query->whereIn(
                'status',
                [
                    'released',
                    'completed',
                ]
            );
PHP;
    $defaultScopeNew = <<<'PHP'
            /* PURCHASE ORDER PAID WORKFLOW V1 EXPORT SCOPE */
            $query->where('status', 'paid');
PHP;
    $sources['export'] = poPaidV1ReplaceOnce(
        $sources['export'],
        $defaultScopeOld,
        $defaultScopeNew,
        'PO Expense export default scope'
    );
    $sources['export'] = str_replace(
        "     * - RELEASED\n     * - COMPLETED",
        "     * - PAID only",
        $sources['export']
    );
    $sources['export'] = str_replace(
        ": 'RELEASED + COMPLETED',",
        ": 'PAID',",
        $sources['export']
    );
    $sources['export'] = poPaidV1ReplaceOnce(
        $sources['export'],
        "                'draft',\n                'released',\n                'completed',\n                'cancelled',",
        "                'draft',\n                'released',\n                'paid',\n                'completed',\n                'cancelled',",
        'PO Expense export paid status'
    );

    $exportHeaderOld = <<<'PHP'
                        'Released At',
                        'Completed At',
PHP;
    $exportHeaderNew = <<<'PHP'
                        'Released At',
                        'Paid At',
                        'Payment Proof',
PHP;
    $sources['export'] = poPaidV1ReplaceOnce(
        $sources['export'],
        $exportHeaderOld,
        $exportHeaderNew,
        'PO Expense export paid headers'
    );

    $exportDatesOld = <<<'PHP'
                            $purchaseOrder
                                ->released_at
                                ?->format(
                                    'Y-m-d H:i:s'
                                ),

                            $purchaseOrder
                                ->completed_at
                                ?->format(
                                    'Y-m-d H:i:s'
                                ),
PHP;
    $exportDatesNew = <<<'PHP'
                            $purchaseOrder
                                ->released_at
                                ?->format(
                                    'Y-m-d H:i:s'
                                ),

                            $purchaseOrder
                                ->paid_at
                                ?->format(
                                    'Y-m-d H:i:s'
                                ),

                            $purchaseOrder
                                ->payment_proof_path
                                ? route(
                                    'admin.purchase-orders.payment-proof',
                                    $purchaseOrder->id
                                )
                                : '',
PHP;
    $sources['export'] = poPaidV1ReplaceOnce(
        $sources['export'],
        $exportDatesOld,
        $exportDatesNew,
        'PO Expense export paid values'
    );

    $guardStatuses = <<<'PHP'
                        'released',
                        'completed',
PHP;
    $guardStatusesNew = <<<'PHP'
                        'paid',
                        'released', // legacy rows posted by the old flow
                        'completed', // legacy rows
PHP;
    $guardCount = substr_count($sources['invoice_controller'], $guardStatuses);

    if ($guardCount !== 2) {
        throw new RuntimeException(
            "Preflight Invoice Expense guard gagal: expected 2 blocks, found {$guardCount}."
        );
    }

    $sources['invoice_controller'] = str_replace(
        $guardStatuses,
        $guardStatusesNew,
        $sources['invoice_controller']
    );
    $sources['invoice_controller'] = str_replace(
        'Expense dari RELEASED/COMPLETED PO dikunci',
        'Expense dari PAID/legacy PO dikunci',
        $sources['invoice_controller']
    );

    $requiredMarkers = [
        'controller' => [
            'PURCHASE ORDER PAID WORKFLOW V1',
            'function paid(',
            "'payment_proof'",
            'createForPaidPurchaseOrder',
            'function paymentProof(',
        ],
        'model' => ["'payment_proof_path'", "'paid_at' => 'datetime'", 'function isPaid('],
        'expense_service' => ['createForPaidPurchaseOrder', "'receipt_path' => \$receiptUrl"],
        'routes' => ['admin.purchase-orders.paid', 'admin.purchase-orders.payment-proof'],
        'acl' => ['purchase-orders.paid', 'admin.purchase-orders.payment-proof'],
        'show' => ['PURCHASE ORDER PAID WORKFLOW V1 FORM', 'name="payment_proof"', 'Mark as PAID'],
        'index' => ["'paid' => ['PAID'", "value=\"paid\""],
        'export' => [
            'PURCHASE ORDER PAID WORKFLOW V1 EXPORT SCOPE',
            "where('status', 'paid')",
            "'Payment Proof'",
            '->paid_at',
        ],
        'invoice_controller' => ["'paid',", 'Expense dari PAID/legacy PO dikunci'],
    ];

    foreach ($requiredMarkers as $key => $markers) {
        foreach ($markers as $marker) {
            if (! str_contains($sources[$key], $marker)) {
                throw new RuntimeException("Validation {$key} gagal: {$marker}");
            }
        }
    }

    $stamp = date('Ymd-His');
    foreach ($sourceKeys as $key) {
        $backup = $paths[$key].'.bak-po-paid-v1-'.$stamp;

        if (! copy($paths[$key], $backup)) {
            throw new RuntimeException("Gagal membuat backup {$backup}");
        }

        $backups[$key] = $backup;
    }

    try {
        foreach ($sourceKeys as $key) {
            poPaidV1AtomicWrite($paths[$key], rtrim($sources[$key]).PHP_EOL);
        }

        $phpFiles = [
            $paths['controller'],
            $paths['model'],
            $paths['expense_service'],
            $paths['proof_service'],
            $paths['routes'],
            $paths['acl'],
            $paths['export'],
            $paths['invoice_controller'],
            $paths['migration'],
            $paths['test'],
        ];

        foreach ($phpFiles as $path) {
            [$lintCode, $lintOutput] = poPaidV1Lint($path);

            if ($lintCode !== 0) {
                throw new RuntimeException("PHP lint gagal {$path}:\n{$lintOutput}");
            }
        }
    } catch (Throwable $exception) {
        foreach ($backups as $key => $backup) {
            @copy($backup, $paths[$key]);
        }

        throw $exception;
    }

    echo "[OK] Source workflow PAID terpasang.\n";
    echo "[OK] Backup source dibuat dengan suffix .bak-po-paid-v1-{$stamp}\n";
} else {
    echo "[SKIP] Source workflow PAID V1 sudah terpasang.\n";
}

$php = escapeshellarg(PHP_BINARY);
$artisan = escapeshellarg($root.'/artisan');
$migrationPath = 'database/migrations/2026_09_03_160000_add_paid_status_and_payment_proof_to_purchase_orders_table.php';

passthru(
    $php.' '.$artisan.' migrate --path='.escapeshellarg($migrationPath).' --force',
    $migrationCode
);

if ($migrationCode !== 0) {
    if (! $alreadyInstalled) {
        foreach ($backups as $key => $backup) {
            @copy($backup, $paths[$key]);
        }
    }

    poPaidV1Fail(
        'Migration PAID gagal. Source lama dipulihkan; perbaiki pesan database di atas lalu jalankan installer kembali.'
    );
}

passthru($php.' '.$artisan.' optimize:clear', $cacheCode);

if ($cacheCode !== 0) {
    echo "[WARN] Source dan migration berhasil, tetapi optimize:clear gagal. Jalankan manual.\n";
}

echo "\n[PASS] Purchase Order PAID workflow siap.\n";
echo "- RELEASED tidak lagi membuat Expense.\n";
echo "- PAID wajib gambar bukti transfer.\n";
echo "- Gambar disimpan privat, dikompres ke JPEG quality 78, maksimum 2000 px.\n";
echo "- PAID membuat tepat satu Expense dan menjadi status final.\n\n";
echo "Jalankan checker:\n";
echo "php tools/check_purchase_order_paid_transfer_proof_v1.php\n";
echo "\nJalankan test opsional:\n";
echo "php artisan test --compact tests/Unit/PurchaseOrderPaymentProofServiceTest.php\n";
