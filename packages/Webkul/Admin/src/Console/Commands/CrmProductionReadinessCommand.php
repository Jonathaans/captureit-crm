<?php

namespace Webkul\Admin\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

class CrmProductionReadinessCommand extends Command
{
    protected $signature =
        'crm:production-readiness';

    protected $description =
        'Non-destructive production readiness audit for the customized CRM.';

    public function handle(): int
    {
        $checks = [];

        $checks[] = [
            'APP_ENV production',
            config('app.env') === 'production',
            (string) config('app.env'),
        ];

        $checks[] = [
            'APP_DEBUG false',
            config('app.debug') === false,
            config('app.debug') ? 'true' : 'false',
        ];

        $checks[] = [
            'APP_URL HTTPS',
            str_starts_with(
                (string) config('app.url'),
                'https://'
            ),
            (string) config('app.url'),
        ];

        try {
            DB::select('SELECT 1');
            $dbOk = true;
            $dbDetail = DB::getDatabaseName();
        } catch (\Throwable $exception) {
            $dbOk = false;
            $dbDetail = $exception->getMessage();
        }

        $checks[] = [
            'Database',
            $dbOk,
            $dbDetail,
        ];

        foreach (
            [
                'crm_audit_logs',
                'crm_system_incidents',
                'purchase_orders',
                'invoices',
                'users',
            ]
            as $table
        ) {
            $checks[] = [
                'Table '.$table,
                Schema::hasTable($table),
                Schema::hasTable($table)
                    ? 'ready'
                    : 'missing',
            ];
        }

        $checks[] = [
            'Storage writable',
            is_writable(storage_path()),
            storage_path(),
        ];

        $checks[] = [
            'bootstrap/cache writable',
            is_writable(
                base_path(
                    'bootstrap/cache'
                )
            ),
            base_path('bootstrap/cache'),
        ];

        if (
            config('google-calendar.enabled')
        ) {
            $checks[] = [
                'Google Calendar ID',
                trim(
                    (string) config(
                        'google-calendar.calendar_id'
                    )
                ) !== '',
                (string) config(
                    'google-calendar.calendar_id'
                ),
            ];

            $credentials =
                (string) config(
                    'google-calendar.credentials_path'
                );

            $checks[] = [
                'Google credentials',
                $credentials !== ''
                    && is_file($credentials),
                $credentials,
            ];
        }

        $checks[] = [
            'System Control route',
            Route::has(
                'admin.system-control.index'
            ),
            Route::has(
                'admin.system-control.index'
            )
                ? 'ready'
                : 'missing',
        ];

        $failures = 0;

        $this->info('CRM PRODUCTION READINESS');
        $this->line(str_repeat('=', 24));

        foreach ($checks as [$name, $ok, $detail]) {
            $status =
                $ok
                    ? 'PASS'
                    : 'FAIL';

            if (! $ok) {
                $failures++;
            }

            $this->line(
                sprintf(
                    '[%s] %-28s %s',
                    $status,
                    $name,
                    $detail
                )
            );
        }

        $this->newLine();

        if ($failures > 0) {
            $this->warn(
                $failures
                .' readiness check(s) perlu diperbaiki.'
            );

            return self::FAILURE;
        }

        $this->info('PRODUCTION READINESS: PASS');

        return self::SUCCESS;
    }
}
