<?php

namespace Webkul\Admin\Http\Controllers\PurchaseOrder;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Core\Support\BusinessUnit;
use Webkul\Invoice\Models\Expense;
use Webkul\Invoice\Models\PurchaseOrder;

class PurchaseOrderExpenseExportController extends Controller
{
    /**
     * Export PO Expense Report.
     *
     * Default scope:
     * - RELEASED
     * - COMPLETED
     *
     * If the PO index explicitly sends a status filter, the export follows it.
     *
     * One CSV detail row = one Purchase Order.
     */
    public function export(
        Request $request
    ): StreamedResponse {
        $status =
            $this->normalizeStatus(
                $request->input(
                    'status'
                )
            );

        $query =
            PurchaseOrder::query()
                ->with([
                    'invoice.person',
                    'invoice.user',
                    'items',
                ])
                ->orderBy(
                    'order_date'
                )
                ->orderBy(
                    'id'
                );

        if ($status) {
            $query->where(
                'status',
                $status
            );
        } else {
            /*
             * PO Expense default = actual posted expense lifecycle.
             * Draft and Cancelled are excluded by default.
             */
            $query->whereIn(
                'status',
                [
                    'released',
                    'completed',
                ]
            );
        }

        if (
            $request->filled(
                'invoice_id'
            )
        ) {
            $query->where(
                'invoice_id',
                $request->integer(
                    'invoice_id'
                )
            );
        }

        $search =
            trim(
                (string) $request->input(
                    'q',
                    ''
                )
            );

        if ($search !== '') {
            $query->where(
                function ($builder) use ($search) {
                    $like =
                        '%'
                        .$search
                        .'%';

                    $builder
                        ->where(
                            'po_number',
                            'like',
                            $like
                        )
                        ->orWhere(
                            'vendor_name',
                            'like',
                            $like
                        )
                        ->orWhere(
                            'invoice_number',
                            'like',
                            $like
                        )
                        ->orWhere(
                            'project_code',
                            'like',
                            $like
                        )
                        ->orWhere(
                            'project_name',
                            'like',
                            $like
                        )
                        ->orWhereHas(
                            'items',
                            fn ($itemQuery) =>
                                $itemQuery->where(
                                    'name',
                                    'like',
                                    $like
                                )
                        );
                }
            );
        }

        $purchaseOrders =
            $query->get();

        /*
         * Resolve posted Expense rows in one query.
         */
        $expenseIds =
            $purchaseOrders
                ->pluck(
                    'expense_id'
                )
                ->filter()
                ->map(
                    fn ($id) =>
                        (int) $id
                )
                ->unique()
                ->values();

        $expenses =
            $expenseIds->isEmpty()
                ? collect()
                : Expense::query()
                    ->whereIn(
                        'id',
                        $expenseIds
                    )
                    ->get()
                    ->keyBy(
                        'id'
                    );

        $summary =
            $this->buildSummary(
                $purchaseOrders,
                $expenses
            );

        $fileName =
            'PO-Expense-Report-'
            .now()->format(
                'Y-m-d'
            )
            .'.csv';

        return response()->streamDownload(
            function () use (
                $purchaseOrders,
                $expenses,
                $summary,
                $status,
                $search,
                $request
            ) {
                $handle =
                    fopen(
                        'php://output',
                        'w'
                    );

                if ($handle === false) {
                    return;
                }

                /*
                 * UTF-8 BOM so Microsoft Excel opens Indonesian/vendor names
                 * correctly without mojibake.
                 */
                fwrite(
                    $handle,
                    "\xEF\xBB\xBF"
                );

                $writeRow =
                    static function (
                        $stream,
                        array $row
                    ): void {
                        fputcsv(
                            $stream,
                            $row,
                            ';',
                            '"',
                            ''
                        );
                    };

                /*
                |--------------------------------------------------------------------------
                | REPORT META
                |--------------------------------------------------------------------------
                */

                $writeRow(
                    $handle,
                    [
                        'PO EXPENSE REPORT',
                    ]
                );

                $writeRow(
                    $handle,
                    [
                        'Exported At',
                        now()->format(
                            'Y-m-d H:i:s'
                        ),
                    ]
                );

                $writeRow(
                    $handle,
                    [
                        'Status Scope',
                        $status
                            ? strtoupper(
                                $status
                            )
                            : 'RELEASED + COMPLETED',
                    ]
                );

                $writeRow(
                    $handle,
                    [
                        'Search',
                        $search !== ''
                            ? $search
                            : 'ALL',
                    ]
                );

                $writeRow(
                    $handle,
                    [
                        'Invoice ID',
                        $request->filled(
                            'invoice_id'
                        )
                            ? $request->integer(
                                'invoice_id'
                            )
                            : 'ALL',
                    ]
                );

                $writeRow(
                    $handle,
                    []
                );

                /*
                |--------------------------------------------------------------------------
                | SUMMARY
                |--------------------------------------------------------------------------
                */

                $writeRow(
                    $handle,
                    [
                        'SUMMARY',
                    ]
                );

                $writeRow(
                    $handle,
                    [
                        'Metric',
                        'Amount',
                    ]
                );

                $writeRow(
                    $handle,
                    [
                        'PO Count',
                        $summary[
                            'count'
                        ],
                    ]
                );

                $writeRow(
                    $handle,
                    [
                        'PO Sub Total',
                        $summary[
                            'sub_total'
                        ],
                    ]
                );

                $writeRow(
                    $handle,
                    [
                        'PO Adjustment',
                        $summary[
                            'adjustment'
                        ],
                    ]
                );

                $writeRow(
                    $handle,
                    [
                        'PO Grand Total',
                        $summary[
                            'grand_total'
                        ],
                    ]
                );

                $writeRow(
                    $handle,
                    [
                        'Posted Expense Total',
                        $summary[
                            'expense_total'
                        ],
                    ]
                );

                $writeRow(
                    $handle,
                    [
                        'PO vs Expense Variance',
                        $summary[
                            'variance'
                        ],
                    ]
                );

                $writeRow(
                    $handle,
                    []
                );

                $writeRow(
                    $handle,
                    []
                );

                /*
                |--------------------------------------------------------------------------
                | DETAIL
                |--------------------------------------------------------------------------
                |
                | One row = one PO.
                | Products / Services are joined using " ; ".
                |
                */

                $writeRow(
                    $handle,
                    [
                        'PO Date',
                        'PO Number',
                        'Vendor Name',
                        'Invoice Number',
                        'Project Code',
                        'Project / Event Name',
                        'Client',
                        'Event Date',
                        'Sales Owner',
                        'Business Unit',
                        'Products / Services',
                        'Payment Terms',
                        'PO Status',
                        'Sub Total',
                        'Adjustment',
                        'Grand Total',
                        'Expense ID',
                        'Expense Category',
                        'Expense Date',
                        'Posted Expense Amount',
                        'Variance',
                        'Released At',
                        'Completed At',
                    ]
                );

                foreach (
                    $purchaseOrders
                    as $purchaseOrder
                ) {
                    $expense =
                        $purchaseOrder
                            ->expense_id
                            ? $expenses->get(
                                (int) $purchaseOrder
                                    ->expense_id
                            )
                            : null;

                    $expenseAmount =
                        $expense
                            ? (float) (
                                $expense->amount
                                ?? 0
                            )
                            : 0.0;

                    $grandTotal =
                        (float) $purchaseOrder
                            ->grand_total;

                    $products =
                        $purchaseOrder
                            ->items
                            ->pluck(
                                'name'
                            )
                            ->filter(
                                fn ($value) =>
                                    trim(
                                        (string) $value
                                    ) !== ''
                            )
                            ->unique()
                            ->implode(
                                ' ; '
                            );

                    $invoice =
                        $purchaseOrder
                            ->invoice;

                    $writeRow(
                        $handle,
                        [
                            $purchaseOrder
                                ->order_date
                                ?->format(
                                    'Y-m-d'
                                ),

                            $purchaseOrder
                                ->po_number,

                            $purchaseOrder
                                ->vendor_name,

                            $purchaseOrder
                                ->invoice_number
                                ?: $invoice
                                    ?->invoice_number,

                            $purchaseOrder
                                ->project_code
                                ?: $invoice
                                    ?->project_code,

                            $purchaseOrder
                                ->project_name
                                ?: $invoice
                                    ?->subject,

                            $invoice
                                ?->person
                                ?->name
                                ?: '-',

                            $invoice
                                ?->event_date
                                ?->format(
                                    'Y-m-d'
                                ),

                            $invoice
                                ?->user
                                ?->name
                                ?: '-',

                            $purchaseOrder
                                ->business_unit
                                ? BusinessUnit::label(
                                    $purchaseOrder
                                        ->business_unit
                                )
                                : (
                                    $invoice
                                        ?->business_unit
                                        ? BusinessUnit::label(
                                            $invoice
                                                ->business_unit
                                        )
                                        : '-'
                                ),

                            $products
                                !== ''
                                ? $products
                                : '-',

                            $purchaseOrder
                                ->payment_terms_label,

                            strtoupper(
                                (string) $purchaseOrder
                                    ->status
                            ),

                            (float) $purchaseOrder
                                ->sub_total,

                            (float) $purchaseOrder
                                ->adjustment_amount,

                            $grandTotal,

                            $purchaseOrder
                                ->expense_id
                                ?: '',

                            $expense
                                ?->category
                                ?: '',

                            $this->formatDateValue(
                                $expense
                                    ?->expense_date
                            ),

                            $expenseAmount,

                            $grandTotal
                                - $expenseAmount,

                            $purchaseOrder
                                ->released_at
                                ?->format(
                                    'Y-m-d H:i:s'
                                ),

                            $purchaseOrder
                                ->completed_at
                                ?->format(
                                    'Y-m-d H:i:s'
                                ),
                        ]
                    );
                }

                fclose(
                    $handle
                );
            },
            $fileName,
            [
                'Content-Type' =>
                    'text/csv; charset=UTF-8',
            ]
        );
    }

