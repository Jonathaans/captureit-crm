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
use Webkul\Warehouse\Models\Warehouse;

class InventoryItemController extends Controller
{
    public function index()
    {
        if (
            request()->ajax()
            || request()->expectsJson()
        ) {
            return app(
                InventoryItemDataGrid::class
            )->toJson();
        }

        return view(
            'admin::inventory.items.index'
        );
    }

    public function create(): View
    {
        $warehouse = Warehouse::query()
            ->orderBy('id')
            ->firstOrFail();

        return view(
            'admin::inventory.items.create',
            compact('warehouse')
        );
    }

    public function store(
        Request $request
    ): RedirectResponse {
        $warehouse = Warehouse::query()
            ->orderBy('id')
            ->firstOrFail();

        $validated = $request->validate([
            'code' => [
                'required',
                'string',
                'max:50',
                'unique:inventory_items,code',
            ],
            'name' => [
                'required',
                'string',
                'max:255',
            ],
            'category' => [
                'nullable',
                'string',
                'max:100',
            ],
            'description' => [
                'nullable',
                'string',
            ],
            'unit' => [
                'required',
                'string',
                'max:30',
            ],
            'is_active' => [
                'nullable',
                'boolean',
            ],
        ]);

        $item = DB::transaction(
            function () use (
                $validated,
                $warehouse
            ) {
                return InventoryItem::create([
                    'code' => trim(
                        $validated['code']
                    ),
                    'name' => trim(
                        $validated['name']
                    ),
                    'category' => ! empty(
                        $validated['category']
                    )
                        ? trim(
                            $validated['category']
                        )
                        : null,
                    'description' => $validated[
                        'description'
                    ] ?? null,
                    'tracking_type' => 'serialized',
                    'unit' => trim(
                        $validated['unit']
                    ),
                    'quantity_on_hand' => 0,
                    'minimum_stock' => 0,
                    'warehouse_id' => $warehouse->id,
                    'warehouse_location_id' => null,
                    'is_active' => (bool) (
                        $validated['is_active']
                        ?? false
                    ),
                ]);
            }
        );

        session()->flash(
            'success',
            'Serialized Inventory Item berhasil dibuat.'
        );

        return redirect()->route(
            'admin.inventory.items.edit',
            $item->id
        );
    }

    public function edit(
        int $id
    ): View {
        $item = InventoryItem::query()
            ->with([
                'warehouse',
                'location',
            ])
            ->where(
                'tracking_type',
                'serialized'
            )
            ->findOrFail($id);

        return view(
            'admin::inventory.items.edit',
            compact('item')
        );
    }

    public function update(
        Request $request,
        int $id
    ): RedirectResponse {
        $item = InventoryItem::query()
            ->where(
                'tracking_type',
                'serialized'
            )
            ->findOrFail($id);

        $validated = $request->validate([
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique(
                    'inventory_items',
                    'code'
                )->ignore($item->id),
            ],
            'name' => [
                'required',
                'string',
                'max:255',
            ],
            'category' => [
                'nullable',
                'string',
                'max:100',
            ],
            'description' => [
                'nullable',
                'string',
            ],
            'unit' => [
                'required',
                'string',
                'max:30',
            ],
            'is_active' => [
                'nullable',
                'boolean',
            ],
        ]);

        $item->update([
            'code' => trim(
                $validated['code']
            ),
            'name' => trim(
                $validated['name']
            ),
            'category' => ! empty(
                $validated['category']
            )
                ? trim(
                    $validated['category']
                )
                : null,
            'description' => $validated[
                'description'
            ] ?? null,
            'unit' => trim(
                $validated['unit']
            ),
            'is_active' => (bool) (
                $validated['is_active']
                ?? false
            ),
        ]);

        session()->flash(
            'success',
            'Serialized Inventory Item berhasil diperbarui.'
        );

        return redirect()->route(
            'admin.inventory.items.edit',
            $item->id
        );
    }
}
