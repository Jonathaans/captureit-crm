<?php

namespace Webkul\Admin\Models;

use Illuminate\Database\Eloquent\Model;

class InternalConversation extends Model
{
    protected $table =
        'internal_conversations';

    protected $guarded = [];

    public function members()
    {
        return $this->hasMany(
            InternalConversationMember::class,
            'conversation_id'
        );
    }

    public function messages()
    {
        return $this->hasMany(
            InternalMessage::class,
            'conversation_id'
        );
    }
}
