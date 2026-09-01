<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * Dedicated workflow notification table.
         *
         * We intentionally do NOT alter a legacy crm_notifications table because
         * its exact required columns may differ across this customized CRM.
         * This module remains isolated and safe.
         */
        if (! Schema::hasTable('crm_workflow_notifications')) {
            Schema::create(
                'crm_workflow_notifications',
                function (Blueprint $table) {
                    $table->increments('id');

                    $table->unsignedInteger('user_id')
                        ->index();

                    $table->string('type', 80)
                        ->index();

                    $table->string('title', 255);

                    $table->text('message')
                        ->nullable();

                    $table->string('action_url', 1000)
                        ->nullable();

                    $table->string('source_type', 80)
                        ->nullable()
                        ->index();

                    $table->unsignedInteger('source_id')
                        ->nullable()
                        ->index();

                    $table->string('dedupe_key', 191);

                    $table->json('meta')
                        ->nullable();

                    $table->timestamp('read_at')
                        ->nullable()
                        ->index();

                    $table->timestamp('popup_at')
                        ->nullable()
                        ->index();

                    $table->timestamps();

                    $table->unique(
                        [
                            'user_id',
                            'dedupe_key',
                        ],
                        'workflow_notifications_user_dedupe_unique'
                    );

                    $table->index(
                        [
                            'user_id',
                            'read_at',
                            'created_at',
                        ],
                        'workflow_notifications_user_unread_idx'
                    );
                }
            );
        }

        if (! Schema::hasTable('internal_conversations')) {
            Schema::create(
                'internal_conversations',
                function (Blueprint $table) {
                    $table->increments('id');

                    $table->string('type', 20)
                        ->default('direct')
                        ->index();

                    /*
                     * For direct chat only:
                     * lower_user_id:higher_user_id
                     */
                    $table->string('direct_key', 100)
                        ->nullable()
                        ->unique();

                    $table->string('name', 255)
                        ->nullable();

                    $table->unsignedInteger('created_by')
                        ->nullable()
                        ->index();

                    $table->timestamps();
                }
            );
        }

        if (! Schema::hasTable('internal_conversation_members')) {
            Schema::create(
                'internal_conversation_members',
                function (Blueprint $table) {
                    $table->increments('id');

                    $table->unsignedInteger('conversation_id')
                        ->index();

                    $table->unsignedInteger('user_id')
                        ->index();

                    $table->timestamp('joined_at')
                        ->nullable();

                    $table->timestamp('last_read_at')
                        ->nullable()
                        ->index();

                    $table->timestamps();

                    $table->unique(
                        [
                            'conversation_id',
                            'user_id',
                        ],
                        'internal_conv_members_unique'
                    );
                }
            );
        }

        if (! Schema::hasTable('internal_messages')) {
            Schema::create(
                'internal_messages',
                function (Blueprint $table) {
                    $table->increments('id');

                    $table->unsignedInteger('conversation_id')
                        ->index();

                    $table->unsignedInteger('user_id')
                        ->index();

                    $table->unsignedInteger('reply_to_message_id')
                        ->nullable()
                        ->index();

                    $table->longText('body')
                        ->nullable();

                    $table->timestamp('edited_at')
                        ->nullable();

                    $table->timestamp('deleted_at')
                        ->nullable()
                        ->index();

                    $table->timestamps();

                    $table->index(
                        [
                            'conversation_id',
                            'id',
                        ],
                        'internal_messages_conversation_id_idx'
                    );
                }
            );
        }

        if (! Schema::hasTable('internal_message_attachments')) {
            Schema::create(
                'internal_message_attachments',
                function (Blueprint $table) {
                    $table->increments('id');

                    $table->unsignedInteger('message_id')
                        ->index();

                    $table->unsignedInteger('user_id')
                        ->index();

                    $table->string('original_name', 500);

                    $table->string('mime_type', 255)
                        ->nullable();

                    $table->unsignedBigInteger('size')
                        ->default(0);

                    $table->string('storage_path', 1000);

                    $table->timestamps();
                }
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'internal_message_attachments'
        );

        Schema::dropIfExists(
            'internal_messages'
        );

        Schema::dropIfExists(
            'internal_conversation_members'
        );

        Schema::dropIfExists(
            'internal_conversations'
        );

        Schema::dropIfExists(
            'crm_workflow_notifications'
        );
    }
};
