<?php

namespace Webkul\Admin\Services;

class WorkOrderAccessService
{
    public function user()
    {
        $user =
            auth()
                ->guard('user')
                ->user();

        abort_unless(
            $user,
            403
        );

        $user->loadMissing(
            'role'
        );

        return $user;
    }

    public function roleName(
        $user
    ): string {
        return strtolower(
            trim(
                (string) (
                    $user->role?->name
                    ?? ''
                )
            )
        );
    }

    public function canView(
        $user
    ): bool {
        return in_array(
            $this->roleName($user),
            [
                'administrator',
                'sales admin',
                'sales user',
                'head warehouse',
                'operational',
            ],
            true
        );
    }

    public function canManageSpk(
        $user
    ): bool {
        return in_array(
            $this->roleName($user),
            [
                'administrator',
                'sales admin',
                'sales user',
            ],
            true
        );
    }

    public function canGenerateDeliveryOrder(
        $user
    ): bool {
        return in_array(
            $this->roleName($user),
            [
                'administrator',
                'sales admin',
                'head warehouse',
                'operational',
            ],
            true
        );
    }

    public function assertView(
        $user
    ): void {
        abort_unless(
            $this->canView($user),
            403
        );
    }

    public function assertManageSpk(
        $user
    ): void {
        abort_unless(
            $this->canManageSpk($user),
            403
        );
    }

    public function assertGenerateDeliveryOrder(
        $user
    ): void {
        abort_unless(
            $this->canGenerateDeliveryOrder($user),
            403
        );
    }
}
