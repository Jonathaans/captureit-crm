<?php

namespace Webkul\Admin\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Webkul\Admin\Services\WorkflowNotificationService;

class CrmInternalCommunicationCheckCommand extends Command
{
    protected $signature =
        'crm:internal-communication-check {--test-user=}';

    protected $description =
        'Check workflow notification recipients and optionally create a test popup notification.';

    public function handle(
        WorkflowNotificationService $notifications
    ): int {
        $this->line(
            'CRM INTERNAL COMMUNICATION CHECK'
        );

        $this->line(
            '================================'
        );

        foreach (
            [
                'crm_workflow_notifications',
                'internal_conversations',
                'internal_conversation_members',
                'internal_messages',
                'internal_message_attachments',
            ]
            as $table
        ) {
            $this->line(
                (
                    Schema::hasTable(
                        $table
                    )
                        ? '[PASS] '
                        : '[FAIL] '
                )
                .$table
            );
        }

        $salesAdminIds =
            $notifications
                ->usersByRoleNames([
                    'Sales Admin',
                ]);

        $warehouseIds =
            $notifications
                ->usersByRoleNames([
                    'Head Warehouse',
                    'Warehouse User',
                ]);

        $this->line(
            'Sales Admin recipients: '
            .$salesAdminIds->implode(', ')
        );

        $this->line(
            'Warehouse recipients: '
            .$warehouseIds->implode(', ')
        );

        $testUserId =
            (int) (
                $this->option(
                    'test-user'
                )
                ?: 0
            );

        if ($testUserId > 0) {
            if (
                ! DB::table('users')
                    ->where(
                        'id',
                        $testUserId
                    )
                    ->exists()
            ) {
                $this->error(
                    'Test user tidak ditemukan.'
                );

                return self::FAILURE;
            }

            $notification =
                $notifications->notifyUser(
                    $testUserId,
                    'system_test',
                    'Test Notification',
                    'Jika popup ini muncul, polling internal notification bekerja.',
                    url(
                        '/admin/internal-notifications'
                    ),
                    'manual-test:'
                        .$testUserId
                        .':'
                        .now()->format(
                            'YmdHis'
                        ),
                    'system',
                    null
                );

            $this->info(
                'Test notification created ID '
                .$notification->id
            );
        }

        return self::SUCCESS;
    }
}
