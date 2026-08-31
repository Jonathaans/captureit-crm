<?php

namespace Webkul\Admin\Http\Controllers\Inventory;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Warehouse\Models\InventoryStockOpnameEntry;
use Webkul\Warehouse\Models\InventoryStockOpnameSession;
use Webkul\Warehouse\Services\InventoryStockOpnameService;

class InventoryStockOpnameController extends Controller
{
    public function __construct(
        protected InventoryStockOpnameService $opnameService
    ) {}

    public function index(): View
    {
        $sessions = InventoryStockOpnameSession::query()
            ->with([
                'warehouse',
                'createdBy',
                'startedBy',
                'finalizedBy',
            ])
            ->orderByDesc('id')
            ->paginate(25);

        $summary = [
            'open' => InventoryStockOpnameSession::query()
                ->whereIn(
                    'status',
                    InventoryStockOpnameSession::OPEN_STATUSES
                )
                ->count(),

            'in_progress' => InventoryStockOpnameSession::query()
                ->where('status', 'in_progress')
                ->count(),

            'review' => InventoryStockOpnameSession::query()
                ->where('status', 'review')
                ->count(),

            'finalized' => InventoryStockOpnameSession::query()
                ->where('status', 'finalized')
                ->count(),
        ];

        return view(
            'admin::inventory.stock-opname.index',
            compact('sessions', 'summary')
        );
    }

