<?php

namespace Webkul\Admin\Http\Controllers\Inventory;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Webkul\Admin\DataGrids\Inventory\InventoryMovementDataGrid;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Warehouse\Models\InventoryItem;
use Webkul\Warehouse\Models\InventoryStockMovement;

class InventoryMovementController extends Controller
{
    public function index()
    {
        if (request()->ajax() || request()->expectsJson()) {
            return app(InventoryMovementDataGrid::class)->toJson();
        }

        return view('admin::inventory.movements.index');
    }

    public function createStockAdjustment(): View
    {
        $items = InventoryItem::query()
            ->where('tracking_type', 'quantity')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $selectedItemId = request()->integer('inventory_item_id') ?: null;

        return view(
            'admin::inventory.movements.adjust-stock',
            compact('items', 'selectedItemId')
        );
    }

    public function storeStockAdjustment(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'inventory_item_id' => [
                'required',
                'integer',
                Rule::exists('inventory_items', 'id')
                    ->where(fn ($query) => $query
                        ->where('tracking_type', 'quantity')
                        ->where('is_active', true)),
            ],
            'movement_type' => [
                'required',
                Rule::in([
                    'stock_in',
                    'stock_out',
                    'adjustment_in',
                    'adjustment_out',
                ]),
            ],
            'quantity' => [
                'required',
                'numeric',
                'gt:0',
            ],
            'reference_number' => [
                'nullable',
                'string',
                'max:100',
            ],
            'notes' => [
                'required',
                'string',
                'max:2000',
            ],
        ]);

        $movement = DB::transaction(function () use ($validated) {
            $item = InventoryItem::query()
                ->where('tracking_type', 'quantity')
                ->where('is_active', true)
                ->lockForUpdate()
                ->findOrFail($validated['inventory_item_id']);

            $quantity = (float) $validated['quantity'];
            $before = (float) $item->quantity_on_hand;

            $isIncoming = in_array(
                $validated['movement_type'],
                ['stock_in', 'adjustment_in'],
                true
            );

            $after = $isIncoming
                ? $before + $quantity
                : $before - $quantity;

            if ($after < 0) {
                throw ValidationException::withMessages([
                    'quantity' => sprintf(
                        'Stock tidak cukup. Stock saat ini %s %s, tetapi Anda mencoba mengurangi %s %s.',
                        $this->formatQuantity($before),
                        $item->unit,
                        $this->formatQuantity($quantity),
                        $item->unit
                    ),
                ]);
            }

            if (! $isIncoming) {
                $reserved = (float) DB::table(
                    'delivery_order_inventory_allocations'
                )
                    ->where('inventory_item_id', $item->id)
                    ->where('tracking_type', 'quantity')
                    ->whereIn('status', [
                        'allocated',
                        'picked',
                    ])
                    ->sum('quantity');

                if ($after + 0.0001 < $reserved) {
                    throw ValidationException::withMessages([
                        'quantity' => sprintf(
                            'Stock tidak dapat dikurangi sampai %s %s karena %s %s sedang reserved untuk Surat Jalan.',
                            $this->formatQuantity($after),
                            $item->unit,
                            $this->formatQuantity($reserved),
                            $item->unit
                        ),
                    ]);
                }
            }

            $item->update([
                'quantity_on_hand' => $after,
            ]);

            $notes = sprintf(
                'Stock %s %s -> %s %s. %s',
                $this->formatQuantity($before),
                $item->unit,
                $this->formatQuantity($after),
                $item->unit,
                trim($validated['notes'])
            );

            return InventoryStockMovement::create([
                'inventory_item_id'     => $item->id,
                'inventory_asset_id'    => null,
                'warehouse_id'          => $item->warehouse_id,
                'warehouse_location_id' => $item->warehouse_location_id,
                'movement_type'         => $validated['movement_type'],
                'quantity'              => $quantity,
                'from_status'           => null,
                'to_status'             => null,
                'reference_type'        => 'manual_stock_movement',
                'reference_id'          => null,
                'reference_number'      => ! empty($validated['reference_number'])
                    ? trim($validated['reference_number'])
                    : null,
                'performed_by'          => auth()->guard('user')->id(),
                'notes'                 => $notes,
                'occurred_at'            => now(),
            ]);
        });

        session()->flash(
            'success',
            'Stock movement berhasil dicatat.'
        );

        return redirect()->route(
            'admin.inventory.movements.index',
            ['inventory_item_id' => $movement->inventory_item_id]
        );
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
