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
        if (
            ! function_exists(
                'stream_socket_client'
            )
        ) {
            throw new RuntimeException(
                'PHP stream_socket_client tidak tersedia.'
            );
        }

        if (
            strtolower(
                (string) $account->imap_encryption
            ) !== 'none'
            && ! extension_loaded(
                'openssl'
            )
        ) {
            throw new RuntimeException(
                'OpenSSL extension belum aktif.'
            );
        }

        $client =
            app(
                PurePhpImapClient::class
            );

        try {
            $client->connect(
                $account,
                'INBOX'
            );

            $account->update([
                'imap_status' =>
                    'connected',

                'last_tested_at' =>
                    now(),

                'last_sync_error' =>
                    null,
            ]);
        } catch (Throwable $exception) {
            $account->update([
                'imap_status' =>
                    'error',

                'last_tested_at' =>
                    now(),

                'last_sync_error' =>
                    $exception->getMessage(),
            ]);

            throw $exception;
        } finally {
            $client->disconnect();
        }
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
}
