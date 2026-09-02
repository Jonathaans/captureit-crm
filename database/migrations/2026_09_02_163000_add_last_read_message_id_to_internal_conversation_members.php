<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            ! Schema::hasTable(
                'internal_conversation_members'
            )
        ) {
            return;
        }

        if (
            ! Schema::hasColumn(
                'internal_conversation_members',
                'last_read_message_id'
            )
        ) {
            Schema::table(
                'internal_conversation_members',
                function (Blueprint $table) {
                    $table->unsignedInteger(
                        'last_read_message_id'
                    )
                        ->nullable()
                        ->after(
                            'last_read_at'
                        )
                        ->index();
                }
            );
        }

        /*
         * Historical baseline.
         *
         * Existing chats were already visible before this cursor existed.
         * Mark the current maximum message id as the starting read cursor for
         * every member so old messages do not suddenly become "unread".
         */
        DB::table(
            'internal_conversation_members'
        )
            ->orderBy(
                'id'
            )
            ->chunkById(
                200,
                function ($members) {
                    foreach ($members as $member) {
                        $maxMessageId =
                            DB::table(
                                'internal_messages'
                            )
                                ->where(
                                    'conversation_id',
                                    $member->conversation_id
                                )
                                ->whereNull(
                                    'deleted_at'
                                )
                                ->max(
                                    'id'
                                );

                        DB::table(
                            'internal_conversation_members'
                        )
                            ->where(
                                'id',
                                $member->id
                            )
                            ->update([
                                'last_read_message_id' =>
                                    $maxMessageId
                                        ? (int) $maxMessageId
                                        : 0,
                            ]);
                    }
                }
            );
    }

    public function down(): void
    {
        if (
            Schema::hasTable(
                'internal_conversation_members'
            )
            && Schema::hasColumn(
                'internal_conversation_members',
                'last_read_message_id'
            )
        ) {
            Schema::table(
                'internal_conversation_members',
                function (Blueprint $table) {
                    $table->dropColumn(
                        'last_read_message_id'
                    );
                }
            );
        }
    }
};
