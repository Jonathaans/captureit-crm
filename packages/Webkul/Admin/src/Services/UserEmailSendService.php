<?php

namespace Webkul\Admin\Services;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Throwable;
use Webkul\Admin\Models\UserEmailAccount;
use Webkul\Admin\Models\UserEmailMessage;

class UserEmailSendService
{
    public function send(
        UserEmailAccount $account,
        array $to,
        array $cc,
        string $subject,
        string $body,
        ?UserEmailMessage $replyTo = null
    ): UserEmailMessage {
        $to =
            $this->normalizeAddresses(
                $to
            );

        $cc =
            $this->normalizeAddresses(
                $cc
            );

        if (empty($to)) {
            throw new RuntimeException(
                'Minimal satu alamat To diperlukan.'
            );
        }

        $mailerName =
            'user_email_send_'
            .$account->id
            .'_'
            .Str::lower(
                Str::random(8)
            );

        $this->configureMailer(
            $mailerName,
            $account
        );

        $email =
            new Email();

        $email->from(
            new Address(
                $account->email_address
            )
        );

        foreach ($to as $address) {
            $email->addTo(
                new Address(
                    $address['email'],
                    $address['name']
                        ?? ''
                )
            );
        }

        foreach ($cc as $address) {
            $email->addCc(
                new Address(
                    $address['email'],
                    $address['name']
                        ?? ''
                )
            );
        }

        $email
            ->subject(
                $subject
            )
            ->text(
                $body
            )
            ->html(
                nl2br(
                    e(
                        $body
                    )
                )
            );

        $inReplyTo = null;
        $references = null;

        if ($replyTo) {
            $inReplyTo =
                trim(
                    (string) (
                        $replyTo->message_id
                        ?? ''
                    )
                );

            if ($inReplyTo !== '') {
                $normalizedMessageId =
                    $this->messageIdHeader(
                        $inReplyTo
                    );

                $email
                    ->getHeaders()
                    ->addTextHeader(
                        'In-Reply-To',
                        $normalizedMessageId
                    );

                $existingReferences =
                    trim(
                        (string) (
                            $replyTo->references_header
                            ?? ''
                        )
                    );

                $references =
                    trim(
                        $existingReferences
                        .' '
                        .$normalizedMessageId
                    );

                $email
                    ->getHeaders()
                    ->addTextHeader(
                        'References',
                        $references
                    );
            }
        }

        try {
            $transport =
                Mail::mailer(
                    $mailerName
                )->getSymfonyTransport();

            $sent =
                $transport->send(
                    $email
                );

            $sentMessageId =
                method_exists(
                    $sent,
                    'getMessageId'
                )
                    ? $sent->getMessageId()
                    : null;

            return DB::transaction(
                function () use (
                    $account,
                    $to,
                    $cc,
                    $subject,
                    $body,
                    $replyTo,
                    $inReplyTo,
                    $references,
                    $sentMessageId
                ) {
                    /*
                     * user_email_messages.imap_uid is NOT nullable in V1.
                     * CRM_SENT uses its own folder namespace, so a local
                     * monotonic UID is safe and never collides with INBOX UIDs.
                     */
                    $lastLocalUid =
                        (int) UserEmailMessage::query()
                            ->where(
                                'account_id',
                                $account->id
                            )
                            ->where(
                                'folder',
                                'CRM_SENT'
                            )
                            ->lockForUpdate()
                            ->max(
                                'imap_uid'
                            );

                    $localUid =
                        $lastLocalUid + 1;

                    return UserEmailMessage::query()
                        ->create([
                            'user_id' =>
                                $account->user_id,

                            'account_id' =>
                                $account->id,

                            'folder' =>
                                'CRM_SENT',

                            'imap_uid' =>
                                $localUid,

                            'message_id' =>
                                $sentMessageId,

                            'direction' =>
                                'outgoing',

                            'from_name' =>
                                null,

                            'from_email' =>
                                $account->email_address,

                            'to_emails' =>
                                json_encode(
                                    $to,
                                    JSON_UNESCAPED_UNICODE
                                    | JSON_UNESCAPED_SLASHES
                                ),

                            'cc_emails' =>
                                $cc
                                    ? json_encode(
                                        $cc,
                                        JSON_UNESCAPED_UNICODE
                                        | JSON_UNESCAPED_SLASHES
                                    )
                                    : null,

                            'subject' =>
                                $subject,

                            'text_body' =>
                                $body,

                            'html_body' =>
                                nl2br(
                                    e(
                                        $body
                                    )
                                ),

                            'received_at' =>
                                now(),

                            'sent_at' =>
                                now(),

                            'read_at' =>
                                now(),

                            'reply_to_message_id' =>
                                $replyTo?->id,

                            'in_reply_to' =>
                                $inReplyTo !== ''
                                    ? $inReplyTo
                                    : null,

                            'references_header' =>
                                $references,
                        ]);
                }
            );
        } catch (Throwable $exception) {
            throw new RuntimeException(
                'Email gagal dikirim: '
                .$exception->getMessage(),
                0,
                $exception
            );
        } finally {
            Mail::purge(
                $mailerName
            );
        }
    }

