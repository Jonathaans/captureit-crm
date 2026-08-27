<x-admin::layouts>
    <x-slot:title>
        Financial Report
    </x-slot>

    <!-- ========================================================= -->
    <!-- PAGE HEADER -->
    <!-- ========================================================= -->

    <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <a
                href="{{ route('admin.invoices.index') }}"
                class="text-sm font-medium text-blue-600 hover:underline dark:text-blue-400"
            >
                ← Back to Invoices
            </a>

            <h1 class="mt-3 text-2xl font-bold text-gray-800 dark:text-white">
                Financial Report
            </h1>

            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Financial performance, payment received, expenses, estimated profit, and cash surplus.
            </p>
        </div>

        <!-- ===================================================== -->
        <!-- FILTER + EXPORT -->
        <!-- ===================================================== -->

        <div class="flex flex-wrap items-end gap-3">
            <form
                method="GET"
                action="{{ route('admin.invoices.financial-report') }}"
                class="flex flex-wrap items-end gap-3"
            >
                <!-- YEAR -->
                <div>
                    <label
                        class="mb-2 block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400"
                    >
                        Year
                    </label>

                    <select
                        name="year"
                        class="min-w-[130px] cursor-pointer rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-800 outline-none transition focus:border-blue-500 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                    >
                        @foreach ($availableYears as $availableYear)
                            <option
                                value="{{ $availableYear }}"
                                @selected((int) $year === (int) $availableYear)
                            >
                                {{ $availableYear }}
                            </option>
                        @endforeach
                    </select>
                </div>


                <!-- EVENT STATUS -->
                <div>
                    <label
                        class="mb-2 block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400"
                    >
                        Event Status
                    </label>

                    <select
                        name="event_status"
                        class="min-w-[170px] cursor-pointer rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-800 outline-none transition focus:border-blue-500 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                    >
                        <option value="">
                            All Events
                        </option>

                        <option
                            value="confirm"
                            @selected($eventStatus === 'confirm')
                        >
                            Confirm
                        </option>

                        <option
                            value="prospect"
                            @selected($eventStatus === 'prospect')
                        >
                            Prospect
                        </option>

                        <option
                            value="cancel"
                            @selected($eventStatus === 'cancel')
                        >
                            Cancel
                        </option>
                    </select>
                </div>


                <!-- APPLY FILTER -->
                <button
                    type="submit"
                    class="primary-button"
                >
                    Apply Filter
                </button>


                <!-- RESET -->
                <a
                    href="{{ route('admin.invoices.financial-report') }}"
                    class="secondary-button"
                >
                    Reset
                </a>
            </form>


            <!-- EXPORT CSV -->
            <a
                href="{{ route('admin.invoices.financial-report.export', [
                    'year' => $year,
                    'event_status' => $eventStatus,
                ]) }}"
                class="secondary-button"
            >
                Export CSV
            </a>
        </div>
    </div>


    <!-- ========================================================= -->
    <!-- CURRENT FILTER -->
    <!-- ========================================================= -->

    <div class="mt-5 flex flex-wrap items-center gap-2">
        <span
            class="rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold text-gray-700 dark:bg-gray-800 dark:text-gray-300"
        >
            Year: {{ $year }}
        </span>

        @if ($eventStatus === 'confirm')
            <span
                class="rounded-full bg-green-100 px-3 py-1 text-xs font-bold text-green-700 dark:bg-green-900/40 dark:text-green-300"
            >
                CONFIRM
            </span>

        @elseif ($eventStatus === 'prospect')
            <span
                class="rounded-full bg-blue-100 px-3 py-1 text-xs font-bold text-blue-700 dark:bg-blue-900/40 dark:text-blue-300"
            >
                PROSPECT
            </span>

        @elseif ($eventStatus === 'cancel')
            <span
                class="rounded-full bg-red-100 px-3 py-1 text-xs font-bold text-red-700 dark:bg-red-900/40 dark:text-red-300"
            >
                CANCEL
            </span>

        @else
            <span
                class="rounded-full bg-gray-100 px-3 py-1 text-xs font-bold text-gray-700 dark:bg-gray-800 dark:text-gray-300"
            >
                ALL EVENTS
            </span>
        @endif
    </div>


    <!-- ========================================================= -->
    <!-- FINANCIAL SUMMARY -->
    <!-- ========================================================= -->

    <div class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-3">

        <!-- ===================================================== -->
        <!-- REVENUE -->
        <!-- ===================================================== -->

        <div
            class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900"
        >
            <p
                class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400"
            >
                Revenue
            </p>

            <p
                class="mt-2 text-2xl font-bold text-gray-800 dark:text-white"
            >
                Rp {{ number_format(
                    (float) $financialSummary['revenue'],
                    0,
                    ',',
                    '.'
                ) }}
            </p>

            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                Nilai invoice event yang sudah Confirm.
            </p>
        </div>


        <!-- ===================================================== -->
        <!-- PAYMENT RECEIVED -->
        <!-- ===================================================== -->

        <div
            class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900"
        >
            <p
                class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400"
            >
                Payment Received
            </p>

            <p
                class="mt-2 text-2xl font-bold text-green-600 dark:text-green-400"
            >
                Rp {{ number_format(
                    (float) $financialSummary['received'],
                    0,
                    ',',
                    '.'
                ) }}
            </p>

            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                Seluruh uang yang benar-benar diterima, termasuk Partial dan Paid.
            </p>
        </div>


        <!-- ===================================================== -->
        <!-- OUTSTANDING -->
        <!-- ===================================================== -->

        <div
            class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900"
        >
            <p
                class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400"
            >
                Outstanding
            </p>

            <p
                class="mt-2 text-2xl font-bold text-red-600 dark:text-red-400"
            >
                Rp {{ number_format(
                    (float) $financialSummary['outstanding'],
                    0,
                    ',',
                    '.'
                ) }}
            </p>

            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                Sisa pembayaran invoice Confirm yang belum diterima.
            </p>
        </div>


        <!-- ===================================================== -->
        <!-- EXPENSE -->
        <!-- ===================================================== -->

        <div
            class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900"
        >
            <p
                class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400"
            >
                Total Expense
            </p>

            <p
                class="mt-2 text-2xl font-bold text-orange-600 dark:text-orange-400"
            >
                Rp {{ number_format(
                    (float) $financialSummary['expense'],
                    0,
                    ',',
                    '.'
                ) }}
            </p>

            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                Total pengeluaran aktual pada periode laporan.
            </p>
        </div>


        <!-- ===================================================== -->
        <!-- ESTIMATED PROFIT -->
        <!-- ===================================================== -->

        <div
            class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900"
        >
            <p
                class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400"
            >
                Estimated Profit
            </p>

            <p
                class="mt-2 text-2xl font-bold
                {{
                    (float) $financialSummary['estimated_profit'] >= 0
                        ? 'text-green-600 dark:text-green-400'
                        : 'text-red-600 dark:text-red-400'
                }}"
            >
                Rp {{ number_format(
                    (float) $financialSummary['estimated_profit'],
                    0,
                    ',',
                    '.'
                ) }}
            </p>

            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                Estimasi profit Confirm + Prospect. Event Cancel tidak dihitung.
            </p>
        </div>


        <!-- ===================================================== -->
        <!-- CASH SURPLUS -->
        <!-- ===================================================== -->

        <div
            class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900"
        >
            <p
                class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400"
            >
                Cash Surplus
            </p>

            <p
                class="mt-2 text-2xl font-bold
                {{
                    (float) $financialSummary['cash_surplus'] >= 0
                        ? 'text-green-600 dark:text-green-400'
                        : 'text-red-600 dark:text-red-400'
                }}"
            >
                Rp {{ number_format(
                    (float) $financialSummary['cash_surplus'],
                    0,
                    ',',
                    '.'
                ) }}
            </p>

            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                Payment Received dikurangi Expense aktual.
            </p>
        </div>
    </div>


    <!-- ========================================================= -->
    <!-- PAYMENT STATUS STATISTICS -->
    <!-- ========================================================= -->

    <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">

        <!-- TOTAL -->
        <div
            class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900"
        >
            <p
                class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400"
            >
                Total Invoice
            </p>

            <p
                class="mt-2 text-2xl font-bold text-gray-800 dark:text-white"
            >
                {{ number_format($invoiceStats['total']) }}
            </p>
        </div>


        <!-- PAID -->
        <div
            class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900"
        >
            <p
                class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400"
            >
                Paid
            </p>

            <p
                class="mt-2 text-2xl font-bold text-green-600 dark:text-green-400"
            >
                {{ number_format($invoiceStats['paid']) }}
            </p>
        </div>


        <!-- PARTIAL -->
        <div
            class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900"
        >
            <p
                class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400"
            >
                Partial
            </p>

            <p
                class="mt-2 text-2xl font-bold text-yellow-600 dark:text-yellow-400"
            >
                {{ number_format($invoiceStats['partial']) }}
            </p>
        </div>


        <!-- UNPAID -->
        <div
            class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900"
        >
            <p
                class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400"
            >
                Unpaid
            </p>

            <p
                class="mt-2 text-2xl font-bold text-red-600 dark:text-red-400"
            >
                {{ number_format($invoiceStats['unpaid']) }}
            </p>
        </div>
    </div>


    <!-- ========================================================= -->
    <!-- MONTHLY PERFORMANCE -->
    <!-- ========================================================= -->

    <div
        class="mt-6 overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900"
    >
        <div
            class="border-b border-gray-200 px-6 py-4 dark:border-gray-800"
        >
            <h2
                class="text-lg font-semibold text-gray-800 dark:text-white"
            >
                Monthly Performance
            </h2>

            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Financial performance per month for {{ $year }}.
            </p>
        </div>


        <div class="overflow-x-auto">
            <table class="w-full min-w-[1050px]">

                <thead>
                    <tr
                        class="border-b border-gray-200 bg-gray-50 dark:border-gray-800 dark:bg-gray-950"
                    >
                        <th
                            class="px-5 py-4 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400"
                        >
                            Month
                        </th>

                        <th
                            class="px-5 py-4 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400"
                        >
                            Revenue
                        </th>

                        <th
                            class="px-5 py-4 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400"
                        >
                            Payment Received
                        </th>

                        <th
                            class="px-5 py-4 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400"
                        >
                            Expense
                        </th>

                        <th
                            class="px-5 py-4 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400"
                        >
                            Est. Profit
                        </th>

                        <th
                            class="px-5 py-4 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400"
                        >
                            Cash Surplus
                        </th>
                    </tr>
                </thead>


                <tbody>
                    @foreach ($monthlyPerformance as $month)
                        <tr
                            class="border-b border-gray-100 dark:border-gray-800"
                        >
                            <!-- MONTH -->
                            <td class="px-5 py-4">
                                <p
                                    class="font-semibold text-gray-800 dark:text-white"
                                >
                                    {{ $month['month_full'] }}
                                </p>

                                <p
                                    class="mt-1 text-xs text-gray-500 dark:text-gray-400"
                                >
                                    {{ $month['month'] }} {{ $year }}
                                </p>
                            </td>


                            <!-- REVENUE -->
                            <td
                                class="whitespace-nowrap px-5 py-4 text-right font-medium text-gray-800 dark:text-gray-200"
                            >
                                Rp {{ number_format(
                                    (float) $month['revenue'],
                                    0,
                                    ',',
                                    '.'
                                ) }}
                            </td>


                            <!-- RECEIVED -->
                            <td
                                class="whitespace-nowrap px-5 py-4 text-right font-medium text-green-600 dark:text-green-400"
                            >
                                Rp {{ number_format(
                                    (float) $month['received'],
                                    0,
                                    ',',
                                    '.'
                                ) }}
                            </td>


                            <!-- EXPENSE -->
                            <td
                                class="whitespace-nowrap px-5 py-4 text-right font-medium text-orange-600 dark:text-orange-400"
                            >
                                Rp {{ number_format(
                                    (float) $month['expense'],
                                    0,
                                    ',',
                                    '.'
                                ) }}
                            </td>


                            <!-- PROFIT -->
                            <td
                                class="whitespace-nowrap px-5 py-4 text-right font-semibold
                                {{
                                    (float) $month['profit'] >= 0
                                        ? 'text-green-600 dark:text-green-400'
                                        : 'text-red-600 dark:text-red-400'
                                }}"
                            >
                                Rp {{ number_format(
                                    (float) $month['profit'],
                                    0,
                                    ',',
                                    '.'
                                ) }}
                            </td>


                            <!-- CASH SURPLUS -->
                            <td
                                class="whitespace-nowrap px-5 py-4 text-right font-semibold
                                {{
                                    (float) $month['cash_surplus'] >= 0
                                        ? 'text-green-600 dark:text-green-400'
                                        : 'text-red-600 dark:text-red-400'
                                }}"
                            >
                                Rp {{ number_format(
                                    (float) $month['cash_surplus'],
                                    0,
                                    ',',
                                    '.'
                                ) }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>

            </table>
        </div>
    </div>


    <!-- ========================================================= -->
    <!-- EXPENSE BREAKDOWN -->
    <!-- ========================================================= -->

    <div
        class="mt-6 overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900"
    >
        <div
            class="border-b border-gray-200 px-6 py-4 dark:border-gray-800"
        >
            <h2
                class="text-lg font-semibold text-gray-800 dark:text-white"
            >
                Expense Breakdown
            </h2>

            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Expense grouped by category.
            </p>
        </div>


        <div class="overflow-x-auto">
            <table class="w-full">

                <thead>
                    <tr
                        class="border-b border-gray-200 bg-gray-50 dark:border-gray-800 dark:bg-gray-950"
                    >
                        <th
                            class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400"
                        >
                            Category
                        </th>

                        <th
                            class="px-6 py-4 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400"
                        >
                            Amount
                        </th>
                    </tr>
                </thead>


                <tbody>
                    @forelse ($expenseByCategory as $category)
                        <tr
                            class="border-b border-gray-100 dark:border-gray-800"
                        >
                            <td
                                class="px-6 py-4 font-medium text-gray-800 dark:text-white"
                            >
                                {{ $category['category'] }}
                            </td>

                            <td
                                class="whitespace-nowrap px-6 py-4 text-right font-semibold text-orange-600 dark:text-orange-400"
                            >
                                Rp {{ number_format(
                                    (float) $category['total'],
                                    0,
                                    ',',
                                    '.'
                                ) }}
                            </td>
                        </tr>

                    @empty
                        <tr>
                            <td
                                colspan="2"
                                class="px-6 py-10 text-center text-sm text-gray-500 dark:text-gray-400"
                            >
                                No expense data found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>

            </table>
        </div>
    </div>


    <!-- ========================================================= -->
    <!-- INVOICE PERFORMANCE -->
    <!-- ========================================================= -->

    <div
        class="mt-6 overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900"
    >
        <div
            class="border-b border-gray-200 px-6 py-4 dark:border-gray-800"
        >
            <div
                class="flex flex-wrap items-center justify-between gap-3"
            >
                <div>
                    <h2
                        class="text-lg font-semibold text-gray-800 dark:text-white"
                    >
                        Invoice Performance
                    </h2>

                    <p
                        class="mt-1 text-sm text-gray-500 dark:text-gray-400"
                    >
                        Revenue, payment, expense, estimated profit, and cash position per invoice.
                    </p>
                </div>

                <div>
                    @if ($eventStatus)
                        <span
                            class="text-xs font-medium text-gray-500 dark:text-gray-400"
                        >
                            Showing:
                            <strong>
                                {{ strtoupper($eventStatus) }}
                            </strong>
                        </span>
                    @else
                        <span
                            class="text-xs font-medium text-gray-500 dark:text-gray-400"
                        >
                            Showing all event statuses
                        </span>
                    @endif
                </div>
            </div>
        </div>


        <div class="overflow-x-auto">
            <table class="w-full min-w-[1550px]">

                <!-- ================================================= -->
                <!-- TABLE HEADER -->
                <!-- ================================================= -->

                <thead>
                    <tr
                        class="border-b border-gray-200 bg-gray-50 dark:border-gray-800 dark:bg-gray-950"
                    >
                        <th
                            class="px-5 py-4 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400"
                        >
                            Invoice
                        </th>

                        <th
                            class="px-5 py-4 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400"
                        >
                            Customer / Subject
                        </th>

                        <th
                            class="px-5 py-4 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400"
                        >
                            Invoice Value
                        </th>

                        <th
                            class="px-5 py-4 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400"
                        >
                            Paid
                        </th>

                        <th
                            class="px-5 py-4 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400"
                        >
                            Outstanding
                        </th>

                        <th
                            class="px-5 py-4 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400"
                        >
                            Expense
                        </th>

                        <th
                            class="px-5 py-4 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400"
                        >
                            Est. Profit
                        </th>

                        <th
                            class="px-5 py-4 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400"
                        >
                            Cash Surplus
                        </th>

                        <th
                            class="px-5 py-4 text-center text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400"
                        >
                            Event Status
                        </th>

                        <th
                            class="px-5 py-4 text-center text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400"
                        >
                            Payment
                        </th>

                        <th
                            class="px-5 py-4 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400"
                        >
                            Action
                        </th>
                    </tr>
                </thead>


                <!-- ================================================= -->
                <!-- TABLE BODY -->
                <!-- ================================================= -->

                <tbody>
                    @forelse ($invoicePerformance as $invoice)

                        <tr
                            class="border-b border-gray-100 transition hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-gray-950/50"
                        >

                            <!-- ===================================== -->
                            <!-- INVOICE -->
                            <!-- ===================================== -->

                            <td class="px-5 py-4">
                                <a
                                    href="{{ route('admin.invoices.show', $invoice['id']) }}"
                                    class="font-semibold text-blue-600 hover:underline dark:text-blue-400"
                                >
                                    {{ $invoice['invoice_number'] }}
                                </a>

                                @if ($invoice['issued_at'])
                                    <p
                                        class="mt-1 text-xs text-gray-500 dark:text-gray-400"
                                    >
                                        {{ $invoice['issued_at']->format('d M Y') }}
                                    </p>
                                @endif
                            </td>


                            <!-- ===================================== -->
                            <!-- CUSTOMER -->
                            <!-- ===================================== -->

                            <td class="px-5 py-4">
                                <p
                                    class="font-medium text-gray-800 dark:text-white"
                                >
                                    {{ $invoice['customer'] }}
                                </p>

                                <p
                                    class="mt-1 max-w-[250px] truncate text-xs text-gray-500 dark:text-gray-400"
                                    title="{{ $invoice['subject'] }}"
                                >
                                    {{ $invoice['subject'] ?: '-' }}
                                </p>
                            </td>


                            <!-- ===================================== -->
                            <!-- INVOICE VALUE -->
                            <!-- ===================================== -->

                            <td
                                class="whitespace-nowrap px-5 py-4 text-right font-medium text-gray-800 dark:text-gray-200"
                            >
                                Rp {{ number_format(
                                    (float) $invoice['revenue'],
                                    0,
                                    ',',
                                    '.'
                                ) }}
                            </td>


                            <!-- ===================================== -->
                            <!-- PAID -->
                            <!-- ===================================== -->

                            <td
                                class="whitespace-nowrap px-5 py-4 text-right font-medium text-green-600 dark:text-green-400"
                            >
                                Rp {{ number_format(
                                    (float) $invoice['paid'],
                                    0,
                                    ',',
                                    '.'
                                ) }}
                            </td>


                            <!-- ===================================== -->
                            <!-- OUTSTANDING -->
                            <!-- ===================================== -->

                            <td
                                class="whitespace-nowrap px-5 py-4 text-right font-medium
                                {{
                                    (float) $invoice['outstanding'] > 0
                                        ? 'text-red-600 dark:text-red-400'
                                        : 'text-gray-500 dark:text-gray-400'
                                }}"
                            >
                                @if ($invoice['event_status'] === 'cancel')
                                    Rp 0
                                @elseif ($invoice['event_status'] === 'prospect')
                                    -
                                @else
                                    Rp {{ number_format(
                                        (float) $invoice['outstanding'],
                                        0,
                                        ',',
                                        '.'
                                    ) }}
                                @endif
                            </td>


                            <!-- ===================================== -->
                            <!-- EXPENSE -->
                            <!-- ===================================== -->

                            <td
                                class="whitespace-nowrap px-5 py-4 text-right font-medium text-orange-600 dark:text-orange-400"
                            >
                                Rp {{ number_format(
                                    (float) $invoice['expense'],
                                    0,
                                    ',',
                                    '.'
                                ) }}
                            </td>


                            <!-- ===================================== -->
                            <!-- ESTIMATED PROFIT -->
                            <!-- ===================================== -->

                            <td
                                class="whitespace-nowrap px-5 py-4 text-right"
                            >
                                @if ($invoice['event_status'] === 'cancel')

                                    <p
                                        class="font-semibold text-gray-400 dark:text-gray-500"
                                    >
                                        Rp 0
                                    </p>

                                    <p
                                        class="mt-1 text-xs text-red-500"
                                    >
                                        Cancelled
                                    </p>

                                @else

                                    <p
                                        class="font-semibold
                                        {{
                                            (float) $invoice['profit'] >= 0
                                                ? 'text-green-600 dark:text-green-400'
                                                : 'text-red-600 dark:text-red-400'
                                        }}"
                                    >
                                        Rp {{ number_format(
                                            (float) $invoice['profit'],
                                            0,
                                            ',',
                                            '.'
                                        ) }}
                                    </p>

                                    @if ($invoice['event_status'] === 'prospect')
                                        <p
                                            class="mt-1 text-xs text-blue-500"
                                        >
                                            Potential
                                        </p>
                                    @endif

                                @endif
                            </td>


                            <!-- ===================================== -->
                            <!-- CASH SURPLUS -->
                            <!-- ===================================== -->

                            <td
                                class="whitespace-nowrap px-5 py-4 text-right font-semibold
                                {{
                                    (float) $invoice['cash_surplus'] >= 0
                                        ? 'text-green-600 dark:text-green-400'
                                        : 'text-red-600 dark:text-red-400'
                                }}"
                            >
                                Rp {{ number_format(
                                    (float) $invoice['cash_surplus'],
                                    0,
                                    ',',
                                    '.'
                                ) }}
                            </td>


                            <!-- ===================================== -->
                            <!-- EVENT STATUS -->
                            <!-- ===================================== -->

                            <td class="px-5 py-4 text-center">

                                @if ($invoice['event_status'] === 'confirm')

                                    <span
                                        class="inline-flex rounded-full bg-green-100 px-3 py-1 text-xs font-bold text-green-700 dark:bg-green-900/40 dark:text-green-300"
                                    >
                                        CONFIRM
                                    </span>

                                @elseif ($invoice['event_status'] === 'cancel')

                                    <span
                                        class="inline-flex rounded-full bg-red-100 px-3 py-1 text-xs font-bold text-red-700 dark:bg-red-900/40 dark:text-red-300"
                                    >
                                        CANCEL
                                    </span>

                                @else

                                    <span
                                        class="inline-flex rounded-full bg-blue-100 px-3 py-1 text-xs font-bold text-blue-700 dark:bg-blue-900/40 dark:text-blue-300"
                                    >
                                        PROSPECT
                                    </span>

                                @endif
                            </td>


                            <!-- ===================================== -->
                            <!-- PAYMENT STATUS -->
                            <!-- ===================================== -->

                            <td class="px-5 py-4 text-center">

                                @if ($invoice['status'] === 'paid')

                                    <span
                                        class="inline-flex rounded-full bg-green-100 px-3 py-1 text-xs font-bold text-green-700 dark:bg-green-900/40 dark:text-green-300"
                                    >
                                        PAID
                                    </span>

                                @elseif ($invoice['status'] === 'partial')

                                    <span
                                        class="inline-flex rounded-full bg-yellow-400 px-3 py-1 text-xs font-bold text-gray-900"
                                    >
                                        PARTIAL
                                    </span>

                                @else

                                    <span
                                        class="inline-flex rounded-full bg-red-100 px-3 py-1 text-xs font-bold text-red-700 dark:bg-red-900/40 dark:text-red-300"
                                    >
                                        UNPAID
                                    </span>

                                @endif
                            </td>


                            <!-- ===================================== -->
                            <!-- ACTION -->
                            <!-- ===================================== -->

                            <td class="px-5 py-4 text-right">
                                <a
                                    href="{{ route('admin.invoices.show', $invoice['id']) }}"
                                    class="text-sm font-semibold text-blue-600 hover:underline dark:text-blue-400"
                                >
                                    View
                                </a>
                            </td>
                        </tr>

                    @empty

                        <tr>
                            <td
                                colspan="11"
                                class="px-6 py-14 text-center"
                            >
                                <p
                                    class="font-medium text-gray-700 dark:text-gray-300"
                                >
                                    No financial data found.
                                </p>

                                <p
                                    class="mt-1 text-sm text-gray-500 dark:text-gray-400"
                                >
                                    Try changing the year or event status filter.
                                </p>
                            </td>
                        </tr>

                    @endforelse
                </tbody>

            </table>
        </div>
    </div>


    <!-- ========================================================= -->
    <!-- FINANCIAL DEFINITIONS -->
    <!-- ========================================================= -->

    <div
        class="mt-6 rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900"
    >
        <h2
            class="text-lg font-semibold text-gray-800 dark:text-white"
        >
            Financial Calculation
        </h2>

        <div
            class="mt-4 grid gap-4 text-sm md:grid-cols-2 xl:grid-cols-3"
        >

            <div>
                <p
                    class="font-semibold text-gray-800 dark:text-white"
                >
                    Revenue
                </p>

                <p
                    class="mt-1 text-gray-500 dark:text-gray-400"
                >
                    Invoice value dari event Confirm.
                </p>
            </div>


            <div>
                <p
                    class="font-semibold text-gray-800 dark:text-white"
                >
                    Payment Received
                </p>

                <p
                    class="mt-1 text-gray-500 dark:text-gray-400"
                >
                    Seluruh pembayaran aktual yang sudah diterima, termasuk Partial dan Paid.
                </p>
            </div>


            <div>
                <p
                    class="font-semibold text-gray-800 dark:text-white"
                >
                    Outstanding
                </p>

                <p
                    class="mt-1 text-gray-500 dark:text-gray-400"
                >
                    Sisa pembayaran invoice Confirm.
                </p>
            </div>


            <div>
                <p
                    class="font-semibold text-gray-800 dark:text-white"
                >
                    Expense
                </p>

                <p
                    class="mt-1 text-gray-500 dark:text-gray-400"
                >
                    Pengeluaran aktual yang sudah dicatat.
                </p>
            </div>


            <div>
                <p
                    class="font-semibold text-gray-800 dark:text-white"
                >
                    Estimated Profit
                </p>

                <p
                    class="mt-1 text-gray-500 dark:text-gray-400"
                >
                    Confirm + Prospect dikurangi expense. Cancel tidak masuk perhitungan.
                </p>
            </div>


            <div>
                <p
                    class="font-semibold text-gray-800 dark:text-white"
                >
                    Cash Surplus
                </p>

                <p
                    class="mt-1 text-gray-500 dark:text-gray-400"
                >
                    Payment Received dikurangi Expense.
                </p>
            </div>

        </div>
    </div>

</x-admin::layouts>