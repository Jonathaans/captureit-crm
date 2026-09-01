<?php

namespace Webkul\Admin\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

class LeadCommercialWorkflowService
{
    public function linkedQuoteId(
        int $leadId
    ): ?int {
        if (
            $leadId < 1
            || ! Schema::hasTable(
                'lead_quotes'
            )
        ) {
            return null;
        }

        $quoteId =
            DB::table(
                'lead_quotes'
            )
                ->where(
                    'lead_id',
                    $leadId
                )
                ->orderByDesc(
                    'quote_id'
                )
                ->value(
                    'quote_id'
                );

        return $quoteId
            ? (int) $quoteId
            : null;
    }

    public function linkedQuoteNumber(
        int $leadId
    ): ?string {
        $quoteId =
            $this->linkedQuoteId(
                $leadId
            );

        if (
            ! $quoteId
            || ! Schema::hasTable(
                'quotes'
            )
        ) {
            return null;
        }

        $number =
            DB::table(
                'quotes'
            )
                ->where(
                    'id',
                    $quoteId
                )
                ->value(
                    'quote_number'
                );

        return $number
            ? (string) $number
            : null;
    }

    public function quotationActionUrl(
        int $leadId
    ): string {
        $quoteId =
            $this->linkedQuoteId(
                $leadId
            );

        if (
            $quoteId
            && Route::has(
                'admin.quotes.edit'
            )
        ) {
            return route(
                'admin.quotes.edit',
                $quoteId
            );
        }

        return route(
            'admin.leads.generate-quotation',
            [
                'leadId' =>
                    $leadId,
            ]
        );
    }

    public function invoiceActionUrl(
        int $leadId
    ): string {
        $quoteId =
            $this->linkedQuoteId(
                $leadId
            );

        /*
         * Invoice should be generated FROM the approved/linked Quotation,
         * never directly from Lead.
         */
        if (
            $quoteId
            && Route::has(
                'admin.quotes.edit'
            )
        ) {
            return route(
                'admin.quotes.edit',
                $quoteId
            );
        }

        /*
         * If WON is reached before somebody created the Quotation,
         * send Admin Sales back to the Lead so the missing commercial
         * document is visible instead of manufacturing an Invoice directly.
         */
        return route(
            'admin.leads.view',
            $leadId
        );
    }

    public function isCommercialAdmin(
        $user
    ): bool {
        if (! $user) {
            return false;
        }

        try {
            $user->loadMissing(
                'role'
            );
        } catch (\Throwable) {
            // Fall back to DB below.
        }

        $roleName =
            strtolower(
                trim(
                    (string) (
                        $user->role?->name
                        ?? ''
                    )
                )
            );

        if ($roleName === '') {
            $roleName =
                strtolower(
                    trim(
                        (string) (
                            DB::table('roles')
                                ->where(
                                    'id',
                                    $user->role_id
                                    ?? 0
                                )
                                ->value(
                                    'name'
                                )
                            ?? ''
                        )
                    )
                );
        }

        return in_array(
            $roleName,
            [
                'administrator',
                'sales admin',
            ],
            true
        );
    }
}
