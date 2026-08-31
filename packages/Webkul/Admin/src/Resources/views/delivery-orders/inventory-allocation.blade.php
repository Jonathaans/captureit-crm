<x-admin::layouts>
    <x-slot:title>
        Inventory Allocation - {{ $deliveryOrder->delivery_order_number }}
    </x-slot>

    @php
        $formatQty = static function ($value) {
            return rtrim(
                rtrim(
                    number_format((float) $value, 2, '.', ''),
                    '0'
                ),
                '.'
            );
        };

        $status = strtolower(
            $deliveryOrder->status ?: 'draft'
        );

        $serializedItems = $deliveryOrder->items
            ->filter(
                fn ($item) =>
                    $item->inventoryItem
                    && $item->inventoryItem->isSerialized()
            );

        $quantityItems = $deliveryOrder->items
            ->filter(
                fn ($item) =>
                    $item->inventoryItem
                    && $item->inventoryItem->isQuantityTracked()
            );
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
                    Scan Allocation
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

        @if ($editable)
            <div
                id="scanner-state"
                class="rounded-lg border border-green-200 bg-green-50 px-5 py-3 text-right"
            >
                <p class="text-xs font-bold uppercase text-green-600">
                    Scanner
                </p>

                <p
                    id="scanner-state-text"
                    class="mt-1 text-sm font-bold text-green-800"
                >
                    READY - langsung scan QR
                </p>
            </div>
        @else
            <div class="rounded-lg bg-gray-100 px-4 py-3 text-sm font-semibold text-gray-600">
                Read Only
            </div>
        @endif
    </div>

    @if ($editable)
        <div class="mt-4 rounded-lg border border-green-200 bg-green-50 p-5">
            <div class="flex items-start justify-between gap-4 max-lg:flex-wrap">
                <div>
                    <p class="text-lg font-bold text-green-900">
                        Tidak perlu klik input atau tombol
                    </p>

                    <p class="mt-1 text-sm text-green-700">
                        Scanner bekerja seperti keyboard. Scan asset serialized dari urutan mana pun.
                        Saat scanner mengirim Enter, asset langsung masuk ke request yang cocok.
                    </p>
                </div>

                <div class="rounded-full bg-white px-4 py-2 text-xs font-bold text-green-700">
                    AVAILABLE &rarr; ALLOCATED
                </div>
            </div>

            <div class="mt-4 flex items-center gap-3">
                <div
                    id="scan-indicator"
                    class="flex h-12 w-12 items-center justify-center rounded-full bg-green-600 text-xl font-bold text-white"
                >
                    QR
                </div>

                <div>
                    <p class="text-sm font-semibold text-green-900">
                        Waiting for scan...
                    </p>

                    <p
                        id="last-scan"
                        class="mt-1 text-xs text-green-700"
                    >
                        Belum ada scan pada sesi ini.
                    </p>
                </div>
            </div>

            <div
                id="scan-message"
                class="mt-4 hidden rounded-md px-4 py-3 text-sm font-semibold"
            ></div>

            <div class="mt-4 rounded-md border border-green-200 bg-white/70 px-4 py-3 text-xs text-green-800">
                <strong>Double Event Protection aktif.</strong>
                Asset yang masih ALLOCATED, OUT, atau RETURN PENDING pada Surat Jalan lain
                akan ditolak otomatis. Asset baru dapat dipakai lagi setelah Return selesai
                dan status kembali AVAILABLE.
            </div>
        </div>
    @endif

    @if ($deliveryOrder->items->contains(fn ($item) => ! $item->inventoryItem))
        <div class="mt-4 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">
            <strong>Ada request yang belum terhubung ke Inventory Item.</strong>
            Edit Surat Jalan terlebih dahulu sebelum release.
        </div>
    @endif

    <div class="mt-5">
        <div class="mb-3 flex items-end justify-between gap-3">
            <div>
                <p class="text-lg font-bold text-gray-800 dark:text-white">
                    Serialized Assets
                </p>

                <p class="text-xs text-gray-500">
                    Camera, Printer, Mini PC, Lighting, dan asset unik lainnya.
                </p>
            </div>

            <p class="text-xs text-gray-500">
                {{ $serializedItems->count() }} request
            </p>
        </div>

        <div class="grid gap-4">
            @forelse ($serializedItems as $item)
                @php
                    $summary = $summaries[$item->id];

                    $allocations = $item->allocations
                        ->whereIn(
                            'status',
                            \Webkul\Invoice\Models\DeliveryOrderInventoryAllocation::ACTIVE_STATUSES
                        )
                        ->where('tracking_type', 'serialized');
                @endphp

                <div
                    id="allocation-item-{{ $item->id }}"
                    data-item-id="{{ $item->id }}"
                    class="rounded-lg border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900"
                >
                    <div class="flex items-start justify-between gap-4 max-sm:flex-wrap">
                        <div>
                            <p class="text-base font-bold text-gray-800 dark:text-white">
                                {{ $item->name }}
                            </p>

                            @if ($item->description)
                                <p class="mt-1 text-sm text-gray-500">
                                    {{ $item->description }}
                                </p>
                            @endif

                            <p class="mt-2 text-xs font-semibold text-gray-500">
                                {{ $item->inventoryItem->code }}
                                &middot;
                                {{ $item->inventoryItem->name }}
                            </p>
                        </div>

                        <span
                            data-complete-badge
                            class="rounded-full px-3 py-1 text-xs font-bold {{ $summary['complete'] ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700' }}"
                        >
                            {{ $summary['complete'] ? 'COMPLETE' : 'WAITING SCAN' }}
                        </span>
                    </div>

                    <div class="mt-4 grid grid-cols-4 gap-3 max-lg:grid-cols-2 max-sm:grid-cols-1">
                        <div class="rounded-md border border-gray-200 p-3">
                            <p class="text-xs uppercase text-gray-500">Request</p>

                            <p class="mt-1 text-xl font-bold text-gray-800">
                                {{ $formatQty($summary['need']) }}
                            </p>
                        </div>

                        <div class="rounded-md border border-gray-200 p-3">
                            <p class="text-xs uppercase text-gray-500">Scanned</p>

                            <p
                                data-allocated-count
                                class="mt-1 text-xl font-bold text-blue-700"
                            >
                                {{ $formatQty($summary['allocated']) }}
                            </p>
                        </div>

                        <div class="rounded-md border border-gray-200 p-3">
                            <p class="text-xs uppercase text-gray-500">Free Available</p>

                            <p
                                data-free-count
                                class="mt-1 text-xl font-bold text-green-700"
                            >
                                {{ $formatQty($summary['free_available']) }}
                            </p>
                        </div>

                        <div class="rounded-md border border-gray-200 p-3">
                            <p class="text-xs uppercase text-gray-500">Shortage</p>

                            <p
                                data-shortage-count
                                class="mt-1 text-xl font-bold {{ $summary['shortage'] > 0 ? 'text-red-700' : 'text-gray-800' }}"
                            >
                                {{ $formatQty($summary['shortage']) }}
                            </p>
                        </div>
                    </div>

                    <div class="mt-4">
                        <p class="mb-2 text-xs font-bold uppercase text-gray-500">
                            Actual Assets
                        </p>

                        <div
                            data-asset-list
                            class="flex flex-wrap gap-2"
                        >
                            @forelse ($allocations as $allocation)
                                <span
                                    data-asset-id="{{ $allocation->inventory_asset_id }}"
                                    class="rounded-full bg-blue-100 px-3 py-1.5 text-xs font-bold text-blue-700"
                                >
                                    {{ $allocation->inventoryAsset?->asset_code ?: '-' }}
                                    &#10003;
                                </span>
                            @empty
                                <span
                                    data-empty-assets
                                    class="text-sm text-gray-400"
                                >
                                    Belum ada asset discan.
                                </span>
                            @endforelse
                        </div>
                    </div>

                    @if ($editable && $summary['allocated'] > 0)
                        <div class="mt-4 flex justify-end">
                            <form
                                method="POST"
                                action="{{ route(
                                    'admin.delivery-orders.inventory-allocation.release',
                                    [
                                        $deliveryOrder->id,
                                        $item->id,
                                    ]
                                ) }}"
                            >
                                @csrf
                                @method('DELETE')

                                <button
                                    type="submit"
                                    class="text-xs font-semibold text-red-600 hover:underline"
                                    onclick="return confirm('Reset seluruh scanned asset untuk {{ $item->name }}?')"
                                >
                                    Reset scanned assets
                                </button>
                            </form>
                        </div>
                    @endif
                </div>
            @empty
                <div class="rounded-lg border border-dashed border-gray-300 bg-white p-6 text-center text-sm text-gray-500">
                    Tidak ada serialized asset pada request ini.
                </div>
            @endforelse
        </div>
    </div>

    <div class="mt-6">
        <div class="mb-3">
            <p class="text-lg font-bold text-gray-800 dark:text-white">
                Manual Quantity Preparation
            </p>

            <p class="mt-1 text-xs text-gray-500">
                Quantity items tidak discan. Masukkan jumlah fisik yang benar-benar disiapkan.
                Paper Roll dan Frame tetap menggunakan flow ini.
            </p>
        </div>

        <div class="grid gap-3">
            @forelse ($quantityItems as $item)
                @php
                    $summary = $summaries[$item->id];
                @endphp

                <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                    <div class="flex items-center justify-between gap-4 max-sm:flex-wrap">
                        <div>
                            <p class="font-bold text-gray-800 dark:text-white">
                                {{ $item->name }}
                            </p>

                            <p class="mt-1 text-xs text-gray-500">
                                {{ $item->inventoryItem->code }}
                                &middot;
                                {{ $item->inventoryItem->name }}
                            </p>
                        </div>

                        <span class="rounded-full px-3 py-1 text-xs font-bold {{ $summary['complete'] ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700' }}">
                            {{ $summary['complete'] ? 'READY' : 'INPUT REQUIRED' }}
                        </span>
                    </div>

                    <div class="mt-3 grid grid-cols-4 gap-3 max-lg:grid-cols-2 max-sm:grid-cols-1">
                        <div class="rounded-md bg-gray-50 p-3 dark:bg-gray-950">
                            <p class="text-xs text-gray-500">Request</p>

                            <p class="mt-1 font-bold">
                                {{ $formatQty($summary['need']) }}
                                {{ $item->inventoryItem->unit }}
                            </p>
                        </div>

                        <div class="rounded-md bg-gray-50 p-3 dark:bg-gray-950">
                            <p class="text-xs text-gray-500">Prepared</p>

                            <p class="mt-1 font-bold text-blue-700">
                                {{ $formatQty($summary['allocated']) }}
                                {{ $item->inventoryItem->unit }}
                            </p>
                        </div>

                        <div class="rounded-md bg-gray-50 p-3 dark:bg-gray-950">
                            <p class="text-xs text-gray-500">Physical Stock</p>

                            <p class="mt-1 font-bold text-gray-800 dark:text-white">
                                {{ $formatQty($item->inventoryItem->quantity_on_hand) }}
                                {{ $item->inventoryItem->unit }}
                            </p>
                        </div>

                        <div class="rounded-md bg-gray-50 p-3 dark:bg-gray-950">
                            <p class="text-xs text-gray-500">Free Available</p>

                            <p class="mt-1 font-bold {{ $summary['free_available'] + 0.0001 < $summary['need'] ? 'text-red-700' : 'text-green-700' }}">
                                {{ $formatQty($summary['free_available']) }}
                                {{ $item->inventoryItem->unit }}
                            </p>
                        </div>
                    </div>

                    @if ($editable)
                        <form
                            method="POST"
                            action="{{ route(
                                'admin.delivery-orders.inventory-allocation.update',
                                [
                                    $deliveryOrder->id,
                                    $item->id,
                                ]
                            ) }}"
                            class="mt-4 grid grid-cols-[minmax(0,1fr)_auto] items-end gap-3 max-sm:grid-cols-1"
                        >
                            @csrf
                            @method('PUT')

                            <div>
                                <label class="mb-1 block text-xs font-bold text-gray-600 dark:text-gray-300">
                                    Prepared Quantity
                                </label>

                                <div class="flex items-center gap-2">
                                    <input
                                        type="number"
                                        name="quantity"
                                        value="{{ $formatQty($summary['allocated']) }}"
                                        min="0"
                                        max="{{ $formatQty($summary['need']) }}"
                                        step="0.01"
                                        required
                                        data-allow-typing
                                        class="w-full rounded-md border border-gray-300 bg-white px-3 py-2.5 text-sm dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                                    >

                                    <span class="whitespace-nowrap text-sm font-semibold text-gray-500">
                                        {{ $item->inventoryItem->unit }}
                                    </span>
                                </div>

                                <p class="mt-1 text-xs text-gray-400">
                                    Agar request COMPLETE, Prepared Quantity harus sama dengan Request.
                                </p>
                            </div>

                            <button
                                type="submit"
                                class="primary-button"
                            >
                                Save Quantity
                            </button>
                        </form>
                    @endif
                </div>
            @empty
                <div class="rounded-lg border border-dashed border-gray-300 bg-white p-6 text-center text-sm text-gray-500 dark:border-gray-700 dark:bg-gray-900">
                    Tidak ada quantity stock pada request ini.
                </div>
            @endforelse
        </div>

        <div class="mt-4 rounded-lg border border-blue-200 bg-blue-50 p-4 text-xs text-blue-800 dark:border-blue-900 dark:bg-blue-900/20 dark:text-blue-200">
            <strong>Barcode Box:</strong>
            item seperti Extension Cable Box dan Props Box akan otomatis masuk bagian Serialized Assets
            setelah master Inventory Item dibuat sebagai <strong>serialized</strong> dan Equipment Template
            dipetakan ke item box tersebut.
        </div>
    </div>

    @if ($editable)
        <script>
            (() => {
                const endpoint = @json(
                    route(
                        'admin.delivery-orders.inventory-allocation.scan',
                        $deliveryOrder->id
                    )
                );

                const csrf = @json(csrf_token());

                let scanBuffer = '';
                let lastKeyAt = 0;
                let queue = [];
                let processing = false;

                const messageBox = document.getElementById('scan-message');
                const lastScan = document.getElementById('last-scan');
                const scannerState = document.getElementById('scanner-state-text');

                function showMessage(message, type = 'success') {
                    messageBox.classList.remove(
                        'hidden',
                        'bg-green-100',
                        'text-green-800',
                        'bg-red-100',
                        'text-red-800',
                        'bg-yellow-100',
                        'text-yellow-800'
                    );

                    if (type === 'error') {
                        messageBox.classList.add('bg-red-100', 'text-red-800');
                    } else if (type === 'warning') {
                        messageBox.classList.add('bg-yellow-100', 'text-yellow-800');
                    } else {
                        messageBox.classList.add('bg-green-100', 'text-green-800');
                    }

                    messageBox.textContent = message;
                }

                function beep(success = true) {
                    try {
                        const AudioContext = window.AudioContext || window.webkitAudioContext;

                        if (! AudioContext) {
                            return;
                        }

                        const context = new AudioContext();
                        const oscillator = context.createOscillator();
                        const gain = context.createGain();

                        oscillator.frequency.value = success ? 880 : 220;
                        gain.gain.value = 0.04;

                        oscillator.connect(gain);
                        gain.connect(context.destination);

                        oscillator.start();
                        oscillator.stop(context.currentTime + (success ? 0.08 : 0.18));
                    } catch (error) {
                        // Visual feedback remains available if audio is blocked.
                    }
                }

                function updateItem(item) {
                    const card = document.querySelector(
                        `[data-item-id="${item.id}"]`
                    );

                    if (! card) {
                        return;
                    }

                    const allocated = card.querySelector('[data-allocated-count]');
                    const free = card.querySelector('[data-free-count]');
                    const shortage = card.querySelector('[data-shortage-count]');
                    const badge = card.querySelector('[data-complete-badge]');
                    const list = card.querySelector('[data-asset-list]');

                    if (allocated) {
                        allocated.textContent = item.allocated;
                    }

                    if (free) {
                        free.textContent = item.free_available;
                    }

                    if (shortage) {
                        shortage.textContent = item.shortage;
                        shortage.classList.toggle('text-red-700', Number(item.shortage) > 0);
                    }

                    if (badge) {
                        badge.textContent = item.complete
                            ? 'COMPLETE'
                            : 'WAITING SCAN';

                        badge.className = 'rounded-full px-3 py-1 text-xs font-bold '
                            + (
                                item.complete
                                    ? 'bg-green-100 text-green-700'
                                    : 'bg-yellow-100 text-yellow-700'
                            );
                    }

                    if (list) {
                        list.innerHTML = '';

                        if (! item.assets.length) {
                            const empty = document.createElement('span');
                            empty.className = 'text-sm text-gray-400';
                            empty.textContent = 'Belum ada asset discan.';
                            list.appendChild(empty);
                        } else {
                            item.assets.forEach((asset) => {
                                const chip = document.createElement('span');

                                chip.className = 'rounded-full bg-blue-100 px-3 py-1.5 text-xs font-bold text-blue-700';
                                chip.dataset.assetId = asset.id;
                                chip.textContent = `${asset.code} ✓`;

                                list.appendChild(chip);
                            });
                        }
                    }
                }

                async function processNext() {
                    if (processing || queue.length === 0) {
                        return;
                    }

                    processing = true;

                    const code = queue.shift();

                    scannerState.textContent = `PROCESSING ${code}`;
                    lastScan.textContent = `Last scan: ${code}`;

                    try {
                        const response = await fetch(endpoint, {
                            method: 'PUT',
                            headers: {
                                'Accept': 'application/json',
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrf,
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            body: JSON.stringify({
                                barcode: code,
                            }),
                        });

                        const data = await response.json();

                        if (! response.ok) {
                            const errors = data.errors || {};
                            const firstError = Object.values(errors)
                                .flat()
                                .shift();

                            throw new Error(
                                firstError
                                || data.message
                                || 'Scan gagal.'
                            );
                        }

                        updateItem(data.item);

                        showMessage(
                            data.message,
                            data.duplicate ? 'warning' : 'success'
                        );

                        beep(true);
                    } catch (error) {
                        showMessage(
                            error.message || 'Scan gagal.',
                            'error'
                        );

                        beep(false);
                    } finally {
                        processing = false;
                        scannerState.textContent = queue.length
                            ? `QUEUE ${queue.length} - PROCESSING`
                            : 'READY - langsung scan QR';

                        processNext();
                    }
                }

                function enqueue(code) {
                    code = String(code || '').trim();

                    if (! code) {
                        return;
                    }

                    queue.push(code);
                    processNext();
                }

                document.addEventListener('keydown', (event) => {
                    const target = event.target;

                    if (
                        target
                        && (
                            target.matches('[data-allow-typing]')
                            || target.tagName === 'INPUT'
                            || target.tagName === 'TEXTAREA'
                            || target.tagName === 'SELECT'
                            || target.isContentEditable
                        )
                    ) {
                        return;
                    }

                    if (
                        event.ctrlKey
                        || event.altKey
                        || event.metaKey
                    ) {
                        return;
                    }

                    const now = Date.now();

                    if (
                        lastKeyAt
                        && now - lastKeyAt > 800
                    ) {
                        scanBuffer = '';
                    }

                    lastKeyAt = now;

                    if (event.key === 'Enter') {
                        if (scanBuffer.trim()) {
                            event.preventDefault();

                            const code = scanBuffer.trim();
                            scanBuffer = '';

                            enqueue(code);
                        }

                        return;
                    }

                    if (
                        event.key.length === 1
                        && ! event.repeat
                    ) {
                        scanBuffer += event.key;
                    }
                });
            })();
        </script>
    @endif
</x-admin::layouts>
