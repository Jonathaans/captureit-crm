<x-admin::layouts>
    <x-slot:title>
        Invoices
    </x-slot>

    <div class="flex items-center justify-between gap-4 max-sm:flex-wrap">
        <p class="text-xl font-bold text-gray-800 dark:text-white">
            Invoices
        </p>
    </div>

    <div class="mt-6 rounded-lg bg-white p-4 dark:bg-gray-900">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="border-b dark:border-gray-800">
                        <th class="p-3 text-left">Invoice</th>
                        <th class="p-3 text-left">Subject</th>
                        <th class="p-3 text-left">Total</th>
                        <th class="p-3 text-left">Paid</th>
                        <th class="p-3 text-left">Balance</th>
                        <th class="p-3 text-left">Status</th>
                        <th class="p-3 text-left">Action</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($invoices as $invoice)
                        <tr class="border-b dark:border-gray-800">
                            <td class="p-3">
                                {{ $invoice->invoice_number }}
                            </td>

                            <td class="p-3">
                                {{ $invoice->subject }}
                            </td>

                            <td class="p-3">
                                {{ number_format((float) $invoice->grand_total, 0, ',', '.') }}
                            </td>

                            <td class="p-3">
                                {{ number_format((float) $invoice->paid_amount, 0, ',', '.') }}
                            </td>

                            <td class="p-3">
                                {{ number_format((float) $invoice->balance_due, 0, ',', '.') }}
                            </td>

                            <td class="p-3">
                                {{ strtoupper($invoice->status) }}
                            </td>

                            <td class="p-3">
                                <a
                                    href="{{ route('admin.invoices.show', $invoice->id) }}"
                                    class="text-blue-600"
                                >
                                    View
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="p-6 text-center">
                                Belum ada invoice.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $invoices->links() }}
        </div>
    </div>
</x-admin::layouts>