<?php

namespace Webkul\Admin\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Webkul\Admin\Services\LeadCommercialStageService;
use Webkul\Admin\Services\LeadCommercialWorkflowService;
use Webkul\Lead\Models\Lead;

class InjectLeadCommercialActionUi
{
    public function handle(
        Request $request,
        Closure $next
    ): Response {
        $response =
            $next(
                $request
            );

        if (
            $request->route()?->getName()
                !== 'admin.leads.view'
            || $response->getStatusCode()
                >= 300
            || ! auth()
                ->guard('user')
                ->check()
        ) {
            return $response;
        }

        $contentType =
            strtolower(
                (string) $response
                    ->headers
                    ->get(
                        'Content-Type',
                        ''
                    )
            );

        if (
            ! str_contains(
                $contentType,
                'text/html'
            )
        ) {
            return $response;
        }

        $content =
            $response->getContent();

        if (
            ! is_string(
                $content
            )
            || ! str_contains(
                strtolower(
                    $content
                ),
                '</body>'
            )
            || str_contains(
                $content,
                'CRM_LEAD_COMMERCIAL_ACTION_WIDGET'
            )
        ) {
            return $response;
        }

        $workflow =
            app(
                LeadCommercialWorkflowService::class
            );

        $user =
            auth()
                ->guard('user')
                ->user();

        if (
            ! $workflow
                ->isCommercialAdmin(
                    $user
                )
        ) {
            return $response;
        }

        $routeParameters =
            $request
                ->route()
                ?->parameters()
            ?? [];

        $leadId =
            (int) (
                $routeParameters['id']
                ?? $routeParameters['leadId']
                ?? $routeParameters['lead_id']
                ?? array_values(
                    $routeParameters
                )[0]
                ?? 0
            );

        if ($leadId < 1) {
            return $response;
        }

        $lead =
            Lead::query()
                ->find(
                    $leadId
                );

        if (! $lead) {
            return $response;
        }

        $stage =
            app(
                LeadCommercialStageService::class
            )->currentStageKey(
                $lead
            );

        if ($stage !== 'quotation') {
            return $response;
        }

        $quoteId =
            $workflow->linkedQuoteId(
                $leadId
            );

        $widget =
            view(
                'admin::lead-commercial-workflow.action-widget',
                [
                    'lead' =>
                        $lead,

                    'quoteId' =>
                        $quoteId,

                    'actionUrl' =>
                        $workflow
                            ->quotationActionUrl(
                                $leadId
                            ),
                ]
            )->render();

        $position =
            strripos(
                $content,
                '</body>'
            );

        if ($position === false) {
            return $response;
        }

        $content =
            substr_replace(
                $content,
                $widget,
                $position,
                0
            );

        $response->setContent(
            $content
        );

        return $response;
    }
}
