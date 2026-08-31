<x-admin::layouts>
    <x-slot:title>Edit {{ $purchaseOrder->po_number }}</x-slot>

    <form
        method="POST"
        action="{{ route('admin.purchase-orders.update', $purchaseOrder->id) }}"
        id="po-edit-form"
        style="width:100%;max-width:1280px;margin:0 auto;display:flex;flex-direction:column;gap:14px;"
    >
        @csrf
        @method('PUT')

        <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div style="display:flex;justify-content:space-between;gap:16px;flex-wrap:wrap;">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ $purchaseOrder->po_number }}</h1>
                    <p class="mt-1 text-sm text-gray-500">Edit DRAFT Purchase Order</p>
                </div>

                <div style="display:flex;gap:8px;">
                    <a href="{{ route('admin.purchase-orders.show', $purchaseOrder->id) }}" class="secondary-button">Cancel</a>
                    <button type="submit" class="primary-button">Save Changes</button>
                </div>
            </div>
        </section>

        @if ($errors->any())
            <div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                <ul class="list-disc pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <section class="rounded-xl border border-blue-200 bg-blue-50 p-4 text-sm text-blue-800">
            <strong>{{ $purchaseOrder->invoice_number }}</strong>
            · {{ $purchaseOrder->project_code }}
            · {{ $purchaseOrder->project_name }}

            <div class="mt-2">
                Invoice tidak dapat diganti. Setelah Release, seluruh nilai finansial PO dikunci.
            </div>
        </section>

        <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <h2 class="text-lg font-bold">Vendor</h2>

            <div class="mt-4" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:14px;">
                <div>
                    <label class="mb-1.5 block text-sm font-medium">Vendor Name *</label>
                    <input type="text" name="vendor_name" value="{{ old('vendor_name', $purchaseOrder->vendor_name) }}" class="w-full rounded-md border px-3 py-2" required>
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium">Order Date *</label>
                    <input type="date" name="order_date" value="{{ old('order_date', $purchaseOrder->order_date?->format('Y-m-d')) }}" class="w-full rounded-md border px-3 py-2" required>
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
                        @foreach (\Webkul\Invoice\Models\PurchaseOrder::paymentTermsOptions() as $termValue => $termLabel)
                            <option
                                value="{{ $termValue }}"
                                @selected(
                                    old(
                                        'payment_terms',
                                        $purchaseOrder->payment_terms
                                    ) === $termValue
                                )
                            >
                                {{ $termLabel }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium">Phone</label>
                    <input type="text" name="vendor_phone" value="{{ old('vendor_phone', $purchaseOrder->vendor_phone) }}" class="w-full rounded-md border px-3 py-2">
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium">Email</label>
                    <input type="email" name="vendor_email" value="{{ old('vendor_email', $purchaseOrder->vendor_email) }}" class="w-full rounded-md border px-3 py-2">
                </div>
            </div>

            <div class="mt-4">
                <label class="mb-1.5 block text-sm font-medium">Vendor Address</label>
                <textarea name="vendor_address" rows="3" class="w-full rounded-md border px-3 py-2">{{ old('vendor_address', $purchaseOrder->vendor_address) }}</textarea>
            </div>
        </section>

        <section class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="border-b border-gray-200 p-4" style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;">
                <div>
                    <h2 class="font-bold">PO Items</h2>
                    <p class="mt-1 text-xs text-gray-500">Product vendor / custom service.</p>
                </div>

                <button type="button" id="add-edit-item" class="secondary-button">+ Add Service</button>
            </div>

            <div id="edit-items" style="display:flex;flex-direction:column;gap:10px;padding:16px;">
                @foreach ($purchaseOrder->items as $index => $item)
                    <div class="rounded-lg border border-gray-200 p-4" data-po-row>
                        <input type="hidden" name="items[{{ $index }}][invoice_item_id]" value="{{ $item->invoice_item_id }}">

                        <div style="display:grid;grid-template-columns:minmax(180px,1.2fr) minmax(160px,1fr) 90px 90px 150px auto;gap:8px;align-items:end;">
                            <div>
                                <label class="mb-1 block text-xs font-bold text-gray-500">Product / Service</label>
                                <input type="text" name="items[{{ $index }}][name]" value="{{ old('items.'.$index.'.name', $item->name) }}" class="w-full rounded-md border px-3 py-2" required>
                            </div>

                            <div>
                                <label class="mb-1 block text-xs font-bold text-gray-500">Description</label>
                                <input type="text" name="items[{{ $index }}][description]" value="{{ old('items.'.$index.'.description', $item->description) }}" class="w-full rounded-md border px-3 py-2">
                            </div>

                            <div>
                                <label class="mb-1 block text-xs font-bold text-gray-500">Qty</label>
                                <input type="number" step="0.01" min="0.01" name="items[{{ $index }}][quantity]" value="{{ old('items.'.$index.'.quantity', $item->quantity) }}" class="w-full rounded-md border px-3 py-2" data-po-qty required>
                            </div>

                            <div>
                                <label class="mb-1 block text-xs font-bold text-gray-500">Unit</label>
                                <input type="text" name="items[{{ $index }}][unit]" value="{{ old('items.'.$index.'.unit', $item->unit) }}" class="w-full rounded-md border px-3 py-2" required>
                            </div>

                            <div>
                                <label class="mb-1 block text-xs font-bold text-gray-500">Unit Price</label>
                                <input type="number" step="0.01" min="0" name="items[{{ $index }}][unit_price]" value="{{ old('items.'.$index.'.unit_price', $item->unit_price) }}" class="w-full rounded-md border px-3 py-2" data-po-price required>
                            </div>

                            <div style="display:flex;gap:8px;align-items:center;">
                                <strong class="whitespace-nowrap" data-po-line-total>Rp 0</strong>
                                <button type="button" class="secondary-button" data-remove-row>×</button>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div style="display:grid;grid-template-columns:minmax(280px,1fr) minmax(320px,420px);gap:20px;">
                <div>
                    <label class="mb-1.5 block text-sm font-medium">Notes</label>
                    <textarea name="notes" rows="6" class="w-full rounded-md border px-3 py-2">{{ old('notes', $purchaseOrder->notes) }}</textarea>
                </div>

                <div>
                    <div class="mt-3">
                        <label class="mb-1.5 block text-sm font-medium">Adjustment</label>
                        <input type="number" step="0.01" name="adjustment_amount" value="{{ old('adjustment_amount', $purchaseOrder->adjustment_amount) }}" class="w-full rounded-md border px-3 py-2" data-po-adjustment>
                    </div>

                    <div class="mt-5 rounded-lg bg-gray-50 p-4">
                        <div class="flex justify-between text-sm"><span>Sub Total</span><strong id="po-subtotal">Rp 0</strong></div>
                                                <div class="mt-2 flex justify-between text-sm"><span>Adjustment</span><strong id="po-adjustment">Rp 0</strong></div>
                        <div class="mt-3 flex justify-between border-t pt-3 text-lg font-bold"><span>GRAND TOTAL</span><strong id="po-grand-total">Rp 0</strong></div>
                    </div>
                </div>
            </div>
        </section>
    </form>

    <script>
        (() => {
            const container = document.getElementById('edit-items');
            const addButton = document.getElementById('add-edit-item');
            let nextIndex = {{ $purchaseOrder->items->count() }};

            const money = (value) => new Intl.NumberFormat(
                'id-ID',
                { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }
            ).format(Number(value || 0));

            function recalculate() {
                let subtotal = 0;

                document.querySelectorAll('[data-po-row]').forEach((row) => {
                    const qty = Number(row.querySelector('[data-po-qty]')?.value || 0);
                    const price = Number(row.querySelector('[data-po-price]')?.value || 0);
                    const total = qty * price;

                    subtotal += total;

                    const label = row.querySelector('[data-po-line-total]');
                    if (label) label.textContent = money(total);
                });

                const adjustment = Number(document.querySelector('[data-po-adjustment]')?.value || 0);

                document.getElementById('po-subtotal').textContent = money(subtotal);
                document.getElementById('po-adjustment').textContent = money(adjustment);
                document.getElementById('po-grand-total').textContent = money(subtotal + adjustment);
            }

            function attachRemove(row) {
                row.querySelector('[data-remove-row]')?.addEventListener('click', () => {
                    if (document.querySelectorAll('[data-po-row]').length <= 1) return;

                    row.remove();
                    recalculate();
                });
            }

            document.querySelectorAll('[data-po-row]').forEach(attachRemove);

            addButton?.addEventListener('click', () => {
                const row = document.createElement('div');
                row.className = 'rounded-lg border border-gray-200 p-4';
                row.setAttribute('data-po-row', '');

                row.innerHTML = `
                    <input type="hidden" name="items[${nextIndex}][invoice_item_id]" value="">
                    <div style="display:grid;grid-template-columns:minmax(180px,1.2fr) minmax(160px,1fr) 90px 90px 150px auto;gap:8px;align-items:end;">
                        <div><label class="mb-1 block text-xs font-bold text-gray-500">Product / Service</label><input type="text" name="items[${nextIndex}][name]" class="w-full rounded-md border px-3 py-2" required></div>
                        <div><label class="mb-1 block text-xs font-bold text-gray-500">Description</label><input type="text" name="items[${nextIndex}][description]" class="w-full rounded-md border px-3 py-2"></div>
                        <div><label class="mb-1 block text-xs font-bold text-gray-500">Qty</label><input type="number" step="0.01" min="0.01" name="items[${nextIndex}][quantity]" value="1" class="w-full rounded-md border px-3 py-2" data-po-qty required></div>
                        <div><label class="mb-1 block text-xs font-bold text-gray-500">Unit</label><input type="text" name="items[${nextIndex}][unit]" value="job" class="w-full rounded-md border px-3 py-2" required></div>
                        <div><label class="mb-1 block text-xs font-bold text-gray-500">Unit Price</label><input type="number" step="0.01" min="0" name="items[${nextIndex}][unit_price]" value="0" class="w-full rounded-md border px-3 py-2" data-po-price required></div>
                        <div style="display:flex;gap:8px;align-items:center;"><strong class="whitespace-nowrap" data-po-line-total>Rp 0</strong><button type="button" class="secondary-button" data-remove-row>×</button></div>
                    </div>
                `;

                container.appendChild(row);
                attachRemove(row);
                nextIndex++;
                recalculate();
            });

            document.addEventListener('input', (event) => {
                if (event.target.matches('[data-po-qty], [data-po-price], [data-po-adjustment]')) {
                    recalculate();
                }
            });

            recalculate();
        })();
    </script>
</x-admin::layouts>
