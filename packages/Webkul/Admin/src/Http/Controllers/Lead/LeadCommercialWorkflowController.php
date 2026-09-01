<?php

namespace Webkul\Admin\Http\Controllers\Lead;

use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Admin\Http\Controllers\Quote\QuoteController;
use Webkul\Admin\Services\LeadCommercialWorkflowService;
use Webkul\Lead\Models\Lead;

class LeadCommercialWorkflowController extends Controller
{
    public function quotationForm(
        int $leadId,
        LeadCommercialWorkflowService $workflow
    ): View|RedirectResponse {
        $user =
            auth()
                ->guard('user')
                ->user();

        abort_unless(
            $workflow->isCommercialAdmin(
                $user
            ),
            403
        );

        $lead =
            Lead::query()
                ->with([
                    'person',
                    'products',
                ])
                ->findOrFail(
                    $leadId
                );

        /*
         * Prevent accidental duplicate commercial documents.
         * If this Lead already has a Quotation, open the existing one.
         */
        $existingQuoteId =
            $workflow->linkedQuoteId(
                $leadId
            );

        if ($existingQuoteId) {
            return redirect()->route(
                'admin.quotes.edit',
                $existingQuoteId
            );
        }

        /*
         * The customized QuoteController already supports lead_id and handles:
         * - Person / Bill To
         * - Sales Owner
         * - Lead lookup
         * - Lead products
         * - billing address
         *
         * We reuse it rather than duplicating the Quote creation workflow.
         */
        request()->merge([
            'lead_id' =>
                $leadId,

            'from' =>
                'lead',
        ]);

        /** @var \Illuminate\View\View $view */
        $view =
            app(
                QuoteController::class
            )->create();

        $data =
            $view->getData();

        $quote =
            $data['quote']
            ?? null;

        if ($quote) {
            $subject =
                $this->firstValue(
                    $lead,
                    [
                        'title',
                        'subject',
                        'name',
                    ]
                );

            $eventDate =
                $this->firstValue(
                    $lead,
                    [
                        'event_date',
                    ]
                );

            $location =
                $this->firstValue(
                    $lead,
                    [
                        'location',
                        'venue',
                    ]
                );

            $businessUnit =
                $this->firstValue(
                    $lead,
                    [
                        'business_unit',
                    ]
                );

            $description =
                $this->firstValue(
                    $lead,
                    [
                        'description',
                    ]
                );

            $prefill = [];

            if ($subject !== null) {
                $prefill['subject'] =
                    $subject;
            }

            if ($eventDate !== null) {
                $prefill['event_date'] =
                    $eventDate;
            }

            if ($location !== null) {
                $prefill['location'] =
                    $location;
            }

            if ($businessUnit !== null) {
                $prefill['business_unit'] =
                    $businessUnit;
            }

            if ($description !== null) {
                $prefill['description'] =
                    $description;
            }

            if ($prefill) {
                $quote->fill(
                    $prefill
                );
            }
        }

        return $view;
    }

    private function firstValue(
        $model,
        array $keys
    ): mixed {
        foreach ($keys as $key) {
            try {
                $value =
                    $model->{$key}
                    ?? null;
            } catch (\Throwable) {
                $value =
                    null;
            }

            if (
                $value !== null
                && trim(
                    (string) $value
                ) !== ''
            ) {
                return $value;
            }
        }

        return null;
    }
}
