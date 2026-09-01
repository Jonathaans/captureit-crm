<?php

namespace Webkul\Admin\Services;

use Carbon\Carbon;
use Throwable;
use Webkul\Admin\Models\UserEmailAccount;
use Webkul\Admin\Models\UserEmailMessage;

class UserEmailSyncService
{
    public function __construct(
        protected PurePhpImapClient $imap,
        protected Rfc822EmailParser $parser
    ) {
    }

    public function sync(
        UserEmailAccount $account,
        int $limit = 50
    ): int {
        if (! $account->sync_enabled) {
            return 0;
        }

        $limit =
            max(
                1,
                min(
                    $limit,
                    200
                )
            );

        $synced = 0;

        try {
            $this->imap->connect(
                $account,
                'INBOX'
            );

            $lastUid =
                (int) (
                    $account->imap_last_uid
                    ?? 0
                );

            $uids =
                $this->imap
                    ->searchUids(
                        $lastUid
                    );

            if (
                count(
                    $uids
                ) > $limit
            ) {
                /*
                 * Preserve the newest batch on first sync.
                 */
                $uids =
                    array_slice(
                        $uids,
                        -$limit
                    );
            }

            foreach ($uids as $uid) {
                $raw =
                    $this->imap
                        ->fetchRawMessage(
                            (int) $uid
                        );

                $message =
                    $this->parser
                        ->parse(
                            $raw
                        );

                $receivedAt =
                    null;

                if (
                    ! empty(
                        $message['date']
                    )
                ) {
                    try {
                        $receivedAt =
                            Carbon::parse(
                                $message['date']
                            );
                    } catch (Throwable) {
                        $receivedAt =
                            null;
                    }
                }

                UserEmailMessage::query()
                    ->updateOrCreate(
                        [
                            'account_id' =>
                                $account->id,

                            'folder' =>
                                'INBOX',

                            'imap_uid' =>
                                (int) $uid,
                        ],
                        [
                            'user_id' =>
                                $account->user_id,

                            'message_id' =>
                                $message[
                                    'message_id'
                                ],

                            'direction' =>
                                'incoming',

                            'from_name' =>
                                $message[
                                    'from_name'
                                ],

                            'from_email' =>
                                $message[
                                    'from_email'
                                ],

                            'to_emails' =>
                                ! empty(
                                    $message['to']
                                )
                                    ? json_encode(
                                        $message['to'],
                                        JSON_UNESCAPED_UNICODE
                                        | JSON_UNESCAPED_SLASHES
                                    )
                                    : null,

                            'cc_emails' =>
                                ! empty(
                                    $message['cc']
                                )
                                    ? json_encode(
                                        $message['cc'],
                                        JSON_UNESCAPED_UNICODE
                                        | JSON_UNESCAPED_SLASHES
                                    )
                                    : null,

                            'subject' =>
                                $message[
                                    'subject'
                                ],

                            'text_body' =>
                                $message[
                                    'text_body'
                                ],

                            'html_body' =>
                                $message[
                                    'html_body'
                                ],

                            'received_at' =>
                                $receivedAt,
                        ]
                    );

                $account->imap_last_uid =
                    max(
                        (int) (
                            $account
                                ->imap_last_uid
                            ?? 0
                        ),
                        (int) $uid
                    );

                $synced++;
            }

            $account->imap_status =
                'connected';

            $account->last_synced_at =
                now();

            $account->last_sync_error =
                null;

            $account->save();

            return $synced;
        } catch (Throwable $exception) {
            $account->update([
                'imap_status' =>
                    'error',

                'last_synced_at' =>
                    now(),

                'last_sync_error' =>
                    $exception->getMessage(),
            ]);

            throw $exception;
        } finally {
            $this->imap->disconnect();
        }
    }
}
