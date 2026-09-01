<?php

namespace Webkul\Admin\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LeadCommercialStageService
{
    public function currentStageKey(
        $lead
    ): string {
        $status =
            $this->normalize(
                $lead->status
                ?? null
            );

        if (
            in_array(
                $status,
                [
                    'won',
                    'quotation',
                    'quote',
                ],
                true
            )
        ) {
            return $status === 'quote'
                ? 'quotation'
                : $status;
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
            return '';
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
            return '';
        }

        foreach (
            [
                'code',
                'name',
            ]
            as $column
        ) {
            $value =
                $this->normalize(
                    $stage->{$column}
                    ?? null
                );

            if (
                in_array(
                    $value,
                    [
                        'quotation',
                        'quote',
                        'quotation-stage',
                    ],
                    true
                )
            ) {
                return 'quotation';
            }

            if ($value === 'won') {
                return 'won';
            }
        }

        return '';
    }

    public function becameQuotation(
        $lead
    ): bool {
        if (
            ! $this->commercialStageChanged(
                $lead
            )
        ) {
            return false;
        }

        return $this->currentStageKey(
            $lead
        ) === 'quotation';
    }

    public function becameWon(
        $lead
    ): bool {
        if (
            ! $this->commercialStageChanged(
                $lead
            )
        ) {
            return false;
        }

        return $this->currentStageKey(
            $lead
        ) === 'won';
    }

    private function commercialStageChanged(
        $lead
    ): bool {
        if (
            ! method_exists(
                $lead,
                'wasChanged'
            )
        ) {
            return true;
        }

        return $lead->wasChanged(
            [
                'status',
                'lead_pipeline_stage_id',
            ]
        );
    }

    private function normalize(
        mixed $value
    ): string {
        $value =
            strtolower(
                trim(
                    (string) (
                        $value
                        ?? ''
                    )
                )
            );

        $value =
            preg_replace(
                '/[^a-z0-9]+/',
                '-',
                $value
            )
            ?? $value;

        return trim(
            $value,
            '-'
        );
    }
}
