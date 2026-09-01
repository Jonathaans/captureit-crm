<?php

namespace Webkul\Invoice\Services;

use Carbon\CarbonInterface;
use Webkul\Invoice\Models\WorkOrder;

class WorkOrderNumberService
{
    public function generate(
        CarbonInterface|string|null $date = null
    ): string {
        $date =
            $date instanceof CarbonInterface
                ? $date
                : (
                    $date
                        ? now()->parse($date)
                        : now()
                );

        $yearMonth =
            $date->format('ym');

        $prefix =
            'SPK '
            .$yearMonth
            .'-';

        $last =
            WorkOrder::query()
                ->where(
                    'work_order_number',
                    'like',
                    $prefix.'%'
                )
                ->orderByDesc(
                    'work_order_number'
                )
                ->lockForUpdate()
                ->first();

        $next = 1;

        if ($last?->work_order_number) {
            $sequence =
                (int) substr(
                    $last->work_order_number,
                    -4
                );

            $next =
                $sequence + 1;
        }

        return $prefix
            .str_pad(
                (string) $next,
                4,
                '0',
                STR_PAD_LEFT
            );
    }
}