    public function create(): View
    {
        $warehouseRows = DB::table('warehouses')
            ->orderBy('id')
            ->get([
                'id',
                'name',
            ]);

        /*
         * Duplicate database rows with the same physical warehouse name
         * must not become separate Stock Opname choices.
         *
         * Canonical row priority:
         * 1. most Inventory Assets
         * 2. most Inventory Items
         * 3. lowest warehouse ID
         */
        $warehouseGroups = $warehouseRows
            ->groupBy(
                fn ($warehouse) => mb_strtolower(
                    trim((string) $warehouse->name)
                )
            );

        $warehouses = $warehouseGroups
            ->map(function ($group) {
                return $group
                    ->map(function ($warehouse) {
                        $warehouse->asset_count = DB::table(
                            'inventory_assets'
                        )
                            ->where(
                                'warehouse_id',
                                $warehouse->id
                            )
                            ->count();

                        $warehouse->item_count = DB::table(
                            'inventory_items'
                        )
                            ->where(
                                'warehouse_id',
                                $warehouse->id
                            )
                            ->count();

                        return $warehouse;
                    })
                    ->sort(function ($left, $right) {
                        if (
                            (int) $left->asset_count
                            !== (int) $right->asset_count
                        ) {
                            return (int) $right->asset_count
                                <=> (int) $left->asset_count;
                        }

                        if (
                            (int) $left->item_count
                            !== (int) $right->item_count
                        ) {
                            return (int) $right->item_count
                                <=> (int) $left->item_count;
                        }

                        return (int) $left->id
                            <=> (int) $right->id;
                    })
                    ->first();
            })
            ->filter()
            ->sortBy('name')
            ->values();

        $ignoredDuplicateWarehouses = max(
            $warehouseRows->count()
            - $warehouses->count(),
            0
        );

        $singleWarehouse = $warehouses->count() === 1
            ? $warehouses->first()
            : null;

        return view(
            'admin::inventory.stock-opname.create',
            compact(
                'warehouses',
                'singleWarehouse',
                'ignoredDuplicateWarehouses'
            )
        );
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'warehouse_id' => [
                'required',
                'integer',
                'exists:warehouses,id',
            ],
            'notes' => [
                'nullable',
                'string',
                'max:5000',
            ],
        ]);

        $session = $this->opnameService->createSession(
            (int) $validated['warehouse_id'],
            $validated['notes'] ?? null,
            auth()->guard('user')->id()
        );

        return redirect()
            ->route(
                'admin.inventory.stock-opname.show',
                $session->id
            )
            ->with(
                'success',
                $session->reference_number.' dibuat. Klik Start Counting untuk mengambil snapshot inventory.'
            );
    }

    public function show(int $id): View
    {
        $session = $this->findSession($id);

        $serializedEntries = $session->entries
            ->where('entry_type', 'serialized')
            ->sortBy([
                fn ($a, $b) => strcmp(
                    (string) ($a->item?->name ?? ''),
                    (string) ($b->item?->name ?? '')
                ),
                fn ($a, $b) => strcmp(
                    (string) ($a->asset?->asset_code ?? ''),
                    (string) ($b->asset?->asset_code ?? '')
                ),
            ])
            ->values();

        $quantityEntries = $session->entries
            ->where('entry_type', 'quantity')
            ->sortBy(
                fn ($entry) => $entry->item?->name ?? ''
            )
            ->values();

        $unknownEntries = $session->entries
            ->where('entry_type', 'unknown')
            ->sortByDesc('scanned_at')
            ->values();

        $summary = $this->opnameService->summary(
            $session->id
        );

        return view(
            'admin::inventory.stock-opname.show',
            compact(
                'session',
                'serializedEntries',
                'quantityEntries',
                'unknownEntries',
                'summary'
            )
        );
    }

    public function exportCsv(int $id): StreamedResponse
    {
        $session = $this->findSession($id);

        $warehouseName = $session->warehouse?->name ?: 'Warehouse';

        $safeWarehouseName = preg_replace(
            '/[^A-Za-z0-9_-]+/',
            '-',
            $warehouseName
        );

        $safeWarehouseName = trim(
            (string) $safeWarehouseName,
            '-'
        );

        $safeWarehouseName = $safeWarehouseName !== ''
            ? $safeWarehouseName
            : 'Warehouse';

        $referenceNumber = $session->reference_number
            ?: 'SO-'.$session->id;

        $safeReferenceNumber = preg_replace(
            '/[^A-Za-z0-9_-]+/',
            '-',
            $referenceNumber
        );

        $filename = sprintf(
            'stock-opname_%s_%s_%s.csv',
            trim((string) $safeReferenceNumber, '-'),
            $safeWarehouseName,
            strtolower($session->status ?: 'unknown')
        );

        return response()->streamDownload(
            function () use (
                $session,
                $warehouseName
            ) {
                $handle = fopen('php://output', 'w');

                if ($handle === false) {
                    return;
                }

                /*
                 * UTF-8 BOM helps Microsoft Excel on Windows recognize
                 * Indonesian text and symbols correctly.
                 */
                fwrite(
                    $handle,
                    "\xEF\xBB\xBF"
                );

                fputcsv(
                    $handle,
                    [
                        'Stock Opname Number',
                        'Warehouse',
                        'Session Status',
                        'Started At',
                        'Finalized At',
                        'Entry Type',
                        'Item Code',
                        'Item Name',
                        'Asset Code',
                        'QR / Barcode',
                        'Expected Presence',
                        'Expected Status',
                        'Observed Status',
                        'Condition Snapshot',
                        'System Quantity',
                        'Actual Quantity',
                        'Variance',
                        'Result',
                        'Scanned At',
                        'Scanned By',
                        'Counted At',
                        'Counted By',
                        'Notes',
                    ],
                    ',',
                    '"',
                    ''
                );

                foreach (
                    $session->entries->sortBy('id')
                    as $entry
                ) {
                    $item = $entry->item
                        ?: $entry->asset?->item;

                    $asset = $entry->asset;

                    fputcsv(
                        $handle,
                        [
                            $session->reference_number
                                ?: 'SO-'.$session->id,

                            $warehouseName,

                            strtoupper(
                                str_replace(
                                    '_',
                                    ' ',
                                    (string) $session->status
                                )
                            ),

                            $session->started_at
                                ? $session->started_at->format(
                                    'Y-m-d H:i:s'
                                )
                                : '',

                            $session->finalized_at
                                ? $session->finalized_at->format(
                                    'Y-m-d H:i:s'
                                )
                                : '',

                            strtoupper(
                                str_replace(
                                    '_',
                                    ' ',
                                    (string) $entry->entry_type
                                )
                            ),

                            $item?->code ?: '',

                            $item?->name ?: '',

                            $asset?->asset_code ?: '',

                            $entry->scan_value
                                ?: $asset?->barcode_value
                                ?: $asset?->asset_code
                                ?: '',

                            $entry->expected_presence
                                ? 'YES'
                                : 'NO',

                            $entry->expected_status
                                ? strtoupper(
                                    str_replace(
                                        '_',
                                        ' ',
                                        $entry->expected_status
                                    )
                                )
                                : '',

                            $entry->observed_status
                                ? strtoupper(
                                    str_replace(
                                        '_',
                                        ' ',
                                        $entry->observed_status
                                    )
                                )
                                : '',

                            $entry->expected_condition
                                ? strtoupper(
                                    str_replace(
                                        '_',
                                        ' ',
                                        $entry->expected_condition
                                    )
                                )
                                : '',

                            $entry->system_quantity !== null
                                ? (string) $entry->system_quantity
                                : '',

                            $entry->actual_quantity !== null
                                ? (string) $entry->actual_quantity
                                : '',

                            $entry->variance !== null
                                ? (string) $entry->variance
                                : '',

                            strtoupper(
                                str_replace(
                                    '_',
                                    ' ',
                                    (string) $entry->result
                                )
                            ),

                            $entry->scanned_at
                                ? $entry->scanned_at->format(
                                    'Y-m-d H:i:s'
                                )
                                : '',

                            $entry->scannedBy?->name ?: '',

                            $entry->counted_at
                                ? $entry->counted_at->format(
                                    'Y-m-d H:i:s'
                                )
                                : '',

                            $entry->countedBy?->name ?: '',

                            trim((string) $entry->notes),
                        ],
                        ',',
                        '"',
                        ''
                    );
                }

                fclose($handle);
            },
            $filename,
            [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Cache-Control' => 'no-store, no-cache, must-revalidate',
            ]
        );
    }

    public function start(int $id): RedirectResponse
    {
        $session = InventoryStockOpnameSession::query()
            ->findOrFail($id);

        $this->opnameService->start(
            $session,
            auth()->guard('user')->id()
        );

        return redirect()
            ->route(
                'admin.inventory.stock-opname.show',
                $session->id
            )
            ->with(
                'success',
                'Stock Opname dimulai. Snapshot inventory sudah dibuat. Scanner READY.'
            );
    }

    public function scan(
        Request $request,
        int $id
    ): JsonResponse {
        $session = InventoryStockOpnameSession::query()
            ->findOrFail($id);

        $validated = $request->validate([
            'barcode' => [
                'required',
                'string',
                'max:100',
            ],
        ]);

        $result = $this->opnameService->scan(
            $session,
            $validated['barcode'],
            auth()->guard('user')->id()
        );

        $entry = $result['entry'];

        /*
         * scanned_by is already stored by InventoryStockOpnameService.
         * Load the relation here so the scanner UI can immediately show
         * which authenticated warehouse account performed the first scan.
         */
        $entry->loadMissing([
            'scannedBy',
            'asset.item',
            'item',
        ]);

        return response()->json([
            'success' => true,
            'duplicate' => $result['duplicate'],
            'message' => $result['message'],
            'type' => $result['type'],
            'summary' => $result['summary'],
            'entry' => [
                'id' => $entry->id,
                'entry_type' => $entry->entry_type,
                'asset_id' => $entry->inventory_asset_id,
                'asset_code' => $entry->asset?->asset_code
                    ?: $entry->scan_value,
                'item_name' => $entry->asset?->item?->name
                    ?: $entry->item?->name,
                'expected_status' => $entry->expected_status,
                'observed_status' => $entry->observed_status,
                'result' => $entry->result,
                'scanned_by' => $entry->scannedBy?->name,
                'scanned_at' => $entry->scanned_at
                    ? $entry->scanned_at->format('d M Y H:i:s')
                    : null,
            ],
        ]);
    }

    public function countQuantity(
        Request $request,
        int $id,
        int $entryId
    ): RedirectResponse {
        $session = InventoryStockOpnameSession::query()
            ->findOrFail($id);

        $entry = InventoryStockOpnameEntry::query()
            ->findOrFail($entryId);

        $validated = $request->validate([
            'actual_quantity' => [
                'required',
                'numeric',
                'min:0',
            ],
        ]);

        $entry = $this->opnameService->countQuantity(
            $session,
            $entry,
            (float) $validated['actual_quantity'],
            auth()->guard('user')->id()
        );

        return redirect()
            ->route(
                'admin.inventory.stock-opname.show',
                $session->id
            )
            ->with(
                'success',
                sprintf(
                    '%s actual count disimpan: %s %s.',
                    $entry->item?->name ?: 'Quantity item',
                    $this->formatQuantity(
                        (float) $entry->actual_quantity
                    ),
                    $entry->item?->unit ?: ''
                )
            );
    }

    public function review(int $id): RedirectResponse
    {
        $session = InventoryStockOpnameSession::query()
            ->findOrFail($id);

        $this->opnameService->review(
            $session,
            auth()->guard('user')->id()
        );

        return redirect()
            ->route(
                'admin.inventory.stock-opname.show',
                $session->id
            )
            ->with(
                'success',
                'Counting ditutup sementara. Review MISSING, CONFLICT, UNKNOWN, dan VARIANCE sebelum Finalize.'
            );
    }

    public function resume(int $id): RedirectResponse
    {
        $session = InventoryStockOpnameSession::query()
            ->findOrFail($id);

        $this->opnameService->resume($session);

        return redirect()
            ->route(
                'admin.inventory.stock-opname.show',
                $session->id
            )
            ->with(
                'success',
                'Session kembali IN PROGRESS. Scanner dan quantity count aktif lagi.'
            );
    }

    public function finalize(int $id): RedirectResponse
    {
        $session = InventoryStockOpnameSession::query()
            ->findOrFail($id);

        $this->opnameService->finalize(
            $session,
            auth()->guard('user')->id()
        );

        return redirect()
            ->route(
                'admin.inventory.stock-opname.show',
                $session->id
            )
            ->with(
                'success',
                'Stock Opname FINALIZED. Quantity variance dan safe serialized corrections sudah dicatat ke Inventory Movements.'
            );
    }

    private function findSession(
        int $id
    ): InventoryStockOpnameSession {
        return InventoryStockOpnameSession::query()
            ->with([
                'warehouse',
                'createdBy',
                'startedBy',
                'reviewedBy',
                'finalizedBy',
                'entries.item',
                'entries.asset.item',
                'entries.scannedBy',
                'entries.countedBy',
            ])
            ->findOrFail($id);
    }

    private function formatQuantity(float $value): string
    {
        return rtrim(
            rtrim(
                number_format($value, 2, '.', ''),
                '0'
            ),
            '.'
        );
    }
}
