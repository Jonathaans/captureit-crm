<?php

namespace Webkul\Admin\Http\Controllers\Dashboard;

use Illuminate\View\View;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Admin\Services\OperationsDashboardService;

class OperationsDashboardController extends Controller
{
    public function index(
        OperationsDashboardService $service
    ): View {
        abort_unless(
            auth()->guard('user')->check(),
            403
        );

        if (
            function_exists('bouncer')
            && ! bouncer()->hasPermission(
                'operations-dashboard'
            )
        ) {
            abort(403);
        }

        $user =
            auth()->guard('user')->user();

        $user->loadMissing('role');

        return view(
            'admin::operations-dashboard.index',
            $service->build($user)
        );
    }
}
