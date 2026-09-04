<?php

namespace Webkul\Admin\Http\Controllers\PurchaseOrder;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Core\Support\BusinessUnit;
use Webkul\Core\Traits\PDFHandler;
use Webkul\Invoice\Models\Invoice;
use Webkul\Invoice\Models\PurchaseOrder;
use Webkul\Invoice\Models\PurchaseOrderItem;
use Webkul\Invoice\Services\PurchaseOrderExpenseService;
use Webkul\Invoice\Services\PurchaseOrderNumberService;

class PurchaseOrderController extends Controller
{
    use PDFHandler;

    public function __construct(
        protected PurchaseOrderNumberService $numberService,
        protected PurchaseOrderExpenseService $expenseService
    ) {
    }

    public function index(Request $request): View
    {
        $query = PurchaseOrder::query()
            ->with(['invoice.person', 'items'])
            ->latest('id');

        if ($request->filled('status')) {
            $query->where('status', (string) $request->input('status'));
        }

        if ($request->filled('invoice_id')) {
            $query->where('invoice_id', $request->integer('invoice_id'));
        }

        if ($request->filled('q')) {
            $search = trim((string) $request->input('q'));

            $query->where(function ($builder) use ($search) {
                $like = '%'.$search.'%';

                $builder
                    ->where('po_number', 'like', $like)
                    ->orWhere('vendor_name', 'like', $like)
                    ->orWhere('invoice_number', 'like', $like)
                    ->orWhere('project_code', 'like', $like)
                    ->orWhere('project_name', 'like', $like)
                    ->orWhereHas(
                        'items',
                        fn ($itemQuery) => $itemQuery->where('name', 'like', $like)
                    );
            });
        }

        $purchaseOrders = $query
            ->paginate(20)
            ->withQueryString();

        $statusCounts = PurchaseOrder::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return view(
            'admin::purchase-orders.index',
            compact('purchaseOrders', 'statusCounts')
        );
    }

