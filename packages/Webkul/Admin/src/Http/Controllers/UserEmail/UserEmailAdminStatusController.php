<?php

namespace Webkul\Admin\Http\Controllers\UserEmail;

use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Admin\Models\UserEmailAccount;

class UserEmailAdminStatusController extends Controller
{
    public function index(): View
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

        abort_unless(
            strtolower(
                trim(
                    (string) (
                        $user->role?->name
                        ?? ''
                    )
                )
            ) === 'administrator',
            403
        );

        $accounts =
            UserEmailAccount::query()
                ->orderBy(
                    'user_id'
                )
                ->paginate(
                    50
                );

        $userNames =
            DB::table('users')
                ->whereIn(
                    'id',
                    $accounts
                        ->getCollection()
                        ->pluck(
                            'user_id'
                        )
                )
                ->pluck(
                    'name',
                    'id'
                );

        return view(
            'admin::user-email.admin-status',
            compact(
                'accounts',
                'userNames'
            )
        );
    }
}
