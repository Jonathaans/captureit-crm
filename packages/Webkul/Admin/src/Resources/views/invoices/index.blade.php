<x-admin::layouts>
    <x-slot:title>
        Invoices
    </x-slot>

    <!-- ========================================================= -->
    <!-- HEADER -->
    <!-- ========================================================= -->

    <div class="flex items-center justify-between gap-4 max-sm:flex-wrap">
        <div>
            <h1 class="text-xl font-bold text-gray-800 dark:text-white">
                Invoices
            </h1>

            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Manage invoices, payments, expenses, and project profitability.
            </p>
        </div>
    </div>

    <!-- ========================================================= -->
<!-- FILTERS -->
<!-- ========================================================= -->

<div class="mt-6 rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
    <form
        method="GET"
        action="{{ route('admin.invoices.index') }}"
        class="grid gap-4 md:grid-cols-2 xl:grid-cols-4"
    >
        <!-- From Date -->
        <div>
            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                From Date
            </label>

        <input
            type="date"
            name="from_date"
            value="{{ $fromDate }}"
            onclick="if (this.showPicker) this.showPicker()"
            class="w-full cursor-pointer rounded-lg border border-gray-300 bg-white px-4 py-3 text-gray-800 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
        >
        </div>

        <!-- To Date -->
        <div>
            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                To Date
            </label>

        <input
            type="date"
            name="to_date"
            value="{{ $toDate }}"
            onclick="if (this.showPicker) this.showPicker()"
            class="w-full cursor-pointer rounded-lg border border-gray-300 bg-white px-4 py-3 text-gray-800 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
        >
        </div>

        <!-- Status -->
        <div>
            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                Status
            </label>

            <select
                name="status"
                class="w-full rounded-lg border border-gray-300 bg-white px-4 py-3 text-gray-800 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
            >
                <option value="">
                    All Status
                </option>

                <option
                    value="unpaid"
                    @selected($status === 'unpaid')
                >
                    Unpaid
                </option>

                <option
                    value="partial"
                    @selected($status === 'partial')
                >
                    Partial
                </option>

                <option
                    value="paid"
                    @selected($status === 'paid')
                >
                    Paid
                </option>
            </select>
        </div>

        <!-- Actions -->
        <div class="flex items-end gap-2">
            <button
                type="submit"
                class="primary-button flex-1 justify-center"
            >
                Filter
            </button>

            <a
                href="{{ route('admin.invoices.index') }}"
                class="secondary-button flex-1 justify-center"
            >
                Reset
            </a>
        </div>
    </form>
