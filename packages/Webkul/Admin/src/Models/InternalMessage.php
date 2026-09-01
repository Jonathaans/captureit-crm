<?php

namespace Webkul\Admin\Models;

use Illuminate\Database\Eloquent\Model;

class InternalMessage extends Model
{
    protected $table =
        'internal_messages';

    protected $guarded = [];

    protected $casts = [
        'edited_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function attachments()
    {
        return $this->hasMany(
            InternalMessageAttachment::class,
            'message_id'
        );
    }
}
