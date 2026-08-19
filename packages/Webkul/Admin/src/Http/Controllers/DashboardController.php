<?php

namespace Webkul\Admin\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Webkul\Admin\Exports\Dashboard\DashboardExport;
use Webkul\Admin\Helpers\Dashboard;
use Webkul\Lead\Repositories\PipelineRepository;




class DashboardController extends Controller
{
    /**
     * Mapping tipe statistik dashboard.
     *
     * @var array<string, string>
     */
    protected array $typeFunctions = [
        'over-all' => 'getOverAllStats',
        'revenue-stats' => 'getRevenueStats',
        'total-leads' => 'getTotalLeadsStats',
        'revenue-by-sources' => 'getLeadsStatsBySources',
        'revenue-by-types' => 'getLeadsStatsByTypes',
        'top-selling-products' => 'getTopSellingProducts',
        'top-persons' => 'getTopPersons',
        'open-leads-by-states' => 'getOpenLeadsByStates',
    ];

    /**
     * Create a new controller instance.
     */
    public function __construct(
        protected Dashboard $dashboardHelper,
        protected PipelineRepository $pipelineRepository
    ) {}

    /**
     * Menampilkan halaman dashboard.
     */
    public function index(): View
    {
        return view('admin::dashboard.index')->with([
            'startDate' => $this->dashboardHelper->getStartDate(),
            'endDate' => $this->dashboardHelper->getEndDate(),
            'pipelines' => $this->pipelineRepository->all(),
            'defaultPipeline' => $this->pipelineRepository->getDefaultPipeline(),
        ]);
    }

    /**
     * Mengambil statistik dashboard.
     */
    public function stats(): JsonResponse
    {
        $type = request()->query('type');

        abort_unless(
            isset($this->typeFunctions[$type]),
            404,
            'Tipe statistik dashboard tidak ditemukan.'
        );

        $stats = $this->dashboardHelper->{$this->typeFunctions[$type]}();

        return response()->json([
            'statistics' => $stats,
            'date_range' => $this->dashboardHelper->getDateRange(),
        ]);
    }

    /**
     * Export dashboard berdasarkan range tanggal.
     *
     * Hanya role Administrator yang boleh melakukan export.
     */
    public function export(Request $request): BinaryFileResponse
    {
        $user = auth()->guard('user')->user();

        abort_unless(
            $user
            && strcasecmp(
                (string) $user->role?->name,
                'Administrator'
            ) === 0,
            403,
            'Anda tidak memiliki izin untuk mengekspor dashboard.'
        );

        $validated = $request->validate([
            'start' => [
                'required',
                'date_format:Y-m-d',
            ],

            'end' => [
                'required',
                'date_format:Y-m-d',
                'after_or_equal:start',
                'before_or_equal:today',
            ],

            'pipeline_id' => [
                'nullable',
                'integer',
            ],
        ]);

        $pipeline = ! empty($validated['pipeline_id'])
            ? $this->pipelineRepository->find($validated['pipeline_id'])
            : $this->pipelineRepository->getDefaultPipeline();

        abort_unless(
            $pipeline,
            404,
            'Pipeline tidak ditemukan.'
        );

        /*
         * DashboardHelper membaca parameter start, end, dan pipeline_id
         * langsung dari request yang sama.
         */
        $startDate = $this->dashboardHelper
            ->getStartDate()
            ->format('Y-m-d');

        $endDate = $this->dashboardHelper
            ->getEndDate()
            ->format('Y-m-d');

        $fileName = sprintf(
            'dashboard-report-%s-to-%s.xlsx',
            $startDate,
            $endDate
        );

        return Excel::download(
            new DashboardExport(
                dashboard: $this->dashboardHelper,
                pipelineName: $pipeline->name,
                startDate: $startDate,
                endDate: $endDate,
                generatedBy: $user->name
            ),
            $fileName
        );
    }
}