</div>


    <!-- ========================================================= -->
    <!-- FINANCIAL OVERVIEW -->
    <!-- ========================================================= -->

    <div class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-5">

        <!-- Revenue -->
        <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
            <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                Total Revenue
            </p>

            <p class="mt-2 text-xl font-bold text-gray-800 dark:text-white">
                Rp {{ number_format((float) $financialSummary['revenue'], 0, ',', '.') }}
            </p>

            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                Total nilai seluruh invoice.
            </p>
        </div>

        <!-- Payment Received -->
        <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
            <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                Payment Received
            </p>

            <p class="mt-2 text-xl font-bold text-green-600 dark:text-green-400">
                Rp {{ number_format((float) $financialSummary['paid'], 0, ',', '.') }}
            </p>

            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                Uang yang sudah diterima.
            </p>
        </div>

        <!-- Outstanding -->
        <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
            <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                Outstanding
            </p>

            <p
                class="mt-2 text-xl font-bold
                    {{ (float) $financialSummary['outstanding'] > 0
                        ? 'text-red-600 dark:text-red-400'
                        : 'text-gray-800 dark:text-white' }}"
            >
                Rp {{ number_format((float) $financialSummary['outstanding'], 0, ',', '.') }}
            </p>

            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                Pembayaran yang belum diterima.
            </p>
        </div>

        <!-- Expense -->
        <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
            <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                Total Expense
            </p>

            <p class="mt-2 text-xl font-bold text-orange-600 dark:text-orange-400">
                Rp {{ number_format((float) $financialSummary['expense'], 0, ',', '.') }}
            </p>

            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                Total pengeluaran seluruh invoice.
            </p>
        </div>

        <!-- Profit -->
        <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
            <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                Est. Profit
            </p>

            <p
                class="mt-2 text-xl font-bold
                    {{ (float) $financialSummary['profit'] >= 0
                        ? 'text-green-600 dark:text-green-400'
                        : 'text-red-600 dark:text-red-400' }}"
            >
                Rp {{ number_format((float) $financialSummary['profit'], 0, ',', '.') }}
            </p>

            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                Revenue dikurangi expense.
            </p>
        </div>
    </div>


    <!-- ========================================================= -->
    <!-- INVOICE TABLE -->
    <!-- ========================================================= -->

    <div class="mt-6 overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">

        <div class="flex items-center justify-between gap-4 border-b border-gray-200 px-6 py-4 dark:border-gray-800">
            <div>
                <h2 class="text-lg font-semibold text-gray-800 dark:text-white">
                    Invoice Performance
                </h2>

                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Revenue, expense, and estimated profit per invoice.
                </p>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="border-b border-gray-200 bg-gray-50 dark:border-gray-800 dark:bg-gray-950">

                        <th class="px-5 py-4 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            Invoice
                        </th>

                        <th class="px-5 py-4 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            Customer / Subject
                        </th>

                        <th class="px-5 py-4 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            Revenue
                        </th>

                        <th class="px-5 py-4 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            Paid
                        </th>

                        <th class="px-5 py-4 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            Expense
                        </th>

                        <th class="px-5 py-4 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            Profit
                        </th>

                        <th class="px-5 py-4 text-center text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            Status
                        </th>

                        <th class="px-5 py-4 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            Action
                        </th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($invoices as $invoice)

                        @php
                            $invoiceExpense = (float) ($invoice->expenses_sum_amount ?? 0);

                            $invoiceProfit =
                                (float) $invoice->grand_total
                                - $invoiceExpense;
                        @endphp

                        <tr class="border-b border-gray-100 transition hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-gray-800/50">

                            <!-- Invoice -->
                            <td class="px-5 py-4">
                                <a
                                    href="{{ route('admin.invoices.show', $invoice->id) }}"
                                    class="font-semibold text-blue-600 hover:underline dark:text-blue-400"
                                >
                                    {{ $invoice->invoice_number }}
                                </a>

                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    {{ $invoice->issued_at?->format('d M Y') ?? '-' }}
                                </p>
                            </td>

                            <!-- Customer / Subject -->
                            <td class="px-5 py-4">
                                <p class="font-medium text-gray-800 dark:text-white">
                                    {{ $invoice->person?->name ?? '-' }}
                                </p>

                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                    {{ $invoice->subject }}
                                </p>
                            </td>

                            <!-- Revenue -->
                            <td class="px-5 py-4 text-right">
                                <p class="font-semibold text-gray-800 dark:text-white">
                                    Rp {{ number_format((float) $invoice->grand_total, 0, ',', '.') }}
                                </p>
                            </td>

                            <!-- Paid -->
                            <td class="px-5 py-4 text-right">
                                <p class="font-medium text-green-600 dark:text-green-400">
                                    Rp {{ number_format((float) $invoice->paid_amount, 0, ',', '.') }}
                                </p>

                                @if ((float) $invoice->balance_due > 0)
                                    <p class="mt-1 text-xs text-red-600 dark:text-red-400">
                                        Due:
                                        Rp {{ number_format((float) $invoice->balance_due, 0, ',', '.') }}
                                    </p>
                                @endif
                            </td>

                            <!-- Expense -->
                            <td class="px-5 py-4 text-right">
                                <p class="font-medium text-orange-600 dark:text-orange-400">
                                    Rp {{ number_format($invoiceExpense, 0, ',', '.') }}
                                </p>
                            </td>

                            <!-- Profit -->
                            <td class="px-5 py-4 text-right">
                                <p
                                    class="font-bold
                                        {{ $invoiceProfit >= 0
                                            ? 'text-green-600 dark:text-green-400'
                                            : 'text-red-600 dark:text-red-400' }}"
                                >
                                    Rp {{ number_format($invoiceProfit, 0, ',', '.') }}
                                </p>
                            </td>

                                                        <!-- Status -->
                            <td class="px-5 py-4 text-center">
                                @if ($invoice->status === 'paid')
                                    <span
                                        class="inline-flex items-center rounded-full
                                            bg-green-100 px-3 py-1
                                            text-xs font-bold text-green-700
                                            dark:bg-green-900/40 dark:text-green-300"
                                    >
                                        PAID
                                    </span>

                                @elseif ($invoice->status === 'partial')
                                    <span
                                        class="inline-flex items-center rounded-full
                                            bg-yellow-400 px-3 py-1
                                            text-xs font-bold text-gray-900
                                            dark:bg-yellow-400 dark:text-gray-900"
                                    >
                                        PARTIAL
                                    </span>

                                @else
                                    <span
                                        class="inline-flex items-center rounded-full
                                            bg-red-100 px-3 py-1
                                            text-xs font-bold text-red-700
                                            dark:bg-red-900/40 dark:text-red-300"
                                    >
                                        UNPAID
                                    </span>
                                @endif
                            </td>

                            <!-- Actions -->
                            <td class="px-5 py-4 text-right">
                                <div class="flex items-center justify-end gap-3">

                                    <a
                                        href="{{ route('admin.invoices.show', $invoice->id) }}"
                                        class="text-sm font-medium text-blue-600 hover:underline dark:text-blue-400"
                                    >
                                        View
                                    </a>

                                    <a
                                        href="{{ route('admin.invoices.print', $invoice->id) }}"
                                        class="text-sm font-medium text-gray-600 hover:underline dark:text-gray-300"
                                    >
                                        PDF
                                    </a>
                                </div>
                            </td>
                        </tr>

                    @empty
                        <tr>
                            <td
                                colspan="8"
                                class="px-5 py-16 text-center"
                            >
                                <p class="font-medium text-gray-700 dark:text-gray-300">
                                    Belum ada invoice.
                                </p>

                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                    Generate invoice dari Quote untuk mulai melihat laporan.
                                </p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>


        <!-- Pagination -->
        @if ($invoices->hasPages())
            <div class="border-t border-gray-200 px-5 py-4 dark:border-gray-800">
                {{ $invoices->links() }}
            </div>
        @endif
    </div>
</x-admin::layouts>