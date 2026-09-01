<?php

namespace Webkul\Admin\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Webkul\User\Models\User;

class CalendarSalesOwnerService
{
    public const ALLOWED_ROLE_NAMES = [
        'administrator',
        'sales admin',
        'sales user',
    ];

    public function options(): Collection
    {
        return User::query()
            ->with('role')
            ->whereHas(
                'role',
                function ($query) {
                    $query->whereIn(
                        DB::raw(
                            'LOWER(name)'
                        ),
                        self::ALLOWED_ROLE_NAMES
                    );
                }
            )
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'role_id',
                'google_calendar_color_id',
            ]);
    }

    public function isEligible(
        int $userId
    ): bool {
        return User::query()
            ->whereKey(
                $userId
            )
            ->whereHas(
                'role',
                function ($query) {
                    $query->whereIn(
                        DB::raw(
                            'LOWER(name)'
                        ),
                        self::ALLOWED_ROLE_NAMES
                    );
                }
            )
            ->exists();
    }
}
