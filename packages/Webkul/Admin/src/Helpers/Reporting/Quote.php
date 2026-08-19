<?php

namespace Webkul\Admin\Helpers\Reporting;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Webkul\Quote\Repositories\QuoteRepository;

class Quote extends AbstractReporting
{
    /**
     * Create a helper instance.
     *
     * @return void
     */
    public function __construct(protected QuoteRepository $quoteRepository)
    {
        parent::__construct();
    }

    /**
     * Build a fresh quotation query scoped to the current user's visibility.
     */
    protected function getScopedQuoteQuery(): Builder
    {
        $query = $this->quoteRepository
            ->resetModel()
            ->getModel()
            ->newQuery();

        if (auth()->guard('user')->check()) {
            $userIds = bouncer()->getAuthorizedUserIds();

            if ($userIds !== null) {
                $query->whereIn('quotes.user_id', $userIds);
            }
        }

        return $query;
    }

    /**
     * Retrieves total quotations and their progress.
     */
    public function getTotalQuotesProgress(): array
    {
        return [
            'previous' => $previous = $this->getTotalQuotes($this->lastStartDate, $this->lastEndDate),
            'current' => $current = $this->getTotalQuotes($this->startDate, $this->endDate),
            'progress' => $this->getPercentageChange($previous, $current),
        ];
    }

    /**
     * Retrieves total quotations by date.
     *
     * @param  Carbon  $startDate
     * @param  Carbon  $endDate
     */
    public function getTotalQuotes($startDate, $endDate): int
    {
        return $this->getScopedQuoteQuery()
            ->whereBetween('quotes.created_at', [$startDate, $endDate])
            ->count();
    }
}
