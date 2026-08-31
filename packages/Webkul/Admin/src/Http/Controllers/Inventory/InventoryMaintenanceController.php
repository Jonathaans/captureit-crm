<?php

namespace Webkul\Admin\Http\Controllers\Inventory;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Warehouse\Models\InventoryAsset;
use Webkul\Warehouse\Models\InventoryAssetMaintenance;
use Webkul\Warehouse\Services\InventoryMaintenanceService;

class InventoryMaintenanceController extends Controller
{
    public function __construct(
        protected InventoryMaintenanceService $maintenanceService
    ) {}

    public function index(): View
    {
        $damagedAssets = InventoryAsset::query()
            ->with('item')
            ->where('status', 'damaged')
            ->orderBy('asset_code')
            ->get();

        $activeMaintenances = InventoryAssetMaintenance::query()
            ->with(['asset.item', 'startedBy'])
            ->where('status', 'in_progress')
            ->orderByDesc('started_at')
            ->get();

        $history = InventoryAssetMaintenance::query()
            ->with([
                'asset.item',
                'startedBy',
                'completedBy',
                'retiredBy',
            ])
            ->whereIn('status', ['completed', 'retired'])
            ->orderByDesc('updated_at')
            ->paginate(25);

        $summary = [
            'damaged' => $damagedAssets->count(),
            'in_progress' => $activeMaintenances->count(),
            'completed' => InventoryAssetMaintenance::query()
                ->where('status', 'completed')
                ->count(),
            'retired' => InventoryAssetMaintenance::query()
                ->where('status', 'retired')
                ->count(),
        ];

        return view(
            'admin::inventory.maintenance.index',
            compact(
                'damagedAssets',
                'activeMaintenances',
                'history',
                'summary'
            )
        );
    }

    public function create(Request $request): View
    {
        $asset = null;

        if ($request->filled('asset_id')) {
            $asset = InventoryAsset::query()
                ->with('item')
                ->where('status', 'damaged')
                ->findOrFail($request->integer('asset_id'));
        }

        $damagedAssets = InventoryAsset::query()
            ->with('item')
            ->where('status', 'damaged')
            ->orderBy('asset_code')
            ->get();

        return view(
            'admin::inventory.maintenance.create',
            compact('asset', 'damagedAssets')
        );
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'inventory_asset_id' => [
                'required',
                'integer',
                Rule::exists('inventory_assets', 'id')
                    ->where(fn ($query) => $query->where('status', 'damaged')),
            ],
            'problem' => ['required', 'string', 'max:5000'],
            'technician_name' => ['nullable', 'string', 'max:150'],
        ]);

        $asset = InventoryAsset::query()
            ->findOrFail($validated['inventory_asset_id']);

        $maintenance = $this->maintenanceService->start(
            $asset,
            $validated['problem'],
            $validated['technician_name'] ?? null,
            auth()->guard('user')->id()
        );

        return redirect()
            ->route('admin.inventory.maintenance.show', $maintenance->id)
            ->with(
                'success',
                $asset->asset_code.' masuk MAINTENANCE.'
            );
    }

    public function show(int $id): View
    {
        $maintenance = InventoryAssetMaintenance::query()
            ->with([
                'asset.item',
                'asset.warehouse',
                'startedBy',
                'completedBy',
                'retiredBy',
            ])
            ->findOrFail($id);

        return view(
            'admin::inventory.maintenance.show',
            compact('maintenance')
        );
    }

    public function complete(
        Request $request,
        int $id
    ): RedirectResponse {
        $maintenance = InventoryAssetMaintenance::query()
            ->findOrFail($id);

        $validated = $request->validate([
            'result_condition' => [
                'required',
                Rule::in(['good', 'fair']),
            ],
            'technician_name' => ['nullable', 'string', 'max:150'],
            'repair_notes' => ['nullable', 'string', 'max:10000'],
            'repair_cost' => ['nullable', 'numeric', 'min:0'],
        ]);

        $this->maintenanceService->complete(
            $maintenance,
            $validated['result_condition'],
            $validated['technician_name'] ?? null,
            $validated['repair_notes'] ?? null,
            (float) ($validated['repair_cost'] ?? 0),
            auth()->guard('user')->id()
        );

        return redirect()
            ->route('admin.inventory.maintenance.show', $maintenance->id)
            ->with(
                'success',
                'Repair selesai. Asset kembali AVAILABLE.'
            );
    }

    public function retire(
        Request $request,
        int $id
    ): RedirectResponse {
        $maintenance = InventoryAssetMaintenance::query()
            ->findOrFail($id);

        $validated = $request->validate([
            'retirement_reason' => [
                'required',
                'string',
                'max:5000',
            ],
        ]);

        $this->maintenanceService->retire(
            $maintenance,
            $validated['retirement_reason'],
            auth()->guard('user')->id()
        );

        return redirect()
            ->route('admin.inventory.maintenance.show', $maintenance->id)
            ->with(
                'success',
                'Asset berhasil di-RETIRED.'
            );
    }
}
