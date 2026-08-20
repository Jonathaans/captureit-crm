<x-admin::layouts>
    <x-slot:title>
        {{ $invoice->invoice_number }}
    </x-slot>

    @php
        $totalExpense = (float) $invoice->expenses->sum('amount');

        $estimatedProfit = (float) $invoice->grand_total - $totalExpense;
    @endphp

    <!-- ========================================================= -->
    <!-- PAGE HEADER -->
    <!-- ========================================================= -->

    <div class="flex items-start justify-between gap-4 max-sm:flex-wrap">
        <div>
            <a
                href="{{ route('admin.invoices.index') }}"
                class="text-sm font-medium text-blue-600 hover:underline dark:text-blue-400"
            >
                ← Back to Invoices
            </a>

            <div class="mt-3 flex items-center gap-3">
                <h1 class="text-2xl font-bold text-gray-800 dark:text-white">
                    {{ $invoice->invoice_number }}
                </h1>

                @if ($invoice->status === 'paid')
                    <span class="rounded-full bg-green-100 px-3 py-1 text-xs font-semibold text-green-700 dark:bg-green-900/30 dark:text-green-400">
                        PAID
                    </span>
                @elseif ($invoice->status === 'partial')
                    <span class="rounded-full bg-yellow-100 px-3 py-1 text-xs font-semibold text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400">
                        PARTIAL
                    </span>
                @else
                    <span class="rounded-full bg-red-100 px-3 py-1 text-xs font-semibold text-red-700 dark:bg-red-900/30 dark:text-red-400">
                        UNPAID
                    </span>
                @endif
            </div>

            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                {{ $invoice->subject }}
            </p>
        </div>

        <div class="flex items-center gap-2 max-sm:w-full max-sm:flex-wrap">
            @if ($invoice->quote)
                <a
                    href="{{ route('admin.quotes.edit', $invoice->quote->id) }}"
                    class="secondary-button"
                >
                    View Quote
                </a>
            @endif

            <a
                href="{{ route('admin.invoices.print', $invoice->id) }}"
                class="primary-button"
            >
                Print Invoice
            </a>
        </div>
    </div>


    <!-- ========================================================= -->
    <!-- INVOICE INFORMATION -->
    <!-- ========================================================= -->

    <div class="mt-6 grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">

        <!-- Customer -->
        <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
            <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                Customer
            </p>

            <p class="mt-2 font-semibold text-gray-800 dark:text-white">
                {{ $invoice->person?->name ?? '-' }}
            </p>
        </div>

        <!-- Issued Date -->
        <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
            <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                Issued Date
            </p>

            <p class="mt-2 font-semibold text-gray-800 dark:text-white">
                {{ $invoice->issued_at?->format('d M Y') ?? '-' }}
            </p>
        </div>

        <!-- Due Date -->
        <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
            <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                Due Date
            </p>

            <p class="mt-2 font-semibold text-gray-800 dark:text-white">
                {{ $invoice->due_at?->format('d M Y') ?? '-' }}
            </p>
        </div>

        <!-- Quote Reference -->
        <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
            <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                Quote Reference
            </p>

            <p class="mt-2 font-semibold text-gray-800 dark:text-white">
                @if ($invoice->quote)
                    Quote #{{ $invoice->quote->id }}
                @else
                    -
                @endif
            </p>
        </div>
    </div>


    <!-- ========================================================= -->
    <!-- FINANCIAL SUMMARY -->
    <!-- ========================================================= -->

    <div class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-5">

        <!-- Grand Total -->
        <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">
                Grand Total
            </p>

            <p class="mt-2 text-xl font-bold text-gray-800 dark:text-white">
                Rp {{ number_format((float) $invoice->grand_total, 0, ',', '.') }}
            </p>
        </div>

        <!-- Paid -->
        <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">
                Paid
            </p>

            <p class="mt-2 text-xl font-bold text-green-600 dark:text-green-400">
                Rp {{ number_format((float) $invoice->paid_amount, 0, ',', '.') }}
            </p>
        </div>

        <!-- Balance -->
        <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">
                Balance Due
            </p>

            <p
                class="mt-2 text-xl font-bold
                    {{ (float) $invoice->balance_due > 0
                        ? 'text-red-600 dark:text-red-400'
                        : 'text-gray-800 dark:text-white' }}"
            >
                Rp {{ number_format((float) $invoice->balance_due, 0, ',', '.') }}
            </p>
        </div>

        <!-- Total Expense -->
        <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">
                Total Expense
            </p>

            <p class="mt-2 text-xl font-bold text-orange-600 dark:text-orange-400">
                Rp {{ number_format($totalExpense, 0, ',', '.') }}
            </p>
        </div>

        <!-- Estimated Profit -->
        <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">
                Est. Profit
            </p>

            <p
                class="mt-2 text-xl font-bold
                    {{ $estimatedProfit >= 0
                        ? 'text-green-600 dark:text-green-400'
                        : 'text-red-600 dark:text-red-400' }}"
            >
                Rp {{ number_format($estimatedProfit, 0, ',', '.') }}
            </p>
        </div>
    </div>


    <!-- ========================================================= -->
    <!-- MAIN CONTENT -->
    <!-- ========================================================= -->

    <div class="mt-6 grid gap-6 xl:grid-cols-[2fr_1fr]">

        <!-- ===================================================== -->
        <!-- LEFT COLUMN -->
        <!-- ===================================================== -->

        <div class="space-y-6">

            <!-- ================================================= -->
            <!-- INVOICE ITEMS -->
            <!-- ================================================= -->

            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
                <div class="border-b border-gray-200 px-6 py-4 dark:border-gray-800">
                    <h2 class="text-lg font-semibold text-gray-800 dark:text-white">
                        Invoice Items
                    </h2>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="border-b border-gray-200 bg-gray-50 dark:border-gray-800 dark:bg-gray-950">
                                <th class="px-6 py-3 text-left text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">
                                    Item
                                </th>

                                <th class="px-6 py-3 text-center text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">
                                    Qty
                                </th>

                                <th class="px-6 py-3 text-right text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">
                                    Price
                                </th>

                                <th class="px-6 py-3 text-right text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">
                                    Total
                                </th>
                            </tr>
                        </thead>

                        <tbody>
                            @forelse ($invoice->items as $item)
                                <tr class="border-b border-gray-100 dark:border-gray-800">
                                    <td class="px-6 py-4">
                                        <p class="font-medium text-gray-800 dark:text-white">
                                            {{ $item->name }}
                                        </p>

                                        @if ($item->sku)
                                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                                SKU: {{ $item->sku }}
                                            </p>
                                        @endif
                                    </td>

                                    <td class="px-6 py-4 text-center text-gray-700 dark:text-gray-300">
                                        {{ $item->quantity }}
                                    </td>

                                    <td class="px-6 py-4 text-right text-gray-700 dark:text-gray-300">
                                        Rp {{ number_format((float) $item->price, 0, ',', '.') }}
                                    </td>

                                    <td class="px-6 py-4 text-right font-semibold text-gray-800 dark:text-white">
                                        Rp {{ number_format((float) $item->total, 0, ',', '.') }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td
                                        colspan="4"
                                        class="px-6 py-10 text-center text-gray-500 dark:text-gray-400"
                                    >
                                        Tidak ada item pada invoice ini.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <!-- Item Totals -->
                <div class="flex justify-end border-t border-gray-200 px-6 py-5 dark:border-gray-800">
                    <div class="w-full max-w-sm space-y-3">

                        <div class="flex justify-between text-sm">
                            <span class="text-gray-500 dark:text-gray-400">
                                Subtotal
                            </span>

                            <span class="font-medium text-gray-800 dark:text-white">
                                Rp {{ number_format((float) $invoice->sub_total, 0, ',', '.') }}
                            </span>
                        </div>

                        @if ((float) $invoice->discount_amount > 0)
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-500 dark:text-gray-400">
                                    Discount
                                </span>

                                <span class="font-medium text-red-600 dark:text-red-400">
                                    - Rp {{ number_format((float) $invoice->discount_amount, 0, ',', '.') }}
                                </span>
                            </div>
                        @endif

                        @if ((float) $invoice->tax_amount > 0)
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-500 dark:text-gray-400">
                                    Tax
                                </span>

                                <span class="font-medium text-gray-800 dark:text-white">
                                    Rp {{ number_format((float) $invoice->tax_amount, 0, ',', '.') }}
                                </span>
                            </div>
                        @endif

                        @if ((float) $invoice->adjustment_amount != 0)
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-500 dark:text-gray-400">
                                    Adjustment
                                </span>

                                <span class="font-medium text-gray-800 dark:text-white">
                                    Rp {{ number_format((float) $invoice->adjustment_amount, 0, ',', '.') }}
                                </span>
                            </div>
                        @endif

                        <div class="flex justify-between border-t border-gray-200 pt-3 dark:border-gray-700">
                            <span class="font-semibold text-gray-800 dark:text-white">
                                Grand Total
                            </span>

                            <span class="text-lg font-bold text-gray-800 dark:text-white">
                                Rp {{ number_format((float) $invoice->grand_total, 0, ',', '.') }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>


            <!-- ================================================= -->
            <!-- PAYMENT HISTORY -->
            <!-- ================================================= -->

            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
                <div class="border-b border-gray-200 px-6 py-4 dark:border-gray-800">
                    <h2 class="text-lg font-semibold text-gray-800 dark:text-white">
                        Payment History
                    </h2>

                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Riwayat pembayaran customer.
                    </p>
                </div>

                <div class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($invoice->payments as $payment)
                        <div class="flex items-start justify-between gap-4 px-6 py-5 max-sm:flex-col">
                            <div>
                                <p class="font-semibold text-green-600 dark:text-green-400">
                                    + Rp {{ number_format((float) $payment->amount, 0, ',', '.') }}
                                </p>

                                <div class="mt-2 space-y-1 text-sm text-gray-500 dark:text-gray-400">
                                    <p>
                                        Method:
                                        {{ ucwords(str_replace('_', ' ', $payment->payment_method ?? '-')) }}
                                    </p>

                                    @if ($payment->reference_number)
                                        <p>
                                            Reference:
                                            {{ $payment->reference_number }}
                                        </p>
                                    @endif

                                    @if ($payment->notes)
                                        <p>
                                            Notes:
                                            {{ $payment->notes }}
                                        </p>
                                    @endif
                                </div>
                            </div>

                            <div class="text-right text-sm text-gray-500 dark:text-gray-400 max-sm:text-left">
                                <p>
                                    {{ $payment->paid_at?->format('d M Y') ?? '-' }}
                                </p>

                                <p class="mt-1">
                                    {{ $payment->paid_at?->format('H:i') ?? '' }}
                                </p>

                                @if ($payment->creator)
                                    <p class="mt-2">
                                        by {{ $payment->creator->name }}
                                    </p>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="px-6 py-10 text-center text-gray-500 dark:text-gray-400">
                            Belum ada pembayaran.
                        </div>
                    @endforelse
                </div>
            </div>


            <!-- ================================================= -->
            <!-- EXPENSE HISTORY -->
            <!-- ================================================= -->

            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
                <div class="flex items-center justify-between gap-4 border-b border-gray-200 px-6 py-4 dark:border-gray-800">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-800 dark:text-white">
                            Expense History
                        </h2>

                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Bon dan pengeluaran untuk invoice ini.
                        </p>
                    </div>

                    <div class="text-right">
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            Total Expense
                        </p>

                        <p class="font-bold text-orange-600 dark:text-orange-400">
                            Rp {{ number_format($totalExpense, 0, ',', '.') }}
                        </p>
                    </div>
                </div>

                <div class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($invoice->expenses->sortByDesc('expense_date') as $expense)
                        <div class="px-6 py-5">
                            <div class="flex items-start justify-between gap-4 max-sm:flex-col">
                                <div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <p class="font-semibold text-gray-800 dark:text-white">
                                            {{ $expense->description }}
                                        </p>

                                        <span class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-600 dark:bg-gray-800 dark:text-gray-300">
                                            {{ ucwords(str_replace('_', ' ', $expense->category)) }}
                                        </span>
                                    </div>

                                    <div class="mt-2 space-y-1 text-sm text-gray-500 dark:text-gray-400">
                                        @if ($expense->vendor_name)
                                            <p>
                                                Vendor: {{ $expense->vendor_name }}
                                            </p>
                                        @endif

                                        @if ($expense->reference_number)
                                            <p>
                                                Reference / No. Bon:
                                                {{ $expense->reference_number }}
                                            </p>
                                        @endif

                                        @if ($expense->notes)
                                            <p>
                                                Notes: {{ $expense->notes }}
                                            </p>
                                        @endif

                                        @if ($expense->creator)
                                            <p>
                                                Added by: {{ $expense->creator->name }}
                                            </p>
                                        @endif
                                    </div>

                                    @if ($expense->receipt_path)
                                        <div class="mt-3">
                                            <a
                                                href="{{ asset('storage/'.$expense->receipt_path) }}"
                                                target="_blank"
                                                rel="noopener"
                                                class="font-medium text-blue-600 hover:underline dark:text-blue-400"
                                            >
                                                View Receipt / Bon
                                            </a>
                                        </div>
                                    @endif
                                </div>

                                <div class="text-right max-sm:text-left">
                                    <p class="text-lg font-bold text-red-600 dark:text-red-400">
                                        - Rp {{ number_format((float) $expense->amount, 0, ',', '.') }}
                                    </p>

                                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                        {{ $expense->expense_date?->format('d M Y') ?? '-' }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="px-6 py-10 text-center">
                            <p class="text-gray-500 dark:text-gray-400">
                                Belum ada pengeluaran.
                            </p>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>


        <!-- ===================================================== -->
        <!-- RIGHT COLUMN -->
        <!-- ===================================================== -->

        <div class="space-y-6">

            <!-- ================================================= -->
            <!-- ADD PAYMENT -->
            <!-- ================================================= -->

            <div class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900">
                <h2 class="text-lg font-semibold text-gray-800 dark:text-white">
                    Add Payment
                </h2>

                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Catat pembayaran yang diterima dari customer.
                </p>

                @if ($invoice->status === 'paid')
                    <div class="mt-5 rounded-lg bg-green-50 p-4 dark:bg-green-900/20">
                        <p class="font-medium text-green-700 dark:text-green-400">
                            Invoice sudah lunas.
                        </p>

                        <p class="mt-1 text-sm text-green-600 dark:text-green-500">
                            Tidak ada sisa pembayaran.
                        </p>
                    </div>
                @else
                    <div class="mt-4 rounded-lg bg-gray-50 p-4 dark:bg-gray-950">
                        <p class="text-xs uppercase text-gray-500 dark:text-gray-400">
                            Remaining Balance
                        </p>

                        <p class="mt-1 text-xl font-bold text-gray-800 dark:text-white">
                            Rp {{ number_format((float) $invoice->balance_due, 0, ',', '.') }}
                        </p>
                    </div>

                    <form
                        method="POST"
                        action="{{ route('admin.invoices.payments.store', $invoice->id) }}"
                        class="mt-6 space-y-5"
                    >
                        @csrf

                        <!-- Payment Amount -->
                        <div>
                            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Amount
                                <span class="text-red-600">*</span>
                            </label>

                            <input
                                type="number"
                                name="amount"
                                min="1"
                                max="{{ (float) $invoice->balance_due }}"
                                step="1"
                                placeholder="Masukkan nominal pembayaran"
                                class="w-full rounded-lg border border-gray-300 bg-white px-4 py-3 text-gray-800 outline-none transition focus:border-blue-500 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                                required
                            >

                            @error('amount')
                                <p class="mt-2 text-sm text-red-600">
                                    {{ $message }}
                                </p>
                            @enderror
                        </div>

                        <!-- Payment Method -->
                        <div>
                            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Payment Method
                            </label>

                            <select
                                name="payment_method"
                                class="w-full rounded-lg border border-gray-300 bg-white px-4 py-3 text-gray-800 outline-none transition focus:border-blue-500 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                            >
                                <option value="bank_transfer">
                                    Bank Transfer
                                </option>

                                <option value="cash">
                                    Cash
                                </option>

                                <option value="other">
                                    Other
                                </option>
                            </select>
                        </div>

                        <!-- Payment Reference -->
                        <div>
                            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Reference Number
                            </label>

                            <input
                                type="text"
                                name="reference_number"
                                placeholder="Contoh: BCA-TRX-001"
                                class="w-full rounded-lg border border-gray-300 bg-white px-4 py-3 text-gray-800 outline-none transition focus:border-blue-500 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                            >
                        </div>

                        <!-- Payment Date -->
                        <div>
                            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Payment Date
                            </label>

                            <input
                                type="datetime-local"
                                name="paid_at"
                                class="w-full rounded-lg border border-gray-300 bg-white px-4 py-3 text-gray-800 outline-none transition focus:border-blue-500 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                            >
                        </div>

                        <!-- Payment Notes -->
                        <div>
                            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Notes
                            </label>

                            <textarea
                                name="notes"
                                rows="3"
                                placeholder="Catatan pembayaran..."
                                class="w-full rounded-lg border border-gray-300 bg-white px-4 py-3 text-gray-800 outline-none transition focus:border-blue-500 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                            ></textarea>
                        </div>

                        <button
                            type="submit"
                            class="primary-button w-full justify-center"
                        >
                            Add Payment
                        </button>
                    </form>
                @endif
            </div>


            <!-- ================================================= -->
            <!-- ADD EXPENSE -->
            <!-- ================================================= -->

            <div class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900">
                <h2 class="text-lg font-semibold text-gray-800 dark:text-white">
                    Add Expense
                </h2>

                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Catat bon atau pengeluaran yang berkaitan dengan invoice ini.
                </p>

                <div class="mt-4 rounded-lg bg-orange-50 p-4 dark:bg-orange-900/10">
                    <p class="text-xs uppercase text-orange-600 dark:text-orange-400">
                        Current Expense
                    </p>

                    <p class="mt-1 text-xl font-bold text-orange-600 dark:text-orange-400">
                        Rp {{ number_format($totalExpense, 0, ',', '.') }}
                    </p>
                </div>

                <form
                    method="POST"
                    action="{{ route('admin.invoices.expenses.store', $invoice->id) }}"
                    enctype="multipart/form-data"
                    class="mt-6 space-y-5"
                >
                    @csrf

                    <!-- Category -->
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Category
                            <span class="text-red-600">*</span>
                        </label>

                        <select
                            name="category"
                            class="w-full rounded-lg border border-gray-300 bg-white px-4 py-3 text-gray-800 outline-none transition focus:border-blue-500 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                            required
                        >
                            <option value="">
                                Select Category
                            </option>

                            <option value="transport" @selected(old('category') === 'transport')>
                                Transport
                            </option>

                            <option value="crew" @selected(old('category') === 'crew')>
                                Crew
                            </option>

                            <option value="printing" @selected(old('category') === 'printing')>
                                Printing
                            </option>

                            <option value="equipment" @selected(old('category') === 'equipment')>
                                Equipment
                            </option>

                            <option value="vendor" @selected(old('category') === 'vendor')>
                                Vendor
                            </option>

                            <option value="consumption" @selected(old('category') === 'consumption')>
                                Consumption
                            </option>

                            <option value="other" @selected(old('category') === 'other')>
                                Other
                            </option>
                        </select>

                        @error('category')
                            <p class="mt-2 text-sm text-red-600">
                                {{ $message }}
                            </p>
                        @enderror
                    </div>

                    <!-- Expense Description -->
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Description
                            <span class="text-red-600">*</span>
                        </label>

                        <input
                            type="text"
                            name="description"
                            value="{{ old('description') }}"
                            placeholder="Contoh: Transport crew"
                            class="w-full rounded-lg border border-gray-300 bg-white px-4 py-3 text-gray-800 outline-none transition focus:border-blue-500 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                            required
                        >

                        @error('description')
                            <p class="mt-2 text-sm text-red-600">
                                {{ $message }}
                            </p>
                        @enderror
                    </div>

                    <!-- Expense Amount -->
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Amount
                            <span class="text-red-600">*</span>
                        </label>

                        <input
                            type="number"
                            name="amount"
                            min="1"
                            step="1"
                            placeholder="Contoh: 500000"
                            class="w-full rounded-lg border border-gray-300 bg-white px-4 py-3 text-gray-800 outline-none transition focus:border-blue-500 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                            required
                        >

                        @error('amount')
                            <p class="mt-2 text-sm text-red-600">
                                {{ $message }}
                            </p>
                        @enderror
                    </div>

                    <!-- Expense Date -->
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Expense Date
                            <span class="text-red-600">*</span>
                        </label>

                        <input
                            type="date"
                            name="expense_date"
                            value="{{ old('expense_date', now()->toDateString()) }}"
                            class="w-full rounded-lg border border-gray-300 bg-white px-4 py-3 text-gray-800 outline-none transition focus:border-blue-500 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                            required
                        >

                        @error('expense_date')
                            <p class="mt-2 text-sm text-red-600">
                                {{ $message }}
                            </p>
                        @enderror
                    </div>

                    <!-- Vendor -->
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Vendor
                        </label>

                        <input
                            type="text"
                            name="vendor_name"
                            value="{{ old('vendor_name') }}"
                            placeholder="Contoh: Grab / Percetakan ABC"
                            class="w-full rounded-lg border border-gray-300 bg-white px-4 py-3 text-gray-800 outline-none transition focus:border-blue-500 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                        >

                        @error('vendor_name')
                            <p class="mt-2 text-sm text-red-600">
                                {{ $message }}
                            </p>
                        @enderror
                    </div>

                    <!-- Reference -->
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Reference / No. Bon
                        </label>

                        <input
                            type="text"
                            name="reference_number"
                            value="{{ old('reference_number') }}"
                            placeholder="Contoh: BON-001"
                            class="w-full rounded-lg border border-gray-300 bg-white px-4 py-3 text-gray-800 outline-none transition focus:border-blue-500 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                        >

                        @error('reference_number')
                            <p class="mt-2 text-sm text-red-600">
                                {{ $message }}
                            </p>
                        @enderror
                    </div>

                    <!-- Receipt -->
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Upload Bon
                        </label>

                        <input
                            type="file"
                            name="receipt"
                            accept=".jpg,.jpeg,.png,.pdf"
                            class="w-full rounded-lg border border-gray-300 bg-white px-4 py-3 text-sm text-gray-700 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-300"
                        >

                        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                            Format: JPG, JPEG, PNG atau PDF. Maksimal 5 MB.
                        </p>

                        @error('receipt')
                            <p class="mt-2 text-sm text-red-600">
                                {{ $message }}
                            </p>
                        @enderror
                    </div>

                    <!-- Expense Notes -->
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Notes
                        </label>

                        <textarea
                            name="notes"
                            rows="3"
                            placeholder="Catatan tambahan..."
                            class="w-full rounded-lg border border-gray-300 bg-white px-4 py-3 text-gray-800 outline-none transition focus:border-blue-500 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                        >{{ old('notes') }}</textarea>

                        @error('notes')
                            <p class="mt-2 text-sm text-red-600">
                                {{ $message }}
                            </p>
                        @enderror
                    </div>

                    <button
                        type="submit"
                        class="primary-button w-full justify-center"
                    >
                        Save Expense
                    </button>
                </form>
            </div>
        </div>
    </div>
</x-admin::layouts>