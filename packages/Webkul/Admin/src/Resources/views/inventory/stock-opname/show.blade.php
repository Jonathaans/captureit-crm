<x-admin::layouts>
    <x-slot:title>
        Stock Opname - {{ $session->reference_number }}
    </x-slot>

    @php
        $formatQty = static function ($value) {
            if ($value === null) {
                return '-';
            }

            return rtrim(
                rtrim(
                    number_format((float) $value, 2, '.', ''),
                    '0'
                ),
                '.'
            );
        };

        $resultClass = static function ($result) {
            return match ($result) {
                'found', 'matched' => 'bg-green-100 text-green-700',
                'pending', 'expected_absent' => 'bg-gray-100 text-gray-700',
                'missing', 'status_conflict', 'unexpected', 'unknown', 'variance' => 'bg-red-100 text-red-700',
                default => 'bg-gray-100 text-gray-700',
            };
        };

        $statusClass = static function ($status) {
            return match ($status) {
                'draft' => 'bg-gray-100 text-gray-700',
                'in_progress' => 'bg-blue-100 text-blue-700',
                'review' => 'bg-yellow-100 text-yellow-700',
                'finalized' => 'bg-green-100 text-green-700',
                default => 'bg-gray-100 text-gray-700',
            };
        };
    @endphp

    <div class="grid gap-5">
        <div class="flex items-start justify-between gap-4 rounded-lg border border-gray-200 bg-white p-5 max-lg:flex-wrap dark:border-gray-800 dark:bg-gray-900">
            <div>
                <a
                    href="{{ route('admin.inventory.stock-opname.index') }}"
                    class="text-sm text-gray-600 hover:text-brandColor"
                >
                    &larr; Back to Stock Opname
                </a>

                <div class="mt-3 flex items-center gap-3">
                    <p class="text-xl font-bold text-gray-800 dark:text-white">
                        {{ $session->reference_number }}
                    </p>

                    <span class="rounded-full px-3 py-1 text-xs font-bold {{ $statusClass($session->status) }}">
                        {{ strtoupper(str_replace('_', ' ', $session->status)) }}
                    </span>
                </div>

                <p class="mt-1 text-sm text-gray-500">
                    {{ $session->warehouse?->name ?: '-' }}

                    @if ($session->started_at)
                        &middot; Started {{ $session->started_at->format('d M Y H:i') }}
                    @endif

                    @if ($session->finalized_at)
                        &middot; Finalized {{ $session->finalized_at->format('d M Y H:i') }}
                    @endif
                </p>

                @if ($session->notes)
                    <p class="mt-2 text-sm text-gray-600">
                        {{ $session->notes }}
                    </p>
                @endif
            </div>

            <div class="flex flex-wrap gap-2">
                @if (
                    $session->status === 'draft'
                    && bouncer()->hasPermission('inventory.stock-opname.count')
                )
                    <form
                        method="POST"
                        action="{{ route('admin.inventory.stock-opname.start', $session->id) }}"
                    >
                        @csrf
                        @method('PUT')

                        <button
                            type="submit"
                            class="primary-button"
                            onclick="return confirm('Start counting dan ambil snapshot inventory sekarang?')"
                        >
                            Start Counting
                        </button>
                    </form>
                @endif

                @if (
                    $session->status === 'in_progress'
                    && bouncer()->hasPermission('inventory.stock-opname.count')
                )
                    <form
                        method="POST"
                        action="{{ route('admin.inventory.stock-opname.review', $session->id) }}"
                    >
                        @csrf
                        @method('PUT')

                        <button
                            type="submit"
                            class="primary-button"
                            onclick="return confirm('Tutup counting sementara dan masuk REVIEW? Asset expected yang belum discan akan ditandai MISSING.')"
                        >
                            Review Results
                        </button>
                    </form>
                @endif

                @if (
                    $session->status === 'review'
                    && bouncer()->hasPermission('inventory.stock-opname.count')
                )
                    <form
                        method="POST"
                        action="{{ route('admin.inventory.stock-opname.resume', $session->id) }}"
                    >
                        @csrf
                        @method('PUT')

                        <button type="submit" class="secondary-button">
                            Resume Counting
                        </button>
                    </form>
                @endif

                @if (
                    $session->status === 'review'
                    && bouncer()->hasPermission('inventory.stock-opname.finalize')
                )
                    <form
                        method="POST"
                        action="{{ route('admin.inventory.stock-opname.finalize', $session->id) }}"
                    >
                        @csrf
                        @method('PUT')

                        <button
                            type="submit"
                            class="primary-button"
                            onclick="return confirm('FINALIZE Stock Opname? Quantity variance akan mengubah stock. AVAILABLE/DAMAGED yang benar-benar missing dapat berubah menjadi MISSING.')"
                        >
                            Finalize Stock Opname
                        </button>
                    </form>
                @endif
            </div>
        </div>

        @if ($errors->any())
            <div class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                <p class="font-bold">Stock Opname belum dapat dilanjutkan:</p>

                <ul class="mt-2 list-disc space-y-1 pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($session->status !== 'draft')
            <div class="grid grid-cols-5 gap-3 max-xl:grid-cols-3 max-md:grid-cols-2 max-sm:grid-cols-1">
                <div class="rounded-lg border border-gray-200 bg-white p-4">
                    <p class="text-xs font-bold uppercase text-gray-500">Expected Assets</p>
                    <p id="summary-expected" class="mt-2 text-2xl font-bold text-gray-800">{{ $summary['expected'] }}</p>
                </div>

                <div class="rounded-lg border border-blue-200 bg-blue-50 p-4">
                    <p class="text-xs font-bold uppercase text-blue-700">Scanned</p>
                    <p id="summary-scanned" class="mt-2 text-2xl font-bold text-blue-700">{{ $summary['scanned'] }}</p>
                </div>

                <div class="rounded-lg border border-red-200 bg-red-50 p-4">
                    <p class="text-xs font-bold uppercase text-red-700">Missing</p>
                    <p id="summary-missing" class="mt-2 text-2xl font-bold text-red-700">{{ $summary['missing'] }}</p>
                </div>

                <div class="rounded-lg border border-orange-200 bg-orange-50 p-4">
                    <p class="text-xs font-bold uppercase text-orange-700">Conflict</p>
                    <p id="summary-conflicts" class="mt-2 text-2xl font-bold text-orange-700">{{ $summary['conflicts'] }}</p>
                </div>

                <div class="rounded-lg border border-purple-200 bg-purple-50 p-4">
                    <p class="text-xs font-bold uppercase text-purple-700">Unknown QR</p>
                    <p id="summary-unknown" class="mt-2 text-2xl font-bold text-purple-700">{{ $summary['unknown'] }}</p>
                </div>
            </div>
        @endif

        @if (
            $session->status === 'in_progress'
            && bouncer()->hasPermission('inventory.stock-opname.count')
        )
            <div class="rounded-lg border border-green-200 bg-green-50 p-5">
                <div class="flex items-start justify-between gap-4 max-lg:flex-wrap">
                    <div>
                        <p class="text-lg font-bold text-green-900">
                            Serialized QR Scanner
                        </p>

                        <p class="mt-1 text-sm text-green-700">
                            Tidak perlu klik input. Scan asset lalu Enter. Scan hanya mencatat fakta fisik dan tidak langsung mengubah status asset.
                        </p>
                    </div>

                    <div class="rounded-full bg-white px-4 py-2 text-xs font-bold text-green-700">
                        OBSERVE ONLY
                    </div>
                </div>

                <div class="mt-4 flex items-center gap-3">
                    <div class="flex h-12 w-12 items-center justify-center rounded-full bg-green-600 text-xl font-bold text-white">
                        QR
                    </div>

                    <div>
                        <p
                            id="scanner-state"
                            class="text-sm font-bold text-green-900"
                        >
                            READY - langsung scan QR
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
            </div>
        @endif

        @if ($session->status === 'review')
            <div class="rounded-lg border border-yellow-200 bg-yellow-50 p-5 text-sm text-yellow-800">
                <p class="font-bold">REVIEW sebelum Finalize</p>

                <p class="mt-1">
                    MISSING dari asset AVAILABLE/DAMAGED dapat menjadi MISSING saat Finalize.
                    Asset ALLOCATED/PICKED yang tidak ditemukan tidak diubah otomatis karena mungkin terkait Surat Jalan.
                    OUT, RETURN PENDING, MAINTENANCE, RETIRED, atau wrong-status yang ditemukan fisik tetap membutuhkan reconciliation workflow.
                </p>
            </div>
        @endif

        @if ($session->status === 'finalized')
            <div class="rounded-lg border border-green-200 bg-green-50 p-5 text-sm text-green-800">
                <p class="font-bold">FINALIZED</p>

                <p class="mt-1">
                    Session menjadi audit history. Adjustment yang benar-benar dilakukan tercatat pada Inventory Movements dengan reference {{ $session->reference_number }}.
                </p>
            </div>
        @endif

        @if ($session->status !== 'draft')
            <div class="rounded-lg border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
                <div class="flex items-center justify-between border-b border-gray-200 p-4 dark:border-gray-800">
                    <div>
                        <p class="font-bold text-gray-800 dark:text-white">
                            Serialized Assets
                        </p>

                        <p class="mt-1 text-xs text-gray-500">
                            FOUND = sesuai. MISSING = expected tapi tidak discan. STATUS CONFLICT = fisik ditemukan tetapi status sistem tidak sesuai.
                        </p>
                    </div>

                    <span class="text-xs font-semibold text-gray-500">
                        {{ $serializedEntries->count() }} asset
                    </span>
                </div>

                @if ($serializedEntries->isEmpty())
                    <div class="p-6 text-sm text-gray-500">
                        Tidak ada serialized asset pada warehouse ini.
                    </div>
                @else
                    <div class="max-h-[650px] overflow-auto">
                        <table class="w-full">
                            <thead class="sticky top-0 border-b border-gray-200 bg-white text-left text-xs uppercase text-gray-500">
                                <tr>
                                    <th class="px-4 py-3">Asset</th>
                                    <th class="px-4 py-3">Item</th>
                                    <th class="px-4 py-3">Expected</th>
                                    <th class="px-4 py-3">Observed</th>
                                    <th class="px-4 py-3">Result</th>
                                    <th class="px-4 py-3">Scanned At</th>
                                </tr>
                            </thead>

                            <tbody>
                                @foreach ($serializedEntries as $entry)
                                    <tr
                                        id="stock-opname-entry-{{ $entry->id }}"
                                        class="border-b border-gray-100 last:border-b-0"
                                    >
                                        <td class="px-4 py-3 font-bold text-gray-800">
                                            {{ $entry->asset?->asset_code ?: '-' }}
                                        </td>

                                        <td class="px-4 py-3 text-sm">
                                            {{ $entry->item?->name ?: $entry->asset?->item?->name ?: '-' }}
                                        </td>

                                        <td class="px-4 py-3 text-sm font-semibold">
                                            {{ strtoupper($entry->expected_status ?: '-') }}
                                        </td>

                                        <td
                                            data-observed-status
                                            class="px-4 py-3 text-sm font-semibold"
                                        >
                                            {{ strtoupper($entry->observed_status ?: '-') }}
                                        </td>

                                        <td class="px-4 py-3">
                                            <span
                                                data-result-badge
                                                class="rounded-full px-3 py-1 text-xs font-bold {{ $resultClass($entry->result) }}"
                                            >
                                                {{ strtoupper(str_replace('_', ' ', $entry->result)) }}
                                            </span>
                                        </td>

                                        <td
                                            data-scanned-at
                                            class="whitespace-nowrap px-4 py-3 text-xs text-gray-500"
                                        >
                                            {{ $entry->scanned_at?->format('d M Y H:i:s') ?: '-' }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            <div class="rounded-lg border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
                <div class="flex items-center justify-between border-b border-gray-200 p-4 dark:border-gray-800">
                    <div>
                        <p class="font-bold text-gray-800 dark:text-white">
                            Quantity Count
                        </p>

                        <p class="mt-1 text-xs text-gray-500">
                            Masukkan actual physical quantity. Semua quantity item wajib dihitung sebelum Review.
                        </p>
                    </div>

                    <span class="text-xs font-semibold text-gray-500">
                        {{ $summary['quantity_counted'] }} / {{ $summary['quantity_total'] }} counted
                    </span>
                </div>

                @if ($quantityEntries->isEmpty())
                    <div class="p-6 text-sm text-gray-500">
                        Tidak ada quantity-tracked item pada warehouse ini.
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="border-b border-gray-200 text-left text-xs uppercase text-gray-500">
                                <tr>
                                    <th class="px-4 py-3">Item</th>
                                    <th class="px-4 py-3">System Snapshot</th>
                                    <th class="px-4 py-3">Actual Count</th>
                                    <th class="px-4 py-3">Variance</th>
                                    <th class="px-4 py-3">Result</th>
                                    @if ($session->status === 'in_progress')
                                        <th class="px-4 py-3 text-right">Action</th>
                                    @endif
                                </tr>
                            </thead>

                            <tbody>
                                @foreach ($quantityEntries as $entry)
                                    <tr class="border-b border-gray-100 last:border-b-0">
                                        <td class="px-4 py-3">
                                            <p class="font-bold text-gray-800">
                                                {{ $entry->item?->code }} — {{ $entry->item?->name }}
                                            </p>

                                            <p class="mt-1 text-xs text-gray-500">
                                                {{ $entry->item?->unit }}
                                            </p>
                                        </td>

                                        <td class="px-4 py-3 font-semibold">
                                            {{ $formatQty($entry->system_quantity) }} {{ $entry->item?->unit }}
                                        </td>

                                        @if (
                                            $session->status === 'in_progress'
                                            && bouncer()->hasPermission('inventory.stock-opname.count')
                                        )
                                            <form
                                                method="POST"
                                                action="{{ route(
                                                    'admin.inventory.stock-opname.quantity',
                                                    [$session->id, $entry->id]
                                                ) }}"
                                            >
                                                @csrf
                                                @method('PUT')

                                                <td class="px-4 py-3">
                                                    <input
                                                        type="number"
                                                        name="actual_quantity"
                                                        min="0"
                                                        step="0.01"
                                                        required
                                                        data-allow-typing
                                                        value="{{ $entry->actual_quantity !== null ? $formatQty($entry->actual_quantity) : '' }}"
                                                        class="w-32 rounded-md border border-gray-300 bg-white px-3 py-2 text-sm"
                                                    >
                                                </td>

                                                <td class="px-4 py-3 font-semibold {{ (float) $entry->variance !== 0.0 ? 'text-red-700' : 'text-gray-800' }}">
                                                    {{ $entry->variance !== null ? $formatQty($entry->variance) : '-' }}
                                                </td>

                                                <td class="px-4 py-3">
                                                    <span class="rounded-full px-3 py-1 text-xs font-bold {{ $resultClass($entry->result) }}">
                                                        {{ strtoupper(str_replace('_', ' ', $entry->result)) }}
                                                    </span>
                                                </td>

                                                <td class="px-4 py-3 text-right">
                                                    <button type="submit" class="secondary-button">
                                                        Save Count
                                                    </button>
                                                </td>
                                            </form>
                                        @else
                                            <td class="px-4 py-3 font-semibold">
                                                {{ $formatQty($entry->actual_quantity) }} {{ $entry->item?->unit }}
                                            </td>

                                            <td class="px-4 py-3 font-semibold {{ (float) $entry->variance !== 0.0 ? 'text-red-700' : 'text-gray-800' }}">
                                                {{ $formatQty($entry->variance) }}
                                            </td>

                                            <td class="px-4 py-3">
                                                <span class="rounded-full px-3 py-1 text-xs font-bold {{ $resultClass($entry->result) }}">
                                                    {{ strtoupper(str_replace('_', ' ', $entry->result)) }}
                                                </span>
                                            </td>
                                        @endif
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            @if ($unknownEntries->isNotEmpty())
                <div class="rounded-lg border border-red-200 bg-red-50">
                    <div class="border-b border-red-200 p-4">
                        <p class="font-bold text-red-800">
                            Unknown QR / Barcode
                        </p>

                        <p class="mt-1 text-xs text-red-700">
                            Kode berikut discan secara fisik tetapi tidak terdaftar sebagai Inventory Asset.
                        </p>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="border-b border-red-200 text-left text-xs uppercase text-red-700">
                                <tr>
                                    <th class="px-4 py-3">Scanned Value</th>
                                    <th class="px-4 py-3">Scanned At</th>
                                    <th class="px-4 py-3">By</th>
                                </tr>
                            </thead>

                            <tbody>
                                @foreach ($unknownEntries as $entry)
                                    <tr class="border-b border-red-100 last:border-b-0">
                                        <td class="px-4 py-3 font-mono font-bold text-red-800">
                                            {{ $entry->scan_value }}
                                        </td>

                                        <td class="px-4 py-3 text-sm text-red-700">
                                            {{ $entry->scanned_at?->format('d M Y H:i:s') ?: '-' }}
                                        </td>

                                        <td class="px-4 py-3 text-sm text-red-700">
                                            {{ $entry->scannedBy?->name ?: '-' }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        @else
            <div class="rounded-lg border border-dashed border-gray-300 bg-white p-8 text-center dark:border-gray-700 dark:bg-gray-900">
                <p class="font-bold text-gray-800 dark:text-white">
                    Session masih DRAFT
                </p>

                <p class="mt-2 text-sm text-gray-500">
                    Klik Start Counting untuk mengambil snapshot serialized assets dan quantity stock.
                </p>
            </div>
        @endif
    </div>

    @if (
        $session->status === 'in_progress'
        && bouncer()->hasPermission('inventory.stock-opname.count')
    )
        <script>
            (() => {
                const endpoint = @json(
                    route(
                        'admin.inventory.stock-opname.scan',
                        $session->id
                    )
                );

                const csrf = @json(csrf_token());

                let scanBuffer = '';
                let lastKeyAt = 0;
                let queue = [];
                let processing = false;

                const messageBox = document.getElementById('scan-message');
                const lastScan = document.getElementById('last-scan');
                const scannerState = document.getElementById('scanner-state');

                function resultClasses(result) {
                    if (result === 'found' || result === 'matched') {
                        return 'bg-green-100 text-green-700';
                    }

                    if (
                        [
                            'missing',
                            'status_conflict',
                            'unexpected',
                            'unknown',
                            'variance'
                        ].includes(result)
                    ) {
                        return 'bg-red-100 text-red-700';
                    }

                    return 'bg-gray-100 text-gray-700';
                }

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
                        messageBox.classList.add(
                            'bg-red-100',
                            'text-red-800'
                        );
                    } else if (type === 'warning') {
                        messageBox.classList.add(
                            'bg-yellow-100',
                            'text-yellow-800'
                        );
                    } else {
                        messageBox.classList.add(
                            'bg-green-100',
                            'text-green-800'
                        );
                    }

                    messageBox.textContent = message;
                }

                function beep(success = true) {
                    try {
                        const AudioContext =
                            window.AudioContext
                            || window.webkitAudioContext;

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
                        oscillator.stop(
                            context.currentTime
                            + (success ? 0.08 : 0.18)
                        );
                    } catch (error) {
                        // Visual feedback remains available.
                    }
                }

                function updateSummary(summary) {
                    [
                        'expected',
                        'scanned',
                        'missing',
                        'conflicts',
                        'unknown'
                    ].forEach((key) => {
                        const el = document.getElementById(
                            `summary-${key}`
                        );

                        if (el) {
                            el.textContent = summary[key];
                        }
                    });
                }

                function updateEntry(entry) {
                    if (! entry || ! entry.id) {
                        return;
                    }

                    const row = document.getElementById(
                        `stock-opname-entry-${entry.id}`
                    );

                    if (! row) {
                        return;
                    }

                    const observed = row.querySelector(
                        '[data-observed-status]'
                    );

                    const badge = row.querySelector(
                        '[data-result-badge]'
                    );

                    const scannedAt = row.querySelector(
                        '[data-scanned-at]'
                    );

                    if (observed) {
                        observed.textContent = String(
                            entry.observed_status || '-'
                        ).toUpperCase();
                    }

                    if (badge) {
                        badge.textContent = String(
                            entry.result || 'pending'
                        )
                            .replaceAll('_', ' ')
                            .toUpperCase();

                        badge.className =
                            'rounded-full px-3 py-1 text-xs font-bold '
                            + resultClasses(entry.result);
                    }

                    if (scannedAt) {
                        scannedAt.textContent =
                            entry.scanned_at || '-';
                    }
                }

                async function processNext() {
                    if (
                        processing
                        || queue.length === 0
                    ) {
                        return;
                    }

                    processing = true;

                    const code = queue.shift();

                    scannerState.textContent =
                        `PROCESSING ${code}`;

                    lastScan.textContent =
                        `Last scan: ${code}`;

                    try {
                        const response = await fetch(
                            endpoint,
                            {
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
                            }
                        );

                        const data = await response.json();

                        if (! response.ok) {
                            const errors = data.errors || {};
                            const firstError = Object
                                .values(errors)
                                .flat()
                                .shift();

                            throw new Error(
                                firstError
                                || data.message
                                || 'Scan gagal.'
                            );
                        }

                        updateEntry(data.entry);
                        updateSummary(data.summary);

                        showMessage(
                            data.message,
                            data.duplicate
                                ? 'warning'
                                : data.type
                        );

                        beep(data.type !== 'error');
                    } catch (error) {
                        showMessage(
                            error.message || 'Scan gagal.',
                            'error'
                        );

                        beep(false);
                    } finally {
                        processing = false;

                        scannerState.textContent =
                            queue.length
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

                document.addEventListener(
                    'keydown',
                    (event) => {
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
                    }
                );
            })();
        </script>
    @endif
</x-admin::layouts>
