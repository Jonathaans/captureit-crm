<?php

namespace Webkul\Admin\Providers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Webkul\Admin\Console\Commands\CrmBackupCommand;
use Webkul\Admin\Console\Commands\CrmBackupVerifyCommand;
use Webkul\Admin\Console\Commands\CrmIncidentListCommand;
use Webkul\Admin\Console\Commands\CrmProductionReadinessCommand;
use Webkul\Admin\Console\Commands\CrmSecurityAuditCommand;
use Webkul\Admin\Http\Controllers\System\SystemControlController;
use Webkul\Admin\Services\CrmAuditService;
use Webkul\Admin\Services\CrmIncidentService;

class CrmHardeningCoreServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        foreach (
            [
                'created',
                'updated',
                'deleted',
                'restored',
            ]
            as $action
        ) {
            Event::listen(
                'eloquent.'.$action.': *',
                function (
                    string $eventName,
                    array $data
                ) use ($action) {
                    $model = $data[0] ?? null;

                    if ($model instanceof Model) {
                        app(CrmAuditService::class)
                            ->record(
                                $action,
                                $model
                            );
                    }
                }
            );
        }

        Log::listen(
            function (MessageLogged $event) {
                app(CrmIncidentService::class)
                    ->captureLog($event);
            }
        );

        Queue::failing(
            function (JobFailed $event) {
                app(CrmIncidentService::class)
                    ->captureException(
                        $event->exception,
                        'error',
                        [
                            'queue' => $event->job->getQueue(),
                            'connection' => $event->connectionName,
                            'job' => $event->job->resolveName(),
                        ]
                    );
            }
        );

        Route::middleware('web')
            ->prefix('admin')
            ->group(
                function () {
                    Route::get(
                        'system-control',
                        [
                            SystemControlController::class,
                            'index',
                        ]
                    )->name(
                        'admin.system-control.index'
                    );

                    Route::get(
                        'system-control/audit-logs',
                        [
                            SystemControlController::class,
                            'auditLogs',
                        ]
                    )->name(
                        'admin.system-control.audit-logs'
                    );

                    Route::get(
                        'system-control/incidents',
                        [
                            SystemControlController::class,
                            'incidents',
                        ]
                    )->name(
                        'admin.system-control.incidents'
                    );

                    Route::post(
                        'system-control/incidents/{id}/resolve',
                        [
                            SystemControlController::class,
                            'resolveIncident',
                        ]
                    )->name(
                        'admin.system-control.incidents.resolve'
                    );
                }
            );

        if ($this->app->runningInConsole()) {
            $this->commands([
                CrmSecurityAuditCommand::class,
                CrmBackupCommand::class,
                CrmBackupVerifyCommand::class,
                CrmProductionReadinessCommand::class,
                CrmIncidentListCommand::class,
            ]);
        }
    }
}
