<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            Schema::hasTable(
                'internal_conversation_members'
            )
        ) {
            Schema::table(
                'internal_conversation_members',
                function (Blueprint $table) {
                    if (
                        ! Schema::hasColumn(
                            'internal_conversation_members',
                            'pinned_at'
                        )
                    ) {
                        $table->timestamp(
                            'pinned_at'
                        )
                            ->nullable()
                            ->after(
                                'last_read_at'
                            )
                            ->index();
                    }

                    if (
                        ! Schema::hasColumn(
                            'internal_conversation_members',
                            'muted_until'
                        )
                    ) {
                        $table->timestamp(
                            'muted_until'
                        )
                            ->nullable()
                            ->after(
                                'pinned_at'
                            )
                            ->index();
                    }

                    if (
                        ! Schema::hasColumn(
                            'internal_conversation_members',
                            'mute_forever'
                        )
                    ) {
                        $table->boolean(
                            'mute_forever'
                        )
                            ->default(
                                false
                            )
                            ->after(
                                'muted_until'
                            );
                    }
                }
            );
        }

        if (
            ! Schema::hasTable(
                'internal_chat_user_states'
            )
        ) {
            Schema::create(
                'internal_chat_user_states',
                function (Blueprint $table) {
                    $table->increments(
                        'id'
                    );

                    $table->unsignedInteger(
                        'user_id'
                    )
                        ->unique();

                    $table->timestamp(
                        'last_seen_at'
                    )
                        ->nullable()
                        ->index();

                    $table->timestamps();
                }
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'internal_chat_user_states'
        );

        if (
            Schema::hasTable(
                'internal_conversation_members'
            )
        ) {
            $columns = [];

            foreach (
                [
                    'pinned_at',
                    'muted_until',
                    'mute_forever',
                ]
                as $column
            ) {
                if (
                    Schema::hasColumn(
                        'internal_conversation_members',
                        $column
                    )
                ) {
                    $columns[] =
                        $column;
                }
            }

            if ($columns !== []) {
                Schema::table(
                    'internal_conversation_members',
                    function (Blueprint $table) use ($columns) {
                        $table->dropColumn(
                            $columns
                        );
                    }
                );
            }
        }
    }
};
