<?php

namespace Webkul\Admin\Services;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;

class SalesLeadAccessService
{
    public function isRestrictedSalesUser(): bool
    {
        if (app()->runningInConsole()) {
            return false;
        }

        try {
            $user =
                auth()
                    ->guard('user')
                    ->user();
        } catch (\Throwable) {
            return false;
        }

        if (! $user) {
            return false;
        }

        try {
            $user->loadMissing('role');
        } catch (\Throwable) {
            // Continue with any already-loaded role relation.
        }

        $roleName =
            strtolower(
                trim(
                    (string) (
                        $user->role?->name
                        ?? ''
                    )
                )
            );

        return $roleName === 'sales user';
    }

    public function currentSalesUserId(): ?int
    {
        if (! $this->isRestrictedSalesUser()) {
            return null;
        }

        try {
            return (int) auth()
                ->guard('user')
                ->id();
        } catch (\Throwable) {
            return null;
        }
    }

    public function scopeEloquent(
        EloquentBuilder $query
    ): EloquentBuilder {
        $userId =
            $this->currentSalesUserId();

        if (! $userId) {
            return $query;
        }

        $model =
            $query->getModel();

        return $query->where(
            $model->qualifyColumn(
                'user_id'
            ),
            $userId
        );
    }

    public function scopeQuery(
        QueryBuilder $query,
        ?string $ownerColumn = null
    ): QueryBuilder {
        $userId =
            $this->currentSalesUserId();

        if (! $userId) {
            return $query;
        }

        $ownerColumn ??=
            $this->resolveRawOwnerColumn(
                $query
            );

        return $query->where(
            $ownerColumn,
            $userId
        );
    }

    public function forceOwnerForSalesUser(
        mixed $lead
    ): void {
        $userId =
            $this->currentSalesUserId();

        if (! $userId) {
            return;
        }

        /*
         * Sales User may not create/reassign a Lead to another owner.
         */
        $lead->setAttribute(
            'user_id',
            $userId
        );
    }

    private function resolveRawOwnerColumn(
        QueryBuilder $query
    ): string {
        $from =
            trim(
                (string) (
                    $query->from
                    ?? 'leads'
                )
            );

        /*
         * Examples:
         * leads
         * leads as l
         * leads AS leads
         */
        if (
            preg_match(
                '/\s+as\s+([a-zA-Z0-9_]+)$/i',
                $from,
                $matches
            )
        ) {
            return $matches[1]
                .'.user_id';
        }

        $parts =
            preg_split(
                '/\s+/',
                $from
            );

        if (
            is_array($parts)
            && count($parts) >= 2
        ) {
            return end($parts)
                .'.user_id';
        }

        return 'leads.user_id';
    }
}
