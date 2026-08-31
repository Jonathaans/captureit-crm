<?php

namespace Webkul\Admin\Http\Controllers\Inventory;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Webkul\Admin\Http\Controllers\Controller;

class InventoryAlertController extends Controller
{
    public function index(): View
    {
        $data = $this->buildDashboardData();

        $tab = request('tab', 'all');

        if (! in_array(
            $tab,
            [
                'all',
                'stock',
                'assets',
                'returns',
                'reorder',
            ],
            true
        )) {
            $tab = 'all';
        }

        $search = trim(
            (string) request('search', '')
        );

        $alerts = $data['alerts'];

        $filteredAlerts = $alerts
            ->filter(function (array $alert) use ($tab) {
                return match ($tab) {
                    'stock' => $alert['category'] === 'stock',
                    'assets' => $alert['category'] === 'asset',
                    'returns' => $alert['category'] === 'return',
                    'reorder' => in_array(
                        $alert['type'],
                        [
                            'critical_stock',
                            'low_stock',
                        ],
                        true
                    ),
                    default => true,
                };
            })
            ->when(
                $search !== '',
                function (Collection $collection) use ($search) {
                    $needle = mb_strtolower($search);

                    return $collection->filter(
                        function (array $alert) use ($needle) {
                            $haystack = mb_strtolower(
                                implode(
                                    ' ',
                                    [
                                        $alert['label'],
                                        $alert['item_name'],
                                        $alert['code'],
                                        $alert['warehouse'],
                                        $alert['current'],
                                        $alert['recommended_action'],
                                    ]
                                )
                            );

                            return str_contains(
                                $haystack,
                                $needle
                            );
                        }
                    );
                }
            )
            ->values();

        return view(
            'admin::inventory.alerts.index',
            [
                'alerts' => $alerts,
                'filteredAlerts' => $filteredAlerts,
                'summary' => $data['summary'],
                'reorderSuggestions' => $data['reorderSuggestions'],
                'attention' => $data['attention'],
                'tab' => $tab,
                'search' => $search,
            ]
        );
    }

