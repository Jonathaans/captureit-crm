<?php

namespace Webkul\Admin\Helpers\Reporting;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Webkul\Contact\Repositories\PersonRepository;

class Person extends AbstractReporting
{
    /**
     * Create a helper instance.
     *
     * @return void
     */
    public function __construct(protected PersonRepository $personRepository)
    {
        parent::__construct();
    }

    /**
     * Build a fresh person query scoped to the current user's visibility.
     */
    protected function getScopedPersonQuery(): Builder
    {
        $query = $this->personRepository
            ->resetModel()
            ->getModel()
            ->newQuery();

        if (auth()->guard('user')->check()) {
            $userIds = bouncer()->getAuthorizedUserIds();

            if ($userIds !== null) {
                $query->whereIn('persons.user_id', $userIds);
            }
        }

        return $query;
    }

    /**
     * Apply lead-owner visibility to a query that joins the leads table.
     */
    protected function applyLeadVisibility(Builder $query): Builder
    {
        if (auth()->guard('user')->check()) {
            $userIds = bouncer()->getAuthorizedUserIds();

            if ($userIds !== null) {
                $query->whereIn('leads.user_id', $userIds);
            }
        }

        return $query;
    }

    /**
     * Retrieves total persons and their progress.
     */
    public function getTotalPersonsProgress(): array
    {
        return [
            'previous' => $previous = $this->getTotalPersons($this->lastStartDate, $this->lastEndDate),
            'current' => $current = $this->getTotalPersons($this->startDate, $this->endDate),
            'progress' => $this->getPercentageChange($previous, $current),
        ];
    }

    /**
     * Retrieves total persons by date.
     *
     * @param  Carbon  $startDate
     * @param  Carbon  $endDate
     */
    public function getTotalPersons($startDate, $endDate): int
    {
        return $this->getScopedPersonQuery()
            ->whereBetween('persons.created_at', [$startDate, $endDate])
            ->count();
    }

    /**
     * Gets top customers by revenue.
     *
     * @param  int|null  $limit
     */
    public function getTopCustomersByRevenue($limit = null): Collection
    {
        $query = $this->personRepository
            ->resetModel()
            ->getModel()
            ->newQuery()
            ->leftJoin('leads', 'persons.id', '=', 'leads.person_id')
            ->select(
                'persons.id',
                'persons.name',
                'persons.emails',
                'persons.contact_numbers',
                DB::raw('SUM(leads.lead_value) as revenue')
            )
            ->whereBetween('leads.closed_at', [$this->startDate, $this->endDate])
            ->groupBy(
                'persons.id',
                'persons.name',
                'persons.emails',
                'persons.contact_numbers'
            )
            ->havingRaw('SUM(leads.lead_value) > 0')
            ->orderByDesc('revenue');

        $query = $this->applyLeadVisibility($query);

        if ($limit !== null) {
            $query->limit($limit);
        }

        return $query->get()->map(function ($item) {
            return [
                'id' => $item->id,
                'name' => $item->name,
                'emails' => $item->emails,
                'contact_numbers' => $item->contact_numbers,
                'revenue' => (float) $item->revenue,
                'formatted_revenue' => core()->formatBasePrice($item->revenue),
            ];
        });
    }
}
