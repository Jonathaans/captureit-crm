<?php

namespace Webkul\Admin\Http\Controllers\Inventory;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Webkul\Admin\DataGrids\Inventory\InventoryItemDataGrid;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Warehouse\Models\InventoryItem;
use Webkul\Warehouse\Models\InventoryStockMovement;
use Webkul\Warehouse\Models\Warehouse;

class InventoryItemController extends Controller
{
    public function index()
    {
        if (request()->ajax() || request()->expectsJson()) {
            return app(InventoryItemDataGrid::class)->toJson();
        }

        return view('admin::inventory.items.index');
    }

    public function create(): View
    {
        $warehouse = Warehouse::query()->orderBy('id')->firstOrFail();

        return view('admin::inventory.items.create', compact('warehouse'));
    }

    public function store(Request $request): RedirectResponse
    {
        $warehouse = Warehouse::query()->orderBy('id')->firstOrFail();

        $validated = $request->validate([
            'code'             => ['required', 'string', 'max:50', 'unique:inventory_items,code'],
            'name'             => ['required', 'string', 'max:255'],
            'category'         => ['nullable', 'string', 'max:100'],
            'description'      => ['nullable', 'string'],
            'tracking_type'    => ['required', Rule::in(['serialized', 'quantity'])],
            'unit'             => ['required', 'string', 'max:30'],
            'opening_stock'    => ['nullable', 'numeric', 'min:0'],
            'minimum_stock'    => ['nullable', 'numeric', 'min:0'],
            'is_active'        => ['nullable', 'boolean'],
        ]);

        $item = DB::transaction(function () use ($validated, $warehouse) {
            $isQuantity = $validated['tracking_type'] === 'quantity';

            $openingStock = $isQuantity
                ? (float) ($validated['opening_stock'] ?? 0)
                : 0;

            $item = InventoryItem::create([
                'code'                  => trim($validated['code']),
                'name'                  => trim($validated['name']),
                'category'              => ! empty($validated['category'])
                    ? trim($validated['category'])
                    : null,
                'description'           => $validated['description'] ?? null,
                'tracking_type'         => $validated['tracking_type'],
                'unit'                  => trim($validated['unit']),
                'quantity_on_hand'      => $openingStock,
                'minimum_stock'         => (float) ($validated['minimum_stock'] ?? 0),
                'warehouse_id'          => $warehouse->id,
                'warehouse_location_id' => null,
                'is_active'             => (bool) ($validated['is_active'] ?? false),
            ]);

            if ($isQuantity && $openingStock > 0) {
                InventoryStockMovement::create([
                    'inventory_item_id'     => $item->id,
                    'inventory_asset_id'    => null,
                    'warehouse_id'          => $warehouse->id,
                    'warehouse_location_id' => null,
                    'movement_type'         => 'opening',
                    'quantity'              => $openingStock,
                    'from_status'           => null,
                    'to_status'             => null,
                    'reference_type'        => 'opening_balance',
                    'reference_id'          => null,
                    'reference_number'      => null,
                    'performed_by'          => auth()->guard('user')->id(),
                    'notes'                 => 'Opening stock from Inventory Item creation.',
                    'occurred_at'            => now(),
                ]);
            }

            return $item;
        });

        session()->flash('success', 'Inventory Item berhasil dibuat.');

        return redirect()->route('admin.inventory.items.edit', $item->id);
    }

    public function edit(int $id): View
    {
        $item = InventoryItem::with(['warehouse', 'location'])
            ->findOrFail($id);

        return view('admin::inventory.items.edit', compact('item'));
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $item = InventoryItem::findOrFail($id);

        $validated = $request->validate([
            'code'          => [
                'required',
                'string',
                'max:50',
                Rule::unique('inventory_items', 'code')->ignore($item->id),
            ],
            'name'          => ['required', 'string', 'max:255'],
            'category'      => ['nullable', 'string', 'max:100'],
            'description'   => ['nullable', 'string'],
            'unit'          => ['required', 'string', 'max:30'],
            'minimum_stock' => ['nullable', 'numeric', 'min:0'],
            'is_active'     => ['nullable', 'boolean'],
        ]);

        $item->update([
            'code'          => trim($validated['code']),
            'name'          => trim($validated['name']),
            'category'      => ! empty($validated['category'])
                ? trim($validated['category'])
                : null,
            'description'   => $validated['description'] ?? null,
            'unit'          => trim($validated['unit']),
            'minimum_stock' => (float) ($validated['minimum_stock'] ?? 0),
            'is_active'     => (bool) ($validated['is_active'] ?? false),
        ]);

        session()->flash('success', 'Inventory Item berhasil diperbarui.');

        return redirect()->route('admin.inventory.items.edit', $item->id);
    }
}
