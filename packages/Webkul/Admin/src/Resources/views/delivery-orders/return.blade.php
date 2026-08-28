<x-admin::layouts>
    <x-slot:title>
        Return / Check-In - {{ $deliveryOrder->delivery_order_number }}
    </x-slot>

    @php
        $status = strtolower(
            $deliveryOrder->status ?: 'draft'
        );

        $formatQty = static function ($value) {
            return rtrim(
                rtrim(
                    number_format((float) $value, 2, '.', ''),
                    '0'
                ),
                '.'
            );
        };

        $statusClass = static function ($allocationStatus) {
            return match ($allocationStatus) {
                'out' =>
                    'bg-purple-100 text-purple-700',

                'return_pending' =>
                    'bg-orange-100 text-orange-700',

                'returned' =>
                    'bg-green-100 text-green-700',

                default =>
                    'bg-gray-100 text-gray-700',
            };
        };
    @endphp

    <div class="flex items-center justify-between gap-4 rounded-lg border border-gray-200 bg-white p-4 max-sm:flex-wrap dark:border-gray-800 dark:bg-gray-900">
        <div>
            <a
                href="{{ route(
                    'admin.delivery-orders.show',
                    $deliveryOrder->id
                ) }}"
                class="text-sm text-gray-600 hover:text-brandColor dark:text-gray-300"
            >
                &larr; Back to Surat Jalan
            </a>

            <div class="mt-2 flex items-center gap-3">
                <p class="text-xl font-bold text-gray-800 dark:text-white">
                    Return / Check-In
                </p>

                <span class="rounded-full bg-gray-100 px-3 py-1 text-xs font-bold uppercase text-gray-700 dark:bg-gray-800 dark:text-gray-300">
                    {{ $status }}
                </span>
            </div>

            <p class="mt-1 text-sm text-gray-500">
                {{ $deliveryOrder->delivery_order_number }}
                &middot; {{ $deliveryOrder->project_code ?: '-' }}

                @if ($deliveryOrder->project_name)
                    &middot; {{ $deliveryOrder->project_name }}
                @endif
            </p>
        </div>

        <div class="flex flex-wrap gap-2">
            @if (
                $canOperate
                && $summary['out'] > 0
                && bouncer()->hasPermission('delivery-orders.return')
            )
                <form
                    method="POST"
                    action="{{ route(
                        'admin.delivery-orders.return.start',
                        $deliveryOrder->id
                    ) }}"
                >
                    @csrf
                    @method('PUT')

                    <button
                        type="submit"
                        class="secondary-button"
                        onclick="return confirm('Mulai proses return? Seluruh inventory OUT akan menjadi RETURN PENDING.')"
                    >
                        Start Return
                    </button>
                </form>
            @endif

            @if (
                $status === 'delivered'
                && $allReturned
                && bouncer()->hasPermission('delivery-orders.returned')
            )
                <form
                    method="POST"
                    action="{{ route(
                        'admin.delivery-orders.returned',
                        $deliveryOrder->id
                    ) }}"
                >
                    @csrf
                    @method('PUT')

                    <button
                        type="submit"
                        class="primary-button"
                        onclick="return confirm('Seluruh inventory sudah selesai Check-In. Tandai Surat Jalan sebagai RETURNED?')"
                    >
                        Mark as Returned
                    </button>
                </form>
            @endif
        </div>
    </div>

    <div class="mt-4 rounded-lg border border-blue-200 bg-blue-50 p-4 text-sm text-blue-800">
        <strong>Return Pending</strong> berarti barang sedang dalam proses kembali dan belum selesai diperiksa.
        Check-In adalah titik saat kondisi fisik dicatat dan stok benar-benar dikembalikan.
    </div>

    @if (
        $canOperate
        && $summary['return_pending'] > 0
        && bouncer()->hasPermission('delivery-orders.return.check-in')
    )
        <div class="mt-4 rounded-lg border border-green-200 bg-green-50 p-4">
            <div class="flex items-start justify-between gap-4 max-sm:flex-wrap">
                <div>
                    <p class="text-base font-semibold text-green-900">
                        Scan Serialized Check-In
                    </p>

                    <p class="mt-1 text-xs text-green-700">
                        Default GOOD. Ubah condition terlebih dahulu untuk FAIR / DAMAGED, lalu scan.
                        Asset MISSING tetap diproses dari kartu asset karena tidak ada barang fisik untuk discan.
                    </p>
                </div>
            </div>

            <form
                method="POST"
                action="{{ route(
                    'admin.delivery-orders.return.scan-check-in',
                    $deliveryOrder->id
                ) }}"
                class="mt-3 grid grid-cols-4 gap-3 max-lg:grid-cols-1"
            >
                @csrf
                @method('PUT')

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-green-900">
                        Condition
                    </label>

                    <select
                        name="condition"
                        class="w-full rounded-md border border-green-300 bg-white px-3 py-3 text-sm"
                    >
                        <option value="good">Good</option>
                        <option value="fair">Fair</option>
                        <option value="damaged">Damaged</option>
                    </select>
                </div>

                <div class="lg:col-span-2">
                    <label class="mb-1.5 block text-sm font-medium text-green-900">
                        Barcode / Asset Code
                    </label>

                    <input
                        type="text"
                        name="barcode"
                        autocomplete="off"
                        maxlength="100"
                        placeholder="Scan asset untuk Check-In..."
                        class="w-full rounded-md border border-green-300 bg-white px-3 py-3 text-base font-semibold outline-none focus:border-green-600"
                        autofocus
                    >
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-green-900">
                        Notes
                    </label>

                    <input
                        type="text"
                        name="notes"
                        maxlength="2000"
                        placeholder="Optional"
                        class="w-full rounded-md border border-green-300 bg-white px-3 py-3 text-sm"
                    >
                </div>
            </form>

            @error('barcode')
                <div class="mt-3 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">
                    {{ $message }}
                </div>
            @enderror
        </div>
    @endif

    <div class="mt-4 grid grid-cols-3 gap-4 max-lg:grid-cols-1">
        <div class="rounded-lg border border-purple-200 bg-purple-50 p-4">
            <p class="text-xs font-semibold uppercase text-purple-600">
                Out
            </p>

            <p class="mt-2 text-2xl font-bold text-purple-800">
                {{ $summary['out'] }}
            </p>
        </div>

        <div class="rounded-lg border border-orange-200 bg-orange-50 p-4">
            <p class="text-xs font-semibold uppercase text-orange-600">
                Return Pending
            </p>

            <p class="mt-2 text-2xl font-bold text-orange-800">
                {{ $summary['return_pending'] }}
            </p>
        </div>

        <div class="rounded-lg border border-green-200 bg-green-50 p-4">
            <p class="text-xs font-semibold uppercase text-green-600">
                Checked In
            </p>

            <p class="mt-2 text-2xl font-bold text-green-800">
                {{ $summary['returned'] }}
            </p>
        </div>
    </div>

    <div class="mt-4 grid gap-4">
        @forelse ($allocations as $allocation)
            <div class="rounded-lg border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
                <div class="flex items-start justify-between gap-4 max-sm:flex-wrap">
                    <div>
                        <p class="text-base font-semibold text-gray-800 dark:text-white">
                            {{ $allocation->deliveryOrderItem?->name ?: '-' }}
                        </p>

                        @if ($allocation->deliveryOrderItem?->description)
                            <p class="mt-1 text-sm text-gray-500">
                                {{ $allocation->deliveryOrderItem->description }}
                            </p>
                        @endif

                        <p class="mt-2 text-xs text-gray-500">
                            {{ $allocation->inventoryItem?->code ?: '-' }}
                            &middot;
                            {{ $allocation->inventoryItem?->name ?: '-' }}
                        </p>
                    </div>

                    <span class="rounded-full px-3 py-1 text-xs font-bold {{ $statusClass($allocation->status) }}">
                        {{ strtoupper(str_replace('_', ' ', $allocation->status)) }}
                    </span>
                </div>

                @if ($allocation->tracking_type === 'serialized')
                    <div class="mt-4 grid grid-cols-4 gap-3 max-lg:grid-cols-2 max-sm:grid-cols-1">
                        <div class="rounded-md border border-gray-200 p-3">
                            <p class="text-xs uppercase text-gray-500">
                                Asset Code
                            </p>

                            <p class="mt-1 font-bold text-gray-800">
                                {{ $allocation->inventoryAsset?->asset_code ?: '-' }}
                            </p>
                        </div>

                        <div class="rounded-md border border-gray-200 p-3">
                            <p class="text-xs uppercase text-gray-500">
                                Serial Number
                            </p>

                            <p class="mt-1 text-sm text-gray-800">
                                {{ $allocation->inventoryAsset?->serial_number ?: '-' }}
                            </p>
                        </div>

                        <div class="rounded-md border border-gray-200 p-3">
                            <p class="text-xs uppercase text-gray-500">
                                Current Asset Status
                            </p>

                            <p class="mt-1 text-sm font-semibold uppercase text-gray-800">
                                {{ $allocation->inventoryAsset?->status ?: '-' }}
                            </p>
                        </div>

                        <div class="rounded-md border border-gray-200 p-3">
                            <p class="text-xs uppercase text-gray-500">
                                Current Condition
                            </p>

                            <p class="mt-1 text-sm font-semibold uppercase text-gray-800">
                                {{ $allocation->inventoryAsset?->condition ?: '-' }}
                            </p>
                        </div>
                    </div>

                    @if (
                        $canOperate
                        && $allocation->status === 'return_pending'
                        && bouncer()->hasPermission('delivery-orders.return.check-in')
                    )
                        <form
                            method="POST"
                            action="{{ route(
                                'admin.delivery-orders.return.check-in',
                                [
                                    $deliveryOrder->id,
                                    $allocation->id,
                                ]
                            ) }}"
                            class="mt-4 grid grid-cols-3 gap-3 max-lg:grid-cols-1"
                        >
                            @csrf
                            @method('PUT')

                            <div>
                                <label class="mb-1.5 block text-sm font-medium text-gray-800">
                                    Return Condition
                                </label>

                                <select
                                    name="condition"
                                    required
                                    class="w-full rounded-md border border-gray-300 bg-white px-3 py-2.5 text-sm"
                                >
                                    <option value="good">Good</option>
                                    <option value="fair">Fair</option>
                                    <option value="damaged">Damaged</option>
                                    <option value="missing">Missing</option>
                                </select>
                            </div>

                            <div>
                                <label class="mb-1.5 block text-sm font-medium text-gray-800">
                                    Notes
                                </label>

                                <input
                                    type="text"
                                    name="notes"
                                    maxlength="2000"
                                    placeholder="Optional inspection note"
                                    class="w-full rounded-md border border-gray-300 bg-white px-3 py-2.5 text-sm"
                                >
                            </div>

                            <div class="flex items-end">
                                <button
                                    type="submit"
                                    class="primary-button w-full justify-center"
                                    onclick="return confirm('Check-In asset ini dengan kondisi yang dipilih?')"
                                >
                                    Check-In Asset
                                </button>
                            </div>
                        </form>
                    @elseif ($allocation->status === 'returned')
                        <div class="mt-4 rounded-md bg-green-50 p-4 text-sm text-green-700">
                            <strong>Check-In selesai.</strong>
                            Condition:
                            {{ strtoupper(str_replace('_', ' ', $allocation->return_condition ?: '-')) }}

                            @if ($allocation->checked_in_at)
                                &middot;
                                {{ $allocation->checked_in_at->format('d M Y H:i') }}
                            @endif

                            @if ($allocation->checkedInBy)
                                &middot;
                                {{ $allocation->checkedInBy->name }}
                            @endif

                            @if ($allocation->return_notes)
                                <div class="mt-2">
                                    {{ $allocation->return_notes }}
                                </div>
                            @endif
                        </div>
                    @endif
                @else
                    @php
                        $outQuantity = (float) $allocation->quantity;
                        $returnedQuantity = $allocation->returned_quantity !== null
                            ? (float) $allocation->returned_quantity
                            : null;

                        $consumedQuantity = $returnedQuantity !== null
                            ? max($outQuantity - $returnedQuantity, 0)
                            : null;
                    @endphp

                    <div class="mt-4 grid grid-cols-3 gap-3 max-lg:grid-cols-1">
                        <div class="rounded-md border border-gray-200 p-3">
                            <p class="text-xs uppercase text-gray-500">
                                Quantity OUT
                            </p>

                            <p class="mt-1 text-lg font-bold text-gray-800">
                                {{ $formatQty($outQuantity) }}
                                {{ $allocation->inventoryItem?->unit ?: '-' }}
                            </p>
                        </div>

                        <div class="rounded-md border border-gray-200 p-3">
                            <p class="text-xs uppercase text-gray-500">
                                Current Warehouse Stock
                            </p>

                            <p class="mt-1 text-lg font-bold text-gray-800">
                                {{ $formatQty($allocation->inventoryItem?->quantity_on_hand ?: 0) }}
                                {{ $allocation->inventoryItem?->unit ?: '-' }}
                            </p>
                        </div>

                        <div class="rounded-md border border-gray-200 p-3">
                            <p class="text-xs uppercase text-gray-500">
                                Return Rule
                            </p>

                            <p class="mt-1 text-sm text-gray-600">
                                Masukkan hanya jumlah fisik yang benar-benar kembali.
                                Sisanya dianggap terpakai / consumed.
                            </p>
                        </div>
                    </div>

                    @if (
                        $canOperate
                        && $allocation->status === 'return_pending'
                        && bouncer()->hasPermission('delivery-orders.return.check-in')
                    )
                        <form
                            method="POST"
                            action="{{ route(
                                'admin.delivery-orders.return.check-in',
                                [
                                    $deliveryOrder->id,
                                    $allocation->id,
                                ]
                            ) }}"
                            class="mt-4 grid grid-cols-3 gap-3 max-lg:grid-cols-1"
                        >
                            @csrf
                            @method('PUT')

                            <div>
                                <label class="mb-1.5 block text-sm font-medium text-gray-800">
                                    Returned Quantity
                                </label>

                                <input
                                    type="number"
                                    name="returned_quantity"
                                    value="0"
                                    min="0"
                                    max="{{ $formatQty($outQuantity) }}"
                                    step="0.01"
                                    required
                                    class="w-full rounded-md border border-gray-300 bg-white px-3 py-2.5 text-sm"
                                >

                                <p class="mt-1 text-xs text-gray-500">
                                    Maximum:
                                    {{ $formatQty($outQuantity) }}
                                    {{ $allocation->inventoryItem?->unit ?: '-' }}
                                </p>
                            </div>

                            <div>
                                <label class="mb-1.5 block text-sm font-medium text-gray-800">
                                    Notes
                                </label>

                                <input
                                    type="text"
                                    name="notes"
                                    maxlength="2000"
                                    placeholder="Contoh: 1 roll unused returned"
                                    class="w-full rounded-md border border-gray-300 bg-white px-3 py-2.5 text-sm"
                                >
                            </div>

                            <div class="flex items-end">
                                <button
                                    type="submit"
                                    class="primary-button w-full justify-center"
                                    onclick="return confirm('Check-In quantity ini? Pastikan Returned Quantity sesuai barang fisik yang benar-benar kembali.')"
                                >
                                    Check-In Quantity
                                </button>
                            </div>
                        </form>
                    @elseif ($allocation->status === 'returned')
                        <div class="mt-4 rounded-md bg-green-50 p-4 text-sm text-green-700">
                            <strong>Check-In selesai.</strong>

                            Returned:
                            {{ $formatQty($returnedQuantity) }}
                            {{ $allocation->inventoryItem?->unit ?: '-' }}

                            &middot;

                            Consumed:
                            {{ $formatQty($consumedQuantity) }}
                            {{ $allocation->inventoryItem?->unit ?: '-' }}

                            @if ($allocation->checked_in_at)
                                &middot;
                                {{ $allocation->checked_in_at->format('d M Y H:i') }}
                            @endif

                            @if ($allocation->checkedInBy)
                                &middot;
                                {{ $allocation->checkedInBy->name }}
                            @endif

                            @if ($allocation->return_notes)
                                <div class="mt-2">
                                    {{ $allocation->return_notes }}
                                </div>
                            @endif
                        </div>
                    @endif
                @endif

                @if ($allocation->status === 'out')
                    <div class="mt-4 rounded-md border border-purple-200 bg-purple-50 px-4 py-3 text-sm text-purple-700">
                        Inventory masih OUT. Klik <strong>Start Return</strong> di bagian atas sebelum Check-In.
                    </div>
                @endif
            </div>
        @empty
            <div class="rounded-lg border border-dashed border-gray-300 bg-white p-8 text-center text-sm text-gray-500">
                Tidak ada allocation OUT / RETURN PENDING / RETURNED untuk Surat Jalan ini.
            </div>
        @endforelse
    </div>

    @if ($allReturned)
        <div class="mt-4 rounded-lg border border-green-200 bg-green-50 p-4 text-sm font-semibold text-green-700">
            Seluruh inventory sudah selesai Check-In.
            Surat Jalan dapat diubah menjadi RETURNED.
        </div>
    @endif
</x-admin::layouts>
