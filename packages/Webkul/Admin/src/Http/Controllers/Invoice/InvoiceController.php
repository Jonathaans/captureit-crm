<?php

namespace Webkul\Admin\Http\Controllers\Invoice;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Webkul\Core\Traits\PDFHandler;
use InvalidArgumentException;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Invoice\Models\Invoice;
use Webkul\Invoice\Services\InvoiceService;
use Webkul\Invoice\Services\PaymentService;
use Webkul\Quote\Models\Quote;
use Webkul\Invoice\Services\ExpenseService;

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
            ->with(['person', 'quote'])
            ->latest('id')
            ->paginate(20);

        return view('admin::invoices.index', compact('invoices'));
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

    public function addExpense(Request $request, int $id): RedirectResponse
{
    $validated = $request->validate([
        'category'         => ['required', 'string', 'max:255'],
        'description'      => ['required', 'string', 'max:255'],
        'amount'           => ['required', 'numeric', 'gt:0'],
        'expense_date'     => ['required', 'date'],
        'vendor_name'      => ['nullable', 'string', 'max:255'],
        'reference_number' => ['nullable', 'string', 'max:255'],
        'notes'            => ['nullable', 'string'],

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

    try {
        $this->expenseService->addExpense($invoice, [
            ...$validated,

            'receipt_path' => $receiptPath,

            'created_by' => auth()
                ->guard('user')
                ->id(),
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
        'Pengeluaran berhasil ditambahkan.'
    );

    return redirect()->route(
        'admin.invoices.show',
        $invoice->id
    );
}

    /**
     * Add payment.
     */
    public function addPayment(Request $request, int $id): RedirectResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0'],
            'payment_method' => ['nullable', 'string', 'max:255'],
            'reference_number' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'paid_at' => ['nullable', 'date'],
        ]);

        $invoice = Invoice::findOrFail($id);

        try {
            $this->paymentService->addPayment($invoice, [
                ...$validated,

                'created_by' => auth()->guard('user')->id(),

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
 * Print and download invoice PDF.
 */
public function print(int $id): Response|StreamedResponse
{
    $invoice = Invoice::with([
        'items',
        'payments',
        'quote',
        'person',
        'user',
    ])->findOrFail($id);

    return $this->downloadPDF(
        view('admin::invoices.pdf', compact('invoice'))->render(),
        'Invoice_'.$invoice->invoice_number
    );
}
}