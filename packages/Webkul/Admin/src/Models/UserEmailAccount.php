<?php

namespace Webkul\Admin\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Throwable;

class UserEmailAccount extends Model
{
    protected $table = 'user_email_accounts';

    protected $guarded = [];

    protected $hidden = [
        'imap_password',
        'smtp_password',
    ];

    protected $casts = [
        'imap_validate_certificate' => 'boolean',
        'sync_enabled' => 'boolean',
        'last_tested_at' => 'datetime',
        'last_synced_at' => 'datetime',
    ];

    public function setImapPasswordAttribute(
        mixed $value
    ): void {
        if (
            $value === null
            || $value === ''
        ) {
            return;
        }

        $this->attributes['imap_password'] =
            Crypt::encryptString(
                (string) $value
            );
    }

    public function getImapPasswordAttribute(
        mixed $value
    ): ?string {
        if (
            $value === null
            || $value === ''
        ) {
            return null;
        }

        try {
            return Crypt::decryptString(
                (string) $value
            );
        } catch (Throwable) {
            return null;
        }
    }

    public function setSmtpPasswordAttribute(
        mixed $value
    ): void {
        if (
            $value === null
            || $value === ''
        ) {
            return;
        }

        $this->attributes['smtp_password'] =
            Crypt::encryptString(
                (string) $value
            );
    }

    public function getSmtpPasswordAttribute(
        mixed $value
    ): ?string {
        if (
            $value === null
            || $value === ''
        ) {
            return null;
        }

        try {
            return Crypt::decryptString(
                (string) $value
            );
        } catch (Throwable) {
            return null;
        }
    }

    public function smtpUsernameValue(): string
    {
        return trim(
            (string) (
                $this->smtp_username
                ?: $this->imap_username
            )
        );
    }

    public function smtpPasswordValue(): ?string
    {
        return $this->smtp_password
            ?: $this->imap_password;
    }
}