    private function normalizeStatus(
        mixed $value
    ): ?string {
        $status =
            strtolower(
                trim(
                    (string) (
                        $value
                        ?? ''
                    )
                )
            );

        return in_array(
            $status,
            [
                'draft',
                'released',
                'completed',
                'cancelled',
            ],
            true
        )
            ? $status
            : null;
    }

    private function buildSummary(
        $purchaseOrders,
        $expenses
    ): array {
        $subTotal =
            (float) $purchaseOrders
                ->sum(
                    fn ($purchaseOrder) =>
                        (float) $purchaseOrder
                            ->sub_total
                );

        $adjustment =
            (float) $purchaseOrders
                ->sum(
                    fn ($purchaseOrder) =>
                        (float) $purchaseOrder
                            ->adjustment_amount
                );

        $grandTotal =
            (float) $purchaseOrders
                ->sum(
                    fn ($purchaseOrder) =>
                        (float) $purchaseOrder
                            ->grand_total
                );

        $expenseTotal =
            (float) $expenses
                ->sum(
                    fn ($expense) =>
                        (float) (
                            $expense->amount
                            ?? 0
                        )
                );

        return [
            'count' =>
                $purchaseOrders
                    ->count(),

            'sub_total' =>
                $subTotal,

            'adjustment' =>
                $adjustment,

            'grand_total' =>
                $grandTotal,

            'expense_total' =>
                $expenseTotal,

            'variance' =>
                $grandTotal
                - $expenseTotal,
        ];
    }

    private function formatDateValue(
        mixed $value
    ): string {
        if (
            $value instanceof
            \DateTimeInterface
        ) {
            return $value->format(
                'Y-m-d'
            );
        }

        $value =
            trim(
                (string) (
                    $value
                    ?? ''
                )
            );

        if ($value === '') {
            return '';
        }

        return substr(
            $value,
            0,
            10
        );
    }
}
