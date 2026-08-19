<?php

namespace Webkul\Admin\Helpers\Reporting;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Webkul\Lead\Repositories\LeadRepository;
use Webkul\Lead\Repositories\PipelineRepository;
use Webkul\Lead\Repositories\StageRepository;

class Lead extends AbstractReporting
{
    /**
     * The stage ids currently used by the over-time query.
     */
    protected array $stageIds;

    /**
     * All stage ids in the selected pipeline.
     */
    protected array $allStageIds;

    /**
     * Won stage ids in the selected pipeline.
     */
    protected array $wonStageIds;

    /**
     * Lost stage ids in the selected pipeline.
     */
    protected array $lostStageIds;

    /**
     * Pipeline used by the dashboard.
     */
    protected $pipeline;

    /**
     * Create a helper instance.
     *
     * @return void
     */
    public function __construct(
        protected LeadRepository $leadRepository,
        protected StageRepository $stageRepository,
        protected PipelineRepository $pipelineRepository
    ) {
        $this->pipeline = request('pipeline_id')
            ? $this->pipelineRepository->find(request('pipeline_id'))
            : null;

        if (! $this->pipeline) {
            $this->pipeline = $this->pipelineRepository->getDefaultPipeline();
        }

        $stages = $this->pipeline->stages;

        $this->allStageIds = $stages->pluck('id')->toArray();
        $this->wonStageIds = $stages->where('code', 'won')->pluck('id')->toArray();
        $this->lostStageIds = $stages->where('code', 'lost')->pluck('id')->toArray();

        parent::__construct();
    }

    /**
     * Build a fresh lead query scoped to the authenticated user's visibility.
     *
     * Global users receive every lead. Group users receive their authorized
     * group members' leads. Individual users receive only their own leads.
     */
    protected function getScopedLeadQuery(): Builder
    {
        $query = $this->leadRepository
            ->resetModel()
            ->getModel()
            ->newQuery();

        if (auth()->guard('user')->check()) {
            $userIds = bouncer()->getAuthorizedUserIds();

            /*
             * Null means global access. An empty array must remain restrictive
             * instead of accidentally falling back to global access.
             */
            if ($userIds !== null) {
                $query->whereIn('leads.user_id', $userIds);
            }
        }

        return $query;
    }

    /**
     * Returns all leads over time.
     *
     * @param  string  $period
     */
    public function getTotalLeadsOverTime($period = 'auto'): array
    {
        $this->stageIds = $this->allStageIds;

        return $this->getOverTimeStats(
            $this->startDate,
            $this->endDate,
            'leads.lead_value',
            'leads.created_at',
            $this->determinePeriod($period)
        );
    }

    /**
     * Returns won leads over time.
     *
     * @param  string  $period
     */
    public function getTotalWonLeadsOverTime($period = 'auto'): array
    {
        $this->stageIds = $this->wonStageIds;

        return $this->getOverTimeStats(
            $this->startDate,
            $this->endDate,
            'leads.lead_value',
            'leads.closed_at',
            $this->determinePeriod($period)
        );
    }

    /**
     * Returns lost leads over time.
     *
     * @param  string  $period
     */
    public function getTotalLostLeadsOverTime($period = 'auto'): array
    {
        $this->stageIds = $this->lostStageIds;

        return $this->getOverTimeStats(
            $this->startDate,
            $this->endDate,
            'leads.lead_value',
            'leads.closed_at',
            $this->determinePeriod($period)
        );
    }

    /**
     * Determine the appropriate period based on date range.
     *
     * @param  string  $period
     */
    protected function determinePeriod($period = 'auto'): string
    {
        if ($period !== 'auto') {
            return $period;
        }

        $diffInDays = $this->startDate->diffInDays($this->endDate);
        $diffInMonths = $this->startDate->diffInMonths($this->endDate);
        $diffInYears = $this->startDate->diffInYears($this->endDate);

        if ($diffInYears > 3) {
            return 'year';
        }

        if ($diffInMonths > 6) {
            return 'month';
        }

        if ($diffInDays > 60) {
            return 'week';
        }

        return 'day';
    }

    /**
     * Retrieves total leads and their progress.
     */
    public function getTotalLeadsProgress(): array
    {
        return [
            'previous' => $previous = $this->getTotalLeads($this->lastStartDate, $this->lastEndDate),
            'current' => $current = $this->getTotalLeads($this->startDate, $this->endDate),
            'progress' => $this->getPercentageChange($previous, $current),
        ];
    }

    /**
     * Retrieves total leads by date.
     *
     * @param  Carbon  $startDate
     * @param  Carbon  $endDate
     */
    public function getTotalLeads($startDate, $endDate): int
    {
        return $this->getScopedLeadQuery()
            ->where('leads.lead_pipeline_id', $this->pipeline->id)
            ->whereBetween('leads.created_at', [$startDate, $endDate])
            ->count();
    }

