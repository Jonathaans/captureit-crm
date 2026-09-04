<?php

declare(strict_types=1);

/**
 * INVENTORY QR 20x10 MM + RETURN DAMAGED NOTE V1
 *
 * Run from the project root:
 * php tools/apply_inventory_qr_return_damage_note_v1.php
 */

$root = dirname(__DIR__);

$paths = [
    'qr_view' => $root.'/packages/Webkul/Admin/src/Resources/views/inventory/assets/qr-labels.blade.php',
    'return_controller' => $root.'/packages/Webkul/Admin/src/Http/Controllers/DeliveryOrder/DeliveryOrderReturnController.php',
    'return_service' => $root.'/packages/Webkul/Invoice/src/Services/DeliveryOrderReturnService.php',
    'return_view' => $root.'/packages/Webkul/Admin/src/Resources/views/delivery-orders/return.blade.php',
    'qr_test' => $root.'/tests/Unit/InventoryQrLabel20x10LayoutTest.php',
    'return_test' => $root.'/tests/Unit/DeliveryOrderReturnDamageNoteTest.php',
];

function inventoryQrReturnV1Fail(string $message): never
{
    fwrite(STDERR, $message.PHP_EOL);
    exit(1);
}

function inventoryQrReturnV1Read(string $path): string
{
    $contents = file_get_contents($path);

    if ($contents === false) {
        throw new RuntimeException("Gagal membaca {$path}");
    }

    return str_replace(["\r\n", "\r"], "\n", $contents);
}

function inventoryQrReturnV1Write(string $path, string $contents): void
{
    $temporary = $path.'.tmp-inventory-qr-return-'.bin2hex(random_bytes(4));

    if (file_put_contents($temporary, $contents) === false) {
        @unlink($temporary);
        throw new RuntimeException("Gagal menulis temporary file {$temporary}");
    }

    if (! rename($temporary, $path)) {
        @unlink($temporary);
        throw new RuntimeException("Gagal mengganti {$path}");
    }
}

function inventoryQrReturnV1ReplaceOnce(
    string $source,
    string $search,
    string $replacement,
    string $label
): string {
    $count = substr_count($source, $search);

    if ($count !== 1) {
        throw new RuntimeException(
            "Preflight {$label} gagal: anchor ditemukan {$count} kali."
        );
    }

    return str_replace($search, $replacement, $source);
}

function inventoryQrReturnV1MethodRange(string $source, string $method): array
{
    $pattern = '/\bpublic\s+function\s+'.preg_quote($method, '/').'\s*\(/';

    if (preg_match($pattern, $source, $match, PREG_OFFSET_CAPTURE) !== 1) {
        throw new RuntimeException("Method public {$method} tidak ditemukan.");
    }

    $methodStart = (int) $match[0][1];
    $brace = strpos($source, '{', $methodStart);

    if ($brace === false) {
        throw new RuntimeException("Opening brace method {$method} tidak ditemukan.");
    }

    $length = strlen($source);
    $depth = 0;
    $state = 'code';
    $escaped = false;
    $end = null;

    for ($position = $brace; $position < $length; $position++) {
        $character = $source[$position];
        $next = $position + 1 < $length ? $source[$position + 1] : '';

        if ($state === 'single' || $state === 'double') {
            if ($escaped) {
                $escaped = false;
                continue;
            }

            if ($character === '\\') {
                $escaped = true;
                continue;
            }

            if (
                ($state === 'single' && $character === "'")
                || ($state === 'double' && $character === '"')
            ) {
                $state = 'code';
            }

            continue;
        }

        if ($state === 'line_comment') {
            if ($character === "\n") {
                $state = 'code';
            }

            continue;
        }

        if ($state === 'block_comment') {
            if ($character === '*' && $next === '/') {
                $state = 'code';
                $position++;
            }

            continue;
        }

        if ($character === "'") {
            $state = 'single';
            continue;
        }

        if ($character === '"') {
            $state = 'double';
            continue;
        }

        if (($character === '/' && $next === '/') || $character === '#') {
            $state = 'line_comment';
            $position += $character === '/' ? 1 : 0;
            continue;
        }

        if ($character === '/' && $next === '*') {
            $state = 'block_comment';
            $position++;
            continue;
        }

        if ($character === '{') {
            $depth++;
        } elseif ($character === '}') {
            $depth--;

            if ($depth === 0) {
                $end = $position + 1;
                break;
            }
        }
    }

    if ($end === null) {
        throw new RuntimeException("Closing brace method {$method} tidak ditemukan.");
    }

    $beforeMethod = substr($source, 0, $methodStart);
    $docStart = strrpos($beforeMethod, '/**');
    $docEnd = strrpos($beforeMethod, '*/');

    if (
        $docStart !== false
        && $docEnd !== false
        && trim(substr($source, $docEnd + 2, $methodStart - ($docEnd + 2))) === ''
    ) {
        $methodStart = $docStart;
    }

    return [$methodStart, $end];
}

