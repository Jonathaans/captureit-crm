<?php

namespace Webkul\Admin\Http\Controllers\Invoice;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Invoice\Models\Invoice;
use Webkul\Invoice\Services\InvoiceService;
use Webkul\Invoice\Services\PaymentService;
use Webkul\Quote\Models\Quote;

class InvoiceController extends Controller
{
    public function __construct(
        protected InvoiceService $invoiceService,
        protected PaymentService $paymentService
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
}