    /**
     * Retrieves average leads per day and their progress.
     */
    public function getAverageLeadsPerDayProgress(): array
    {
        return [
            'previous' => $previous = $this->getAverageLeadsPerDay($this->lastStartDate, $this->lastEndDate),
            'current' => $current = $this->getAverageLeadsPerDay($this->startDate, $this->endDate),
            'progress' => $this->getPercentageChange($previous, $current),
        ];
    }

    /**
     * Retrieves average leads per day.
     *
     * @param  Carbon  $startDate
     * @param  Carbon  $endDate
     */
    public function getAverageLeadsPerDay($startDate, $endDate): float
    {
        $days = $startDate->diffInDays($endDate);

        if ($days == 0) {
            return 0;
        }

        return $this->getTotalLeads($startDate, $endDate) / $days;
    }

    /**
     * Retrieves total lead value and their progress.
     */
    public function getTotalLeadValueProgress(): array
    {
        return [
            'previous' => $previous = $this->getTotalLeadValue($this->lastStartDate, $this->lastEndDate),
            'current' => $current = $this->getTotalLeadValue($this->startDate, $this->endDate),
            'formatted_total' => core()->formatBasePrice($current),
            'progress' => $this->getPercentageChange($previous, $current),
        ];
    }

    /**
     * Retrieves total lead value.
     *
     * @param  Carbon  $startDate
     * @param  Carbon  $endDate
     */
    public function getTotalLeadValue($startDate, $endDate): float
    {
        return (float) $this->getScopedLeadQuery()
            ->where('leads.lead_pipeline_id', $this->pipeline->id)
            ->whereBetween('leads.created_at', [$startDate, $endDate])
            ->sum('leads.lead_value');
    }

    /**
     * Retrieves average lead value and their progress.
     */
    public function getAverageLeadValueProgress(): array
    {
        return [
            'previous' => $previous = $this->getAverageLeadValue($this->lastStartDate, $this->lastEndDate),
            'current' => $current = $this->getAverageLeadValue($this->startDate, $this->endDate),
            'formatted_total' => core()->formatBasePrice($current),
            'progress' => $this->getPercentageChange($previous, $current),
        ];
    }

    /**
     * Retrieves average lead value.
     *
     * @param  Carbon  $startDate
     * @param  Carbon  $endDate
     */
    public function getAverageLeadValue($startDate, $endDate): float
    {
        return (float) ($this->getScopedLeadQuery()
            ->where('leads.lead_pipeline_id', $this->pipeline->id)
            ->whereBetween('leads.created_at', [$startDate, $endDate])
            ->avg('leads.lead_value') ?? 0);
    }

    /**
     * Retrieves total won lead value and its progress.
     */
    public function getTotalWonLeadValueProgress(): array
    {
        return [
            'previous' => $previous = $this->getTotalWonLeadValue($this->lastStartDate, $this->lastEndDate),
            'current' => $current = $this->getTotalWonLeadValue($this->startDate, $this->endDate),
            'formatted_total' => core()->formatBasePrice($current),
            'progress' => $this->getPercentageChange($previous, $current),
        ];
    }

    /**
     * Retrieves won lead value by closing date.
     *
     * @param  Carbon  $startDate
     * @param  Carbon  $endDate
     */
    public function getTotalWonLeadValue($startDate, $endDate): ?float
    {
        return (float) $this->getScopedLeadQuery()
            ->where('leads.lead_pipeline_id', $this->pipeline->id)
            ->whereIn('leads.lead_pipeline_stage_id', $this->wonStageIds)
            ->whereBetween('leads.closed_at', [$startDate, $endDate])
            ->sum('leads.lead_value');
    }

    /**
     * Retrieves total lost lead value and its progress.
     */
    public function getTotalLostLeadValueProgress(): array
    {
        return [
            'previous' => $previous = $this->getTotalLostLeadValue($this->lastStartDate, $this->lastEndDate),
            'current' => $current = $this->getTotalLostLeadValue($this->startDate, $this->endDate),
            'formatted_total' => core()->formatBasePrice($current),
            'progress' => $this->getPercentageChange($previous, $current),
        ];
    }

    /**
     * Retrieves lost lead value by closing date.
     *
     * @param  Carbon  $startDate
     * @param  Carbon  $endDate
     */
    public function getTotalLostLeadValue($startDate, $endDate): ?float
    {
        return (float) $this->getScopedLeadQuery()
            ->where('leads.lead_pipeline_id', $this->pipeline->id)
            ->whereIn('leads.lead_pipeline_stage_id', $this->lostStageIds)
            ->whereBetween('leads.closed_at', [$startDate, $endDate])
            ->sum('leads.lead_value');
    }

    /**
     * Retrieves won revenue grouped by lead source.
     */
    public function getTotalWonLeadValueBySources()
    {
        return $this->getScopedLeadQuery()
            ->leftJoin('lead_sources', 'leads.lead_source_id', '=', 'lead_sources.id')
            ->select(
                'lead_sources.name',
                DB::raw('SUM(leads.lead_value) as total')
            )
            ->where('leads.lead_pipeline_id', $this->pipeline->id)
            ->whereIn('leads.lead_pipeline_stage_id', $this->wonStageIds)
            ->whereBetween('leads.closed_at', [$this->startDate, $this->endDate])
            ->groupBy('lead_sources.id', 'lead_sources.name')
            ->get();
    }

