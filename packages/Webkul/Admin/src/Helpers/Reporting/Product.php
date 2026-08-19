<?php

namespace Webkul\Admin\Helpers\Reporting;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Webkul\Lead\Repositories\ProductRepository;

class Product extends AbstractReporting
{
    /**
     * Create a helper instance.
     *
     * @return void
     */
    public function __construct(
        protected ProductRepository $productRepository
    ) {
        parent::__construct();
    }

    /**
     * Build a lead-product query and apply visibility through the lead owner.
     */
    protected function getScopedLeadProductQuery(): Builder
    {
        $query = $this->productRepository
            ->resetModel()
            ->getModel()
            ->newQuery()
            ->leftJoin('leads', 'lead_products.lead_id', '=', 'leads.id');

        if (auth()->guard('user')->check()) {
            $userIds = bouncer()->getAuthorizedUserIds();

            if ($userIds !== null) {
                $query->whereIn('leads.user_id', $userIds);
            }
        }

        return $query;
    }

    /**
     * Gets top-selling products by revenue.
     *
     * @param  int|null  $limit
     */
    public function getTopSellingProductsByRevenue($limit = null): Collection
    {
        $query = $this->getScopedLeadProductQuery()
            ->with('product')
            ->select(
                'lead_products.product_id',
                DB::raw('SUM(lead_products.amount) as revenue')
            )
            ->whereBetween('leads.closed_at', [$this->startDate, $this->endDate])
            ->groupBy('lead_products.product_id')
            ->havingRaw('SUM(lead_products.amount) > 0')
            ->orderByDesc('revenue');

        if ($limit !== null) {
            $query->limit($limit);
        }

        return $query->get()->map(function ($item) {
            $price = $item->product?->price ?? 0;

            return [
                'id' => $item->product_id,
                'name' => $item->product?->name,
                'price' => $price,
                'formatted_price' => core()->formatBasePrice($price),
                'revenue' => (float) $item->revenue,
                'formatted_revenue' => core()->formatBasePrice($item->revenue),
            ];
        });
    }

    /**
     * Gets top-selling products by quantity.
     *
     * @param  int|null  $limit
     */
    public function getTopSellingProductsByQuantity($limit = null): Collection
    {
        $query = $this->getScopedLeadProductQuery()
            ->with('product')
            ->select(
                'lead_products.product_id',
                DB::raw('SUM(lead_products.quantity) as total_qty_ordered')
            )
            ->whereBetween('leads.closed_at', [$this->startDate, $this->endDate])
            ->groupBy('lead_products.product_id')
            ->havingRaw('SUM(lead_products.quantity) > 0')
            ->orderByDesc('total_qty_ordered');

        if ($limit !== null) {
            $query->limit($limit);
        }

        return $query->get()->map(function ($item) {
            $price = $item->product?->price ?? 0;

            return [
                'id' => $item->product_id,
                'name' => $item->product?->name,
                'price' => $price,
                'formatted_price' => core()->formatBasePrice($price),
                'total_qty_ordered' => (float) $item->total_qty_ordered,
            ];
        });
    }
}
