<?php

namespace Webkul\Admin\Observers;

use Illuminate\Support\Facades\Schema;
use Webkul\Admin\Models\InternalMessage;
use Webkul\Admin\Models\InternalMessageAudit;

class InternalMessageAuditObserver
{
    /**
     * Update contexts are held only between updating() and updated() for the
     * same PHP request. This lets us write the audit only after the message
     * update succeeded.
     */
    private static array $pending =
        [];

    public function created(
        InternalMessage $message
    ): void {
        if (! $this->auditTableReady()) {
            return;
        }

        InternalMessageAudit::query()
            ->create([
                'message_id' =>
                    $message->id,

                'conversation_id' =>
                    $message->conversation_id,

                'message_user_id' =>
                    $message->user_id,

                'actor_user_id' =>
                    $this->actorUserId(
                        $message
                    ),

                'action' =>
                    'created',

                'old_body' =>
                    null,

                'new_body' =>
                    $message->body,

                'old_deleted_at' =>
                    null,

                'new_deleted_at' =>
                    $message->deleted_at,

                'meta' => [
                    'reply_to_message_id' =>
                        $message
                            ->reply_to_message_id,
                ],
            ]);
    }

    public function updating(
        InternalMessage $message
    ): void {
        if (! $this->auditTableReady()) {
            return;
        }

        $events = [];

        if ($message->isDirty('body')) {
            $events[] = [
                'action' =>
                    'edited',

                'old_body' =>
                    $message->getRawOriginal(
                        'body'
                    ),

                'new_body' =>
                    $message->body,

                'old_deleted_at' =>
                    $message->getRawOriginal(
                        'deleted_at'
                    ),

                'new_deleted_at' =>
                    $message->deleted_at,

                'meta' => [
                    'reply_to_message_id' =>
                        $message
                            ->reply_to_message_id,
                ],
            ];
        }

        if (
            $message->isDirty(
                'deleted_at'
            )
            && empty(
                $message->getRawOriginal(
                    'deleted_at'
                )
            )
            && ! empty(
                $message->deleted_at
            )
        ) {
            $events[] = [
                'action' =>
                    'deleted',

                'old_body' =>
                    $message->getRawOriginal(
                        'body'
                    ),

                'new_body' =>
                    $message->body,

                'old_deleted_at' =>
                    null,

                'new_deleted_at' =>
                    $message->deleted_at,

                'meta' => [
                    'reply_to_message_id' =>
                        $message
                            ->reply_to_message_id,
                ],
            ];
        }

        if ($events === []) {
            return;
        }

        self::$pending[
            spl_object_id(
                $message
            )
        ] = $events;
    }

    public function updated(
        InternalMessage $message
    ): void {
        if (! $this->auditTableReady()) {
            return;
        }

        $key =
            spl_object_id(
                $message
            );

        $events =
            self::$pending[
                $key
            ]
            ?? [];

        unset(
            self::$pending[
                $key
            ]
        );

        foreach ($events as $event) {
            InternalMessageAudit::query()
                ->create([
                    'message_id' =>
                        $message->id,

                    'conversation_id' =>
                        $message
                            ->conversation_id,

                    'message_user_id' =>
                        $message
                            ->user_id,

                    'actor_user_id' =>
                        $this->actorUserId(
                            $message
                        ),

                    'action' =>
                        $event['action'],

                    'old_body' =>
                        $event['old_body'],

                    'new_body' =>
                        $event['new_body'],

                    'old_deleted_at' =>
                        $event[
                            'old_deleted_at'
                        ],

                    'new_deleted_at' =>
                        $event[
                            'new_deleted_at'
                        ],

                    'meta' =>
                        $event['meta'],
                ]);
        }
    }

    private function actorUserId(
        InternalMessage $message
    ): ?int {
        $user =
            auth()
                ->guard('user')
                ->user();

        return $user
            ? (int) $user->id
            : (
                $message->user_id
                    ? (int) $message->user_id
                    : null
            );
    }

    private function auditTableReady(): bool
    {
        try {
            return Schema::hasTable(
                'internal_message_audits'
            );
        } catch (\Throwable) {
            return false;
        }
    }
}
