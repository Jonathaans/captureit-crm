<x-admin::layouts>
    <x-slot:title>
        Edit Invoice {{ $invoice->invoice_number }}
    </x-slot:title>

    @php
        /*
         * Address compatibility:
         * - New records use the normal array structure.
         * - Older edits may have stored a plain string, so keep supporting it.
         */
        $rawBilling = $invoice->billing_address;

        if (is_array($rawBilling)) {
            $billing = $rawBilling;
        } elseif (is_string($rawBilling) && trim($rawBilling) !== '') {
            $decodedBilling = json_decode($rawBilling, true);

            $billing = is_array($decodedBilling)
                ? $decodedBilling
                : ['address' => $rawBilling];
        } else {
            $billing = [];
        }

        $rawShipping = $invoice->shipping_address;

        if (is_array($rawShipping)) {
            $shipping = $rawShipping;
        } elseif (is_string($rawShipping) && trim($rawShipping) !== '') {
            $decodedShipping = json_decode($rawShipping, true);

            $shipping = is_array($decodedShipping)
                ? $decodedShipping
                : ['address' => $rawShipping];
        } else {
            $shipping = [];
        }

        $grandTotal = (float) ($invoice->grand_total ?? 0);
        $paidAmount = (float) ($invoice->paid_amount ?? 0);
        $balanceDue = (float) ($invoice->balance_due ?? 0);

        /*
         * Custom Bill To selector.
         * We intentionally keep the working "__new__" flow so the user can
         * choose an existing person OR create a new one while saving invoice.
         */
        $persons = \Webkul\Contact\Models\Person::query()
            ->orderBy('name')
            ->get(['id', 'name']);

        $selectedPersonId = (string) old('person_id', $invoice->person_id);
        $isCreatingNewPerson = $selectedPersonId === '__new__';

        $paymentBadge = match ($invoice->status) {
            'paid' => ['label' => 'PAID', 'bg' => '#dcfce7', 'color' => '#15803d', 'border' => '#86efac'],
            'partial' => ['label' => 'PARTIAL', 'bg' => '#fef3c7', 'color' => '#b45309', 'border' => '#fcd34d'],
            default => ['label' => 'UNPAID', 'bg' => '#fee2e2', 'color' => '#b91c1c', 'border' => '#fca5a5'],
        };

        $eventBadge = match ($invoice->event_status) {
            'cancel' => ['label' => 'CANCEL', 'bg' => '#fee2e2', 'color' => '#b91c1c', 'border' => '#fca5a5'],
            'prospect' => ['label' => 'PROSPECT', 'bg' => '#dbeafe', 'color' => '#1d4ed8', 'border' => '#93c5fd'],
            default => ['label' => 'CONFIRM', 'bg' => '#dcfce7', 'color' => '#15803d', 'border' => '#86efac'],
        };
    @endphp

    <form
        action="{{ route('admin.invoices.update', $invoice->id) }}"
        method="POST"
        id="invoice-edit-form"
    >
        @csrf
        @method('PUT')

        <input
            type="hidden"
            name="adjustment_amount"
            value="{{ old('adjustment_amount', (float) $invoice->adjustment_amount) }}"
        >

        <div style="display:flex; flex-direction:column; gap:28px;">

            {{-- ========================================================= --}}
            {{-- HEADER --}}
            {{-- ========================================================= --}}

            <section
                class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900"
            >
                <div
                    style="
                        display:flex;
                        align-items:flex-start;
                        justify-content:space-between;
                        gap:18px;
                        flex-wrap:wrap;
                    "
                >
                    <div>
                        <a
                            href="{{ route('admin.invoices.show', $invoice->id) }}"
                            class="text-sm font-medium text-blue-600 hover:underline dark:text-blue-400"
                        >
                            ← Back to Manage Invoice
                        </a>

                        <div
                            class="mt-3"
                            style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;"
                        >
                            <h1 class="text-2xl font-bold text-gray-800 dark:text-white">
                                Edit Invoice
                            </h1>

                            <span
                                class="rounded-md px-3 py-1.5 text-xs font-bold"
                                style="
                                    background:#d4a72c;
                                    color:#111827;
                                    border:1px solid #f3ce63;
                                    box-shadow:0 1px 2px rgba(0,0,0,.12);
                                "
                            >
                                {{ $invoice->invoice_number }}
                            </span>
                        </div>

                        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                            Edit informasi project, customer, alamat, dan package invoice.
                        </p>
                    </div>

                    <div
                        style="
                            display:flex;
                            align-items:flex-end;
                            gap:18px;
                            flex-wrap:wrap;
                        "
                    >
                        <div class="text-right">
                            <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                Project Code
                            </p>

                            <p class="mt-1 font-semibold text-gray-800 dark:text-white">
                                {{ $invoice->project_code ?: '-' }}
                            </p>
                        </div>

                        <span
                            style="
                                display:inline-flex;
                                align-items:center;
                                justify-content:center;
                                padding:6px 11px;
                                border-radius:9999px;
                                border:1px solid {{ $paymentBadge['border'] }};
                                background:{{ $paymentBadge['bg'] }};
                                color:{{ $paymentBadge['color'] }};
                                font-size:11px;
                                font-weight:700;
                            "
                        >
                            {{ $paymentBadge['label'] }}
                        </span>

                        <span
                            style="
                                display:inline-flex;
                                align-items:center;
                                justify-content:center;
                                padding:6px 11px;
                                border-radius:9999px;
                                border:1px solid {{ $eventBadge['border'] }};
                                background:{{ $eventBadge['bg'] }};
                                color:{{ $eventBadge['color'] }};
                                font-size:11px;
                                font-weight:700;
                            "
                        >
                            {{ $eventBadge['label'] }}
                        </span>
                    </div>
                </div>
            </section>

            {{-- ERRORS --}}
            @if ($errors->any())
                <div class="rounded-xl border border-red-200 bg-red-50 p-5 dark:border-red-900/50 dark:bg-red-950/30">
                    <p class="font-semibold text-red-700 dark:text-red-300">
                        Please fix the following errors:
                    </p>

                    <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-red-600 dark:text-red-300">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- ========================================================= --}}
            {{-- MAIN EDIT AREA --}}
            {{-- ========================================================= --}}

            <div
                style="
                    display:grid;
                    grid-template-columns:repeat(auto-fit, minmax(min(520px, 100%), 1fr));
                    gap:24px;
                    align-items:start;
                "
            >
                {{-- LEFT / MAIN --}}
                <div style="display:flex; flex-direction:column; gap:24px;">

                    {{-- PROJECT INFORMATION --}}
                    <section class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900">
                        <div class="mb-6">
                            <p class="text-xs font-semibold uppercase tracking-wider text-blue-600 dark:text-blue-400">
                                Project
                            </p>

                            <h2 class="mt-1 text-lg font-semibold text-gray-800 dark:text-white">
                                Project Information
                            </h2>

                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                Informasi utama yang akan tampil pada invoice.
                            </p>
                        </div>

                        <div
                            style="
                                display:grid;
                                grid-template-columns:repeat(2, minmax(0, 1fr));
                                gap:18px;
                            "
                        >
                            <div style="grid-column:1 / -1;">
                                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Project Name
                                </label>

                                <input
                                    type="text"
                                    name="subject"
                                    value="{{ old('subject', $invoice->subject) }}"
                                    class="w-full rounded-lg border border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 outline-none transition focus:border-blue-500 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                                >
                            </div>

                            <div style="grid-column:1 / -1;">
                                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Description
                                </label>

                                <textarea
                                    name="description"
                                    rows="3"
                                    class="w-full rounded-lg border border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 outline-none transition focus:border-blue-500 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                                >{{ old('description', $invoice->description) }}</textarea>
                            </div>

                            <div>
                                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Event Date
                                </label>

                                <input
                                    type="date"
                                    name="event_date"
                                    value="{{ old('event_date', optional($invoice->event_date)->format('Y-m-d')) }}"
                                    class="w-full rounded-lg border border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 outline-none transition focus:border-blue-500 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                                >
                            </div>

                            <div>
                                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Due Date
                                </label>

                                <input
                                    type="date"
                                    name="due_at"
                                    value="{{ old('due_at', optional($invoice->due_at)->format('Y-m-d')) }}"
                                    class="w-full rounded-lg border border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 outline-none transition focus:border-blue-500 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                                >
                            </div>

                            <div>
                                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Payment Term
                                </label>

                                <input
                                    type="text"
                                    name="payment_term"
                                    value="{{ old('payment_term', $invoice->payment_term) }}"
                                    placeholder="Contoh: 7 Days"
                                    class="w-full rounded-lg border border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 outline-none transition focus:border-blue-500 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                                >
                            </div>

                            <div>
                                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Location
                                </label>

                                <input
                                    type="text"
                                    name="location"
                                    value="{{ old('location', $invoice->location) }}"
                                    class="w-full rounded-lg border border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 outline-none transition focus:border-blue-500 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                                >
                            </div>
                        </div>
                    </section>

                    {{-- ADDRESS / ADVANCED --}}
                    <section class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
                        <details>
                            <summary
                                class="cursor-pointer list-none px-6 py-5"
                                style="
                                    display:flex;
                                    align-items:center;
                                    justify-content:space-between;
                                    gap:16px;
                                "
                            >
                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                        Address
                                    </p>

                                    <h2 class="mt-1 text-base font-semibold text-gray-800 dark:text-white">
                                        Billing & Shipping
                                    </h2>

                                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                        Optional — buka hanya jika alamat perlu diubah.
                                    </p>
                                </div>

                                <span class="rounded-lg border border-gray-200 px-3 py-2 text-xs font-semibold text-gray-600 dark:border-gray-700 dark:text-gray-300">
                                    Edit Address
                                </span>
                            </summary>

                            <div
                                class="border-t border-gray-200 p-6 dark:border-gray-800"
                                style="
                                    display:grid;
                                    grid-template-columns:repeat(2, minmax(0, 1fr));
                                    gap:18px;
                                "
                            >
                                <div>
                                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Billing Address
                                    </label>

                                    <textarea
                                        name="billing_address[address]"
                                        rows="4"
                                        class="w-full rounded-lg border border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 outline-none transition focus:border-blue-500 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                                    >{{ old('billing_address.address', $billing['address'] ?? '') }}</textarea>
                                </div>

                                <div>
                                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Shipping Address
                                    </label>

                                    <textarea
                                        name="shipping_address[address]"
                                        rows="4"
                                        class="w-full rounded-lg border border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 outline-none transition focus:border-blue-500 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                                    >{{ old('shipping_address.address', $shipping['address'] ?? '') }}</textarea>
                                </div>
                            </div>
                        </details>
                    </section>
                </div>

                {{-- RIGHT / SIDEBAR --}}
                <aside style="display:flex; flex-direction:column; gap:24px;">

                    {{-- CUSTOMER --}}
                    <section class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900">
                        <p
                            class="text-xs font-bold uppercase tracking-wider"
                            style="color:#f3c94f;"
                        >
                            Customer
                        </p>

                        <h2 class="mt-1 text-lg font-semibold text-gray-800 dark:text-white">
                            Bill To
                        </h2>

                        <div class="mt-5">
                            <label
                                for="invoice-person-id"
                                class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300"
                            >
                                Bill To <span class="text-red-600">*</span>
                            </label>

                            <select
                                id="invoice-person-id"
                                name="person_id"
                                onchange="window.toggleInvoiceNewPerson && window.toggleInvoiceNewPerson(this)"
                                required
                                class="w-full rounded-lg border border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 outline-none transition focus:border-yellow-500 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                            >
                                <option value="">
                                    Select Person
                                </option>

                                @foreach ($persons as $person)
                                    <option
                                        value="{{ $person->id }}"
                                        @selected($selectedPersonId === (string) $person->id)
                                    >
                                        {{ $person->name }}
                                    </option>
                                @endforeach

                                <option
                                    value="__new__"
                                    @selected($isCreatingNewPerson)
                                >
                                    + Add New Person
                                </option>
                            </select>

                            {{-- NEW PERSON FORM --}}
                            <div
                                id="new-person-fields"
                                class="mt-4 rounded-xl p-4"
                                style="
                                    {{ $isCreatingNewPerson ? '' : 'display:none;' }}
                                    border:1px solid #d4a72c;
                                    background:rgba(212,167,44,.08);
                                "
                            >
                                <div class="mb-4">
                                    <p class="text-sm font-semibold text-gray-800 dark:text-white">
                                        New Person
                                    </p>

                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                        Person baru akan dibuat saat Save Changes.
                                    </p>
                                </div>

                                <div style="display:flex; flex-direction:column; gap:14px;">
                                    <div>
                                        <label
                                            for="new-person-name"
                                            class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300"
                                        >
                                            Name <span class="text-red-600">*</span>
                                        </label>

                                        <input
                                            id="new-person-name"
                                            type="text"
                                            name="new_person_name"
                                            value="{{ old('new_person_name') }}"
                                            placeholder="Contoh: Fajrul"
                                            class="w-full rounded-lg border border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 outline-none transition focus:border-yellow-500 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                                            @required($isCreatingNewPerson)
                                        >
                                    </div>

                                    <div>
                                        <label
                                            for="new-person-email"
                                            class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300"
                                        >
                                            Email <span class="text-red-600">*</span>
                                        </label>

                                        <input
                                            id="new-person-email"
                                            type="email"
                                            name="new_person_email"
                                            value="{{ old('new_person_email') }}"
                                            placeholder="contoh@email.com"
                                            class="w-full rounded-lg border border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 outline-none transition focus:border-yellow-500 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                                            @required($isCreatingNewPerson)
                                        >
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div
                            class="mt-5 rounded-lg bg-gray-50 p-4 dark:bg-gray-950"
                            style="display:grid; grid-template-columns:repeat(2, minmax(0, 1fr)); gap:14px;"
                        >
                            <div>
                                <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                    Invoice
                                </p>
                                <p class="mt-1 text-sm font-semibold text-gray-800 dark:text-white">
                                    {{ $invoice->invoice_number }}
                                </p>
                            </div>

                            <div>
                                <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                    Project
                                </p>
                                <p class="mt-1 text-sm font-semibold text-gray-800 dark:text-white">
                                    {{ $invoice->project_code ?: '-' }}
                                </p>
                            </div>
                        </div>
                    </section>

                    {{-- STATUS --}}
                    <section class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900">
                        <p
                            class="text-xs font-bold uppercase tracking-wider"
                            style="color:#a78bfa;"
                        >
                            Status
                        </p>

                        <h2 class="mt-1 text-lg font-semibold text-gray-800 dark:text-white">
                            Invoice Status
                        </h2>

                        <div class="mt-5" style="display:flex; flex-direction:column; gap:16px;">
                            <div>
                                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Event Status
                                </label>

                                <select
                                    name="event_status"
                                    class="w-full rounded-lg border border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 outline-none transition focus:border-purple-500 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                                >
                                    <option value="prospect" @selected(old('event_status', $invoice->event_status) === 'prospect')>
                                        Prospect
                                    </option>
                                    <option value="confirm" @selected(old('event_status', $invoice->event_status) === 'confirm')>
                                        Confirm
                                    </option>
                                    <option value="cancel" @selected(old('event_status', $invoice->event_status) === 'cancel')>
                                        Cancel
                                    </option>
                                </select>
                            </div>

                            <div>
                                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Payment Status
                                </label>

                                {{-- Keep current value for backward compatibility while
                                     InvoiceController is being hardened to calculate it automatically. --}}
                                <input
                                    type="hidden"
                                    name="status"
                                    value="{{ $invoice->status }}"
                                >

                                <div
                                    class="rounded-xl border p-4"
                                    style="
                                        display:flex;
                                        align-items:center;
                                        justify-content:space-between;
                                        gap:14px;
                                        flex-wrap:wrap;
                                        border-color:{{ $paymentBadge['border'] }};
                                        background:{{ $paymentBadge['bg'] }};
                                    "
                                >
                                    <div>
                                        <p
                                            class="text-xs font-semibold uppercase tracking-wider"
                                            style="color:{{ $paymentBadge['color'] }};"
                                        >
                                            Automatic
                                        </p>

                                        <p
                                            class="mt-1 text-sm font-medium"
                                            style="color:{{ $paymentBadge['color'] }};"
                                        >
                                            Status mengikuti total payment yang tercatat.
                                        </p>
                                    </div>

                                    <span
                                        style="
                                            display:inline-flex;
                                            align-items:center;
                                            justify-content:center;
                                            min-width:88px;
                                            padding:7px 12px;
                                            border-radius:9999px;
                                            border:1px solid {{ $paymentBadge['border'] }};
                                            background:#ffffff;
                                            color:{{ $paymentBadge['color'] }};
                                            font-size:12px;
                                            font-weight:800;
                                            letter-spacing:.04em;
                                        "
                                    >
                                        {{ $paymentBadge['label'] }}
                                    </span>
                                </div>

                                <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                                    Ubah status melalui pencatatan payment di halaman Manage Invoice, bukan secara manual.
                                </p>
                            </div>
                        </div>
                    </section>

                    {{-- FINANCIAL SUMMARY --}}
                    <section
                        class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900"
                    >
                        <p class="text-xs font-semibold uppercase tracking-wider text-green-600 dark:text-green-400">
                            Summary
                        </p>

                        <h2 class="mt-1 text-lg font-semibold text-gray-800 dark:text-white">
                            Financial Summary
                        </h2>

                        <div class="mt-5" style="display:flex; flex-direction:column; gap:12px;">
                            <div class="flex justify-between gap-4 text-sm">
                                <span class="text-gray-500 dark:text-gray-400">Subtotal</span>
                                <span id="summary-subtotal" class="font-medium text-gray-800 dark:text-white">
                                    Rp {{ number_format((float) $invoice->sub_total, 0, ',', '.') }}
                                </span>
                            </div>

                            <div class="flex justify-between gap-4 text-sm">
                                <span class="text-gray-500 dark:text-gray-400">Discount</span>
                                <span id="summary-discount" class="font-medium text-gray-800 dark:text-white">
                                    Rp {{ number_format((float) $invoice->discount_amount, 0, ',', '.') }}
                                </span>
                            </div>

                            <div class="flex justify-between gap-4 text-sm">
                                <span class="text-gray-500 dark:text-gray-400">Tax</span>
                                <span id="summary-tax" class="font-medium text-gray-800 dark:text-white">
                                    Rp {{ number_format((float) $invoice->tax_amount, 0, ',', '.') }}
                                </span>
                            </div>

                            <div class="flex justify-between gap-4 text-sm">
                                <span class="text-gray-500 dark:text-gray-400">Adjustment</span>
                                <span id="summary-adjustment" class="font-medium text-gray-800 dark:text-white">
                                    Rp {{ number_format((float) $invoice->adjustment_amount, 0, ',', '.') }}
                                </span>
                            </div>

                            <div class="my-1 border-t border-gray-200 dark:border-gray-800"></div>

                            <div
                                class="rounded-lg bg-gray-50 p-4 dark:bg-gray-950"
                                style="display:flex; justify-content:space-between; gap:16px; align-items:center;"
                            >
                                <span class="font-semibold text-gray-700 dark:text-gray-300">
                                    Grand Total
                                </span>

                                <span id="summary-grand-total" class="text-lg font-bold text-gray-800 dark:text-white">
                                    Rp {{ number_format($grandTotal, 0, ',', '.') }}
                                </span>
                            </div>

                            <div
                                style="
                                    display:grid;
                                    grid-template-columns:repeat(2, minmax(0, 1fr));
                                    gap:10px;
                                "
                            >
                                <div class="rounded-lg bg-green-50 p-3 dark:bg-green-900/10">
                                    <p class="text-xs uppercase text-green-600 dark:text-green-400">
                                        Paid
                                    </p>
                                    <p class="mt-1 font-bold text-green-600 dark:text-green-400">
                                        Rp {{ number_format($paidAmount, 0, ',', '.') }}
                                    </p>
                                </div>

                                <div class="rounded-lg bg-red-50 p-3 dark:bg-red-900/10">
                                    <p class="text-xs uppercase text-red-600 dark:text-red-400">
                                        Balance
                                    </p>
                                    <p id="summary-balance" class="mt-1 font-bold text-red-600 dark:text-red-400">
                                        Rp {{ number_format($balanceDue, 0, ',', '.') }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </section>
                </aside>
            </div>

            {{-- ITEMS --}}
            <div class="rounded-lg border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
                <div class="mb-5">
                    <h2 class="text-base font-semibold text-gray-800 dark:text-white">Invoice Items</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Package, description, day, quantity, price, discount and tax can be edited.
                    </p>
                </div>

                <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                    <table class="min-w-[1100px] w-full">
                        <thead class="bg-gray-50 dark:bg-gray-800">
                            <tr class="border-b border-gray-200 dark:border-gray-700">
                                <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">Package</th>
                                <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">Description</th>
                                <th class="w-24 px-3 py-3 text-center text-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">Day</th>
                                <th class="w-24 px-3 py-3 text-center text-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">Qty</th>
                                <th class="w-40 px-3 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">Unit Price</th>
                                <th class="w-32 px-3 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">Discount %</th>
                                <th class="w-32 px-3 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">Tax %</th>
                                <th class="w-44 px-3 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">Total</th>
                            </tr>
                        </thead>

                        <tbody>
                            @forelse ($invoice->items as $item)
                                <tr class="invoice-item-row border-b border-gray-100 align-top last:border-0 dark:border-gray-800">
                                    <td class="px-3 py-4">
                                        <input
                                            type="text"
                                            name="items[{{ $item->id }}][name]"
                                            value="{{ old("items.{$item->id}.name", $item->name) }}"
                                            class="w-full min-w-[180px] rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                                        >
                                    </td>

                                    <td class="px-3 py-4">
                                        <textarea
                                            name="items[{{ $item->id }}][description]"
                                            rows="2"
                                            class="w-full min-w-[220px] rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                                        >{{ old("items.{$item->id}.description", $item->description) }}</textarea>
                                    </td>

                                    <td class="px-3 py-4">
                                        <input
                                            type="number"
                                            min="1"
                                            name="items[{{ $item->id }}][day]"
                                            value="{{ old("items.{$item->id}.day", $item->day ?: 1) }}"
                                            class="item-day w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-center text-sm text-gray-800 outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                                        >
                                    </td>

                                    <td class="px-3 py-4">
                                        <input
                                            type="number"
                                            min="1"
                                            name="items[{{ $item->id }}][quantity]"
                                            value="{{ old("items.{$item->id}.quantity", $item->quantity ?: 1) }}"
                                            class="item-quantity w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-center text-sm text-gray-800 outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                                        >
                                    </td>

                                    <td class="px-3 py-4">
                                        <input
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            name="items[{{ $item->id }}][price]"
                                            value="{{ old("items.{$item->id}.price", $item->price) }}"
                                            class="item-price w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-right text-sm text-gray-800 outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                                        >
                                    </td>

                                    <td class="px-3 py-4">
                                        <input
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            name="items[{{ $item->id }}][discount_percent]"
                                            value="{{ old("items.{$item->id}.discount_percent", $item->discount_percent ?: 0) }}"
                                            class="item-discount w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-right text-sm text-gray-800 outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                                        >
                                    </td>

                                    <td class="px-3 py-4">
                                        <input
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            name="items[{{ $item->id }}][tax_percent]"
                                            value="{{ old("items.{$item->id}.tax_percent", $item->tax_percent ?: 0) }}"
                                            class="item-tax w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-right text-sm text-gray-800 outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                                        >
                                    </td>

                                    <td class="px-3 py-4 text-right">
                                        <div class="item-total rounded-lg bg-gray-50 px-3 py-2.5 font-semibold text-gray-800 dark:bg-gray-800 dark:text-white">
                                            Rp {{ number_format((float) $item->total, 0, ',', '.') }}
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="px-4 py-10 text-center text-sm text-gray-500 dark:text-gray-400">
                                        No invoice items found.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- ========================================================= --}}
            {{-- SAVE ACTIONS --}}
            {{-- ========================================================= --}}

            <section
                class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900"
                style="
                    display:flex;
                    align-items:center;
                    justify-content:space-between;
                    gap:16px;
                    flex-wrap:wrap;
                "
            >
                <div>
                    <p class="font-semibold text-gray-800 dark:text-white">
                        Ready to save?
                    </p>

                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Payment dan expense tetap dikelola dari halaman Manage Invoice.
                    </p>
                </div>

                <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
                    <a
                        href="{{ route('admin.invoices.show', $invoice->id) }}"
                        class="rounded-lg border border-gray-300 bg-white px-5 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                    >
                        Cancel
                    </a>

                    <button
                        type="submit"
                        class="primary-button"
                    >
                        Save Changes
                    </button>
                </div>
            </section>
        </div>
    </form>

    <script>
        /*
         * Bill To: existing person / Add New Person.
         * This is deliberately initialized immediately AND on DOMContentLoaded
         * so it also works when the Blade script is rendered after the DOM is ready.
         */
        window.toggleInvoiceNewPerson = function (selectElement = null) {
            const personSelect = selectElement || document.getElementById('invoice-person-id');
            const newPersonFields = document.getElementById('new-person-fields');
            const newPersonName = document.getElementById('new-person-name');
            const newPersonEmail = document.getElementById('new-person-email');

            if (! personSelect || ! newPersonFields) {
                return;
            }

            const isNew = personSelect.value === '__new__';

            newPersonFields.style.display = isNew ? 'block' : 'none';

            if (newPersonName) {
                newPersonName.required = isNew;
            }

            if (newPersonEmail) {
                newPersonEmail.required = isNew;
            }
        };

        function initInvoiceEditPage() {
            const personSelect = document.getElementById('invoice-person-id');

            if (personSelect && ! personSelect.dataset.newPersonBound) {
                personSelect.addEventListener('change', function () {
                    window.toggleInvoiceNewPerson(this);
                });

                personSelect.dataset.newPersonBound = '1';
            }

            window.toggleInvoiceNewPerson(personSelect);

            const formatCurrency = (value) =>
                new Intl.NumberFormat('id-ID').format(Math.round(Number(value) || 0));

            const rows = document.querySelectorAll('.invoice-item-row');

            function recalculate() {
                let subtotal = 0;
                let discount = 0;
                let tax = 0;

                rows.forEach((row) => {
                    const day = Math.max(Number(row.querySelector('.item-day')?.value) || 0, 0);
                    const quantity = Math.max(Number(row.querySelector('.item-quantity')?.value) || 0, 0);
                    const price = Math.max(Number(row.querySelector('.item-price')?.value) || 0, 0);
                    const discountPercent = Math.max(Number(row.querySelector('.item-discount')?.value) || 0, 0);
                    const taxPercent = Math.max(Number(row.querySelector('.item-tax')?.value) || 0, 0);

                    const base = day * quantity * price;
                    const itemDiscount = base * (discountPercent / 100);
                    const taxable = Math.max(base - itemDiscount, 0);
                    const itemTax = taxable * (taxPercent / 100);
                    const itemTotal = taxable + itemTax;

                    subtotal += base;
                    discount += itemDiscount;
                    tax += itemTax;

                    const totalElement = row.querySelector('.item-total');

                    if (totalElement) {
                        totalElement.textContent = 'Rp ' + formatCurrency(itemTotal);
                    }
                });

                const adjustment = Number(@json((float) $invoice->adjustment_amount)) || 0;
                const grandTotal = Math.max(subtotal - discount + tax + adjustment, 0);
                const paid = Number(@json($paidAmount)) || 0;
                const balance = Math.max(grandTotal - paid, 0);

                document.getElementById('summary-subtotal').textContent = 'Rp ' + formatCurrency(subtotal);
                document.getElementById('summary-discount').textContent = 'Rp ' + formatCurrency(discount);
                document.getElementById('summary-tax').textContent = 'Rp ' + formatCurrency(tax);
                document.getElementById('summary-adjustment').textContent = 'Rp ' + formatCurrency(adjustment);
                document.getElementById('summary-grand-total').textContent = 'Rp ' + formatCurrency(grandTotal);
                document.getElementById('summary-balance').textContent = 'Rp ' + formatCurrency(balance);
            }

            document.querySelectorAll(
                '.item-day, .item-quantity, .item-price, .item-discount, .item-tax'
            ).forEach((input) => {
                input.addEventListener('input', recalculate);
                input.addEventListener('change', recalculate);
            });

            recalculate();
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initInvoiceEditPage, { once: true });
        } else {
            initInvoiceEditPage();
        }
    </script>

    {!! view_render_event('admin.invoices.edit.after') !!}
</x-admin::layouts>