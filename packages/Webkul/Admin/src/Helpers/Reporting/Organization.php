<?php

namespace Webkul\Admin\Helpers\Reporting;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Webkul\Contact\Repositories\OrganizationRepository;

class Organization extends AbstractReporting
{
    /**
     * Create a helper instance.
     *
     * @return void
     */
    public function __construct(protected OrganizationRepository $organizationRepository)
    {
        parent::__construct();
    }

    /**
     * Build a fresh organization query scoped to the current user's visibility.
     */
    protected function getScopedOrganizationQuery(): Builder
    {
        $query = $this->organizationRepository
            ->resetModel()
            ->getModel()
            ->newQuery();

        if (auth()->guard('user')->check()) {
            $userIds = bouncer()->getAuthorizedUserIds();

            if ($userIds !== null) {
                $query->whereIn('organizations.user_id', $userIds);
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
     * Retrieves total organizations and their progress.
     */
    public function getTotalOrganizationsProgress(): array
    {
        return [
            'previous' => $previous = $this->getTotalOrganizations($this->lastStartDate, $this->lastEndDate),
            'current' => $current = $this->getTotalOrganizations($this->startDate, $this->endDate),
            'progress' => $this->getPercentageChange($previous, $current),
        ];
    }

    /**
     * Retrieves total organizations by date.
     *
     * @param  Carbon  $startDate
     * @param  Carbon  $endDate
     */
    public function getTotalOrganizations($startDate, $endDate): int
    {
        return $this->getScopedOrganizationQuery()
            ->whereBetween('organizations.created_at', [$startDate, $endDate])
            ->count();
    }

    /**
     * Gets top organizations by revenue.
     *
     * @param  int|null  $limit
     */
    public function getTopOrganizationsByRevenue($limit = null): Collection
    {
        $query = $this->organizationRepository
            ->resetModel()
            ->getModel()
            ->newQuery()
            ->leftJoin('persons', 'organizations.id', '=', 'persons.organization_id')
            ->leftJoin('leads', 'persons.id', '=', 'leads.person_id')
            ->select(
                'organizations.id',
                'organizations.name',
                DB::raw('SUM(leads.lead_value) as revenue')
            )
            ->whereBetween('leads.closed_at', [$this->startDate, $this->endDate])
            ->groupBy('organizations.id', 'organizations.name')
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
                'revenue' => (float) $item->revenue,
                'formatted_revenue' => core()->formatBasePrice($item->revenue),
            ];
        });
    }
}
