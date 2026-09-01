<?php

namespace Webkul\Admin\Http\Controllers\Notification;

use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Admin\Models\CrmNotification;

class CrmNotificationController extends Controller
{
    public function index(): View
    {
        $this->authorizeAccess();

        $userId =
            auth()->guard('user')->id();

        $notifications =
            CrmNotification::query()
                ->where(
                    function ($query) use ($userId) {
                        $query
                            ->whereNull('user_id')
                            ->orWhere(
                                'user_id',
                                $userId
                            );
                    }
                )
                ->whereNull('resolved_at')
                ->latest('id')
                ->paginate(50);

        return view(
            'admin::crm-notifications.index',
            compact('notifications')
        );
    }

    public function read(
        int $id
    ): RedirectResponse {
        $this->authorizeAccess();

        CrmNotification::query()
            ->where('id', $id)
            ->update([
                'read_at' => now(),
            ]);

        return back();
    }

    public function readAll(): RedirectResponse
    {
        $this->authorizeAccess();

        $userId =
            auth()->guard('user')->id();

        CrmNotification::query()
            ->where(
                function ($query) use ($userId) {
                    $query
                        ->whereNull('user_id')
                        ->orWhere(
                            'user_id',
                            $userId
                        );
                }
            )
            ->whereNull('read_at')
            ->update([
                'read_at' => now(),
            ]);

        return back();
    }

    private function authorizeAccess(): void
    {
        abort_unless(
            auth()->guard('user')->check(),
            403
        );

        if (
            function_exists('bouncer')
            && ! bouncer()->hasPermission(
                'crm-notifications'
            )
        ) {
            abort(403);
        }
    }
}
