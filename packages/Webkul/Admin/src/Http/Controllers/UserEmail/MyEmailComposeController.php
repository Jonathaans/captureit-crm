<?php

namespace Webkul\Admin\Http\Controllers\UserEmail;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;
use Throwable;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Admin\Models\UserEmailAccount;
use Webkul\Admin\Models\UserEmailMessage;
use Webkul\Admin\Services\UserEmailSendService;

class MyEmailComposeController extends Controller
{
    public function create(
        Request $request,
        UserEmailSendService $sender
    ): View {
        $user =
            $this->user();

        $account =
            $this->account(
                $user->id
            );

        $replyTo = null;
        $to = '';
        $cc = '';
        $subject = '';
        $body = '';

        $replyId =
            $request->integer(
                'reply_to'
            );

        $mode =
            strtolower(
                trim(
                    (string) $request->input(
                        'mode',
                        'reply'
                    )
                )
            );

        if ($replyId > 0) {
            $replyTo =
                UserEmailMessage::query()
                    ->where(
                        'user_id',
                        $user->id
                    )
                    ->findOrFail(
                        $replyId
                    );

            $to =
                $replyTo->from_email
                    ?: '';

            if ($mode === 'reply_all') {
                $ccAddresses =
                    array_merge(
                        $this->decodeAddresses(
                            $replyTo->to_emails
                        ),
                        $this->decodeAddresses(
                            $replyTo->cc_emails
                        )
                    );

                $current =
                    strtolower(
                        trim(
                            (string) $account
                                ->email_address
                        )
                    );

                $senderEmail =
                    strtolower(
                        trim(
                            (string) $replyTo
                                ->from_email
                        )
                    );

                $ccAddresses =
                    array_values(
                        array_filter(
                            $ccAddresses,
                            fn ($item) =>
                                strtolower(
                                    trim(
                                        (string) (
                                            $item['email']
                                            ?? ''
                                        )
                                    )
                                ) !== $current
                                && strtolower(
                                    trim(
                                        (string) (
                                            $item['email']
                                            ?? ''
                                        )
                                    )
                                ) !== $senderEmail
                        )
                    );

                $cc =
                    $sender
                        ->addressesToInput(
                            $ccAddresses
                        );
            }

            $subject =
                $this->replySubject(
                    (string) (
                        $replyTo->subject
                        ?? ''
                    )
                );

            $body =
                "\n\n"
                .'--- Original Message ---'
                ."\n"
                .'From: '
                .(
                    $replyTo->from_email
                    ?: '-'
                )
                ."\n"
                .'Date: '
                .(
                    $replyTo->received_at
                        ?->format(
                            'Y-m-d H:i:s'
                        )
                    ?: '-'
                )
                ."\n"
                .'Subject: '
                .(
                    $replyTo->subject
                    ?: '(No Subject)'
                )
                ."\n\n"
                .trim(
                    (string) (
                        $replyTo->text_body
                        ?: strip_tags(
                            (string) $replyTo
                                ->html_body
                        )
                    )
                );
        }

        return view(
            'admin::user-email.compose',
            compact(
                'account',
                'replyTo',
                'to',
                'cc',
                'subject',
                'body'
            )
        );
    }

    public function send(
        Request $request,
        UserEmailSendService $sender
    ): RedirectResponse {
        $user =
            $this->user();

        $account =
            $this->account(
                $user->id
            );

        $validated =
            $request->validate([
                'to' => [
                    'required',
                    'string',
                    'max:5000',
                ],

                'cc' => [
                    'nullable',
                    'string',
                    'max:5000',
                ],

                'subject' => [
                    'required',
                    'string',
                    'max:998',
                ],

                'message' => [
                    'required',
                    'string',
                    'max:200000',
                ],

                'reply_to_message_id' => [
                    'nullable',
                    'integer',
                ],
            ]);

        try {
            $replyTo = null;

            if (
                ! empty(
                    $validated[
                        'reply_to_message_id'
                    ]
                )
            ) {
                $replyTo =
                    UserEmailMessage::query()
                        ->where(
                            'user_id',
                            $user->id
                        )
                        ->findOrFail(
                            (int) $validated[
                                'reply_to_message_id'
                            ]
                        );
            }

            $to =
                $sender->parseAddressInput(
                    $validated['to']
                );

            $cc =
                $sender->parseAddressInput(
                    $validated['cc']
                    ?? ''
                );

            $sent =
                $sender->send(
                    $account,
                    $to,
                    $cc,
                    trim(
                        $validated['subject']
                    ),
                    $validated['message'],
                    $replyTo
                );

            session()->flash(
                'success',
                'Email berhasil dikirim dari '
                .$account->email_address
                .'.'
            );

            return redirect()->route(
                'admin.my-email.sent.show',
                $sent->id
            );
        } catch (Throwable $exception) {
            session()->flash(
                'warning',
                $exception->getMessage()
            );

            return back()
                ->withInput();
        }
    }

    public function sent(): View
    {
        $user =
            $this->user();

        $messages =
            UserEmailMessage::query()
                ->where(
                    'user_id',
                    $user->id
                )
                ->where(
                    'direction',
                    'outgoing'
                )
                ->latest(
                    'sent_at'
                )
                ->latest(
                    'id'
                )
                ->paginate(
                    40
                );

        return view(
            'admin::user-email.sent',
            compact(
                'messages'
            )
        );
    }

    public function showSent(
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
                ->where(
                    'direction',
                    'outgoing'
                )
                ->findOrFail(
                    $id
                );

        return view(
            'admin::user-email.sent-message',
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

    private function account(
        int $userId
    ): UserEmailAccount {
        $account =
            UserEmailAccount::query()
                ->where(
                    'user_id',
                    $userId
                )
                ->first();

        abort_unless(
            $account,
            422,
            'Configure My Email terlebih dahulu.'
        );

        abort_unless(
            strtolower(
                (string) $account
                    ->smtp_status
            ) === 'connected',
            422,
            'SMTP belum CONNECTED. Test SMTP terlebih dahulu.'
        );

        return $account;
    }

    private function decodeAddresses(
        mixed $json
    ): array {
        if (
            $json === null
            || $json === ''
        ) {
            return [];
        }

        if (is_array($json)) {
            return $json;
        }

        $decoded =
            json_decode(
                (string) $json,
                true
            );

        return is_array(
            $decoded
        )
            ? $decoded
            : [];
    }

    private function replySubject(
        string $subject
    ): string {
        $subject =
            trim(
                $subject
            );

        if (
            preg_match(
                '/^re\s*:/i',
                $subject
            )
        ) {
            return $subject;
        }

        return 'Re: '
            .$subject;
    }
}
