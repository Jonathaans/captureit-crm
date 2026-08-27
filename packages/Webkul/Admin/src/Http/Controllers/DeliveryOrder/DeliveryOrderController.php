<?php

namespace Webkul\Admin\Http\Controllers\DeliveryOrder;

use Illuminate\View\View;
use Webkul\Admin\DataGrids\DeliveryOrder\DeliveryOrderDataGrid;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Invoice\Models\DeliveryOrder;

class DeliveryOrderController extends Controller
{
    /**
     * Delivery Order listing.
     */
    public function index()
    {
        if (
            request()->ajax()
            || request()->expectsJson()
        ) {
            return app(
                DeliveryOrderDataGrid::class
            )->toJson();
        }

        return view(
            'admin::delivery-orders.index'
        );
    }

    /**
     * Delivery Order detail.
     */
    public function show(int $id): View
    {
        $deliveryOrder = DeliveryOrder::with([
            'invoice',
            'quote',
            'person',
            'user',
            'creator',
            'items',
        ])->findOrFail($id);

        return view(
            'admin::delivery-orders.show',
            compact('deliveryOrder')
        );
    }
}