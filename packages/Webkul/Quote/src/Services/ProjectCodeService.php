<?php

namespace Webkul\Quote\Services;

use Webkul\Quote\Models\Quote;

class ProjectCodeService
{
    /**
     * Generate project code.
     *
     * Example:
     * PRJ-2026-00001
     * PRJ-2026-00002
     */
    public function generate(): string
    {
        $year = now()->format('Y');

        $prefix = 'PRJ-'.$year.'-';

        $lastQuote = Quote::query()
            ->whereNotNull('project_code')
            ->where(
                'project_code',
                'like',
                $prefix.'%'
            )
            ->orderByDesc('project_code')
            ->first();

        $nextNumber = 1;

        if ($lastQuote?->project_code) {
            $lastNumber = (int) substr(
                $lastQuote->project_code,
                -5
            );

            $nextNumber = $lastNumber + 1;
        }

        return $prefix.str_pad(
            $nextNumber,
            5,
            '0',
            STR_PAD_LEFT
        );
    }
}