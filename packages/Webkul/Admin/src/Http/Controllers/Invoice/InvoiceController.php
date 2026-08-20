<?php

namespace Webkul\Admin\Http\Controllers\Invoice;

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

    public function __construct(
        protected InvoiceService $invoiceService,
        protected PaymentService $paymentService,
        protected ExpenseService $expenseService
    ) {
    }

    /**
     * Display invoice list.
     */
    public function index(): View
{
    $invoices = Invoice::query()
        ->with([
            'person',
            'quote',
        ])
        ->withSum('expenses', 'amount')
        ->latest('id')
        ->paginate(20);

    $financialSummary = [
        'revenue' => (float) Invoice::sum('grand_total'),

        'paid' => (float) Invoice::sum('paid_amount'),

        'outstanding' => (float) Invoice::sum('balance_due'),

        'expense' => (float) Expense::sum('amount'),
    ];

    $financialSummary['profit'] =
        $financialSummary['revenue']
        - $financialSummary['expense'];

    return view(
        'admin::invoices.index',
        compact(
            'invoices',
            'financialSummary'
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

        return view('admin::invoices.show', compact('invoice'));
    }

    /**
     * Generate invoice from quotation.
     */
    public function generate(int $quoteId): RedirectResponse
    {
        $quote = Quote::with('items')->findOrFail($quoteId);

        $invoice = $this->invoiceService->createFromQuote($quote);

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
     */
    public function addPayment(
        Request $request,
        int $id
    ): RedirectResponse {
        $validated = $request->validate([
            'amount'           => ['required', 'numeric', 'gt:0'],
            'payment_method'   => ['nullable', 'string', 'max:255'],
            'reference_number' => ['nullable', 'string', 'max:255'],
            'notes'            => ['nullable', 'string'],
            'paid_at'          => ['nullable', 'date'],
        ]);

        $invoice = Invoice::findOrFail($id);

        try {
            $this->paymentService->addPayment($invoice, [
                ...$validated,

                'created_by' => auth()
                    ->guard('user')
                    ->id(),

                'paid_at' => $validated['paid_at'] ?? now(),
            ]);
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

        if ($request->hasFile('receipt')) {
            $receiptPath = $request
                ->file('receipt')
                ->store('expense-receipts', 'public');
        }

        /*
         * UploadedFile tidak perlu diteruskan ke ExpenseService.
         */
        unset($validated['receipt']);

        try {
            $this->expenseService->addExpense($invoice, [
                ...$validated,

                'receipt_path' => $receiptPath,

                'created_by' => auth()
                    ->guard('user')
                    ->id(),
            ]);
        } catch (InvalidArgumentException $exception) {
            /*
             * Kalau database gagal tetapi file sudah ter-upload,
             * hapus file supaya tidak menjadi file yatim.
             */
            if ($receiptPath) {
                Storage::disk('public')->delete($receiptPath);
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
        $invoice = Invoice::findOrFail($invoiceId);

        /*
         * Penting:
         * Expense hanya boleh diedit jika memang milik invoice ini.
         */
        $expense = Expense::query()
            ->where('invoice_id', $invoice->id)
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

        if ($request->hasFile('receipt')) {
            $newReceiptPath = $request
                ->file('receipt')
                ->store('expense-receipts', 'public');
        }

        unset($validated['receipt']);

        if ($newReceiptPath) {
            $validated['receipt_path'] = $newReceiptPath;
        }

        try {
            $this->expenseService->updateExpense(
                $expense,
                $validated
            );
        } catch (InvalidArgumentException $exception) {
            /*
             * Kalau update gagal, hapus bon baru yang tadi di-upload.
             */
            if ($newReceiptPath) {
                Storage::disk('public')->delete($newReceiptPath);
            }

            return back()
                ->withInput()
                ->withErrors([
                    'amount' => $exception->getMessage(),
                ]);
        }

        /*
         * Update berhasil.
         * Kalau ada bon baru, bon lama boleh dihapus.
         */
        if (
            $newReceiptPath
            && $oldReceiptPath
            && $oldReceiptPath !== $newReceiptPath
        ) {
            Storage::disk('public')->delete($oldReceiptPath);
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
        $invoice = Invoice::findOrFail($invoiceId);

        /*
         * Expense harus benar-benar milik invoice ini.
         */
        $expense = Expense::query()
            ->where('invoice_id', $invoice->id)
            ->findOrFail($expenseId);

        $receiptPath = $expense->receipt_path;

        $this->expenseService->deleteExpense($expense);

        /*
         * Setelah record berhasil dihapus,
         * hapus juga file bon dari storage.
         */
        if ($receiptPath) {
            Storage::disk('public')->delete($receiptPath);
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
    public function print(int $id): Response|StreamedResponse
    {
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