    /**
     * Retrieves won revenue grouped by lead type.
     */
    public function getTotalWonLeadValueByTypes()
    {
        return $this->getScopedLeadQuery()
            ->leftJoin('lead_types', 'leads.lead_type_id', '=', 'lead_types.id')
            ->select(
                'lead_types.name',
                DB::raw('SUM(leads.lead_value) as total')
            )
            ->where('leads.lead_pipeline_id', $this->pipeline->id)
            ->whereIn('leads.lead_pipeline_stage_id', $this->wonStageIds)
            ->whereBetween('leads.closed_at', [$this->startDate, $this->endDate])
            ->groupBy('lead_types.id', 'lead_types.name')
            ->get();
    }

    /**
     * Retrieves open leads grouped by stage.
     */
    public function getOpenLeadsByStates()
    {
        return $this->getScopedLeadQuery()
            ->leftJoin(
                'lead_pipeline_stages',
                'leads.lead_pipeline_stage_id',
                '=',
                'lead_pipeline_stages.id'
            )
            ->select(
                'lead_pipeline_stages.name',
                DB::raw('COUNT(DISTINCT leads.id) as total')
            )
            ->where('leads.lead_pipeline_id', $this->pipeline->id)
            ->whereNotIn('leads.lead_pipeline_stage_id', $this->wonStageIds)
            ->whereNotIn('leads.lead_pipeline_stage_id', $this->lostStageIds)
            ->whereBetween('leads.created_at', [$this->startDate, $this->endDate])
            ->groupBy('lead_pipeline_stages.id', 'lead_pipeline_stages.name')
            ->orderByDesc('total')
            ->get();
    }

    /**
     * Returns over-time lead statistics.
     *
     * @param  Carbon  $startDate
     * @param  Carbon  $endDate
     * @param  string  $valueColumn
     * @param  string  $dateColumn
     * @param  string  $period
     */
    public function getOverTimeStats(
        $startDate,
        $endDate,
        $valueColumn,
        $dateColumn = 'leads.created_at',
        $period = 'auto'
    ): array {
        $period = $this->determinePeriod($period);
        $intervals = $this->generateTimeIntervals($startDate, $endDate, $period);
        $groupColumn = $this->getGroupColumn($dateColumn, $period);

        $query = $this->getScopedLeadQuery()
            ->select(
                DB::raw("$groupColumn AS date"),
                DB::raw('COUNT(DISTINCT leads.id) AS count'),
                DB::raw("SUM($valueColumn) AS total")
            )
            ->where('leads.lead_pipeline_id', $this->pipeline->id)
            ->whereIn('leads.lead_pipeline_stage_id', $this->stageIds)
            ->whereBetween($dateColumn, [$startDate, $endDate])
            ->groupBy(DB::raw($groupColumn))
            ->orderBy(DB::raw($groupColumn));

        $resultLookup = $query->get()->keyBy('date');
        $stats = [];

        foreach ($intervals as $interval) {
            $result = $resultLookup->get($interval['key']);

            $stats[] = [
                'label' => $interval['label'],
                'count' => $result ? (int) $result->count : 0,
                'total' => $result ? (float) $result->total : 0,
            ];
        }

        return $stats;
    }

    /**
     * Generate time intervals based on period.
     */
    protected function generateTimeIntervals(Carbon $startDate, Carbon $endDate, string $period): array
    {
        $intervals = [];
        $current = $startDate->copy();

        while ($current <= $endDate) {
            $intervals[] = [
                'key' => $this->formatDateForGrouping($current, $period),
                'label' => $this->formatDateForLabel($current, $period),
            ];

            match ($period) {
                'week' => $current->addWeek(),
                'month' => $current->addMonth(),
                'year' => $current->addYear(),
                default => $current->addDay(),
            };
        }

        return $intervals;
    }

    /**
     * Get the SQL group expression for a period.
     */
    protected function getGroupColumn(string $dateColumn, string $period): string
    {
        return match ($period) {
            'week' => "DATE_FORMAT($dateColumn, '%Y-%u')",
            'month' => "DATE_FORMAT($dateColumn, '%Y-%m')",
            'year' => "YEAR($dateColumn)",
            default => "DATE($dateColumn)",
        };
    }

    /**
     * Format date for grouping key.
     */
    protected function formatDateForGrouping(Carbon $date, string $period): string
    {
        return match ($period) {
            'week' => $date->format('Y-W'),
            'month' => $date->format('Y-m'),
            'year' => $date->format('Y'),
            default => $date->format('Y-m-d'),
        };
    }

    /**
     * Format date for display label.
     */
    protected function formatDateForLabel(Carbon $date, string $period): string
    {
        return match ($period) {
            'week' => 'Week '.$date->format('W, Y'),
            'month' => $date->format('M Y'),
            'year' => $date->format('Y'),
            default => $date->format('M d'),
        };
    }
}
