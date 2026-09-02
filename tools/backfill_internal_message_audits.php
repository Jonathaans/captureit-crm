<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

require __DIR__.'/../vendor/autoload.php';

$app =
    require_once __DIR__.'/../bootstrap/app.php';

$app->make(
    Kernel::class
)->bootstrap();

echo "INTERNAL MESSAGE AUDIT BACKFILL\n";
echo "===============================\n\n";

if (
    ! Schema::hasTable(
        'internal_message_audits'
    )
) {
    echo "FAIL\n";
    echo " - internal_message_audits table missing. Run php artisan migrate first.\n";
    exit(1);
}

$inserted = 0;

DB::table(
    'internal_messages'
)
    ->orderBy(
        'id'
    )
    ->chunkById(
        200,
        function ($messages) use (&$inserted) {
            foreach ($messages as $message) {
                $hasAny =
                    DB::table(
                        'internal_message_audits'
                    )
                        ->where(
                            'message_id',
                            $message->id
                        )
                        ->exists();

                if (! $hasAny) {
                    DB::table(
                        'internal_message_audits'
                    )
                        ->insert([
                            'message_id' =>
                                $message->id,

                            'conversation_id' =>
                                $message
                                    ->conversation_id,

                            'message_user_id' =>
                                $message->user_id,

                            'actor_user_id' =>
                                $message->user_id,

                            'action' =>
                                'created',

                            'old_body' =>
                                null,

                            'new_body' =>
                                $message->body,

                            'old_deleted_at' =>
                                null,

                            'new_deleted_at' =>
                                null,

                            'meta' =>
                                json_encode([
                                    'legacy_backfill' =>
                                        true,

                                    'history_note' =>
                                        'Created snapshot. Exact historical revision sequence before audit installation may be unavailable.',
                                ]),

                            'created_at' =>
                                $message
                                    ->created_at
                                ?? now(),

                            'updated_at' =>
                                $message
                                    ->created_at
                                ?? now(),
                        ]);

                    $inserted++;
                }

                if (
                    ! empty(
                        $message->edited_at
                    )
                    && ! DB::table(
                        'internal_message_audits'
                    )
                        ->where(
                            'message_id',
                            $message->id
                        )
                        ->where(
                            'action',
                            'edited'
                        )
                        ->exists()
                ) {
                    DB::table(
                        'internal_message_audits'
                    )
                        ->insert([
                            'message_id' =>
                                $message->id,

                            'conversation_id' =>
                                $message
                                    ->conversation_id,

                            'message_user_id' =>
                                $message->user_id,

                            'actor_user_id' =>
                                $message->user_id,

                            'action' =>
                                'edited',

                            'old_body' =>
                                null,

                            'new_body' =>
                                $message->body,

                            'old_deleted_at' =>
                                null,

                            'new_deleted_at' =>
                                null,

                            'meta' =>
                                json_encode([
                                    'legacy_backfill' =>
                                        true,

                                    'history_note' =>
                                        'Previous body cannot be reconstructed because this edit occurred before immutable audit history was installed.',
                                ]),

                            'created_at' =>
                                $message
                                    ->edited_at,

                            'updated_at' =>
                                $message
                                    ->edited_at,
                        ]);

                    $inserted++;
                }

                if (
                    ! empty(
                        $message->deleted_at
                    )
                    && ! DB::table(
                        'internal_message_audits'
                    )
                        ->where(
                            'message_id',
                            $message->id
                        )
                        ->where(
                            'action',
                            'deleted'
                        )
                        ->exists()
                ) {
                    DB::table(
                        'internal_message_audits'
                    )
                        ->insert([
                            'message_id' =>
                                $message->id,

                            'conversation_id' =>
                                $message
                                    ->conversation_id,

                            'message_user_id' =>
                                $message->user_id,

                            'actor_user_id' =>
                                $message->user_id,

                            'action' =>
                                'deleted',

                            'old_body' =>
                                $message->body,

                            'new_body' =>
                                $message->body,

                            'old_deleted_at' =>
                                null,

                            'new_deleted_at' =>
                                $message
                                    ->deleted_at,

                            'meta' =>
                                json_encode([
                                    'legacy_backfill' =>
                                        true,

                                    'history_note' =>
                                        'Deleted snapshot reconstructed from the retained soft-deleted message row.',
                                ]),

                            'created_at' =>
                                $message
                                    ->deleted_at,

                            'updated_at' =>
                                $message
                                    ->deleted_at,
                        ]);

                    $inserted++;
                }
            }
        },
        'id'
    );

echo "PASS\n";
echo " - Audit snapshots inserted: {$inserted}\n";
