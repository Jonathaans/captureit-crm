<x-admin::layouts>
    <x-slot:title>{{ $purchaseOrder->po_number }}</x-slot>

    @php
        $money = static fn ($value) =>
            'Rp '.number_format((float) $value, 0, ',', '.');

        $badge = match ($purchaseOrder->status) {
            'released' => ['RELEASED', '#dbeafe', '#1d4ed8'],
            'completed' => ['COMPLETED', '#dcfce7', '#15803d'],
            'cancelled' => ['CANCELLED', '#fee2e2', '#b91c1c'],
            default => ['DRAFT', '#f3f4f6', '#4b5563'],
        };
    @endphp

    <div style="width:100%;max-width:1280px;margin:0 auto;display:flex;flex-direction:column;gap:14px;">
        <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div style="display:flex;justify-content:space-between;gap:16px;flex-wrap:wrap;">
                <div>
                    <a href="{{ route('admin.purchase-orders.index') }}" class="text-sm font-semibold text-blue-600 hover:underline">← Purchase Orders</a>

                    <div class="mt-3" style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ $purchaseOrder->po_number }}</h1>
                        <span style="display:inline-flex;padding:5px 9px;border-radius:9999px;background:{{ $badge[1] }};color:{{ $badge[2] }};font-size:10px;font-weight:800;">{{ $badge[0] }}</span>
                    </div>

                    <p class="mt-2 text-sm text-gray-500">Vendor Event Purchase Order</p>
                </div>

                <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-start;">
                    @if (bouncer()->hasPermission('purchase-orders.print'))
                        <a href="{{ route('admin.purchase-orders.print', $purchaseOrder->id) }}" class="secondary-button">Print PDF</a>
                    @endif

                    @if ($purchaseOrder->status === 'draft' && bouncer()->hasPermission('purchase-orders.edit'))
                        <a href="{{ route('admin.purchase-orders.edit', $purchaseOrder->id) }}" class="secondary-button">Edit</a>
                    @endif

                    @if ($purchaseOrder->status === 'draft' && bouncer()->hasPermission('purchase-orders.release'))
                        <form method="POST" action="{{ route('admin.purchase-orders.release', $purchaseOrder->id) }}" onsubmit="return confirm('Release PO ini? Grand Total akan langsung menjadi Expense pada Invoice terkait.');">
                            @csrf
                            <button type="submit" class="primary-button">Release PO</button>
                        </form>
                    @endif

                    @if ($purchaseOrder->status === 'released' && bouncer()->hasPermission('purchase-orders.complete'))
                        <form method="POST" action="{{ route('admin.purchase-orders.complete', $purchaseOrder->id) }}">
                            @csrf
                            <button type="submit" class="primary-button">Mark Completed</button>
                        </form>
                    @endif

                    @if (in_array($purchaseOrder->status, ['draft', 'released'], true) && bouncer()->hasPermission('purchase-orders.cancel'))
                        <form method="POST" action="{{ route('admin.purchase-orders.cancel', $purchaseOrder->id) }}" onsubmit="return confirm('Cancel PO ini? Jika sudah RELEASED, Expense milik PO ini juga akan dihapus.');">
                            @csrf
                            <button type="submit" class="secondary-button">Cancel PO</button>
                        </form>
                    @endif
                </div>
            </div>
        </section>

        @if ($purchaseOrder->status === 'draft')
            <section class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
                <strong>DRAFT:</strong> PO belum memengaruhi Expense maupun Financial Report. Nominal baru diposting ketika Finance menekan Release PO.
            </section>
        @elseif ($purchaseOrder->expense_id)
            <section class="rounded-xl border border-green-200 bg-green-50 p-4 text-sm text-green-800">
                <strong>EXPENSE POSTED:</strong>
                {{ $money($purchaseOrder->grand_total) }}
                sudah menjadi Expense Invoice
                <strong>{{ $purchaseOrder->invoice_number }}</strong>
                dengan Expense ID #{{ $purchaseOrder->expense_id }}.
            </section>
        @endif

        <section style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:12px;">
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                <p class="text-[11px] font-bold uppercase tracking-wide text-gray-500">Related Invoice</p>
                <a href="{{ route('admin.invoices.show', $purchaseOrder->invoice_id) }}" class="mt-2 block font-bold text-blue-600 hover:underline">
                    {{ $purchaseOrder->invoice_number }}
                </a>
                <p class="mt-1 text-xs text-gray-500">{{ $purchaseOrder->project_code }} · {{ $purchaseOrder->project_name }}</p>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                <p class="text-[11px] font-bold uppercase tracking-wide text-gray-500">Vendor</p>
                <p class="mt-2 font-bold">{{ $purchaseOrder->vendor_name }}</p>
                <p class="mt-1 text-xs text-gray-500">{{ $purchaseOrder->vendor_phone ?: '-' }} · {{ $purchaseOrder->vendor_email ?: '-' }}</p>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                <p class="text-[11px] font-bold uppercase tracking-wide text-gray-500">Order Date</p>
                <p class="mt-2 font-bold">{{ $purchaseOrder->order_date?->format('d M Y') ?: '-' }}</p>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                <p class="text-[11px] font-bold uppercase tracking-wide text-gray-500">Terms of Payment</p>
                <p class="mt-2 font-bold">{{ $purchaseOrder->payment_terms_label }}</p>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                <p class="text-[11px] font-bold uppercase tracking-wide text-gray-500">Grand Total</p>
                <p class="mt-2 text-xl font-bold">{{ $money($purchaseOrder->grand_total) }}</p>
            </div>
        </section>

        <section class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
            <div class="border-b border-gray-200 p-4"><h2 class="font-bold">Product / Service</h2></div>

            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="border-b border-gray-200 bg-gray-50 text-left text-xs uppercase text-gray-500">
                        <tr>
                            <th class="px-4 py-3">#</th>
                            <th class="px-4 py-3">Product / Service</th>
                            <th class="px-4 py-3">Description</th>
                            <th class="px-4 py-3">Qty</th>
                            <th class="px-4 py-3">Unit</th>
                            <th class="px-4 py-3 text-right">Unit Price</th>
                            <th class="px-4 py-3 text-right">Total</th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach ($purchaseOrder->items as $index => $item)
                            <tr class="border-b border-gray-100 last:border-b-0">
                                <td class="px-4 py-3 text-sm text-gray-500">{{ $index + 1 }}</td>
                                <td class="px-4 py-3 font-bold">
                                    {{ $item->name }}
                                    @if ($item->invoice_item_id)
                                        <span class="ml-1 text-[10px] font-bold text-blue-600">INVOICE PRODUCT</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-500">{{ $item->description ?: '-' }}</td>
                                <td class="px-4 py-3">{{ rtrim(rtrim(number_format((float) $item->quantity, 2, '.', ''), '0'), '.') }}</td>
                                <td class="px-4 py-3">{{ $item->unit }}</td>
                                <td class="px-4 py-3 text-right">{{ $money($item->unit_price) }}</td>
                                <td class="px-4 py-3 text-right font-bold">{{ $money($item->total) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="border-t border-gray-200 p-5">
                <div style="width:min(100%,420px);margin-left:auto;display:grid;gap:8px;">
                    <div class="flex justify-between text-sm"><span class="text-gray-500">Sub Total</span><strong>{{ $money($purchaseOrder->sub_total) }}</strong></div>
                    <div class="flex justify-between text-sm"><span class="text-gray-500">Adjustment</span><strong>{{ $money($purchaseOrder->adjustment_amount) }}</strong></div>
                    <div class="flex justify-between border-t pt-3 text-lg font-bold"><span>GRAND TOTAL</span><strong>{{ $money($purchaseOrder->grand_total) }}</strong></div>
                </div>
            </div>
        </section>

        @if ($purchaseOrder->vendor_address || $purchaseOrder->notes)
            <section style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:12px;">
                @if ($purchaseOrder->vendor_address)
                    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                        <p class="text-[11px] font-bold uppercase tracking-wide text-gray-500">Vendor Address</p>
                        <p class="mt-2 whitespace-pre-line text-sm text-gray-700">{{ $purchaseOrder->vendor_address }}</p>
                    </div>
                @endif

                @if ($purchaseOrder->notes)
                    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                        <p class="text-[11px] font-bold uppercase tracking-wide text-gray-500">Notes</p>
                        <p class="mt-2 whitespace-pre-line text-sm text-gray-700">{{ $purchaseOrder->notes }}</p>
                    </div>
                @endif
            </section>
        @endif

        <section class="rounded-xl border border-gray-200 bg-white p-4 text-sm shadow-sm">
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;">
                <div><span class="text-gray-500">Created By</span><strong class="ml-2">{{ $purchaseOrder->created_by_name ?: '-' }}</strong></div>
                <div><span class="text-gray-500">Released By</span><strong class="ml-2">{{ $purchaseOrder->released_by_name ?: '-' }}</strong></div>
                <div><span class="text-gray-500">Released At</span><strong class="ml-2">{{ $purchaseOrder->released_at?->format('d M Y H:i') ?: '-' }}</strong></div>
                <div><span class="text-gray-500">Completed At</span><strong class="ml-2">{{ $purchaseOrder->completed_at?->format('d M Y H:i') ?: '-' }}</strong></div>
            </div>
        </section>
    </div>
</x-admin::layouts>
