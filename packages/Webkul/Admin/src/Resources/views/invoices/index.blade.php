<x-admin::layouts>
    <x-slot:title>
        Invoices
    </x-slot>

    <!-- Header -->
    <div class="flex items-center justify-between gap-4 max-sm:flex-wrap">
        <div>
            <h1 class="text-xl font-bold text-gray-800 dark:text-white">
                Invoices
            </h1>

            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Manage customer invoices and payment status.
            </p>
        </div>
    </div>

    <!-- Invoice Table -->
    <div class="mt-6 overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="border-b border-gray-200 bg-gray-50 dark:border-gray-800 dark:bg-gray-950">
                        <th class="px-5 py-4 text-left text-sm font-semibold text-gray-700 dark:text-gray-300">
                            Invoice
                        </th>

                        <th class="px-5 py-4 text-left text-sm font-semibold text-gray-700 dark:text-gray-300">
                            Subject
                        </th>

                        <th class="px-5 py-4 text-right text-sm font-semibold text-gray-700 dark:text-gray-300">
                            Total
                        </th>

                        <th class="px-5 py-4 text-right text-sm font-semibold text-gray-700 dark:text-gray-300">
                            Paid
                        </th>

                        <th class="px-5 py-4 text-right text-sm font-semibold text-gray-700 dark:text-gray-300">
                            Balance
                        </th>

                        <th class="px-5 py-4 text-center text-sm font-semibold text-gray-700 dark:text-gray-300">
                            Status
                        </th>

                        <th class="px-5 py-4 text-right text-sm font-semibold text-gray-700 dark:text-gray-300">
                            Action
                        </th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($invoices as $invoice)
                        <tr class="border-b border-gray-100 transition hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-gray-800/50">
                            <td class="px-5 py-4">
                                <a
                                    href="{{ route('admin.invoices.show', $invoice->id) }}"
                                    class="font-semibold text-blue-600 hover:underline dark:text-blue-400"
                                >
                                    {{ $invoice->invoice_number }}
                                </a>
                            </td>

                            <td class="px-5 py-4 text-gray-700 dark:text-gray-300">
                                {{ $invoice->subject }}
                            </td>

                            <td class="px-5 py-4 text-right font-semibold text-gray-800 dark:text-gray-100">
                                Rp {{ number_format((float) $invoice->grand_total, 0, ',', '.') }}
                            </td>

                            <td class="px-5 py-4 text-right text-gray-700 dark:text-gray-300">
                                Rp {{ number_format((float) $invoice->paid_amount, 0, ',', '.') }}
                            </td>

                            <td class="px-5 py-4 text-right text-gray-700 dark:text-gray-300">
                                Rp {{ number_format((float) $invoice->balance_due, 0, ',', '.') }}
                            </td>

                            <td class="px-5 py-4 text-center">
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
                            </td>

                            <td class="px-5 py-4 text-right">
                                <a
                                    href="{{ route('admin.invoices.show', $invoice->id) }}"
                                    class="font-medium text-blue-600 hover:underline dark:text-blue-400"
                                >
                                    View
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td
                                colspan="7"
                                class="px-5 py-12 text-center text-gray-500 dark:text-gray-400"
                            >
                                Belum ada invoice.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($invoices->hasPages())
            <div class="border-t border-gray-200 px-5 py-4 dark:border-gray-800">
                {{ $invoices->links() }}
            </div>
        @endif
    </div>
</x-admin::layouts>