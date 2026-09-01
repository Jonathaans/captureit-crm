<?php

namespace Webkul\Admin\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Webkul\Admin\Models\GoogleCalendarEvent;

class LeadWonCalendarService
{
    public function isWon(
        Model $lead
    ): bool {
        $status =
            strtolower(
                trim(
                    (string) (
                        $lead->getAttribute(
                            'status'
                        )
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
            $lead->getAttribute(
                'lead_pipeline_stage_id'
            );

        if (
            $stageId
            && Schema::hasTable(
                'lead_pipeline_stages'
            )
        ) {
            $columns =
                Schema::getColumnListing(
                    'lead_pipeline_stages'
                );

            $nameColumn =
                in_array(
                    'name',
                    $columns,
                    true
                )
                    ? 'name'
                    : (
                        in_array(
                            'code',
                            $columns,
                            true
                        )
                            ? 'code'
                            : null
                    );

            if ($nameColumn) {
                $stage =
                    DB::table(
                        'lead_pipeline_stages'
                    )
                        ->where(
                            'id',
                            $stageId
                        )
                        ->value(
                            $nameColumn
                        );

                if (
                    strtolower(
                        trim(
                            (string) $stage
                        )
                    ) === 'won'
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    public function ensureDraft(
        Model $lead
    ): GoogleCalendarEvent {
        $event =
            GoogleCalendarEvent::query()
                ->firstOrNew([
                    'lead_id' =>
                        (int) $lead->getKey(),
                ]);

        if (! $event->exists) {
            $event->fill([
                'sales_owner_id' =>
                    $lead->getAttribute(
                        'user_id'
                    )
                    ?: null,

                'title' =>
                    $this->defaultTitle(
                        $lead
                    ),

                'event_status' =>
                    'needs_schedule',

                'sync_status' =>
                    'needs_schedule',
            ]);

            $event->save();
        }

        return $event;
    }

    private function defaultTitle(
        Model $lead
    ): string {
        $title =
            trim(
                (string) (
                    $lead->getAttribute(
                        'title'
                    )
                    ?: ''
                )
            );

        if ($title !== '') {
            return $title;
        }

        $personId =
            $lead->getAttribute(
                'person_id'
            );

        if (
            $personId
            && Schema::hasTable(
                'persons'
            )
        ) {
            $name =
                DB::table(
                    'persons'
                )
                    ->where(
                        'id',
                        $personId
                    )
                    ->value(
                        'name'
                    );

            if ($name) {
                return 'Event - '
                    .$name;
            }
        }

        return 'Confirmed Event - Lead #'
            .$lead->getKey();
    }
}
