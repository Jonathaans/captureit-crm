<?php

namespace Webkul\Admin\Services;

use Carbon\Carbon;
use RuntimeException;
use Throwable;
use Webkul\Admin\Models\UserEmailAccount;
use Webkul\Admin\Models\UserEmailMessage;

class UserEmailSyncService
{
    public function __construct(
        protected UserEmailConnectionService $connection
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

        $stream =
            $this->connection
                ->openImap(
                    $account,
                    'INBOX'
                );

        $synced = 0;

        try {
            $uids =
                imap_search(
                    $stream,
                    'ALL',
                    SE_UID
                );

            if (! is_array($uids)) {
                $account->update([
                    'imap_status' => 'connected',
                    'last_synced_at' => now(),
                    'last_sync_error' => null,
                ]);

                return 0;
            }

            sort(
                $uids,
                SORT_NUMERIC
            );

            $lastUid =
                (int) (
                    $account->imap_last_uid
                    ?? 0
                );

            $newUids =
                array_values(
                    array_filter(
                        $uids,
                        fn ($uid) =>
                            (int) $uid
                            > $lastUid
                    )
                );

            if (
                count(
                    $newUids
                ) > $limit
            ) {
                $newUids =
                    array_slice(
                        $newUids,
                        -$limit
                    );
            }

            foreach ($newUids as $uid) {
                $uid =
                    (int) $uid;

                $overview =
                    imap_fetch_overview(
                        $stream,
                        (string) $uid,
                        FT_UID
                    );

                $overview =
                    is_array($overview)
                        ? (
                            $overview[0]
                            ?? null
                        )
                        : null;

                if (! $overview) {
                    continue;
                }

                $messageNumber =
                    imap_msgno(
                        $stream,
                        $uid
                    );

                if ($messageNumber < 1) {
                    continue;
                }

                $header =
                    imap_headerinfo(
                        $stream,
                        $messageNumber
                    );

                $structure =
                    imap_fetchstructure(
                        $stream,
                        $messageNumber
                    );

                [
                    $textBody,
                    $htmlBody,
                ] =
                    $this->extractBodies(
                        $stream,
                        $messageNumber,
                        $structure
                    );

                $from =
                    $this->firstAddress(
                        $header->from
                        ?? []
                    );

                $to =
                    $this->addresses(
                        $header->to
                        ?? []
                    );

                $cc =
                    $this->addresses(
                        $header->cc
                        ?? []
                    );

                $receivedAt = null;

                if (
                    ! empty(
                        $overview->date
                    )
                ) {
                    try {
                        $receivedAt =
                            Carbon::parse(
                                $overview->date
                            );
                    } catch (Throwable) {
                        $receivedAt = null;
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
                                $uid,
                        ],
                        [
                            'user_id' =>
                                $account->user_id,

                            'message_id' =>
                                $overview->message_id
                                ?? null,

                            'direction' =>
                                'incoming',

                            'from_name' =>
                                $from['name'],

                            'from_email' =>
                                $from['email'],

                            'to_emails' =>
                                $to
                                    ? json_encode(
                                        $to,
                                        JSON_UNESCAPED_UNICODE
                                        | JSON_UNESCAPED_SLASHES
                                    )
                                    : null,

                            'cc_emails' =>
                                $cc
                                    ? json_encode(
                                        $cc,
                                        JSON_UNESCAPED_UNICODE
                                        | JSON_UNESCAPED_SLASHES
                                    )
                                    : null,

                            'subject' =>
                                $this->decodeHeader(
                                    $overview->subject
                                    ?? ''
                                ),

                            'text_body' =>
                                $textBody,

                            'html_body' =>
                                $htmlBody,

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
                        $uid
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
                'imap_status' => 'error',
                'last_synced_at' => now(),
                'last_sync_error' =>
                    $exception->getMessage(),
            ]);

            throw $exception;
        } finally {
            imap_close(
                $stream
            );
        }
    }

    private function extractBodies(
        $stream,
        int $messageNumber,
        mixed $structure
    ): array {
        if (! $structure) {
            return [
                $this->decodeBody(
                    (string) imap_body(
                        $stream,
                        $messageNumber,
                        FT_PEEK
                    ),
                    0
                ),
                null,
            ];
        }

        $text = null;
        $html = null;

        $this->walkParts(
            $stream,
            $messageNumber,
            $structure,
            '',
            $text,
            $html
        );

        if (
            $text === null
            && $html === null
        ) {
            $raw =
                (string) imap_body(
                    $stream,
                    $messageNumber,
                    FT_PEEK
                );

            $text =
                $this->decodeBody(
                    $raw,
                    (int) (
                        $structure->encoding
                        ?? 0
                    )
                );
        }

        return [
            $text,
            $html,
        ];
    }

    private function walkParts(
        $stream,
        int $messageNumber,
        mixed $structure,
        string $prefix,
        ?string &$text,
        ?string &$html
    ): void {
        $parts =
            $structure->parts
            ?? null;

        if (
            ! is_array(
                $parts
            )
        ) {
            $type =
                (int) (
                    $structure->type
                    ?? 0
                );

            $subtype =
                strtoupper(
                    (string) (
                        $structure->subtype
                        ?? ''
                    )
                );

            if ($type === 0) {
                $body =
                    (string) imap_body(
                        $stream,
                        $messageNumber,
                        FT_PEEK
                    );

                $body =
                    $this->decodeBody(
                        $body,
                        (int) (
                            $structure->encoding
                            ?? 0
                        )
                    );

                if (
                    $subtype === 'HTML'
                    && $html === null
                ) {
                    $html = $body;
                } elseif (
                    $text === null
                ) {
                    $text = $body;
                }
            }

            return;
        }

        foreach (
            $parts
            as $index => $part
        ) {
            $number =
                $prefix === ''
                    ? (string) (
                        $index + 1
                    )
                    : $prefix
                        .'.'
                        .(
                            $index + 1
                        );

            if (
                isset(
                    $part->parts
                )
                && is_array(
                    $part->parts
                )
            ) {
                $this->walkParts(
                    $stream,
                    $messageNumber,
                    $part,
                    $number,
                    $text,
                    $html
                );

                continue;
            }

            $type =
                (int) (
                    $part->type
                    ?? -1
                );

            if ($type !== 0) {
                continue;
            }

            $subtype =
                strtoupper(
                    (string) (
                        $part->subtype
                        ?? ''
                    )
                );

            if (
                ! in_array(
                    $subtype,
                    [
                        'PLAIN',
                        'HTML',
                    ],
                    true
                )
            ) {
                continue;
            }

            $body =
                (string) imap_fetchbody(
                    $stream,
                    $messageNumber,
                    $number,
                    FT_PEEK
                );

            $body =
                $this->decodeBody(
                    $body,
                    (int) (
                        $part->encoding
                        ?? 0
                    )
                );

            if (
                $subtype === 'PLAIN'
                && $text === null
            ) {
                $text = $body;
            }

            if (
                $subtype === 'HTML'
                && $html === null
            ) {
                $html = $body;
            }
        }
    }

    private function decodeBody(
        string $body,
        int $encoding
    ): string {
        return match ($encoding) {
            3 =>
                (string) base64_decode(
                    $body,
                    true
                ),

            4 =>
                quoted_printable_decode(
                    $body
                ),

            default =>
                $body,
        };
    }

    private function decodeHeader(
        string $value
    ): string {
        if (
            $value === ''
            || ! function_exists(
                'imap_mime_header_decode'
            )
        ) {
            return $value;
        }

        $parts =
            imap_mime_header_decode(
                $value
            );

        if (! is_array($parts)) {
            return $value;
        }

        $decoded = '';

        foreach ($parts as $part) {
            $decoded .=
                $part->text
                ?? '';
        }

        return $decoded !== ''
            ? $decoded
            : $value;
    }

    private function firstAddress(
        array $addresses
    ): array {
        $all =
            $this->addresses(
                $addresses
            );

        return $all[0]
            ?? [
                'name' => null,
                'email' => null,
            ];
    }

    private function addresses(
        array $addresses
    ): array {
        $result = [];

        foreach ($addresses as $address) {
            $mailbox =
                $address->mailbox
                ?? null;

            $host =
                $address->host
                ?? null;

            if (
                ! $mailbox
                || ! $host
            ) {
                continue;
            }

            $result[] = [
                'name' =>
                    isset(
                        $address->personal
                    )
                        ? $this->decodeHeader(
                            (string) $address->personal
                        )
                        : null,

                'email' =>
                    $mailbox
                    .'@'
                    .$host,
            ];
        }

        return $result;
    }
}
