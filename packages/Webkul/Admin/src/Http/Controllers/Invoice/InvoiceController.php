<?php

namespace Webkul\Admin\Http\Controllers\Invoice;

use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Core\Traits\PDFHandler;
use Webkul\Invoice\Models\Expense;
use Webkul\Invoice\Models\Invoice;
use Webkul\Invoice\Services\ExpenseService;
use Webkul\Invoice\Services\InvoiceService;
use Webkul\Invoice\Services\PaymentService;
use Webkul\Quote\Models\Quote;

class InvoiceController extends Controller
{
    use PDFHandler;

    /**
     * Constructor.
     */
    public function __construct(
        protected InvoiceService $invoiceService,
        protected PaymentService $paymentService,
        protected ExpenseService $expenseService
    ) {
    }

    /**
     * Display invoice list + financial overview + filters.
     */
    public function index(Request $request): View
    {
        $fromDate = $request->input('from_date');
        $toDate = $request->input('to_date');
        $status = $request->input('status');

        /**
         * Base invoice query.
         */
        $baseQuery = Invoice::query()
            ->when($fromDate, function ($query) use ($fromDate) {
                $query->whereDate('issued_at', '>=', $fromDate);
            })
            ->when($toDate, function ($query) use ($toDate) {
                $query->whereDate('issued_at', '<=', $toDate);
            })
            ->when($status, function ($query) use ($status) {
                $query->where('status', $status);
            });

        /**
         * Invoice IDs sesuai filter.
         *
         * Digunakan agar expense summary mengikuti
         * invoice yang sedang difilter.
         */
        $filteredInvoiceIds = (clone $baseQuery)
            ->pluck('id');

        /**
         * Financial summary.
         */
        $financialSummary = [
            'revenue' => (float) (clone $baseQuery)
                ->sum('grand_total'),

            'paid' => (float) (clone $baseQuery)
                ->sum('paid_amount'),

            'outstanding' => (float) (clone $baseQuery)
                ->sum('balance_due'),

            'expense' => (float) Expense::query()
                ->whereIn('invoice_id', $filteredInvoiceIds)
                ->sum('amount'),
        ];

        /**
         * Estimated Profit:
         *
         * Invoice Revenue - Expense
         */
        $financialSummary['profit'] =
            $financialSummary['revenue']
            - $financialSummary['expense'];

        /**
         * Invoice table.
         */
        $invoices = $baseQuery
            ->with([
                'person',
                'quote',
            ])
            ->withSum('expenses', 'amount')
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view(
            'admin::invoices.index',
            compact(
                'invoices',
                'financialSummary',
                'fromDate',
                'toDate',
                'status'
            )
        );
    }

    /**
     * Display invoice detail.
     */
    public function show(int $id): View
    {
        $invoice = Invoice::with([
            'items',
            'payments.creator',
            'expenses.creator',
            'quote',
            'person',
            'user',
        ])->findOrFail($id);

        return view(
            'admin::invoices.show',
            compact('invoice')
        );
    }

    /**
     * Generate invoice from quotation.
     */
    public function generate(int $quoteId): RedirectResponse
    {
        $quote = Quote::with('items')
            ->findOrFail($quoteId);

        $invoice = $this->invoiceService
            ->createFromQuote($quote);

        session()->flash(
            'success',
            'Invoice berhasil dibuat: '.$invoice->invoice_number
        );

        return redirect()->route(
            'admin.invoices.show',
            $invoice->id
        );
    }

    /**
     * Add payment to invoice.
     *
     * Payment date dan payment time berasal
     * dari dua input terpisah di show.blade.php.
     */
    public function addPayment(
        Request $request,
        int $id
    ): RedirectResponse {
        $validated = $request->validate([
            'amount' => [
                'required',
                'numeric',
                'gt:0',
            ],

            'payment_method' => [
                'nullable',
                'string',
                'max:255',
            ],

            'reference_number' => [
                'nullable',
                'string',
                'max:255',
            ],

            'notes' => [
                'nullable',
                'string',
            ],

            'paid_date' => [
                'required',
                'date',
            ],

            'paid_time' => [
                'required',
                'date_format:H:i',
            ],
        ]);

        $invoice = Invoice::findOrFail($id);

        /**
         * Gabungkan:
         *
         * paid_date = 2026-08-20
         * paid_time = 14:30
         *
         * menjadi:
         *
         * 2026-08-20 14:30:00
         */
        $paidAt = Carbon::createFromFormat(
            'Y-m-d H:i',
            $validated['paid_date'].' '.$validated['paid_time']
        );

        try {
            $this->paymentService->addPayment(
                $invoice,
                [
                    'amount' => $validated['amount'],

                    'payment_method' =>
                        $validated['payment_method'] ?? null,

                    'reference_number' =>
                        $validated['reference_number'] ?? null,

                    'notes' =>
                        $validated['notes'] ?? null,

                    'created_by' => auth()
                        ->guard('user')
                        ->id(),

                    'paid_at' => $paidAt,
                ]
            );
        } catch (InvalidArgumentException $exception) {
            return back()
                ->withInput()
                ->withErrors([
                    'amount' => $exception->getMessage(),
                ]);
        }

        session()->flash(
            'success',
            'Pembayaran berhasil ditambahkan.'
        );

        return redirect()->route(
            'admin.invoices.show',
            $invoice->id
        );
    }

