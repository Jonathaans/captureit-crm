<?php

namespace Webkul\Admin\Http\Controllers\UserEmail;

use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Throwable;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Admin\Models\UserEmailAccount;
use Webkul\Admin\Models\UserEmailMessage;
use Webkul\Admin\Services\UserEmailSyncService;

class MyEmailInboxController extends Controller
{
    public function index(): View
    {
        $user =
            $this->user();

        $account =
            UserEmailAccount::query()
                ->where(
                    'user_id',
                    $user->id
                )
                ->first();

        $messages =
            UserEmailMessage::query()
                ->where(
                    'user_id',
                    $user->id
                )
                ->latest(
                    'received_at'
                )
                ->latest(
                    'id'
                )
                ->paginate(
                    40
                );

        return view(
            'admin::user-email.inbox',
            compact(
                'account',
                'messages'
            )
        );
    }

    public function sync(
        UserEmailSyncService $sync
    ): RedirectResponse {
        $user =
            $this->user();

        $account =
            UserEmailAccount::query()
                ->where(
                    'user_id',
                    $user->id
                )
                ->firstOrFail();

        try {
            $count =
                $sync->sync(
                    $account,
                    100
                );

            session()->flash(
                'success',
                $count
                .' email baru berhasil disinkronkan.'
            );
        } catch (Throwable $exception) {
            session()->flash(
                'warning',
                'Email sync gagal: '
                .$exception->getMessage()
            );
        }

        return back();
    }

    public function show(
        int $id
    ): View {
        $user =
            $this->user();

        $message =
            UserEmailMessage::query()
                ->where(
                    'user_id',
                    $user->id
                )
                ->findOrFail(
                    $id
                );

        if (! $message->read_at) {
            $message->update([
                'read_at' =>
                    now(),
            ]);
        }

        return view(
            'admin::user-email.message',
            compact(
                'message'
            )
        );
    }

    private function user()
    {
        $user =
            auth()
                ->guard('user')
                ->user();

        abort_unless(
            $user,
            403
        );

        return $user;
    }
}