    public function exportCsv(): StreamedResponse
    {
        $data = $this->buildDashboardData();

        $filename = sprintf(
            'inventory-alerts_%s.csv',
            now()->format('Ymd_His')
        );

        return response()->streamDownload(
            function () use ($data) {
                $handle = fopen(
                    'php://output',
                    'w'
                );

                if ($handle === false) {
                    return;
                }

                /*
                 * UTF-8 BOM for clean Microsoft Excel opening on Windows.
                 */
                fwrite(
                    $handle,
                    "\xEF\xBB\xBF"
                );

                fputcsv(
                    $handle,
                    [
                        'Alert Type',
                        'Category',
                        'Severity',
                        'Item / Asset',
                        'Code',
                        'Warehouse',
                        'Current Stock / Status',
                        'Detail',
                        'Recommended Action',
                        'Last Updated',
                    ],
                    ',',
                    '"',
                    ''
                );

                foreach (
                    $data['alerts']
                    as $alert
                ) {
                    fputcsv(
                        $handle,
                        [
                            $alert['label'],
                            strtoupper(
                                $alert['category']
                            ),
                            strtoupper(
                                $alert['severity']
                            ),
                            $alert['item_name'],
                            $alert['code'],
                            $alert['warehouse'],
                            $alert['current'],
                            $alert['detail'],
                            $alert['recommended_action'],
                            $alert['updated_at']
                                instanceof Carbon
                                ? $alert['updated_at']->format(
                                    'Y-m-d H:i:s'
                                )
                                : '',
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

    /**
     * Alert engine is intentionally computed from current operational state.
     * No separate alerts table means no stale duplicated state to maintain.
     */
    private function buildDashboardData(): array
    {
        $alerts = collect();

        $quantityItems = DB::table('inventory_items')
            ->leftJoin(
                'warehouses',
                'inventory_items.warehouse_id',
                '=',
                'warehouses.id'
            )
            ->where(
                'inventory_items.tracking_type',
                'quantity'
            )
            ->where(
                'inventory_items.is_active',
                true
            )
            ->where(
                'inventory_items.minimum_stock',
                '>',
                0
            )
            ->select([
                'inventory_items.id',
                'inventory_items.code',
                'inventory_items.name',
                'inventory_items.unit',
                'inventory_items.quantity_on_hand',
                'inventory_items.minimum_stock',
                'inventory_items.updated_at',
                'warehouses.name as warehouse_name',
            ])
            ->orderBy('inventory_items.name')
            ->get();

        $reorderSuggestions = collect();

        foreach ($quantityItems as $item) {
            $current = (float) $item->quantity_on_hand;
            $minimum = (float) $item->minimum_stock;

            if ($current > $minimum) {
                continue;
            }

            $target = max(
                $minimum * 2,
                $minimum
            );

            $suggested = max(
                round(
                    $target - $current,
                    2
                ),
                0
            );

            $severity = $current <= 0
                ? 'critical'
                : 'warning';

            $type = $current <= 0
                ? 'critical_stock'
                : 'low_stock';

            $label = $current <= 0
                ? 'Critical Stock'
                : 'Low Stock';

            $alerts->push([
                'category' => 'stock',
                'type' => $type,
                'label' => $label,
                'severity' => $severity,
                'item_name' => $item->name,
                'code' => $item->code,
                'warehouse' => $item->warehouse_name
                    ?: 'Warehouse',
                'current' => $this->formatQuantity(
                    $current
                ).' '.$item->unit,
                'detail' => 'Minimum '
                    .$this->formatQuantity($minimum)
                    .' '.$item->unit,
                'recommended_action' => 'Reorder '
                    .$this->formatQuantity($suggested)
                    .' '.$item->unit,
                'updated_at' => $this->carbon(
                    $item->updated_at
                ),
                'entity_type' => 'item',
                'entity_id' => (int) $item->id,
            ]);

            $reorderSuggestions->push([
                'id' => (int) $item->id,
                'code' => $item->code,
                'name' => $item->name,
                'warehouse' => $item->warehouse_name
                    ?: 'Warehouse',
                'unit' => $item->unit,
                'current' => $current,
                'minimum' => $minimum,
                'target' => $target,
                'suggested' => $suggested,
                'severity' => $severity,
                'ratio' => $minimum > 0
                    ? $current / $minimum
                    : 1,
            ]);
        }

        $problemAssets = DB::table('inventory_assets')
            ->join(
                'inventory_items',
                'inventory_assets.inventory_item_id',
                '=',
                'inventory_items.id'
            )
            ->leftJoin(
                'warehouses',
                'inventory_assets.warehouse_id',
                '=',
                'warehouses.id'
            )
            ->whereIn(
                'inventory_assets.status',
                [
                    'missing',
                    'damaged',
                    'maintenance',
                    'return_pending',
                ]
            )
            ->select([
                'inventory_assets.id',
                'inventory_assets.asset_code',
                'inventory_assets.status',
                'inventory_assets.condition',
                'inventory_assets.updated_at',
                'inventory_items.name as item_name',
                'warehouses.name as warehouse_name',
            ])
            ->orderByDesc(
                'inventory_assets.updated_at'
            )
            ->get();

        foreach ($problemAssets as $asset) {
            $status = strtolower(
                (string) $asset->status
            );

            [$category, $label, $severity, $action] = match ($status) {
                'missing' => [
                    'asset',
                    'Missing Asset',
                    'critical',
                    'Investigate & locate asset',
                ],
                'damaged' => [
                    'asset',
                    'Damaged Asset',
                    'warning',
                    'Send to Maintenance',
                ],
                'maintenance' => [
                    'asset',
                    'Maintenance',
                    'info',
                    'Review repair progress',
                ],
                'return_pending' => [
                    'return',
                    'Return Pending',
                    'warning',
                    'Finalize return inspection',
                ],
                default => [
                    'asset',
                    'Asset Attention',
                    'info',
                    'Review asset',
                ],
            };

            $alerts->push([
                'category' => $category,
                'type' => $status === 'return_pending'
                    ? 'return_pending'
                    : $status.'_asset',
                'label' => $label,
                'severity' => $severity,
                'item_name' => $asset->item_name,
                'code' => $asset->asset_code,
                'warehouse' => $asset->warehouse_name
                    ?: 'Warehouse',
                'current' => strtoupper(
                    str_replace(
                        '_',
                        ' ',
                        $status
                    )
                ),
                'detail' => 'Condition '
                    .strtoupper(
                        (string) (
                            $asset->condition
                            ?: '-'
                        )
                    ),
                'recommended_action' => $action,
                'updated_at' => $this->carbon(
                    $asset->updated_at
                ),
                'entity_type' => 'asset',
                'entity_id' => (int) $asset->id,
            ]);
        }

        /*
         * Availability risk:
         * item has physical serialized assets but zero AVAILABLE units.
         */
        $serializedAvailability = DB::table('inventory_items')
            ->leftJoin(
                'inventory_assets',
                'inventory_assets.inventory_item_id',
                '=',
                'inventory_items.id'
            )
            ->leftJoin(
                'warehouses',
                'inventory_items.warehouse_id',
                '=',
                'warehouses.id'
            )
            ->where(
                'inventory_items.tracking_type',
                'serialized'
            )
            ->where(
                'inventory_items.is_active',
                true
            )
            ->groupBy(
                'inventory_items.id',
                'inventory_items.code',
                'inventory_items.name',
                'inventory_items.updated_at',
                'warehouses.name'
            )
            ->selectRaw(
                'inventory_items.id,
                 inventory_items.code,
                 inventory_items.name,
                 inventory_items.updated_at,
                 warehouses.name as warehouse_name,
                 COUNT(inventory_assets.id) as total_assets,
                 SUM(
                     CASE
                         WHEN inventory_assets.status = "available"
                         THEN 1
                         ELSE 0
                     END
                 ) as available_assets'
            )
            ->get();

        foreach ($serializedAvailability as $item) {
            $total = (int) $item->total_assets;
            $available = (int) $item->available_assets;

            if ($total <= 0) {
                $alerts->push([
                    'category' => 'asset',
                    'type' => 'no_registered_assets',
                    'label' => 'No Registered Assets',
                    'severity' => 'warning',
                    'item_name' => $item->name,
                    'code' => $item->code,
                    'warehouse' => $item->warehouse_name
                        ?: 'Warehouse',
                    'current' => '0 assets',
                    'detail' => 'Serialized item has no physical asset',
                    'recommended_action' => 'Register physical assets',
                    'updated_at' => $this->carbon(
                        $item->updated_at
                    ),
                    'entity_type' => 'item',
                    'entity_id' => (int) $item->id,
                ]);

                continue;
            }

            if ($available > 0) {
                continue;
            }

            $alerts->push([
                'category' => 'asset',
                'type' => 'no_available_assets',
                'label' => 'No Available Asset',
                'severity' => 'critical',
                'item_name' => $item->name,
                'code' => $item->code,
                'warehouse' => $item->warehouse_name
                    ?: 'Warehouse',
                'current' => '0 / '.$total.' available',
                'detail' => 'All registered assets are currently unavailable',
                'recommended_action' => 'Review allocation, return, or maintenance',
                'updated_at' => $this->carbon(
                    $item->updated_at
                ),
                'entity_type' => 'item',
                'entity_id' => (int) $item->id,
            ]);
        }

        $severityWeight = [
            'critical' => 1,
            'warning' => 2,
            'info' => 3,
        ];

        $alerts = $alerts
            ->sort(function (
                array $left,
                array $right
            ) use ($severityWeight) {
                $leftWeight = $severityWeight[
                    $left['severity']
                ] ?? 99;

                $rightWeight = $severityWeight[
                    $right['severity']
                ] ?? 99;

                if ($leftWeight !== $rightWeight) {
                    return $leftWeight
                        <=> $rightWeight;
                }

                return (
                    $right['updated_at']?->timestamp
                    ?? 0
                ) <=> (
                    $left['updated_at']?->timestamp
                    ?? 0
                );
            })
            ->values();

        $reorderSuggestions = $reorderSuggestions
            ->sortBy('ratio')
            ->values();

        $summary = [
            'total_alerts' => $alerts->count(),

            'low_stock' => $alerts
                ->where(
                    'type',
                    'low_stock'
                )
                ->count(),

            'critical_stock' => $alerts
                ->where(
                    'type',
                    'critical_stock'
                )
                ->count(),

            'missing' => $alerts
                ->where(
                    'type',
                    'missing_asset'
                )
                ->count(),

            'maintenance' => $alerts
                ->where(
                    'type',
                    'maintenance_asset'
                )
                ->count(),

            'return_pending' => $alerts
                ->where(
                    'type',
                    'return_pending'
                )
                ->count(),

            'reorder' => $reorderSuggestions->count(),

            'critical' => $alerts
                ->where(
                    'severity',
                    'critical'
                )
                ->count(),

            'warning' => $alerts
                ->where(
                    'severity',
                    'warning'
                )
                ->count(),
        ];

        $attention = $alerts
            ->take(6)
            ->values();

        return compact(
            'alerts',
            'summary',
            'reorderSuggestions',
            'attention'
        );
    }

    private function carbon(
        mixed $value
    ): ?Carbon {
        if (! $value) {
            return null;
        }

        return Carbon::parse($value);
    }

    private function formatQuantity(
        float $value
    ): string {
        return rtrim(
            rtrim(
                number_format(
                    $value,
                    2,
                    '.',
                    ''
                ),
                '0'
            ),
            '.'
        );
    }
}