    /**
     * Add expense to invoice.
     */
    public function addExpense(
        Request $request,
        int $id
    ): RedirectResponse {
        $validated = $request->validate([
            'category' => [
                'required',
                'string',
                'max:255',
            ],

            'description' => [
                'required',
                'string',
                'max:255',
            ],

            'amount' => [
                'required',
                'numeric',
                'gt:0',
            ],

            'expense_date' => [
                'required',
                'date',
            ],

            'vendor_name' => [
                'nullable',
                'string',
                'max:255',
            ],

            'reference_number' => [
                'nullable',
                'string',
                'max:255',
            ],

            'notes' => [
                'nullable',
                'string',
            ],

            'receipt' => [
                'nullable',
                'file',
                'mimes:jpg,jpeg,png,pdf',
                'max:5120',
            ],
        ]);

        $invoice = Invoice::findOrFail($id);

        $receiptPath = null;

        /**
         * Upload receipt / bon.
         */
        if ($request->hasFile('receipt')) {
            $receiptPath = $request
                ->file('receipt')
                ->store(
                    'expense-receipts',
                    'public'
                );
        }

        /**
         * UploadedFile tidak perlu dikirim
         * ke ExpenseService.
         */
        unset($validated['receipt']);

        try {
            $this->expenseService->addExpense(
                $invoice,
                [
                    ...$validated,

                    'receipt_path' => $receiptPath,

                    'created_by' => auth()
                        ->guard('user')
                        ->id(),
                ]
            );
        } catch (InvalidArgumentException $exception) {
            /**
             * Kalau insert expense gagal,
             * hapus file bon yang sudah ter-upload.
             */
            if ($receiptPath) {
                Storage::disk('public')
                    ->delete($receiptPath);
            }

            return back()
                ->withInput()
                ->withErrors([
                    'amount' => $exception->getMessage(),
                ]);
        }

        session()->flash(
            'success',
            'Pengeluaran berhasil ditambahkan.'
        );

        return redirect()->route(
            'admin.invoices.show',
            $invoice->id
        );
    }

    /**
     * Update existing expense.
     */
    public function updateExpense(
        Request $request,
        int $invoiceId,
        int $expenseId
    ): RedirectResponse {
        $invoice = Invoice::findOrFail(
            $invoiceId
        );

        /**
         * Expense harus benar-benar milik
         * invoice yang sedang dibuka.
         */
        $expense = Expense::query()
            ->where(
                'invoice_id',
                $invoice->id
            )
            ->findOrFail($expenseId);

        $validated = $request->validate([
            'category' => [
                'required',
                'string',
                'max:255',
            ],

            'description' => [
                'required',
                'string',
                'max:255',
            ],

            'amount' => [
                'required',
                'numeric',
                'gt:0',
            ],

            'expense_date' => [
                'required',
                'date',
            ],

            'vendor_name' => [
                'nullable',
                'string',
                'max:255',
            ],

            'reference_number' => [
                'nullable',
                'string',
                'max:255',
            ],

            'notes' => [
                'nullable',
                'string',
            ],

            'receipt' => [
                'nullable',
                'file',
                'mimes:jpg,jpeg,png,pdf',
                'max:5120',
            ],
        ]);

        $oldReceiptPath = $expense->receipt_path;

        $newReceiptPath = null;

        /**
         * Upload bon baru jika user memilih file.
         */
        if ($request->hasFile('receipt')) {
            $newReceiptPath = $request
                ->file('receipt')
                ->store(
                    'expense-receipts',
                    'public'
                );
        }

        unset($validated['receipt']);

        /**
         * Jika tidak ada bon baru,
         * ExpenseService akan mempertahankan bon lama.
         */
        if ($newReceiptPath) {
            $validated['receipt_path'] =
                $newReceiptPath;
        }

        try {
            $this->expenseService->updateExpense(
                $expense,
                $validated
            );
        } catch (InvalidArgumentException $exception) {
            /**
             * Update gagal:
             * hapus bon baru agar tidak orphan.
             */
            if ($newReceiptPath) {
                Storage::disk('public')
                    ->delete($newReceiptPath);
            }

            return back()
                ->withInput()
                ->withErrors([
                    'amount' => $exception->getMessage(),
                ]);
        }

        /**
         * Update berhasil.
         *
         * Kalau user upload bon baru,
         * hapus bon lama.
         */
        if (
            $newReceiptPath
            && $oldReceiptPath
            && $oldReceiptPath !== $newReceiptPath
        ) {
            Storage::disk('public')
                ->delete($oldReceiptPath);
        }

        session()->flash(
            'success',
            'Pengeluaran berhasil diperbarui.'
        );

        return redirect()->route(
            'admin.invoices.show',
            $invoice->id
        );
    }

    /**
     * Delete expense.
     */
    public function deleteExpense(
        int $invoiceId,
        int $expenseId
    ): RedirectResponse {
        $invoice = Invoice::findOrFail(
            $invoiceId
        );

        /**
         * Expense harus milik invoice terkait.
         */
        $expense = Expense::query()
            ->where(
                'invoice_id',
                $invoice->id
            )
            ->findOrFail($expenseId);

        $receiptPath = $expense->receipt_path;

        /**
         * Delete database record.
         */
        $this->expenseService
            ->deleteExpense($expense);

        /**
         * Delete receipt / bon file.
         */
        if ($receiptPath) {
            Storage::disk('public')
                ->delete($receiptPath);
        }

        session()->flash(
            'success',
            'Pengeluaran berhasil dihapus.'
        );

        return redirect()->route(
            'admin.invoices.show',
            $invoice->id
        );
    }

    /**
     * Print and download invoice PDF.
     */
    public function print(
        int $id
    ): Response|StreamedResponse {
        $invoice = Invoice::with([
            'items',
            'payments',
            'expenses',
            'quote',
            'person',
            'user',
        ])->findOrFail($id);

        return $this->downloadPDF(
            view(
                'admin::invoices.pdf',
                compact('invoice')
            )->render(),

            'Invoice_'.$invoice->invoice_number
        );
    }
}