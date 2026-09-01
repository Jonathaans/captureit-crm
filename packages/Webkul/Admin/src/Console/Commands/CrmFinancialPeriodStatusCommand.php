<?php

namespace Webkul\Admin\Console\Commands;

use Illuminate\Console\Command;
use Webkul\Admin\Models\FinancialPeriodLock;

class CrmFinancialPeriodStatusCommand extends Command
{
    protected $signature =
        'crm:financial-periods';

    protected $description =
        'List currently closed financial periods.';

    public function handle(): int
    {
        $locks =
            FinancialPeriodLock::query()
                ->latest('starts_at')
                ->limit(24)
                ->get();

        if ($locks->isEmpty()) {
            $this->info(
                'No closed financial periods.'
            );

            return self::SUCCESS;
        }

        foreach ($locks as $lock) {
            $this->line(
                sprintf(
                    '%s CLOSED by %s at %s',
                    $lock->period,
                    $lock->locked_by_name
                        ?: 'Unknown',
                    $lock->locked_at?->format(
                        'Y-m-d H:i:s'
                    )
                        ?: '-'
                )
            );
        }

        return self::SUCCESS;
    }
}