function inventoryQrReturnV1ReplaceMethod(
    string $source,
    string $method,
    string $replacement
): string {
    [$start, $end] = inventoryQrReturnV1MethodRange($source, $method);

    return substr_replace($source, rtrim($replacement), $start, $end - $start);
}

function inventoryQrReturnV1Lint(string $path): array
{
    exec(
        escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($path).' 2>&1',
        $output,
        $code
    );

    return [$code, implode(PHP_EOL, $output)];
}

set_exception_handler(
    static function (Throwable $exception): void {
        inventoryQrReturnV1Fail('PATCH GAGAL: '.$exception->getMessage());
    }
);

echo "INVENTORY QR 20x10 MM + RETURN DAMAGED NOTE V1\n";
echo "===============================================\n\n";

foreach ($paths as $label => $path) {
    if (! is_file($path)) {
        inventoryQrReturnV1Fail("File {$label} tidak ditemukan: {$path}");
    }
}

$sources = [];

foreach (['qr_view', 'return_controller', 'return_service', 'return_view'] as $key) {
    $sources[$key] = inventoryQrReturnV1Read($paths[$key]);
}

$installedChecks = [
    str_contains($sources['qr_view'], 'INVENTORY QR LABEL 20X10MM V1'),
    str_contains($sources['return_controller'], 'RETURN DAMAGED NOTE V1'),
    str_contains($sources['return_service'], 'RETURN DAMAGED NOTE V1'),
    str_contains($sources['return_view'], 'RETURN DAMAGED NOTE V1'),
];

$alreadyInstalled = ! in_array(false, $installedChecks, true);

if (in_array(true, $installedChecks, true) && ! $alreadyInstalled) {
    throw new RuntimeException(
        'Patch terdeteksi hanya terpasang sebagian. Pulihkan backup .bak-inventory-qr-return-v1 lalu jalankan ulang.'
    );
}

$backups = [];

