<?php

namespace Webkul\Admin\Models;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class InternalMessageAudit extends Model
{
    protected $table =
        'internal_message_audits';

    protected $guarded = [];

    protected $casts = [
        'old_deleted_at' =>
            'datetime',

        'new_deleted_at' =>
            'datetime',

        'meta' =>
            'array',
    ];

    protected static function booted(): void
    {
        /*
         * Audit records are append-only through Eloquent.
         * The application never edits or deletes a historical audit row.
         */
        static::updating(
            function () {
                throw new RuntimeException(
                    'Internal message audit rows are immutable.'
                );
            }
        );

        static::deleting(
            function () {
                throw new RuntimeException(
                    'Internal message audit rows are immutable.'
                );
            }
        );
    }
}
