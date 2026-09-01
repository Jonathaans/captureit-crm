<?php

namespace Webkul\Admin\Services;

use Illuminate\Support\Facades\DB;
use Webkul\User\Models\User;

class SalesCalendarColorService
{
    /**
     * Google Calendar event color IDs.
     *
     * Ordered for strong visual separation:
     * Blueberry, Basil, Grape, Tangerine, Flamingo, Banana,
     * Peacock, Tomato, Lavender, Sage, Graphite.
     */
    public const PALETTE = [
        '9',
        '10',
        '3',
        '6',
        '4',
        '5',
        '7',
        '11',
        '1',
        '2',
        '8',
    ];

    public function assignIfEligible(
        int $userId
    ): ?string {
        $user = User::query()
            ->with('role')
            ->find(
                $userId
            );

        if (! $user) {
            return null;
        }

        $roleName = strtolower(
            trim(
                (string) (
                    $user->role?->name
                    ?? ''
                )
            )
        );

        if (
            ! in_array(
                $roleName,
                CalendarSalesOwnerService::ALLOWED_ROLE_NAMES,
                true
            )
        ) {
            return null;
        }

        if (
            ! empty(
                $user->google_calendar_color_id
            )
        ) {
            return (string) $user
                ->google_calendar_color_id;
        }

        $used = User::query()
            ->whereNotNull(
                'google_calendar_color_id'
            )
            ->pluck(
                'google_calendar_color_id'
            )
            ->map(
                fn ($value) =>
                    (string) $value
            )
            ->all();

        $available = array_values(
            array_diff(
                self::PALETTE,
                $used
            )
        );

        if ($available) {
            $colorId =
                $available[0];
        } else {
            /*
             * Google only exposes a finite event-color palette.
             * After all colors are used, reuse them deterministically.
             */
            $eligibleCount = User::query()
                ->whereHas(
                    'role',
                    function ($query) {
                        $query->whereIn(
                            DB::raw(
                                'LOWER(name)'
                            ),
                            CalendarSalesOwnerService::ALLOWED_ROLE_NAMES
                        );
                    }
                )
                ->count();

            $colorId =
                self::PALETTE[
                    max(
                        0,
                        $eligibleCount - 1
                    )
                    % count(
                        self::PALETTE
                    )
                ];
        }

        /*
         * Direct query update avoids recursively triggering User::saved.
         */
        DB::table(
            'users'
        )
            ->where(
                'id',
                $userId
            )
            ->update([
                'google_calendar_color_id' =>
                    $colorId,
            ]);

        return $colorId;
    }
}