if (! $alreadyInstalled) {
    $sources['qr_view'] = <<<'BLADE'
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <title>Inventory Asset QR Labels - A4 - 20x10 mm</title>

    <style>
        @page {
            size: A4 portrait;
            margin: 8mm;
        }

        * {
            box-sizing: border-box;
        }

        html,
        body {
            margin: 0;
            padding: 0;
        }

        body {
            color: #111827;
            background: #e5e7eb;
            font-family: Arial, Helvetica, sans-serif;
        }

        .toolbar {
            position: sticky;
            top: 0;
            z-index: 10;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 12px 16px;
            border-bottom: 1px solid #d1d5db;
            background: white;
        }

        .toolbar-left,
        .toolbar-right {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .toolbar-info {
            color: #4b5563;
            font-size: 12px;
            font-weight: 700;
        }

        .toolbar a,
        .toolbar button {
            border: 1px solid #c79a19;
            border-radius: 6px;
            padding: 8px 12px;
            color: #8a6410;
            background: white;
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
        }

        .toolbar button {
            color: white;
            background: #c79a19;
        }

        .preview-wrapper {
            display: grid;
            justify-content: center;
            gap: 18px;
            padding: 20px;
        }

        /* INVENTORY QR LABEL 20X10MM V1
         * A4 usable area after 8 mm page margins: 194 x 281 mm.
         * 9 columns x 25 rows = 225 physical labels per page.
         * Every label is exactly 20 x 10 mm. The QR remains square at
         * 8 x 8 mm so it is not distorted.
         */
        .sheet {
            display: grid;
            grid-template-columns: repeat(9, 20mm);
            grid-template-rows: repeat(25, 10mm);
            gap: 1mm;
            width: 194mm;
            min-height: 281mm;
            align-content: start;
            justify-content: start;
            padding: 3.5mm 3mm;
            background: white;
            box-shadow: 0 4px 18px rgba(0, 0, 0, 0.12);
        }

        .label {
            display: grid;
            grid-template-columns: 8mm minmax(0, 1fr);
            align-items: center;
            gap: 0.8mm;
            width: 20mm;
            height: 10mm;
            overflow: hidden;
            border: 0.2mm dashed #9ca3af;
            border-radius: 0.6mm;
            padding: 0.7mm;
            background: white;
        }

        .qr {
            display: block;
            width: 8mm;
            height: 8mm;
            object-fit: contain;
        }

        .label-copy {
            min-width: 0;
            overflow: hidden;
        }

        .asset-code {
            overflow-wrap: anywhere;
            color: #111827;
            font-family: "Courier New", Courier, monospace;
            font-size: 5pt;
            font-weight: 800;
            line-height: 1.05;
        }

        .item-name {
            max-height: 3.2mm;
            margin-top: 0.6mm;
            overflow: hidden;
            color: #4b5563;
            font-size: 3.7pt;
            font-weight: 600;
            line-height: 1.05;
        }

        .empty {
            margin: 20px;
            padding: 30px;
            border: 1px dashed #9ca3af;
            background: white;
            text-align: center;
        }

        @media print {
            html,
            body {
                width: 210mm;
                min-height: 297mm;
                background: white;
            }

            .toolbar,
            .empty {
                display: none !important;
            }

            .preview-wrapper {
                display: block;
                padding: 0;
            }

            .sheet {
                width: 194mm;
                min-height: 281mm;
                margin: 0;
                box-shadow: none;
                page-break-after: always;
                break-after: page;
            }

            .sheet:last-child {
                page-break-after: auto;
                break-after: auto;
            }

            .label {
                break-inside: avoid;
                page-break-inside: avoid;
            }
        }
    </style>
</head>

<body>
    <div class="toolbar">
        <div class="toolbar-left">
            <a href="{{ route('admin.inventory.assets.index') }}">
                &larr; Back to Assets
            </a>

            <span class="toolbar-info">
                {{ $assets->count() }} label
                &middot;
                225 label / A4
                &middot;
                20 x 10 mm / label
                &middot;
                {{ (int) ceil($assets->count() / 225) }} page
            </span>
        </div>

        <div class="toolbar-right">
            <span class="toolbar-info">Print scale: 100% / Actual size</span>

            <button type="button" onclick="window.print()">
                Print A4 QR Sheet
            </button>
        </div>
    </div>

    @if ($assets->isEmpty())
        <div class="empty">
            Tidak ada asset untuk dicetak.
        </div>
    @else
        <div class="preview-wrapper">
            @foreach ($assets->chunk(225) as $pageAssets)
                <section class="sheet">
                    @foreach ($pageAssets as $asset)
                        <div class="label">
                            <img
                                src="{{ route(
                                    'admin.inventory.assets.qr-labels.svg',
                                    $asset->id
                                ) }}"
                                class="qr"
                                alt="QR {{ $asset->qr_value ?: $asset->asset_code }}"
                            >

                            <div class="label-copy">
                                <div class="asset-code">
                                    {{ $asset->asset_code }}
                                </div>

                                <div class="item-name">
                                    {{ $asset->item?->name ?: '-' }}
                                </div>
                            </div>
                        </div>
                    @endforeach
                </section>
            @endforeach
        </div>
    @endif
</body>
</html>
BLADE;

    $finalizeMethod = <<<'PHP'
    /**
     * RETURN DAMAGED NOTE V1
     *
     * Finalize inspection and quantity return in one submit. A damage reason
     * is mandatory for every serialized asset marked DAMAGED.
     */
    public function finalize(
        Request $request,
        int $id
    ): RedirectResponse {
        $deliveryOrder = $this->findDeliveryOrder($id);

        $validated = $request->validate([
            'conditions' => [
                'nullable',
                'array',
            ],

            'conditions.*' => [
                'required',
                Rule::in([
                    'good',
                    'fair',
                    'damaged',
                ]),
            ],

            'return_notes' => [
                'nullable',
                'array',
            ],

            'return_notes.*' => [
                'nullable',
                'string',
                'max:2000',
            ],

            'quantities' => [
                'nullable',
                'array',
            ],

            'quantities.*' => [
                'required',
                'numeric',
                'min:0',
            ],
        ]);

        $conditions = $validated['conditions'] ?? [];
        $returnNotes = $validated['return_notes'] ?? [];
        $damageErrors = [];

        foreach ($conditions as $allocationId => $condition) {
            if (strtolower((string) $condition) !== 'damaged') {
                continue;
            }

            $damageNote = trim((string) ($returnNotes[$allocationId] ?? ''));

            if ($damageNote === '') {
                $damageErrors['return_notes.'.$allocationId] =
                    'Alasan kerusakan wajib diisi untuk barang DAMAGED.';
            }
        }

        if ($damageErrors !== []) {
            throw ValidationException::withMessages($damageErrors);
        }

        $this->returnService->finalizeReturnBatch(
            $deliveryOrder,
            $conditions,
            $validated['quantities'] ?? [],
            auth()->guard('user')->id(),
            $returnNotes
        );

        return redirect()
            ->route(
                'admin.delivery-orders.return.show',
                $deliveryOrder->id
            )
            ->with(
                'success',
                'Return berhasil difinalisasi. Condition, alasan kerusakan, dan quantity return sudah disimpan.'
            );
    }