    public function parseAddressInput(
        ?string $value
    ): array {
        $value =
            trim(
                (string) (
                    $value
                    ?? ''
                )
            );

        if ($value === '') {
            return [];
        }

        $result = [];

        foreach (
            preg_split(
                '/[,;\r\n]+/',
                $value
            )
            ?: []
            as $item
        ) {
            $item =
                trim(
                    $item
                );

            if ($item === '') {
                continue;
            }

            if (
                preg_match(
                    '/^(?:"?([^"]*)"?\s*)?<([^<>]+)>$/',
                    $item,
                    $matches
                )
            ) {
                $name =
                    trim(
                        (string) (
                            $matches[1]
                            ?? ''
                        )
                    );

                $email =
                    trim(
                        (string) (
                            $matches[2]
                            ?? ''
                        )
                    );
            } else {
                $name = '';
                $email =
                    $item;
            }

            if (
                ! filter_var(
                    $email,
                    FILTER_VALIDATE_EMAIL
                )
            ) {
                throw new RuntimeException(
                    'Alamat email tidak valid: '
                    .$item
                );
            }

            $result[] = [
                'name' =>
                    $name !== ''
                        ? $name
                        : null,

                'email' =>
                    strtolower(
                        $email
                    ),
            ];
        }

        return $this->normalizeAddresses(
            $result
        );
    }

    public function addressesToInput(
        mixed $json
    ): string {
        if (
            $json === null
            || $json === ''
        ) {
            return '';
        }

        $items =
            is_array(
                $json
            )
                ? $json
                : json_decode(
                    (string) $json,
                    true
                );

        if (! is_array($items)) {
            return '';
        }

        return collect(
            $this->normalizeAddresses(
                $items
            )
        )
            ->map(
                function ($item) {
                    if (
                        ! empty(
                            $item['name']
                        )
                    ) {
                        return $item['name']
                            .' <'
                            .$item['email']
                            .'>';
                    }

                    return $item['email'];
                }
            )
            ->implode(
                ', '
            );
    }

    private function configureMailer(
        string $mailerName,
        UserEmailAccount $account
    ): void {
        $encryption =
            strtolower(
                trim(
                    (string) $account
                        ->smtp_encryption
                )
            );

        if ($encryption === 'starttls') {
            $encryption = 'tls';
        }

        if ($encryption === 'none') {
            $encryption = null;
        }

        Config::set(
            'mail.mailers.'
            .$mailerName,
            [
                'transport' =>
                    'smtp',

                'host' =>
                    $account->smtp_host,

                'port' =>
                    (int) $account
                        ->smtp_port,

                'encryption' =>
                    $encryption,

                'username' =>
                    $account
                        ->smtpUsernameValue(),

                'password' =>
                    $account
                        ->smtpPasswordValue(),

                'timeout' =>
                    30,

                'local_domain' =>
                    parse_url(
                        (string) config(
                            'app.url'
                        ),
                        PHP_URL_HOST
                    ),
            ]
        );

        Mail::purge(
            $mailerName
        );
    }

    private function normalizeAddresses(
        array $addresses
    ): array {
        $result = [];
        $seen = [];

        foreach ($addresses as $address) {
            if (is_string($address)) {
                $email =
                    trim(
                        $address
                    );

                $name = null;
            } else {
                $email =
                    trim(
                        (string) (
                            $address['email']
                            ?? ''
                        )
                    );

                $name =
                    trim(
                        (string) (
                            $address['name']
                            ?? ''
                        )
                    )
                        ?: null;
            }

            if (
                $email === ''
                || ! filter_var(
                    $email,
                    FILTER_VALIDATE_EMAIL
                )
            ) {
                continue;
            }

            $email =
                strtolower(
                    $email
                );

            if (
                isset(
                    $seen[$email]
                )
            ) {
                continue;
            }

            $seen[$email] = true;

            $result[] = [
                'name' =>
                    $name,

                'email' =>
                    $email,
            ];
        }

        return $result;
    }

    private function messageIdHeader(
        string $messageId
    ): string {
        $messageId =
            trim(
                $messageId
            );

        if (
            str_starts_with(
                $messageId,
                '<'
            )
            && str_ends_with(
                $messageId,
                '>'
            )
        ) {
            return $messageId;
        }

        return '<'
            .trim(
                $messageId,
                '<>'
            )
            .'>';
    }
}
