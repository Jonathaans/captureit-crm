<?php

namespace Webkul\Admin\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Webkul\User\Models\User;

class QuoteSalesOwnerService
{
    public const ALLOWED_ROLE_NAMES = [
        'administrator',
        'sales admin',
        'sales user',
    ];

    /**
     * Users eligible to become a Quote Sales Owner.
     *
     * On Edit, an existing legacy owner outside Sales roles is appended only
     * so an old Quote can be saved without silently changing its owner.
     * Any NEW owner selection still has to be Sales Admin / Sales User.
     */
    public function options(
        ?int $currentOwnerId = null
    ): Collection {
        $users = User::query()
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
            ])
            ->map(
                function ($user) {
                    return (object) [
                        'id' =>
                            (int) $user->id,

                        'name' =>
                            (string) $user->name,

                        'role_name' =>
                            (string) (
                                $user->role?->name
                                ?? ''
                            ),

                        'is_legacy_current' =>
                            false,
                    ];
                }
            );

        if (
            $currentOwnerId
            && ! $users->contains(
                'id',
                $currentOwnerId
            )
        ) {
            $currentOwner = User::query()
                ->with('role')
                ->find(
                    $currentOwnerId
                );

            if ($currentOwner) {
                $users->prepend(
                    (object) [
                        'id' =>
                            (int) $currentOwner->id,

                        'name' =>
                            (string) $currentOwner->name,

                        'role_name' =>
                            (string) (
                                $currentOwner->role?->name
                                ?? ''
                            ),

                        'is_legacy_current' =>
                            true,
                    ]
                );
            }
        }

        return $users->values();
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

    public function roleSummary(): Collection
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
            ])
            ->map(
                fn ($user) => [
                    'id' =>
                        (int) $user->id,

                    'name' =>
                        (string) $user->name,

                    'role' =>
                        (string) (
                            $user->role?->name
                            ?? '-'
                        ),
                ]
            );
    }
}