PHP;

    $sources['return_controller'] = inventoryQrReturnV1ReplaceMethod(
        $sources['return_controller'],
        'finalize',
        $finalizeMethod
    );

    $finalizeServiceMethod = <<<'PHP'
    /**
     * RETURN DAMAGED NOTE V1
     *
     * Finalize all received serialized assets and all quantity returns in one
     * transaction. Damage notes are mandatory and are persisted on the
     * allocation and its inventory movement.
     *
     * @param array<int, string> $conditions
     * @param array<int, float|int|string> $quantities
     * @param array<int, string> $returnNotes
     */
    public function finalizeReturnBatch(
        DeliveryOrder $deliveryOrder,
        array $conditions,
        array $quantities,
        ?int $performedBy = null,
        array $returnNotes = []
    ): void {
        $this->assertDelivered($deliveryOrder);

        DB::transaction(function () use (
            $deliveryOrder,
            $conditions,
            $quantities,
            $performedBy,
            $returnNotes
        ) {
            $notReceived = DeliveryOrderInventoryAllocation::query()
                ->where('delivery_order_id', $deliveryOrder->id)
                ->where('tracking_type', 'serialized')
                ->where('status', 'out')
                ->with('inventoryAsset')
                ->get();

            if ($notReceived->isNotEmpty()) {
                $codes = $notReceived
                    ->pluck('inventoryAsset.asset_code')
                    ->filter()
                    ->values()
                    ->all();

                throw ValidationException::withMessages([
                    'return' => 'Masih ada serialized asset yang belum discan kembali: '
                        .implode(', ', $codes)
                        .'. Scan barangnya atau tandai Missing.',
                ]);
            }

            $pendingSerialized = DeliveryOrderInventoryAllocation::query()
                ->where('delivery_order_id', $deliveryOrder->id)
                ->where('tracking_type', 'serialized')
                ->where('status', 'return_pending')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($pendingSerialized as $allocation) {
                $key = (string) $allocation->id;

                if (! array_key_exists($key, $conditions)) {
                    throw ValidationException::withMessages([
                        'conditions.'.$allocation->id => 'Pilih condition untuk seluruh asset yang sudah diterima.',
                    ]);
                }

                $condition = strtolower(
                    trim(
                        (string) $conditions[$key]
                    )
                );

                if (
                    ! in_array(
                        $condition,
                        [
                            'good',
                            'fair',
                            'damaged',
                        ],
                        true
                    )
                ) {
                    throw ValidationException::withMessages([
                        'conditions.'.$allocation->id => 'Condition hanya boleh Good, Fair, atau Damaged.',
                    ]);
                }

                $damageNote = null;

                if ($condition === 'damaged') {
                    $damageNote = trim((string) ($returnNotes[$key] ?? ''));

                    if ($damageNote === '') {
                        throw ValidationException::withMessages([
                            'return_notes.'.$allocation->id =>
                                'Alasan kerusakan wajib diisi untuk barang DAMAGED.',
                        ]);
                    }

                    if (mb_strlen($damageNote) > 2000) {
                        throw ValidationException::withMessages([
                            'return_notes.'.$allocation->id =>
                                'Alasan kerusakan maksimal 2000 karakter.',
                        ]);
                    }
                }

                $this->finalizeSerializedCheckIn(
                    $deliveryOrder,
                    $allocation,
                    $condition,
                    $damageNote,
                    $performedBy
                );
            }

            $activeQuantity = DeliveryOrderInventoryAllocation::query()
                ->where('delivery_order_id', $deliveryOrder->id)
                ->where('tracking_type', 'quantity')
                ->whereIn(
                    'status',
                    [
                        'out',
                        'return_pending',
                    ]
                )
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($activeQuantity as $allocation) {
                $key = (string) $allocation->id;

                if (! array_key_exists($key, $quantities)) {
                    throw ValidationException::withMessages([
                        'quantities.'.$allocation->id => 'Isi Returned Quantity untuk seluruh quantity item.',
                    ]);
                }

                $returnedQuantity = round(
                    (float) $quantities[$key],
                    2
                );

                if (
                    $returnedQuantity < 0
                    || $returnedQuantity > (float) $allocation->quantity + 0.0001
                ) {
                    throw ValidationException::withMessages([
                        'quantities.'.$allocation->id => sprintf(
                            'Returned Quantity harus antara 0 dan %s.',
                            $this->formatQuantity(
                                (float) $allocation->quantity
                            )
                        ),
                    ]);
                }

                if ($allocation->status === 'out') {
                    $this->moveOneToReturnPending(
                        $deliveryOrder,
                        $allocation,
                        $performedBy
                    );

                    $allocation->refresh();
                }

                $this->finalizeQuantityCheckIn(
                    $deliveryOrder,
                    $allocation,
                    $returnedQuantity,
                    null,
                    $performedBy
                );
            }
        });
    }
