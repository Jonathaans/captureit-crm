<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            Schema::hasTable('internal_message_audits')
        ) {
            return;
        }

        Schema::create(
            'internal_message_audits',
            function (Blueprint $table) {
                $table->increments('id');

                $table->unsignedInteger('message_id')
                    ->index();

                $table->unsignedInteger('conversation_id')
                    ->index();

                $table->unsignedInteger('message_user_id')
                    ->index();

                $table->unsignedInteger('actor_user_id')
                    ->nullable()
                    ->index();

                $table->string('action', 30)
                    ->index();

                $table->longText('old_body')
                    ->nullable();

                $table->longText('new_body')
                    ->nullable();

                $table->timestamp('old_deleted_at')
                    ->nullable();

                $table->timestamp('new_deleted_at')
                    ->nullable();

                $table->json('meta')
                    ->nullable();

                $table->timestamps();

                $table->index(
                    [
                        'message_id',
                        'created_at',
                    ],
                    'internal_message_audits_message_time_idx'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'internal_message_audits'
        );
    }
};
