<x-admin::layouts>
    <x-slot:title>Create Purchase Order</x-slot>

    <?php $selectedInvoiceId = old('invoice_id', $selectedInvoice?->id); ?>

    <div style="width:100%;max-width:1280px;margin:0 auto;display:flex;flex-direction:column;gap:14px;">
        <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div style="display:flex;justify-content:space-between;gap:16px;flex-wrap:wrap;">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Create Purchase Order</h1>
                    <p class="mt-1 text-sm text-gray-500">
                        Vendor event / outsource. Save sebagai DRAFT, lalu Finance Release saat nominal final.
                    </p>
                </div>

                <a href="{{ route('admin.purchase-orders.index') }}" class="secondary-button">Back</a>
            </div>
        </section>

        <?php if ($errors->any()): ?>
            <div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                <ul class="list-disc pl-5">
                    <?php foreach ($errors->all() as $error): ?>
                        <li>{{ $error }}</li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <form
                method="GET"
                action="{{ route('admin.purchase-orders.create') }}"
                id="invoice-load-form"
            >
                <label class="mb-1.5 block text-sm font-bold">Related Invoice / Event *</label>

                <div style="display:grid;grid-template-columns:minmax(0,1fr) auto;gap:10px;align-items:end;">
                    <select
                        id="invoice-selector"
                        name="invoice_id"
                        class="w-full rounded-md border px-3 py-2 dark:border-gray-800 dark:bg-gray-950"
                        required
                    >
                        <option value="">Select CONFIRM Invoice</option>

                        <?php foreach ($invoices as $invoiceOption): ?>
                            <option value="{{ $invoiceOption->id }}" {{ (string) $selectedInvoiceId === (string) $invoiceOption->id ? 'selected' : '' }}>
                                {{ $invoiceOption->invoice_number }}
                                - {{ $invoiceOption->project_code }}
                                - {{ $invoiceOption->subject }}
                                - {{ $invoiceOption->person?->name }}
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <button type="submit" class="primary-button">
                        Load Event
                    </button>
                </div>
            </form>

            <p class="mt-2 text-xs text-gray-500">
                Hanya Invoice/Event CONFIRM yang dapat dipakai. Setelah event dimuat, form Vendor, Product/Service, nominal PO, Terms of Payment, Adjustment, Notes, dan Save Draft akan muncul di bawah.
            </p>
        </section>

        <?php if ($selectedInvoice): ?>
            <form
                method="POST"
                action="{{ route('admin.purchase-orders.store') }}"
                id="po-form"
                style="display:flex;flex-direction:column;gap:14px;"
            >
                @csrf
                <input type="hidden" name="invoice_id" value="{{ $selectedInvoice->id }}">

                <section class="rounded-xl border border-blue-200 bg-blue-50 p-4 text-sm text-blue-800">
                    <strong>{{ $selectedInvoice->invoice_number }}</strong>
                    · {{ $selectedInvoice->project_code }}
                    · {{ $selectedInvoice->subject }}
                    · {{ $selectedInvoice->person?->name }}

                    <div class="mt-2">
                        Release PO = <strong>Grand Total PO otomatis menjadi Expense Invoice ini</strong>.
                    </div>
                </section>

                <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <h2 class="text-lg font-bold">Vendor</h2>

                    <div class="mt-4" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:14px;">
                        <div>
                            <label class="mb-1.5 block text-sm font-medium">Vendor Name *</label>
                            <input type="text" name="vendor_name" value="{{ old('vendor_name') }}" class="w-full rounded-md border px-3 py-2" placeholder="Vendor Photobooth Semarang" required>
                        </div>

                        <div>
                            <label class="mb-1.5 block text-sm font-medium">Order Date *</label>
                            <input type="date" name="order_date" value="{{ old('order_date', now()->toDateString()) }}" class="w-full rounded-md border px-3 py-2" required>
                        </div>

                        <div>
                            <label class="mb-1.5 block text-sm font-medium">
                                Terms of Payment *
                            </label>

                            <select
                                name="payment_terms"
                                class="w-full rounded-md border px-3 py-2"
                                required
                            >
                                <?php foreach (\Webkul\Invoice\Models\PurchaseOrder::paymentTermsOptions() as $termValue => $termLabel): ?>
                                    <option
                                        value="{{ $termValue }}"
                                        {{ old('payment_terms', '7_days') === $termValue ? 'selected' : '' }}
                                    >
                                        {{ $termLabel }}
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            <p class="mt-1 text-xs text-gray-500">
                                7 / 14 / 21 / 30 hari atau Full Payment Before Event.
                            </p>
                        </div>

                        <div>
                            <label class="mb-1.5 block text-sm font-medium">Phone</label>
                            <input type="text" name="vendor_phone" value="{{ old('vendor_phone') }}" class="w-full rounded-md border px-3 py-2">
                        </div>

                        <div>
                            <label class="mb-1.5 block text-sm font-medium">Email</label>
                            <input type="email" name="vendor_email" value="{{ old('vendor_email') }}" class="w-full rounded-md border px-3 py-2">
                        </div>
                    </div>

                    <div class="mt-4">
                        <label class="mb-1.5 block text-sm font-medium">Vendor Address</label>
                        <textarea name="vendor_address" rows="3" class="w-full rounded-md border px-3 py-2">{{ old('vendor_address') }}</textarea>
                    </div>
                </section>

                <section class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <div class="border-b border-gray-200 p-4">
                        <h2 class="font-bold">Invoice Products</h2>
                        <p class="mt-1 text-xs text-gray-500">
                            Centang product yang dilempar ke vendor. Bisa Classic saja, 360 saja, atau keduanya.
                        </p>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="border-b border-gray-200 bg-gray-50 text-left text-xs uppercase text-gray-500">
                                <tr>
                                    <th class="px-4 py-3">Use</th>
                                    <th class="px-4 py-3">Product</th>
                                    <th class="px-4 py-3">Qty</th>
                                    <th class="px-4 py-3">Unit</th>
                                    <th class="px-4 py-3">Vendor Unit Price</th>
                                    <th class="px-4 py-3 text-right">Total</th>
                                </tr>
                            </thead>

                            <tbody>
                                <?php foreach ($selectedInvoice->items as $invoiceItem): ?>
                                    <?php
                                        $line = old('invoice_items.'.$invoiceItem->id, []);
                                        $enabled = (bool) ($line['enabled'] ?? false);
                                    ?>

                                    <tr class="border-b border-gray-100 last:border-b-0" data-po-row>
                                        <td class="px-4 py-3">
                                            <input type="hidden" name="invoice_items[{{ $invoiceItem->id }}][enabled]" value="0">
                                            <input type="checkbox" name="invoice_items[{{ $invoiceItem->id }}][enabled]" value="1" data-po-enabled {{ $enabled ? 'checked' : '' }}>
                                        </td>

                                        <td class="px-4 py-3">
                                            <p class="font-bold">{{ $invoiceItem->name }}</p>
                                            <?php if ($invoiceItem->description): ?>
                                                <p class="mt-1 max-w-[360px] text-xs text-gray-500">{{ $invoiceItem->description }}</p>
                                            <?php endif; ?>
                                        </td>

                                        <td class="px-4 py-3">
                                            <input type="number" step="0.01" min="0.01" name="invoice_items[{{ $invoiceItem->id }}][quantity]" value="{{ $line['quantity'] ?? $invoiceItem->quantity ?? 1 }}" class="w-24 rounded-md border px-3 py-2" data-po-qty>
                                        </td>

                                        <td class="px-4 py-3">
                                            <input type="text" name="invoice_items[{{ $invoiceItem->id }}][unit]" value="{{ $line['unit'] ?? 'job' }}" class="w-24 rounded-md border px-3 py-2">
                                        </td>

                                        <td class="px-4 py-3">
                                            <input type="number" step="0.01" min="0" name="invoice_items[{{ $invoiceItem->id }}][unit_price]" value="{{ $line['unit_price'] ?? 0 }}" class="w-40 rounded-md border px-3 py-2" data-po-price>
                                        </td>

                                        <td class="whitespace-nowrap px-4 py-3 text-right font-bold" data-po-line-total>Rp 0</td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </section>

                <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;">
                        <div>
                            <h2 class="font-bold">Additional Vendor Services</h2>
                            <p class="mt-1 text-xs text-gray-500">Transport, operator, hotel, atau biaya vendor lain.</p>
                        </div>

                        <button
                            type="button"
                            class="secondary-button"
                            id="add-custom-item"
                            onclick="window.addPurchaseOrderService(); return false;"
                        >
                            + Add Service
                        </button>
                    </div>

                    <div id="custom-items" class="mt-4" style="display:flex;flex-direction:column;gap:10px;"></div>
                </section>

                <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <div style="display:grid;grid-template-columns:minmax(280px,1fr) minmax(320px,420px);gap:20px;">
                        <div>
                            <label class="mb-1.5 block text-sm font-medium">Notes</label>
                            <textarea name="notes" rows="6" class="w-full rounded-md border px-3 py-2" placeholder="Scope vendor, PIC, detail pelaksanaan, payment term vendor, dll.">{{ old('notes') }}</textarea>
                        </div>

                        <div>
                            <div class="mt-3">
                                <label class="mb-1.5 block text-sm font-medium">Adjustment</label>
                                <input type="number" step="0.01" name="adjustment_amount" value="{{ old('adjustment_amount', 0) }}" class="w-full rounded-md border px-3 py-2" data-po-adjustment>
                                <p class="mt-1 text-xs text-gray-500">Bisa negatif untuk discount vendor.</p>
                            </div>

                            <div class="mt-5 rounded-lg bg-gray-50 p-4">
                                <div class="flex justify-between text-sm"><span>Sub Total</span><strong id="po-subtotal">Rp 0</strong></div>
                                                                <div class="mt-2 flex justify-between text-sm"><span>Adjustment</span><strong id="po-adjustment">Rp 0</strong></div>
                                <div class="mt-3 flex justify-between border-t pt-3 text-lg font-bold"><span>GRAND TOTAL</span><strong id="po-grand-total">Rp 0</strong></div>
                            </div>
                        </div>
                    </div>
                </section>

                <div style="display:flex;justify-content:flex-end;gap:8px;">
                    <a href="{{ route('admin.purchase-orders.index') }}" class="secondary-button">Cancel</a>
                    <button type="submit" class="primary-button">Save Draft PO</button>
                </div>
            </form>
        <?php endif; ?>
    </div>

    <script>
        (function () {
            var customIndex = 0;

            function money(value) {
                return new Intl.NumberFormat(
                    'id-ID',
                    {
                        style: 'currency',
                        currency: 'IDR',
                        maximumFractionDigits: 0
                    }
                ).format(
                    Number(value || 0)
                );
            }

            function recalculatePurchaseOrder() {
                var subtotal = 0;

                var rows =
                    document.querySelectorAll(
                        '[data-po-row]'
                    );

                for (
                    var index = 0;
                    index < rows.length;
                    index++
                ) {
                    var row =
                        rows[index];

                    var enabled =
                        row.querySelector(
                            '[data-po-enabled]'
                        );

                    var qtyInput =
                        row.querySelector(
                            '[data-po-qty]'
                        );

                    var priceInput =
                        row.querySelector(
                            '[data-po-price]'
                        );

                    var qty =
                        Number(
                            qtyInput
                                ? qtyInput.value
                                : 0
                        );

                    var price =
                        Number(
                            priceInput
                                ? priceInput.value
                                : 0
                        );

                    var total =
                        qty * price;

                    var totalLabel =
                        row.querySelector(
                            '[data-po-line-total]'
                        );

                    if (totalLabel) {
                        totalLabel.textContent =
                            money(total);
                    }

                    /*
                     * Invoice Product rows have a checkbox.
                     * Custom Service rows do not, so they always count.
                     */
                    if (
                        ! enabled
                        || enabled.checked
                    ) {
                        subtotal += total;
                    }
                }

                var adjustmentInput =
                    document.querySelector(
                        '[data-po-adjustment]'
                    );

                var adjustment =
                    Number(
                        adjustmentInput
                            ? adjustmentInput.value
                            : 0
                    );

                var subtotalLabel =
                    document.getElementById(
                        'po-subtotal'
                    );

                var adjustmentLabel =
                    document.getElementById(
                        'po-adjustment'
                    );

                var grandTotalLabel =
                    document.getElementById(
                        'po-grand-total'
                    );

                if (subtotalLabel) {
                    subtotalLabel.textContent =
                        money(subtotal);
                }

                if (adjustmentLabel) {
                    adjustmentLabel.textContent =
                        money(adjustment);
                }

                if (grandTotalLabel) {
                    grandTotalLabel.textContent =
                        money(
                            subtotal
                            + adjustment
                        );
                }
            }

            window.addPurchaseOrderService =
                function () {
                    var customContainer =
                        document.getElementById(
                            'custom-items'
                        );

                    if (! customContainer) {
                        console.error(
                            'Purchase Order custom-items container not found.'
                        );

                        return;
                    }

                    var row =
                        document.createElement(
                            'div'
                        );

                    row.setAttribute(
                        'data-po-row',
                        ''
                    );

                    row.className =
                        'rounded-lg border border-gray-200 p-4';

                    var itemIndex =
                        customIndex++;

                    row.innerHTML =
                        '<div style="display:grid;grid-template-columns:minmax(180px,1.2fr) minmax(160px,1fr) 90px 90px 150px auto;gap:8px;align-items:end;">'
                        + '<div>'
                        + '<label class="mb-1 block text-xs font-bold text-gray-500">Service</label>'
                        + '<input type="text" name="custom_items[' + itemIndex + '][name]" class="w-full rounded-md border px-3 py-2" placeholder="Transport / Operator / Hotel" required>'
                        + '</div>'
                        + '<div>'
                        + '<label class="mb-1 block text-xs font-bold text-gray-500">Description</label>'
                        + '<input type="text" name="custom_items[' + itemIndex + '][description]" class="w-full rounded-md border px-3 py-2">'
                        + '</div>'
                        + '<div>'
                        + '<label class="mb-1 block text-xs font-bold text-gray-500">Qty</label>'
                        + '<input type="number" min="0.01" step="0.01" name="custom_items[' + itemIndex + '][quantity]" value="1" class="w-full rounded-md border px-3 py-2" data-po-qty required>'
                        + '</div>'
                        + '<div>'
                        + '<label class="mb-1 block text-xs font-bold text-gray-500">Unit</label>'
                        + '<input type="text" name="custom_items[' + itemIndex + '][unit]" value="job" class="w-full rounded-md border px-3 py-2" required>'
                        + '</div>'
                        + '<div>'
                        + '<label class="mb-1 block text-xs font-bold text-gray-500">Unit Price</label>'
                        + '<input type="number" min="0" step="0.01" name="custom_items[' + itemIndex + '][unit_price]" value="0" class="w-full rounded-md border px-3 py-2" data-po-price required>'
                        + '</div>'
                        + '<div style="display:flex;gap:8px;align-items:center;">'
                        + '<strong class="whitespace-nowrap" data-po-line-total>Rp 0</strong>'
                        + '<button type="button" class="secondary-button" data-remove-row>×</button>'
                        + '</div>'
                        + '</div>';

                    customContainer.appendChild(
                        row
                    );

                    var removeButton =
                        row.querySelector(
                            '[data-remove-row]'
                        );

                    if (removeButton) {
                        removeButton.addEventListener(
                            'click',
                            function () {
                                row.remove();

                                recalculatePurchaseOrder();
                            }
                        );
                    }

                    recalculatePurchaseOrder();
                };

            document.addEventListener(
                'input',
                function (event) {
                    if (
                        event.target
                        && event.target.matches(
                            '[data-po-qty], [data-po-price], [data-po-adjustment]'
                        )
                    ) {
                        recalculatePurchaseOrder();
                    }
                }
            );

            document.addEventListener(
                'change',
                function (event) {
                    if (
                        event.target
                        && event.target.matches(
                            '[data-po-enabled]'
                        )
                    ) {
                        recalculatePurchaseOrder();
                    }
                }
            );

            document.addEventListener(
                'DOMContentLoaded',
                function () {
                    var invoiceSelector =
                        document.getElementById(
                            'invoice-selector'
                        );

                    var invoiceLoadForm =
                        document.getElementById(
                            'invoice-load-form'
                        );

                    if (
                        invoiceSelector
                        && invoiceLoadForm
                    ) {
                        invoiceSelector.addEventListener(
                            'change',
                            function () {
                                if (
                                    invoiceSelector.value
                                ) {
                                    invoiceLoadForm.submit();
                                }
                            }
                        );

                        var currentUrl =
                            new URL(
                                window.location.href
                            );

                        if (
                            invoiceSelector.value
                            && ! currentUrl.searchParams.has(
                                'invoice_id'
                            )
                        ) {
                            invoiceLoadForm.submit();

                            return;
                        }
                    }

                    recalculatePurchaseOrder();
                }
            );

            /*
             * Blade places this script at the end of the page, so DOM may
             * already be ready by the time it executes.
             */
            if (
                document.readyState
                !== 'loading'
            ) {
                recalculatePurchaseOrder();
            }
        })();
    </script>
</x-admin::layouts>