    public function create(Request $request): View
    {
        $invoices = $this->availableInvoices();
        $selectedInvoice = null;

        if ($request->filled('invoice_id')) {
            $selectedInvoice = Invoice::query()
                ->with(['person', 'user', 'items'])
                ->where('event_status', 'confirm')
                ->find($request->integer('invoice_id'));
        }

        return view(
            'admin::purchase-orders.create',
            compact('invoices', 'selectedInvoice')
        );
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'invoice_id' => ['required', 'integer', 'exists:invoices,id'],
            'vendor_name' => ['required', 'string', 'max:255'],
            'vendor_phone' => ['nullable', 'string', 'max:100'],
            'vendor_email' => ['nullable', 'email', 'max:255'],
            'vendor_address' => ['nullable', 'string', 'max:3000'],
            'order_date' => ['required', 'date'],
            'payment_terms' => [
                'required',
                Rule::in(
                    array_keys(
                        PurchaseOrder::paymentTermsOptions()
                    )
                ),
            ],

            'invoice_items' => ['nullable', 'array'],
            'invoice_items.*.enabled' => ['nullable', 'boolean'],
            'invoice_items.*.quantity' => ['nullable', 'numeric', 'gt:0'],
            'invoice_items.*.unit' => ['nullable', 'string', 'max:30'],
            'invoice_items.*.unit_price' => ['nullable', 'numeric', 'min:0'],

            'custom_items' => ['nullable', 'array'],
            'custom_items.*.name' => ['nullable', 'string', 'max:255'],
            'custom_items.*.description' => ['nullable', 'string', 'max:3000'],
            'custom_items.*.quantity' => ['nullable', 'numeric', 'gt:0'],
            'custom_items.*.unit' => ['nullable', 'string', 'max:30'],
            'custom_items.*.unit_price' => ['nullable', 'numeric', 'min:0'],

            'adjustment_amount' => ['nullable', 'numeric'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $invoice = Invoice::query()
            ->with(['items', 'person'])
            ->findOrFail($validated['invoice_id']);

        if ($invoice->event_status !== 'confirm') {
            throw ValidationException::withMessages([
                'invoice_id' => 'Purchase Order vendor hanya dapat dibuat untuk event/invoice CONFIRM.',
            ]);
        }

        $items = $this->buildCreateItems($invoice, $validated);

        if ($items->isEmpty()) {
            throw ValidationException::withMessages([
                'invoice_items' => 'Pilih minimal satu product invoice atau tambahkan custom vendor service.',
            ]);
        }

        $totals = $this->calculateTotals(
            $items,
            (float) ($validated['adjustment_amount'] ?? 0)
        );

        if ($totals['grand_total'] < 0) {
            throw ValidationException::withMessages([
                'adjustment_amount' => 'Grand Total Purchase Order tidak boleh negatif.',
            ]);
        }

        $user = auth()->guard('user')->user();

        $purchaseOrder = DB::transaction(
            function () use ($invoice, $validated, $items, $totals, $user) {
                $purchaseOrder = PurchaseOrder::create([
                    'po_number' => $this->numberService->generate(),
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'project_code' => $invoice->project_code,
                    'project_name' => $invoice->subject,
                    'business_unit' => $invoice->business_unit,

                    'vendor_name' => trim($validated['vendor_name']),
                    'vendor_phone' => $this->nullableTrim($validated['vendor_phone'] ?? null),
                    'vendor_email' => $this->nullableTrim($validated['vendor_email'] ?? null),
                    'vendor_address' => $this->nullableTrim($validated['vendor_address'] ?? null),

                    'order_date' => $validated['order_date'],
                    'payment_terms' => $validated['payment_terms'],
                    'status' => 'draft',

                    'sub_total' => $totals['sub_total'],
                    /*
                     * Tax intentionally disabled in PO V1.4.
                     * Keep DB column at zero for backward compatibility.
                     */
                    'tax_amount' => 0,
                    'adjustment_amount' => $totals['adjustment_amount'],
                    'grand_total' => $totals['grand_total'],

                    'notes' => $this->nullableTrim($validated['notes'] ?? null),

                    'created_by' => $user?->id,
                    'created_by_name' => $user?->name,
                ]);

                $this->persistItems($purchaseOrder, $items);

                return $purchaseOrder;
            }
        );

        session()->flash(
            'success',
            'Purchase Order '.$purchaseOrder->po_number.' berhasil dibuat sebagai DRAFT.'
        );

        return redirect()->route(
            'admin.purchase-orders.show',
            $purchaseOrder->id
        );
    }

    public function show(int $id): View
    {
        $purchaseOrder = PurchaseOrder::query()
            ->with([
                'invoice.person',
                'invoice.user',
                'invoice.items',
                'items',
            ])
            ->findOrFail($id);

        return view(
            'admin::purchase-orders.show',
            compact('purchaseOrder')
        );
    }

    public function edit(int $id): View
    {
        $purchaseOrder = PurchaseOrder::query()
            ->with(['invoice.person', 'invoice.items', 'items'])
            ->findOrFail($id);

        if (! $purchaseOrder->isDraft()) {
            abort(403, 'Hanya Purchase Order DRAFT yang dapat diedit.');
        }

        return view(
            'admin::purchase-orders.edit',
            compact('purchaseOrder')
        );
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $purchaseOrder = PurchaseOrder::query()
            ->with('items')
            ->findOrFail($id);

        if (! $purchaseOrder->isDraft()) {
            abort(403, 'Hanya Purchase Order DRAFT yang dapat diedit.');
        }

        $validated = $request->validate([
            'vendor_name' => ['required', 'string', 'max:255'],
            'vendor_phone' => ['nullable', 'string', 'max:100'],
            'vendor_email' => ['nullable', 'email', 'max:255'],
            'vendor_address' => ['nullable', 'string', 'max:3000'],
            'order_date' => ['required', 'date'],
            'payment_terms' => [
                'required',
                Rule::in(
                    array_keys(
                        PurchaseOrder::paymentTermsOptions()
                    )
                ),
            ],

            'items' => ['required', 'array', 'min:1'],
            'items.*.invoice_item_id' => ['nullable', 'integer'],
            'items.*.name' => ['required', 'string', 'max:255'],
            'items.*.description' => ['nullable', 'string', 'max:3000'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit' => ['required', 'string', 'max:30'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],

            'adjustment_amount' => ['nullable', 'numeric'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $items = collect($validated['items'])
            ->map(function ($item) use ($purchaseOrder) {
                $invoiceItemId = ! empty($item['invoice_item_id'])
                    ? (int) $item['invoice_item_id']
                    : null;

                if ($invoiceItemId) {
                    $belongsToInvoice = DB::table('invoice_items')
                        ->where('id', $invoiceItemId)
                        ->where('invoice_id', $purchaseOrder->invoice_id)
                        ->exists();

                    if (! $belongsToInvoice) {
                        throw ValidationException::withMessages([
                            'items' => 'Salah satu Invoice Item tidak berasal dari Invoice PO ini.',
                        ]);
                    }
                }

                $quantity = (float) $item['quantity'];
                $unitPrice = (float) $item['unit_price'];

                return [
                    'invoice_item_id' => $invoiceItemId,
                    'name' => trim($item['name']),
                    'description' => $this->nullableTrim($item['description'] ?? null),
                    'quantity' => $quantity,
                    'unit' => trim($item['unit']),
                    'unit_price' => $unitPrice,
                    'total' => $quantity * $unitPrice,
                ];
            });

        $totals = $this->calculateTotals(
            $items,
            (float) ($validated['adjustment_amount'] ?? 0)
        );

        if ($totals['grand_total'] < 0) {
            throw ValidationException::withMessages([
                'adjustment_amount' => 'Grand Total Purchase Order tidak boleh negatif.',
            ]);
        }

        DB::transaction(function () use ($purchaseOrder, $validated, $items, $totals) {
            $purchaseOrder->update([
                'vendor_name' => trim($validated['vendor_name']),
                'vendor_phone' => $this->nullableTrim($validated['vendor_phone'] ?? null),
                'vendor_email' => $this->nullableTrim($validated['vendor_email'] ?? null),
                'vendor_address' => $this->nullableTrim($validated['vendor_address'] ?? null),
                'order_date' => $validated['order_date'],
                'payment_terms' => $validated['payment_terms'],
                'sub_total' => $totals['sub_total'],
                /*
                 * Tax disabled in PO V1.4.
                 */
                'tax_amount' => 0,
                'adjustment_amount' => $totals['adjustment_amount'],
                'grand_total' => $totals['grand_total'],
                'notes' => $this->nullableTrim($validated['notes'] ?? null),
            ]);

            PurchaseOrderItem::query()
                ->where('purchase_order_id', $purchaseOrder->id)
                ->delete();

            $this->persistItems($purchaseOrder, $items);
        });

        session()->flash('success', 'Purchase Order berhasil diperbarui.');

        return redirect()->route(
            'admin.purchase-orders.show',
            $purchaseOrder->id
        );
    }

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

    public function print(int $id): Response|StreamedResponse
    {
        $purchaseOrder = PurchaseOrder::query()
            ->with([
                'invoice.person',
                'invoice.user',
                'invoice.items',
                'items',
            ])
            ->findOrFail($id);

        $businessUnitLabel = $purchaseOrder->business_unit
            ? BusinessUnit::label($purchaseOrder->business_unit)
            : '-';

        return $this->downloadPDF(
            view(
                'admin::purchase-orders.pdf',
                compact('purchaseOrder', 'businessUnitLabel')
            )->render(),
            'Purchase_Order_'.str_replace(
                [' ', '/'],
                '_',
                $purchaseOrder->po_number
            )
        );
    }

    private function availableInvoices(): Collection
    {
        return Invoice::query()
            ->with(['person', 'items'])
            ->where('event_status', 'confirm')
            ->whereNotNull('issued_at')
            ->latest('issued_at')
            ->limit(500)
            ->get();
    }

    private function buildCreateItems(Invoice $invoice, array $validated): Collection
    {
        $items = collect();
        $requestedInvoiceItems = collect($validated['invoice_items'] ?? []);

        $selectedIds = $requestedInvoiceItems
            ->filter(fn ($item) => (bool) ($item['enabled'] ?? false))
            ->keys()
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->values();

        $invoiceItems = $invoice->items->whereIn('id', $selectedIds);

        foreach ($invoiceItems as $invoiceItem) {
            $requestItem = $requestedInvoiceItems->get(
                (string) $invoiceItem->id,
                $requestedInvoiceItems->get($invoiceItem->id, [])
            );

            $quantity = max(
                0.01,
                (float) (
                    $requestItem['quantity']
                    ?? $invoiceItem->quantity
                    ?? 1
                )
            );

            $unitPrice = max(
                0,
                (float) ($requestItem['unit_price'] ?? 0)
            );

            $items->push([
                'invoice_item_id' => $invoiceItem->id,
                'name' => trim((string) $invoiceItem->name),
                'description' => $this->nullableTrim($invoiceItem->description ?? null),
                'quantity' => $quantity,
                'unit' => trim((string) ($requestItem['unit'] ?? 'job')) ?: 'job',
                'unit_price' => $unitPrice,
                'total' => $quantity * $unitPrice,
            ]);
        }

        foreach ($validated['custom_items'] ?? [] as $customItem) {
            $name = trim((string) ($customItem['name'] ?? ''));

            if ($name === '') {
                continue;
            }

            $quantity = max(
                0.01,
                (float) ($customItem['quantity'] ?? 1)
            );

            $unitPrice = max(
                0,
                (float) ($customItem['unit_price'] ?? 0)
            );

            $items->push([
                'invoice_item_id' => null,
                'name' => $name,
                'description' => $this->nullableTrim($customItem['description'] ?? null),
                'quantity' => $quantity,
                'unit' => trim((string) ($customItem['unit'] ?? 'job')) ?: 'job',
                'unit_price' => $unitPrice,
                'total' => $quantity * $unitPrice,
            ]);
        }

        return $items;
    }

    private function calculateTotals(
        Collection $items,
        float $adjustmentAmount
    ): array {
        $subTotal = (float) $items->sum(
            fn ($item) => (float) $item['total']
        );

        return [
            'sub_total' => $subTotal,
            'tax_amount' => 0.0,
            'adjustment_amount' => $adjustmentAmount,
            'grand_total' => $subTotal + $adjustmentAmount,
        ];
    }

    private function persistItems(
        PurchaseOrder $purchaseOrder,
        Collection $items
    ): void {
        foreach ($items->values() as $index => $item) {
            PurchaseOrderItem::create([
                'purchase_order_id' => $purchaseOrder->id,
                'invoice_item_id' => $item['invoice_item_id'] ?? null,
                'name' => $item['name'],
                'description' => $item['description'] ?? null,
                'quantity' => $item['quantity'],
                'unit' => $item['unit'],
                'unit_price' => $item['unit_price'],
                'total' => $item['total'],
                'sort_order' => $index + 1,
            ]);
        }
    }

    private function nullableTrim(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value !== '' ? $value : null;
    }
}
