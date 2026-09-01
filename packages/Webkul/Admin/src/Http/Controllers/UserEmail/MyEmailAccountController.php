<?php

namespace Webkul\Admin\Http\Controllers\UserEmail;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Admin\Models\UserEmailAccount;
use Webkul\Admin\Services\UserEmailConnectionService;

class MyEmailAccountController extends Controller
{
    public function edit(): View
    {
        $user =
            $this->user();

        $account =
            UserEmailAccount::query()
                ->firstOrNew([
                    'user_id' =>
                        $user->id,
                ]);

        if (! $account->exists) {
            $account->email_address =
                $user->email;

            $account->imap_port =
                993;

            $account->imap_encryption =
                'ssl';

            $account->imap_validate_certificate =
                true;

            $account->imap_username =
                $user->email;

            $account->smtp_port =
                465;

            $account->smtp_encryption =
                'ssl';

            $account->smtp_username =
                $user->email;

            $account->sync_enabled =
                true;
        }

        return view(
            'admin::user-email.account',
            compact(
                'account'
            )
        );
    }

    public function update(
        Request $request
    ): RedirectResponse {
        $user =
            $this->user();

        $data =
            $request->validate([
                'email_address' => [
                    'required',
                    'email',
                    'max:255',
                ],

                'imap_host' => [
                    'required',
                    'string',
                    'max:255',
                ],

                'imap_port' => [
                    'required',
                    'integer',
                    'min:1',
                    'max:65535',
                ],

                'imap_encryption' => [
                    'required',
                    'in:ssl,tls,none',
                ],

                'imap_validate_certificate' => [
                    'nullable',
                    'boolean',
                ],

                'imap_username' => [
                    'required',
                    'string',
                    'max:255',
                ],

                'imap_password' => [
                    'nullable',
                    'string',
                    'max:2000',
                ],

                'smtp_host' => [
                    'required',
                    'string',
                    'max:255',
                ],

                'smtp_port' => [
                    'required',
                    'integer',
                    'min:1',
                    'max:65535',
                ],

                'smtp_encryption' => [
                    'required',
                    'in:ssl,tls,starttls,none',
                ],

                'smtp_username' => [
                    'nullable',
                    'string',
                    'max:255',
                ],

                'smtp_password' => [
                    'nullable',
                    'string',
                    'max:2000',
                ],

                'sync_enabled' => [
                    'nullable',
                    'boolean',
                ],
            ]);

        $account =
            UserEmailAccount::query()
                ->firstOrNew([
                    'user_id' =>
                        $user->id,
                ]);

        $account->fill([
            'email_address' =>
                trim(
                    $data[
                        'email_address'
                    ]
                ),

            'imap_host' =>
                trim(
                    $data[
                        'imap_host'
                    ]
                ),

            'imap_port' =>
                (int) $data[
                    'imap_port'
                ],

            'imap_encryption' =>
                $data[
                    'imap_encryption'
                ],

            'imap_validate_certificate' =>
                $request->boolean(
                    'imap_validate_certificate'
                ),

            'imap_username' =>
                trim(
                    $data[
                        'imap_username'
                    ]
                ),

            'smtp_host' =>
                trim(
                    $data[
                        'smtp_host'
                    ]
                ),

            'smtp_port' =>
                (int) $data[
                    'smtp_port'
                ],

            'smtp_encryption' =>
                $data[
                    'smtp_encryption'
                ],

            'smtp_username' =>
                trim(
                    (string) (
                        $data[
                            'smtp_username'
                        ]
                        ?? ''
                    )
                )
                    ?: null,

            'sync_enabled' =>
                $request->boolean(
                    'sync_enabled'
                ),
        ]);

        if (
            ! empty(
                $data[
                    'imap_password'
                ]
            )
        ) {
            $account->imap_password =
                $data[
                    'imap_password'
                ];
        }

        if (
            ! empty(
                $data[
                    'smtp_password'
                ]
            )
        ) {
            $account->smtp_password =
                $data[
                    'smtp_password'
                ];
        }

        $account->save();

        session()->flash(
            'success',
            'Email Integration berhasil disimpan.'
        );

        return back();
    }

    public function testImap(
        UserEmailConnectionService $connection
    ): RedirectResponse {
        $account =
            $this->account();

        try {
            $connection->testImap(
                $account
            );

            session()->flash(
                'success',
                'IMAP connection: PASS'
            );
        } catch (Throwable $exception) {
            session()->flash(
                'warning',
                $exception->getMessage()
            );
        }

        return back();
    }

    public function testSmtp(
        UserEmailConnectionService $connection
    ): RedirectResponse {
        $account =
            $this->account();

        try {
            $connection->testSmtp(
                $account
            );

            session()->flash(
                'success',
                'SMTP connection: PASS. Test email dikirim ke mailbox Anda.'
            );
        } catch (Throwable $exception) {
            session()->flash(
                'warning',
                'SMTP connection failed: '
                .$exception->getMessage()
            );
        }

        return back();
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

    private function account(): UserEmailAccount
    {
        $user =
            $this->user();

        return UserEmailAccount::query()
            ->where(
                'user_id',
                $user->id
            )
            ->firstOrFail();
    }
}
