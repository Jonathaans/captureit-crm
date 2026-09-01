<?php

namespace Webkul\Admin\Providers;

use Illuminate\Support\ServiceProvider;
use Webkul\Admin\Console\Commands\GoogleCalendarCheckCommand;
use Webkul\Admin\Console\Commands\GoogleCalendarSyncCommand;
use Webkul\Admin\Services\LeadWonCalendarService;
use Webkul\Admin\Services\SalesCalendarColorService;
use Webkul\Lead\Models\Lead;
use Webkul\User\Models\User;

class GoogleCalendarIntegrationServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        User::saved(
            function (User $user) {
                if (
                    ! app()->runningInConsole()
                    || app()->runningUnitTests()
                ) {
                    app(
                        SalesCalendarColorService::class
                    )->assignIfEligible(
                        (int) $user->id
                    );
                }
            }
        );

        Lead::updated(
            function (Lead $lead) {
                $service =
                    app(
                        LeadWonCalendarService::class
                    );

                if (
                    $service->isWon(
                        $lead
                    )
                ) {
                    $service->ensureDraft(
                        $lead
                    );
                }
            }
        );

        if ($this->app->runningInConsole()) {
            $this->commands([
                GoogleCalendarCheckCommand::class,
                GoogleCalendarSyncCommand::class,
            ]);
        }
    }
}
