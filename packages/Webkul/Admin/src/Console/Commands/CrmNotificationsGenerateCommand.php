<?php

namespace Webkul\Admin\Console\Commands;

use Illuminate\Console\Command;
use Webkul\Admin\Services\CrmNotificationGeneratorService;

class CrmNotificationsGenerateCommand extends Command
{
    protected $signature =
        'crm:notifications-generate';

    protected $description =
        'Generate actionable CRM notifications.';

    public function handle(
        CrmNotificationGeneratorService $service
    ): int {
        $counts =
            $service->generate();

        $this->info('Notification generation PASS');

        foreach ($counts as $type => $count) {
            $this->line(
                $type
                .': '
                .$count
            );
        }

        return self::SUCCESS;
    }
}