PHP;

    $sources['return_service'] = inventoryQrReturnV1ReplaceMethod(
        $sources['return_service'],
        'finalizeReturnBatch',
        $finalizeServiceMethod
    );

    $serializedGuardAnchor = <<<'PHP'
    private function finalizeSerializedCheckIn(
        DeliveryOrder $deliveryOrder,
        DeliveryOrderInventoryAllocation $allocation,
        string $condition,
        ?string $notes,
        ?int $performedBy
    ): void {
        if ($allocation->status !== 'return_pending') {
PHP;
    $serializedGuardReplacement = <<<'PHP'
    private function finalizeSerializedCheckIn(
        DeliveryOrder $deliveryOrder,
        DeliveryOrderInventoryAllocation $allocation,
        string $condition,
        ?string $notes,
        ?int $performedBy
    ): void {
        // RETURN DAMAGED NOTE V1: enforce the rule at the service boundary too.
        $notes = $notes !== null ? trim($notes) : null;

        if ($condition === 'damaged' && ($notes === null || $notes === '')) {
            throw ValidationException::withMessages([
                'notes' => 'Alasan kerusakan wajib diisi untuk barang DAMAGED.',
            ]);
        }

        if ($notes !== null && mb_strlen($notes) > 2000) {
            throw ValidationException::withMessages([
                'notes' => 'Alasan kerusakan maksimal 2000 karakter.',
            ]);
        }

        if ($allocation->status !== 'return_pending') {
PHP;
    $sources['return_service'] = inventoryQrReturnV1ReplaceOnce(
        $sources['return_service'],
        $serializedGuardAnchor,
        $serializedGuardReplacement,
        'return serialized damage note service guard'
    );

    $sources['return_view'] = inventoryQrReturnV1ReplaceOnce(
        $sources['return_view'],
        "                        \$isReturned = \$allocation->status === 'returned';\n",
        "                        \$isReturned = \$allocation->status === 'returned';\n"
            ."                        // RETURN DAMAGED NOTE V1\n"
            ."                        \$selectedCondition = old('conditions.'.\$allocation->id, 'good');\n",
        'return selected condition'
    );

    $oldConditionOptions = <<<'BLADE'
                                        <option value="good" selected>GOOD</option>
                                        <option value="fair">FAIR</option>
                                        <option value="damaged">DAMAGED</option>
BLADE;
    $newConditionOptions = <<<'BLADE'
                                        <option value="good" @selected($selectedCondition === 'good')>GOOD</option>
                                        <option value="fair" @selected($selectedCondition === 'fair')>FAIR</option>
                                        <option value="damaged" @selected($selectedCondition === 'damaged')>DAMAGED</option>
BLADE;
    $sources['return_view'] = inventoryQrReturnV1ReplaceOnce(
        $sources['return_view'],
        $oldConditionOptions,
        $newConditionOptions,
        'return condition options'
    );

    $damageNoteAnchor = <<<'BLADE'
                            </div>
                        </div>

                        @if (
                            $isWaiting
BLADE;
    $damageNoteMarkup = <<<'BLADE'
                            </div>
                        </div>

                        @if ($isReceived)
                            <!-- RETURN DAMAGED NOTE V1 -->
                            <div
                                data-damage-note-container
                                class="mt-3 rounded-md border border-red-200 bg-red-50 p-3 {{ $selectedCondition === 'damaged' ? '' : 'hidden' }}"
                            >
                                <label class="mb-1 block text-xs font-bold text-red-700">
                                    Alasan Barang Rusak <span class="text-red-600">*</span>
                                </label>

                                <textarea
                                    name="return_notes[{{ $allocation->id }}]"
                                    data-damage-note
                                    rows="2"
                                    maxlength="2000"
                                    placeholder="Jelaskan kerusakan barang saat kembali..."
                                    @disabled($selectedCondition !== 'damaged')
                                    @required($selectedCondition === 'damaged')
                                    class="w-full rounded-md border border-red-300 bg-white px-3 py-2 text-sm text-gray-800"
                                >{{ old('return_notes.'.$allocation->id) }}</textarea>

                                @error('return_notes.'.$allocation->id)
                                    <p class="mt-1 text-xs font-semibold text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        @elseif (
                            $isReturned
                            && $allocation->return_condition === 'damaged'
                            && $allocation->return_notes
                        )
                            <div class="mt-3 rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-800">
                                <strong>Alasan Rusak:</strong>
                                {{ $allocation->return_notes }}
                            </div>
                        @endif

                        @if (
                            $isWaiting
BLADE;
    $sources['return_view'] = inventoryQrReturnV1ReplaceOnce(
        $sources['return_view'],
        $damageNoteAnchor,
        $damageNoteMarkup,
        'return damage note markup'
    );

    $jsFunctionAnchor = <<<'BLADE'
                function updateAllocation(allocation) {
BLADE;
    $jsFunctions = <<<'BLADE'
                function ensureDamageNoteField(card, allocationId) {
                    let container = card.querySelector(
                        '[data-damage-note-container]'
                    );

                    if (container) {
                        return container;
                    }

                    container = document.createElement('div');
                    container.dataset.damageNoteContainer = '1';
                    container.className = 'mt-3 hidden rounded-md border border-red-200 bg-red-50 p-3';
                    container.innerHTML = `
                        <label class="mb-1 block text-xs font-bold text-red-700">
                            Alasan Barang Rusak <span class="text-red-600">*</span>
                        </label>
                        <textarea
                            name="return_notes[${allocationId}]"
                            data-damage-note
                            rows="2"
                            maxlength="2000"
                            placeholder="Jelaskan kerusakan barang saat kembali..."
                            disabled
                            class="w-full rounded-md border border-red-300 bg-white px-3 py-2 text-sm text-gray-800"
                        ></textarea>
                    `;
                    card.appendChild(container);

                    return container;
                }

                function syncDamageNoteField(select) {
                    const card = select.closest(
                        '[data-return-allocation-id]'
                    );

                    if (! card) {
                        return;
                    }

                    const allocationId = card.dataset.returnAllocationId;
                    const container = ensureDamageNoteField(
                        card,
                        allocationId
                    );
                    const note = container.querySelector(
                        '[data-damage-note]'
                    );
                    const damaged = select.value === 'damaged';

                    container.classList.toggle('hidden', ! damaged);

                    if (note) {
                        note.disabled = ! damaged;
                        note.required = damaged;

                        if (! damaged) {
                            note.value = '';
                        }
                    }
                }

                function bindConditionSelect(select) {
                    if (select.dataset.damageNoteBound === '1') {
                        return;
                    }

                    select.dataset.damageNoteBound = '1';
                    select.addEventListener(
                        'change',
                        () => syncDamageNoteField(select)
                    );
                    syncDamageNoteField(select);
                }

                function updateAllocation(allocation) {
BLADE;
    $sources['return_view'] = inventoryQrReturnV1ReplaceOnce(
        $sources['return_view'],
        $jsFunctionAnchor,
        $jsFunctions,
        'return damage note javascript functions'
    );

    $dynamicSelectAnchor = <<<'BLADE'
                            actions.appendChild(select);
BLADE;
    $dynamicSelectReplacement = <<<'BLADE'
                            actions.appendChild(select);
                            bindConditionSelect(select);
BLADE;
    $sources['return_view'] = inventoryQrReturnV1ReplaceOnce(
        $sources['return_view'],
        $dynamicSelectAnchor,
        $dynamicSelectReplacement,
        'return dynamic condition binding'
    );

    $initialBindingAnchor = <<<'BLADE'
                updateCounters();
            })();
BLADE;
    $initialBindingReplacement = <<<'BLADE'
                document.querySelectorAll('[data-condition-select]')
                    .forEach(bindConditionSelect);

                updateCounters();
            })();
BLADE;
    $sources['return_view'] = inventoryQrReturnV1ReplaceOnce(
        $sources['return_view'],
        $initialBindingAnchor,
        $initialBindingReplacement,
        'return initial condition binding'
    );

    $requiredMarkers = [
        'qr_view' => [
            'INVENTORY QR LABEL 20X10MM V1',
            'grid-template-columns: repeat(9, 20mm)',
            'grid-template-rows: repeat(25, 10mm)',
            'width: 20mm',
            'height: 10mm',
            '$assets->chunk(225)',
        ],
        'return_controller' => [
            'RETURN DAMAGED NOTE V1',
            "'return_notes.*'",
            'Alasan kerusakan wajib diisi',
        ],
        'return_service' => [
            'RETURN DAMAGED NOTE V1',
            'array $returnNotes = []',
            '$damageNote',
            "if (\$condition === 'damaged' && (\$notes === null || \$notes === ''))",
        ],
        'return_view' => [
            'RETURN DAMAGED NOTE V1',
            'data-damage-note-container',
            'name="return_notes[{{ $allocation->id }}]"',
            'syncDamageNoteField',
            'Alasan Rusak:',
        ],
    ];

    foreach ($requiredMarkers as $key => $markers) {
        foreach ($markers as $marker) {
            if (! str_contains($sources[$key], $marker)) {
                throw new RuntimeException("Validation {$key} gagal: {$marker}");
            }
        }
    }

    $stamp = date('Ymd-His');

    foreach (['qr_view', 'return_controller', 'return_service', 'return_view'] as $key) {
        $backup = $paths[$key].'.bak-inventory-qr-return-v1-'.$stamp;

        if (! copy($paths[$key], $backup)) {
            throw new RuntimeException("Gagal membuat backup {$backup}");
        }

        $backups[$key] = $backup;
    }

    try {
        foreach (['qr_view', 'return_controller', 'return_service', 'return_view'] as $key) {
            inventoryQrReturnV1Write(
                $paths[$key],
                rtrim($sources[$key]).PHP_EOL
            );
        }

        foreach (['return_controller', 'return_service', 'qr_test', 'return_test'] as $key) {
            [$lintCode, $lintOutput] = inventoryQrReturnV1Lint($paths[$key]);

            if ($lintCode !== 0) {
                throw new RuntimeException(
                    "PHP lint gagal {$paths[$key]}:\n{$lintOutput}"
                );
            }
        }
    } catch (Throwable $exception) {
        foreach ($backups as $key => $backup) {
            @copy($backup, $paths[$key]);
        }

        throw $exception;
    }

    echo "[OK] Layout QR 20 x 10 mm terpasang.\n";
    echo "[OK] Damage note Return terpasang.\n";
    echo "[OK] Backup source: .bak-inventory-qr-return-v1-{$stamp}\n";
} else {
    echo "[SKIP] Patch sudah terpasang.\n";
}

$php = escapeshellarg(PHP_BINARY);
$artisan = escapeshellarg($root.'/artisan');
passthru($php.' '.$artisan.' optimize:clear', $cacheCode);

if ($cacheCode !== 0) {
    echo "[WARN] Patch berhasil, tetapi optimize:clear gagal. Jalankan manual.\n";
}

echo "\n[PASS] Inventory QR + Return Damage Note V1 siap.\n";
echo "- Kertas A4, 225 label per halaman.\n";
echo "- Setiap label tepat 20 x 10 mm; QR di dalamnya 8 x 8 mm.\n";
echo "- Condition DAMAGED mewajibkan alasan maksimal 2000 karakter.\n";
echo "- Alasan tersimpan pada allocation dan movement history.\n\n";
echo "Jalankan checker:\n";
echo "php tools/check_inventory_qr_return_damage_note_v1.php\n";
