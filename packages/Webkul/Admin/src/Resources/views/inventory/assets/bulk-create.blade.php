<x-admin::layouts>
    <x-slot:title>
        Bulk Create Inventory Assets
    </x-slot>

    @php
        $oldPrefix = old('prefix', '');
        $oldStart = old('start_number', 1);
        $oldPadding = old('padding', 3);
        $oldQuantity = old('quantity', 1);
    @endphp

    <div class="flex items-center justify-between gap-4 rounded-lg border border-gray-200 bg-white p-4 max-sm:flex-wrap dark:border-gray-800 dark:bg-gray-900">
        <div>
            <a
                href="{{ route('admin.inventory.assets.index') }}"
                class="text-sm text-gray-600 hover:text-brandColor dark:text-gray-300"
            >
                &larr; Back to Assets
            </a>

            <p class="mt-2 text-xl font-bold text-gray-800 dark:text-white">
                Bulk Asset Registration
            </p>

            <p class="mt-1 text-sm text-gray-500">
                Buat banyak serialized asset sekaligus dan gunakan Asset Code yang sama sebagai QR payload.
            </p>
        </div>

        <div class="rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-xs text-blue-800 dark:border-blue-900 dark:bg-blue-900/20 dark:text-blue-200">
            Asset Code = Barcode Value = QR
        </div>
    </div>

    <div class="mt-4 grid grid-cols-[minmax(0,2fr)_minmax(280px,1fr)] gap-4 max-lg:grid-cols-1">
        <form
            method="POST"
            action="{{ route('admin.inventory.assets.bulk-store') }}"
            class="rounded-lg border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900"
        >
            @csrf

            <div class="grid gap-5">
                <div>
                    <label class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-300">
                        Serialized Inventory Item
                        <span class="text-red-600">*</span>
                    </label>

                    <select
                        name="inventory_item_id"
                        required
                        class="w-full rounded-md border border-gray-300 bg-white px-3 py-2.5 text-sm dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                    >
                        <option value="">Select Inventory Item</option>

                        @foreach ($items as $item)
                            <option
                                value="{{ $item->id }}"
                                @selected(
                                    (string) old(
                                        'inventory_item_id',
                                        $selectedItemId
                                    ) === (string) $item->id
                                )
                            >
                                {{ $item->code }} - {{ $item->name }}
                            </option>
                        @endforeach
                    </select>

                    @error('inventory_item_id')
                        <p class="mt-1 text-xs font-semibold text-red-600">
                            {{ $message }}
                        </p>
                    @enderror

                    <p class="mt-1 text-xs text-gray-400">
                        Hanya Inventory Item dengan Tracking Type = Serialized yang tampil.
                    </p>
                </div>

                <div class="grid grid-cols-2 gap-4 max-sm:grid-cols-1">
                    <div>
                        <label class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-300">
                            Prefix
                            <span class="text-red-600">*</span>
                        </label>

                        <input
                            id="bulk-prefix"
                            type="text"
                            name="prefix"
                            value="{{ $oldPrefix }}"
                            maxlength="24"
                            required
                            autocomplete="off"
                            placeholder="EXT-BOX"
                            class="w-full rounded-md border border-gray-300 bg-white px-3 py-2.5 text-sm uppercase dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                        >

                        @error('prefix')
                            <p class="mt-1 text-xs font-semibold text-red-600">
                                {{ $message }}
                            </p>
                        @enderror

                        <p class="mt-1 text-xs text-gray-400">
                            Contoh: CAM, LGT, EXT-BOX, PROP-BOX.
                        </p>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-300">
                            Starting Number
                            <span class="text-red-600">*</span>
                        </label>

                        <input
                            id="bulk-start"
                            type="number"
                            name="start_number"
                            value="{{ $oldStart }}"
                            min="0"
                            max="999999999"
                            required
                            class="w-full rounded-md border border-gray-300 bg-white px-3 py-2.5 text-sm dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                        >

                        @error('start_number')
                            <p class="mt-1 text-xs font-semibold text-red-600">
                                {{ $message }}
                            </p>
                        @enderror
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4 max-sm:grid-cols-1">
                    <div>
                        <label class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-300">
                            Number Padding
                            <span class="text-red-600">*</span>
                        </label>

                        <select
                            id="bulk-padding"
                            name="padding"
                            required
                            class="w-full rounded-md border border-gray-300 bg-white px-3 py-2.5 text-sm dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                        >
                            @foreach ([2, 3, 4, 5, 6] as $padding)
                                <option
                                    value="{{ $padding }}"
                                    @selected((int) $oldPadding === $padding)
                                >
                                    {{ $padding }} digits
                                </option>
                            @endforeach
                        </select>

                        @error('padding')
                            <p class="mt-1 text-xs font-semibold text-red-600">
                                {{ $message }}
                            </p>
                        @enderror
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-300">
                            Quantity Assets
                            <span class="text-red-600">*</span>
                        </label>

                        <input
                            id="bulk-quantity"
                            type="number"
                            name="quantity"
                            value="{{ $oldQuantity }}"
                            min="1"
                            max="200"
                            required
                            class="w-full rounded-md border border-gray-300 bg-white px-3 py-2.5 text-sm dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                        >

                        @error('quantity')
                            <p class="mt-1 text-xs font-semibold text-red-600">
                                {{ $message }}
                            </p>
                        @enderror
                    </div>
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-300">
                        Initial Condition
                    </label>

                    <select
                        name="condition"
                        class="w-full rounded-md border border-gray-300 bg-white px-3 py-2.5 text-sm dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                    >
                        <option value="good" @selected(old('condition', 'good') === 'good')>
                            GOOD
                        </option>

                        <option value="fair" @selected(old('condition') === 'fair')>
                            FAIR
                        </option>

                        <option value="damaged" @selected(old('condition') === 'damaged')>
                            DAMAGED
                        </option>
                    </select>

                    @error('condition')
                        <p class="mt-1 text-xs font-semibold text-red-600">
                            {{ $message }}
                        </p>
                    @enderror
                </div>

                <div class="grid grid-cols-2 gap-4 max-sm:grid-cols-1">
                    <div>
                        <label class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-300">
                            Purchase Date
                        </label>

                        <input
                            type="date"
                            name="purchase_date"
                            value="{{ old('purchase_date') }}"
                            class="w-full rounded-md border border-gray-300 bg-white px-3 py-2.5 text-sm dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                        >

                        @error('purchase_date')
                            <p class="mt-1 text-xs font-semibold text-red-600">
                                {{ $message }}
                            </p>
                        @enderror
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-300">
                            Purchase Price / Asset
                        </label>

                        <input
                            type="number"
                            name="purchase_price"
                            value="{{ old('purchase_price') }}"
                            min="0"
                            step="0.01"
                            placeholder="0"
                            class="w-full rounded-md border border-gray-300 bg-white px-3 py-2.5 text-sm dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                        >

                        @error('purchase_price')
                            <p class="mt-1 text-xs font-semibold text-red-600">
                                {{ $message }}
                            </p>
                        @enderror
                    </div>
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-300">
                        Common Notes
                    </label>

                    <textarea
                        name="notes"
                        rows="3"
                        maxlength="2000"
                        placeholder="Optional notes applied to every asset in this batch."
                        class="w-full rounded-md border border-gray-300 bg-white px-3 py-2.5 text-sm dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                    >{{ old('notes') }}</textarea>

                    @error('notes')
                        <p class="mt-1 text-xs font-semibold text-red-600">
                            {{ $message }}
                        </p>
                    @enderror
                </div>

                <label class="flex items-start gap-3 rounded-lg border border-green-200 bg-green-50 p-4 text-sm text-green-900 dark:border-green-900 dark:bg-green-900/20 dark:text-green-200">
                    <input
                        type="checkbox"
                        name="print_qr_after_create"
                        value="1"
                        @checked((bool) old('print_qr_after_create', true))
                        class="mt-1"
                    >

                    <span>
                        <strong>Open QR Labels after create.</strong>
                        Setelah batch berhasil, langsung buka label QR hanya untuk asset yang baru dibuat.
                    </span>
                </label>

                <div class="flex justify-end gap-2">
                    <a
                        href="{{ route('admin.inventory.assets.index') }}"
                        class="secondary-button"
                    >
                        Cancel
                    </a>

                    <button
                        type="submit"
                        class="primary-button"
                    >
                        Create Assets
                    </button>
                </div>
            </div>
        </form>

        <div class="grid content-start gap-4">
            <div class="rounded-lg border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
                <p class="font-bold text-gray-800 dark:text-white">
                    Live Code Preview
                </p>

                <p class="mt-1 text-xs text-gray-500">
                    Preview hanya membantu operator. Backend tetap memvalidasi duplicate dan panjang QR.
                </p>

                <div class="mt-4 rounded-lg bg-gray-950 p-4 font-mono text-sm text-green-300">
                    <div>
                        First:
                        <span id="preview-first">-</span>
                    </div>

                    <div class="mt-2">
                        Last:
                        <span id="preview-last">-</span>
                    </div>

                    <div class="mt-2">
                        Count:
                        <span id="preview-count">0</span>
                    </div>
                </div>
            </div>

            <div class="rounded-lg border border-blue-200 bg-blue-50 p-5 text-sm text-blue-900 dark:border-blue-900 dark:bg-blue-900/20 dark:text-blue-200">
                <p class="font-bold">
                    Phase 4A Master Rule
                </p>

                <div class="mt-3 grid gap-2 text-xs leading-5">
                    <p>
                        <strong>Serialized + QR:</strong>
                        Camera, Printer, Mini PC, Lighting, Extension Cable Box, Props Box.
                    </p>

                    <p>
                        <strong>Manual Quantity:</strong>
                        Paper Roll dan Frame.
                    </p>

                    <p>
                        Jangan mengubah Inventory Item quantity lama menjadi serialized jika sudah punya histori.
                        Buat item serialized BARU untuk box, lalu update Equipment Template untuk Delivery Order berikutnya.
                    </p>
                </div>
            </div>

            <div class="rounded-lg border border-yellow-200 bg-yellow-50 p-5 text-sm text-yellow-900 dark:border-yellow-900 dark:bg-yellow-900/20 dark:text-yellow-200">
                <p class="font-bold">
                    Contoh Extension Box
                </p>

                <pre class="mt-3 overflow-x-auto whitespace-pre-wrap font-mono text-xs">Inventory Item:
