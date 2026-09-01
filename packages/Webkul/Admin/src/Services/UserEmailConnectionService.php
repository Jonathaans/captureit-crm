<?php

namespace Webkul\Admin\Services;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Throwable;
use Webkul\Admin\Models\UserEmailAccount;

class UserEmailConnectionService
{
    public function testImap(
        UserEmailAccount $account
    ): void {
        $stream =
            $this->openImap(
                $account,
                'INBOX',
                true
            );

        try {
            $check =
                imap_check(
                    $stream
                );

            if (! $check) {
                throw new RuntimeException(
                    'IMAP connected, tetapi mailbox check gagal.'
                );
            }
        } finally {
            imap_close(
                $stream
            );
        }

        $account->update([
            'imap_status' => 'connected',
            'last_tested_at' => now(),
            'last_sync_error' => null,
        ]);
    }

    public function testSmtp(
        UserEmailAccount $account
    ): void {
        $mailerName =
            'user_email_test_'
            .$account->id
            .'_'
            .bin2hex(
                random_bytes(4)
            );

        $encryption =
            strtolower(
                trim(
                    (string) $account
                        ->smtp_encryption
                )
            );

        if (
            ! in_array(
                $encryption,
                [
                    'ssl',
                    'tls',
                    'starttls',
                    'none',
                ],
                true
            )
        ) {
            throw new RuntimeException(
                'SMTP encryption tidak valid.'
            );
        }

        if (
            $encryption === 'starttls'
        ) {
            $encryption = 'tls';
        }

        if (
            $encryption === 'none'
        ) {
            $encryption = null;
        }

        Config::set(
            'mail.mailers.'
            .$mailerName,
            [
                'transport' => 'smtp',
                'host' => $account->smtp_host,
                'port' => (int) $account->smtp_port,
                'encryption' => $encryption,
                'username' => $account
                    ->smtpUsernameValue(),
                'password' => $account
                    ->smtpPasswordValue(),
                'timeout' => 20,
                'local_domain' => parse_url(
                    (string) config(
                        'app.url'
                    ),
                    PHP_URL_HOST
                ),
            ]
        );

        try {
            Mail::purge(
                $mailerName
            );

            Mail::mailer(
                $mailerName
            )->raw(
                'SMTP connection test from CRM. '
                .'If you receive this message, the per-user SMTP connection is working.',
                function ($message) use ($account) {
                    $message
                        ->from(
                            $account->email_address
                        )
                        ->to(
                            $account->email_address
                        )
                        ->subject(
                            '[CRM] SMTP Connection Test'
                        );
                }
            );

            $account->update([
                'smtp_status' => 'connected',
                'last_tested_at' => now(),
                'last_sync_error' => null,
            ]);
        } catch (Throwable $exception) {
            $account->update([
                'smtp_status' => 'error',
                'last_tested_at' => now(),
                'last_sync_error' =>
                    $exception->getMessage(),
            ]);

            throw $exception;
        } finally {
            Mail::purge(
                $mailerName
            );
        }
    }

    public function openImap(
        UserEmailAccount $account,
        string $folder = 'INBOX',
        bool $halfOpen = false
    ) {
        if (
            ! function_exists(
                'imap_open'
            )
        ) {
            throw new RuntimeException(
                'PHP IMAP extension belum aktif. '
                .'Aktifkan ext-imap pada PHP CLI dan web server.'
            );
        }

        $mailbox =
            $this->mailboxString(
                $account,
                $folder
            );

        imap_timeout(
            IMAP_OPENTIMEOUT,
            15
        );

        imap_timeout(
            IMAP_READTIMEOUT,
            30
        );

        $flags =
            $halfOpen
                ? OP_HALFOPEN
                : 0;

        $stream =
            @imap_open(
                $mailbox,
                $account->imap_username,
                (string) $account->imap_password,
                $flags,
                1
            );

        if (! $stream) {
            $error =
                imap_last_error()
                ?: 'Unknown IMAP error.';

            $account->update([
                'imap_status' => 'error',
                'last_tested_at' => now(),
                'last_sync_error' => $error,
            ]);

            throw new RuntimeException(
                'IMAP connection failed: '
                .$error
            );
        }

        return $stream;
    }

    public function mailboxString(
        UserEmailAccount $account,
        string $folder = 'INBOX'
    ): string {
        $encryption =
            strtolower(
                trim(
                    (string) $account
                        ->imap_encryption
                )
            );

        $flags = [
            'imap',
        ];

        if ($encryption === 'ssl') {
            $flags[] = 'ssl';
        } elseif (
            $encryption === 'tls'
        ) {
            $flags[] = 'tls';
        } elseif (
            $encryption === 'none'
        ) {
            $flags[] = 'notls';
        }

        if (
            ! $account
                ->imap_validate_certificate
        ) {
            $flags[] =
                'novalidate-cert';
        }

        return sprintf(
            '{%s:%d/%s}%s',
            $account->imap_host,
            (int) $account->imap_port,
            implode(
                '/',
                $flags
            ),
            $folder
        );
    }
}
