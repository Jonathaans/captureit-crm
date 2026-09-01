<?php

namespace Webkul\Admin\Services;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Webkul\Admin\Models\FinancialPeriodLock;

class FinancialPeriodLockService
{
    public function findLock(
        CarbonInterface|string|null $date
    ): ?FinancialPeriodLock {
        $date =
            $date instanceof CarbonInterface
                ? $date->copy()
                : Carbon::parse(
                    $date ?: now()
                );

        return FinancialPeriodLock::query()
            ->whereDate(
                'starts_at',
                '<=',
                $date->toDateString()
            )
            ->whereDate(
                'ends_at',
                '>=',
                $date->toDateString()
            )
            ->first();
    }

    public function isLocked(
        CarbonInterface|string|null $date
    ): bool {
        return $this->findLock($date) !== null;
    }
}
