<?php

namespace Webkul\Admin\Models;

use Illuminate\Database\Eloquent\Model;

class InternalConversationMember extends Model
{
    protected $table =
        'internal_conversation_members';

    protected $guarded = [];

    protected $casts = [
        'joined_at' => 'datetime',
        'last_read_at' => 'datetime',
    ];
}
