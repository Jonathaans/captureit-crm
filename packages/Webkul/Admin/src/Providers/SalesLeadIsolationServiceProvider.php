<?php

namespace Webkul\Admin\Providers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\ServiceProvider;
use Webkul\Admin\Services\SalesLeadAccessService;
use Webkul\Lead\Models\Lead;

class SalesLeadIsolationServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        /*
         * SALES USER LEAD ISOLATION V1
         *
         * Administrator / Sales Admin:
         *   no row restriction here.
         *
         * Sales User:
         *   Lead::query() always receives:
         *   leads.user_id = current logged-in user.
         *
         * This protects repository/Eloquent based:
         * - Lead list/search
         * - Lead detail
         * - Lead edit/update lookup
         * - Dashboard metrics using LeadRepository / Lead model
         */
        Lead::addGlobalScope(
            'sales_user_owner',
            function (Builder $builder) {
                app(
                    SalesLeadAccessService::class
                )->scopeEloquent(
                    $builder
                );
            }
        );

        /*
         * A Sales User creating or updating a Lead cannot assign the
         * Lead to another Sales Owner.
         */
        Lead::creating(
            function (Lead $lead) {
                app(
                    SalesLeadAccessService::class
                )->forceOwnerForSalesUser(
                    $lead
                );
            }
        );

        Lead::updating(
            function (Lead $lead) {
                app(
                    SalesLeadAccessService::class
                )->forceOwnerForSalesUser(
                    $lead
                );
            }
        );
    }
}
