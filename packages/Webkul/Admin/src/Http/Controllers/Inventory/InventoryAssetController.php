<?php

namespace Webkul\Admin\Http\Controllers\Inventory;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Webkul\Admin\DataGrids\Inventory\InventoryAssetDataGrid;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Warehouse\Models\InventoryAsset;
use Webkul\Warehouse\Models\InventoryItem;
use Webkul\Warehouse\Models\InventoryStockMovement;

class InventoryAssetController extends Controller
{
    public function index()
    {
        if (request()->ajax() || request()->expectsJson()) {
            return app(InventoryAssetDataGrid::class)->toJson();
        }

        $selectedItem = null;

        if (request()->filled('inventory_item_id')) {
            $selectedItem = InventoryItem::query()
                ->where('tracking_type', 'serialized')
                ->find(request()->integer('inventory_item_id'));
        }

        return view('admin::inventory.assets.index', compact('selectedItem'));
    }

    public function create(): View
    {
        $items = InventoryItem::query()
            ->where('tracking_type', 'serialized')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $selectedItemId = request()->integer('inventory_item_id') ?: null;

        return view(
            'admin::inventory.assets.create',
            compact('items', 'selectedItemId')
        );
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'inventory_item_id' => [
                'required',
                'integer',
                Rule::exists('inventory_items', 'id')
                    ->where(fn ($query) => $query
                        ->where('tracking_type', 'serialized')
                        ->where('is_active', true)),
            ],
            'asset_code' => [
                'required',
                'string',
                'max:50',
                'unique:inventory_assets,asset_code',
            ],
            'barcode_value' => [
                'nullable',
                'string',
                'max:100',
                'unique:inventory_assets,barcode_value',
            ],
            'serial_number' => [
                'nullable',
                'string',
                'max:150',
                'unique:inventory_assets,serial_number',
            ],
            'condition' => [
                'required',
                Rule::in(['good', 'fair', 'damaged']),
            ],
            'purchase_date'  => ['nullable', 'date'],
            'purchase_price' => ['nullable', 'numeric', 'min:0'],
            'notes'          => ['nullable', 'string'],
        ]);

        $item = InventoryItem::query()
            ->where('tracking_type', 'serialized')
            ->where('is_active', true)
            ->findOrFail($validated['inventory_item_id']);

        $asset = DB::transaction(function () use ($validated, $item) {
            $assetCode = trim($validated['asset_code']);

            $barcodeValue = trim((string) ($validated['barcode_value'] ?? ''));

            if ($barcodeValue === '') {
                $barcodeValue = $assetCode;
            }

            $condition = $validated['condition'];

            $status = $condition === 'damaged'
                ? 'damaged'
                : 'available';

            $asset = InventoryAsset::create([
                'inventory_item_id'     => $item->id,
                'asset_code'            => $assetCode,
                'barcode_value'         => $barcodeValue,
                'serial_number'         => ! empty($validated['serial_number'])
                    ? trim($validated['serial_number'])
                    : null,
                'warehouse_id'          => $item->warehouse_id,
                'warehouse_location_id' => $item->warehouse_location_id,
                'status'                => $status,
                'condition'             => $condition,
                'purchase_date'         => $validated['purchase_date'] ?? null,
                'purchase_price'        => $validated['purchase_price'] ?? null,
                'notes'                 => $validated['notes'] ?? null,
            ]);

            InventoryStockMovement::create([
                'inventory_item_id'     => $item->id,
                'inventory_asset_id'    => $asset->id,
                'warehouse_id'          => $asset->warehouse_id,
                'warehouse_location_id' => $asset->warehouse_location_id,
                'movement_type'         => 'opening',
                'quantity'              => 1,
                'from_status'           => null,
                'to_status'             => $status,
                'reference_type'        => 'asset_registration',
                'reference_id'          => $asset->id,
                'reference_number'      => $asset->asset_code,
                'performed_by'          => auth()->guard('user')->id(),
                'notes'                 => 'Initial asset registration.',
                'occurred_at'            => now(),
            ]);

            return $asset;
        });

        session()->flash(
            'success',
            'Asset '.$asset->asset_code.' berhasil dibuat.'
        );

        return redirect()->route(
            'admin.inventory.assets.edit',
            $asset->id
        );
    }

    public function edit(int $id): View
    {
        $asset = InventoryAsset::with([
            'item',
            'warehouse',
            'location',
        ])->findOrFail($id);

        return view('admin::inventory.assets.edit', compact('asset'));
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $asset = InventoryAsset::findOrFail($id);

        $validated = $request->validate([
            'asset_code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('inventory_assets', 'asset_code')
                    ->ignore($asset->id),
            ],
            'barcode_value' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('inventory_assets', 'barcode_value')
                    ->ignore($asset->id),
            ],
            'serial_number' => [
                'nullable',
                'string',
                'max:150',
                Rule::unique('inventory_assets', 'serial_number')
                    ->ignore($asset->id),
            ],
            'purchase_date'  => ['nullable', 'date'],
            'purchase_price' => ['nullable', 'numeric', 'min:0'],
            'notes'          => ['nullable', 'string'],
        ]);

        $assetCode = trim($validated['asset_code']);

        $barcodeValue = trim((string) ($validated['barcode_value'] ?? ''));

        if ($barcodeValue === '') {
            $barcodeValue = $assetCode;
        }

        $asset->update([
            'asset_code'     => $assetCode,
            'barcode_value'  => $barcodeValue,
            'serial_number'  => ! empty($validated['serial_number'])
                ? trim($validated['serial_number'])
                : null,
            'purchase_date'  => $validated['purchase_date'] ?? null,
            'purchase_price' => $validated['purchase_price'] ?? null,
            'notes'          => $validated['notes'] ?? null,
        ]);

        session()->flash(
            'success',
            'Asset '.$asset->asset_code.' berhasil diperbarui.'
        );

        return redirect()->route(
            'admin.inventory.assets.edit',
            $asset->id
        );
    }
}
