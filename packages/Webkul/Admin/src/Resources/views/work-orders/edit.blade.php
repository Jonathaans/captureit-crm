<x-admin::layouts>
    <x-slot:title>
        Edit {{ $workOrder->work_order_number }}
    </x-slot>

    @php
        $oldItems = old('items');

        if (! is_array($oldItems)) {
            $oldItems = $workOrder->items
                ->map(fn ($item) => [
                    'product_id' => $item->product_id,
                    'name' => $item->name,
                    'notes' => $item->notes,
                ])
                ->values()
                ->all();
        }
    @endphp

    <div class="mx-auto flex max-w-6xl flex-col gap-4">
        <div class="rounded-xl border bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <a
                href="{{ route('admin.work-orders.show', $workOrder->id) }}"
                class="text-sm font-semibold text-blue-600"
            >
                ← Back to SPK
            </a>

            <h1 class="mt-3 text-2xl font-bold">
                Edit {{ $workOrder->work_order_number }}
            </h1>

            <p class="mt-1 text-sm text-gray-500">
                Tambahkan product/service operasional atau note tanpa mengubah Invoice.
            </p>
        </div>

        <form
            method="POST"
            action="{{ route('admin.work-orders.update', $workOrder->id) }}"
            class="flex flex-col gap-4"
        >
            @csrf
            @method('PUT')

            <div
                class="rounded-xl border bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900"
                style="
                    display:grid;
                    grid-template-columns:repeat(auto-fit,minmax(260px,1fr));
                    gap:14px;
                "
            >
                <div>
                    <label class="mb-1.5 block text-sm font-semibold">
                        Event Date
                    </label>

                    <input
                        type="date"
                        name="event_date"
                        value="{{ old('event_date', $workOrder->event_date?->format('Y-m-d')) }}"
                        class="w-full rounded-md border px-3 py-2"
                    >
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-semibold">
                        Location
                    </label>

                    <input
                        name="location"
                        value="{{ old('location', $workOrder->location) }}"
                        class="w-full rounded-md border px-3 py-2"
                    >
                </div>
            </div>

            <div class="rounded-xl border bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-bold">
                            Product / Service
                        </h2>

                        <p class="mt-1 text-xs text-gray-500">
                            PDF hanya menampilkan nama product/service.
                        </p>
                    </div>

                    <button
                        type="button"
                        class="secondary-button"
                        onclick="addSpkItem()"
                    >
                        + Add Item
                    </button>
                </div>

                <div
                    id="spk-items"
                    class="mt-4 flex flex-col gap-3"
                >
                    @foreach ($oldItems as $index => $item)
                        <div
                            class="spk-item rounded-lg border p-4"
                            data-index="{{ $index }}"
                        >
                            <input
                                type="hidden"
                                data-field="product_id"
                                name="items[{{ $index }}][product_id]"
                                value="{{ $item['product_id'] ?? '' }}"
                            >

                            <div
                                style="
                                    display:grid;
                                    grid-template-columns:1fr 1fr auto;
                                    gap:10px;
                                    align-items:end;
                                "
                            >
                                <div>
                                    <label class="mb-1.5 block text-sm font-semibold">
                                        Product / Service Name *
                                    </label>

                                    <input
                                        data-field="name"
                                        name="items[{{ $index }}][name]"
                                        value="{{ $item['name'] ?? '' }}"
                                        class="w-full rounded-md border px-3 py-2"
                                    >
                                </div>

                                <div>
                                    <label class="mb-1.5 block text-sm font-semibold">
                                        Internal Item Note
                                    </label>

                                    <input
                                        data-field="notes"
                                        name="items[{{ $index }}][notes]"
                                        value="{{ $item['notes'] ?? '' }}"
                                        class="w-full rounded-md border px-3 py-2"
                                        placeholder="optional"
                                    >
                                </div>

                                <button
                                    type="button"
                                    class="secondary-button"
                                    onclick="this.closest('.spk-item').remove(); reindexSpkItems();"
                                >
                                    Remove
                                </button>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="rounded-xl border bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <label class="mb-1.5 block text-sm font-semibold">
                    Notes / Operational Instruction
                </label>

                <textarea
                    name="notes"
                    rows="7"
                    class="w-full rounded-md border px-3 py-2"
                    placeholder="Contoh: tambahan 1 lighting, setup lebih awal, kabel extension tambahan..."
                >{{ old('notes', $workOrder->notes) }}</textarea>
            </div>

            <div class="rounded-xl border bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <h2 class="text-lg font-bold">
                    Nama Tanda Tangan PDF
                </h2>

                <div
                    class="mt-4"
                    style="
                        display:grid;
                        grid-template-columns:repeat(3,minmax(0,1fr));
                        gap:12px;
                    "
                >
                    <div>
                        <label class="mb-1.5 block text-sm font-semibold">
                            Admin Sales
                        </label>

                        <input
                            name="admin_sales_name"
                            value="{{ old('admin_sales_name', $workOrder->admin_sales_name) }}"
                            class="w-full rounded-md border px-3 py-2"
                        >
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-semibold">
                            Sales
                        </label>

                        <input
                            name="sales_name"
                            value="{{ old('sales_name', $workOrder->sales_name) }}"
                            class="w-full rounded-md border px-3 py-2"
                        >
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-semibold">
                            Operational
                        </label>

                        <input
                            name="operational_name"
                            value="{{ old('operational_name', $workOrder->operational_name) }}"
                            class="w-full rounded-md border px-3 py-2"
                        >
                    </div>
                </div>
            </div>

            <div class="flex justify-end">
                <button class="primary-button">
                    Save SPK
                </button>
            </div>
        </form>
    </div>

    @pushOnce('scripts')
        <script>
            function reindexSpkItems() {
                document
                    .querySelectorAll('#spk-items .spk-item')
                    .forEach((row, index) => {
                        row.dataset.index = index;

                        row
                            .querySelectorAll('[data-field]')
                            .forEach((input) => {
                                input.name =
                                    `items[${index}][${input.dataset.field}]`;
                            });
                    });
            }

            function addSpkItem() {
                const container =
                    document.getElementById('spk-items');

                const row =
                    document.createElement('div');

                row.className =
                    'spk-item rounded-lg border p-4';

                row.innerHTML = `
                    <input
                        type="hidden"
                        data-field="product_id"
                        value=""
                    >

                    <div
                        style="
                            display:grid;
                            grid-template-columns:1fr 1fr auto;
                            gap:10px;
                            align-items:end;
                        "
                    >
                        <div>
                            <label class="mb-1.5 block text-sm font-semibold">
                                Product / Service Name *
                            </label>

                            <input
                                data-field="name"
                                class="w-full rounded-md border px-3 py-2"
                            >
                        </div>

                        <div>
                            <label class="mb-1.5 block text-sm font-semibold">
                                Internal Item Note
                            </label>

                            <input
                                data-field="notes"
                                class="w-full rounded-md border px-3 py-2"
                                placeholder="optional"
                            >
                        </div>

                        <button
                            type="button"
                            class="secondary-button"
                            onclick="this.closest('.spk-item').remove(); reindexSpkItems();"
                        >
                            Remove
                        </button>
                    </div>
                `;

                container.appendChild(row);

                reindexSpkItems();
            }

            reindexSpkItems();
        </script>
    @endPushOnce
</x-admin::layouts>
