<x-admin::layouts>
    <x-slot:title>
        {{ $invoice->invoice_number }}
    </x-slot>

    <div class="flex items-center justify-between gap-4">
        <div>
            <p class="text-xl font-bold text-gray-800 dark:text-white">
                {{ $invoice->invoice_number }}
            </p>

            <p class="text-gray-600 dark:text-gray-300">
                {{ $invoice->subject }}
            </p>
        </div>

        <span class="font-semibold">
            {{ strtoupper($invoice->status) }}
        </span>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <div class="rounded-lg bg-white p-5 dark:bg-gray-900">
            <h2 class="text-lg font-semibold">
                Invoice Summary
            </h2>

            <div class="mt-4 space-y-2">
                <p>
                    Total:
                    Rp {{ number_format((float) $invoice->grand_total, 0, ',', '.') }}
                </p>

                <p>
                    Paid:
                    Rp {{ number_format((float) $invoice->paid_amount, 0, ',', '.') }}
                </p>

                <p>
                    Balance:
                    Rp {{ number_format((float) $invoice->balance_due, 0, ',', '.') }}
                </p>
            </div>
        </div>

        <div class="rounded-lg bg-white p-5 dark:bg-gray-900">
            <h2 class="text-lg font-semibold">
                Add Payment
            </h2>

            @if ($invoice->status !== 'paid')
                <form
                    method="POST"
                    action="{{ route('admin.invoices.payments.store', $invoice->id) }}"
                    class="mt-4 space-y-4"
                >
                    @csrf

                    <div>
                        <label>Amount</label>

                        <input
                            type="number"
                            name="amount"
                            min="1"
                            max="{{ $invoice->balance_due }}"
                            class="w-full rounded border p-2"
                            required
                        >

                        @error('amount')
                            <p class="text-red-600">
                                {{ $message }}
                            </p>
                        @enderror
                    </div>

                    <div>
                        <label>Payment Method</label>

                        <select
                            name="payment_method"
                            class="w-full rounded border p-2"
                        >
                            <option value="bank_transfer">Bank Transfer</option>
                            <option value="cash">Cash</option>
                            <option value="other">Other</option>
                        </select>
                    </div>

                    <div>
                        <label>Reference Number</label>

                        <input
                            type="text"
                            name="reference_number"
                            class="w-full rounded border p-2"
                        >
                    </div>

                    <div>
                        <label>Notes</label>

                        <textarea
                            name="notes"
                            class="w-full rounded border p-2"
                        ></textarea>
                    </div>

                    <button
                        type="submit"
                        class="rounded bg-blue-600 px-4 py-2 text-white"
                    >
                        Add Payment
                    </button>
                </form>
            @else
                <p class="mt-4">
                    Invoice sudah lunas.
                </p>
            @endif
        </div>
    </div>

    <div class="mt-6 rounded-lg bg-white p-5 dark:bg-gray-900">
        <h2 class="text-lg font-semibold">
            Invoice Items
        </h2>

        <div class="mt-4 space-y-3">
            @foreach ($invoice->items as $item)
                <div class="flex justify-between border-b pb-2">
                    <div>
                        {{ $item->name }}
                        × {{ $item->quantity }}
                    </div>

                    <div>
                        Rp {{ number_format((float) $item->total, 0, ',', '.') }}
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <div class="mt-6 rounded-lg bg-white p-5 dark:bg-gray-900">
        <h2 class="text-lg font-semibold">
            Payment History
        </h2>

        <div class="mt-4 space-y-3">
            @forelse ($invoice->payments as $payment)
                <div class="border-b pb-3">
                    <strong>
                        Rp {{ number_format((float) $payment->amount, 0, ',', '.') }}
                    </strong>

                    <p>
                        {{ $payment->payment_method }}
                    </p>

                    <p>
                        {{ $payment->reference_number }}
                    </p>

                    <p>
                        {{ optional($payment->paid_at)->format('d M Y H:i') }}
                    </p>
                </div>
            @empty
                <p>Belum ada pembayaran.</p>
            @endforelse
        </div>
    </div>
</x-admin::layouts>