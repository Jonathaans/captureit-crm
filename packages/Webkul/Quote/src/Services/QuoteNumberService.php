<?php

namespace Webkul\Quote\Services;

use Webkul\Quote\Models\Quote;

class QuoteNumberService
{
    /**
     * Generate quotation number.
     *
     * Format:
     * QT YYMM-XXXX
     *
     * Example:
     * QT 2608-0001
     * QT 2608-0002
     */
    public function generate(): string
    {
        $yearMonth = now()->format('ym');

        $prefix = 'QT '.$yearMonth.'-';

        $lastQuote = Quote::query()
            ->whereNotNull('quote_number')
            ->where(
                'quote_number',
                'like',
                $prefix.'%'
            )
            ->orderByDesc('quote_number')
            ->first();

        $nextNumber = 1;

        if ($lastQuote?->quote_number) {
            $lastSequence = (int) substr(
                $lastQuote->quote_number,
                -4
            );

            $nextNumber = $lastSequence + 1;
        }

        return $prefix.str_pad(
            $nextNumber,
            4,
            '0',
            STR_PAD_LEFT
        );
    }
}