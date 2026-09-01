<?php

namespace Webkul\Admin\Console\Commands;

use Illuminate\Console\Command;
use Throwable;
use Webkul\Admin\Models\UserEmailAccount;
use Webkul\Admin\Services\UserEmailSyncService;

class CrmUserEmailSyncCommand extends Command
{
    protected $signature =
        'crm:user-email-sync {--user=} {--limit=50}';

    protected $description =
        'Sync enabled per-user IMAP mailboxes into CRM.';

    public function handle(
        UserEmailSyncService $sync
    ): int {
        $query =
            UserEmailAccount::query()
                ->where(
                    'sync_enabled',
                    true
                );

        if (
            $this->option(
                'user'
            )
        ) {
            $query->where(
                'user_id',
                (int) $this->option(
                    'user'
                )
            );
        }

        $limit =
            max(
                1,
                min(
                    (int) $this->option(
                        'limit'
                    ),
                    200
                )
            );

        $accounts =
            $query->get();

        if ($accounts->isEmpty()) {
            $this->info(
                'Tidak ada user email account yang aktif.'
            );

            return self::SUCCESS;
        }

        $failures = 0;

        foreach ($accounts as $account) {
            try {
                $count =
                    $sync->sync(
                        $account,
                        $limit
                    );

                $this->info(
                    sprintf(
                        'User #%d %s -> %d new email(s)',
                        $account->user_id,
                        $account->email_address,
                        $count
                    )
                );
            } catch (Throwable $exception) {
                $failures++;

                $this->error(
                    sprintf(
                        'User #%d %s -> FAIL: %s',
                        $account->user_id,
                        $account->email_address,
                        $exception->getMessage()
                    )
                );
            }
        }

        return $failures > 0
            ? self::FAILURE
            : self::SUCCESS;
    }
}
