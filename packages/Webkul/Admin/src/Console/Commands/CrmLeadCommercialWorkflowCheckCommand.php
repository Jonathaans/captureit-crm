<?php

namespace Webkul\Admin\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Webkul\Admin\Services\LeadCommercialStageService;
use Webkul\Admin\Services\LeadCommercialWorkflowService;
use Webkul\Admin\Services\WorkflowNotificationService;
use Webkul\Lead\Models\Lead;

class CrmLeadCommercialWorkflowCheckCommand extends Command
{
    protected $signature =
        'crm:lead-commercial-workflow-check {--lead=}';

    protected $description =
        'Check Quotation-stage and WON commercial notification workflow.';

    public function handle(
        WorkflowNotificationService $notifications,
        LeadCommercialStageService $stages,
        LeadCommercialWorkflowService $workflow
    ): int {
        $this->line(
            'CRM LEAD COMMERCIAL WORKFLOW CHECK'
        );

        $this->line(
            '=================================='
        );

        $checks = [
            'Workflow notifications table' =>
                Schema::hasTable(
                    'crm_workflow_notifications'
                ),

            'Lead pipeline stages table' =>
                Schema::hasTable(
                    'lead_pipeline_stages'
                ),

            'Lead -> Generate Quotation route' =>
                Route::has(
                    'admin.leads.generate-quotation'
                ),

            'Quote create route' =>
                Route::has(
                    'admin.quotes.create'
                ),

            'Quote edit route' =>
                Route::has(
                    'admin.quotes.edit'
                ),

            'Lead view route' =>
                Route::has(
                    'admin.leads.view'
                ),
        ];

        foreach ($checks as $label => $ok) {
            $this->line(
                (
                    $ok
                        ? '[PASS] '
                        : '[FAIL] '
                )
                .$label
            );
        }

        $salesAdminIds =
            $notifications
                ->usersByRoleNames([
                    'Sales Admin',
                ]);

        $this->line(
            'Sales Admin recipients: '
            .(
                $salesAdminIds
                    ->isNotEmpty()
                    ? $salesAdminIds
                        ->implode(', ')
                    : '(none)'
            )
        );

        $leadId =
            (int) (
                $this->option(
                    'lead'
                )
                ?: 0
            );

        if ($leadId > 0) {
            $lead =
                Lead::query()
                    ->find(
                        $leadId
                    );

            if (! $lead) {
                $this->error(
                    'Lead tidak ditemukan: '
                    .$leadId
                );

                return self::FAILURE;
            }

            $this->line(
                'Lead #'
                .$lead->id
                .' current stage key: '
                .(
                    $stages
                        ->currentStageKey(
                            $lead
                        )
                    ?: '(unrecognized)'
                )
            );

            $quoteId =
                $workflow
                    ->linkedQuoteId(
                        $lead->id
                    );

            $this->line(
                'Linked Quote ID: '
                .(
                    $quoteId
                        ?: '(none)'
                )
            );

            $this->line(
                'Quotation action URL: '
                .$workflow
                    ->quotationActionUrl(
                        $lead->id
                    )
            );

            $this->line(
                'Invoice action URL: '
                .$workflow
                    ->invoiceActionUrl(
                        $lead->id
                    )
            );
        }

        $failed =
            collect(
                $checks
            )
                ->contains(
                    false
                );

        return $failed
            ? self::FAILURE
            : self::SUCCESS;
    }
}