Code: EXT-BOX
Tracking: Serialized
Unit: box

Bulk Asset:
Prefix: EXT-BOX
Start: 1
Padding: 3
Qty: 10

Hasil:
EXT-BOX-001
EXT-BOX-002
...
EXT-BOX-010</pre>
            </div>
        </div>
    </div>

    <script>
        (() => {
            const prefix = document.getElementById('bulk-prefix');
            const start = document.getElementById('bulk-start');
            const padding = document.getElementById('bulk-padding');
            const quantity = document.getElementById('bulk-quantity');

            const first = document.getElementById('preview-first');
            const last = document.getElementById('preview-last');
            const count = document.getElementById('preview-count');

            function makeCode(number) {
                const cleanPrefix = String(prefix.value || '')
                    .trim()
                    .toUpperCase();

                const digits = Math.max(
                    Number(padding.value || 3),
                    1
                );

                return cleanPrefix
                    ? `${cleanPrefix}-${String(number).padStart(digits, '0')}`
                    : '-';
            }

            function render() {
                const startNumber = Number(start.value || 0);
                const qty = Math.max(
                    Number(quantity.value || 0),
                    0
                );

                first.textContent = qty > 0
                    ? makeCode(startNumber)
                    : '-';

                last.textContent = qty > 0
                    ? makeCode(startNumber + qty - 1)
                    : '-';

                count.textContent = qty;
            }

            [
                prefix,
                start,
                padding,
                quantity,
            ].forEach((element) => {
                element.addEventListener(
                    'input',
                    render
                );

                element.addEventListener(
                    'change',
                    render
                );
            });

            prefix.addEventListener('blur', () => {
                prefix.value = prefix.value
                    .trim()
                    .toUpperCase();

                render();
            });

            render();
        })();
    </script>
</x-admin::layouts>
