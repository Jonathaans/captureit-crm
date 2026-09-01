<?php

namespace Webkul\Admin\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LeadWonNotificationDetector
{
    public function isWon(
        $lead
    ): bool {
        $status =
            strtolower(
                trim(
                    (string) (
                        $lead->status
                        ?? ''
                    )
                )
            );

        if (
            in_array(
                $status,
                [
                    'won',
                    '1',
                ],
                true
            )
        ) {
            return true;
        }

        $stageId =
            (int) (
                $lead->lead_pipeline_stage_id
                ?? 0
            );

        if (
            $stageId < 1
            || ! Schema::hasTable(
                'lead_pipeline_stages'
            )
        ) {
            return false;
        }

        $stage =
            DB::table(
                'lead_pipeline_stages'
            )
                ->where(
                    'id',
                    $stageId
                )
                ->first();

        if (! $stage) {
            return false;
        }

        foreach (
            [
                'code',
                'name',
            ]
            as $column
        ) {
            $value =
                strtolower(
                    trim(
                        (string) (
                            $stage->{$column}
                            ?? ''
                        )
                    )
                );

            if ($value === 'won') {
                return true;
            }
        }

        return false;
    }

    public function becameWon(
        $lead
    ): bool {
        /*
         * Avoid duplicate notifications when unrelated fields are saved on an
         * already-WON Lead.
         */
        if (
            method_exists(
                $lead,
                'wasChanged'
            )
            && ! $lead->wasChanged(
                [
                    'status',
                    'lead_pipeline_stage_id',
                ]
            )
        ) {
            return false;
        }

        return $this->isWon(
            $lead
        );
    }
}
