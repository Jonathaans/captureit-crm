<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
|
| This file is where you may define all of your Closure based console
| commands. Each Closure is bound to a command instance allowing a
| simple approach to interacting with each command's IO methods.
|
*/

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('inbound-emails:process')->everyFiveMinutes()->withoutOverlapping(10); // MY EMAIL AUTO SYNC 5 MINUTES V1 // MY EMAIL AUTO SYNC 5 MINUTES V1

// MY EMAIL PERSONAL AUTO SYNC V2 START
Artisan::command('my-email:sync', function () {
    $sync =
        app(
            \Webkul\Admin\Services\UserEmailSyncService::class
        );

    $accounts =
        \Webkul\Admin\Models\UserEmailAccount::query()
            ->where(
                'sync_enabled',
                true
            )
            ->orderBy('id')
            ->get();

    if ($accounts->isEmpty()) {
        $this->warn(
            'Tidak ada My Email account dengan sync_enabled=1.'
        );

        return 0;
    }

    $totalNew =
        0;

    $failed =
        0;

    foreach ($accounts as $account) {
        try {
            $count =
                $sync->sync(
                    $account,
                    100
                );

            $totalNew +=
                $count;

            $this->info(
                sprintf(
                    '[OK] %s | new=%d | last_synced_at=%s',
                    $account->email_address,
                    $count,
                    (string) (
                        $account
                            ->fresh()
                            ?->last_synced_at
                        ?? '-'
                    )
                )
            );
        } catch (\Throwable $exception) {
            $failed++;

            $this->error(
                sprintf(
                    '[FAIL] %s | %s',
                    $account->email_address,
                    $exception->getMessage()
                )
            );
        }
    }

    $this->line(
        sprintf(
            'My Email sync complete: accounts=%d new=%d failed=%d',
            $accounts->count(),
            $totalNew,
            $failed
        )
    );

    return $failed > 0
        ? 1
        : 0;
})
    ->purpose(
        'Sync Personal My Email accounts using the same service as Sync Now'
    );

Schedule::command('my-email:sync')
    ->everyFiveMinutes()
    ->withoutOverlapping(10);
// MY EMAIL PERSONAL AUTO SYNC V2 END
