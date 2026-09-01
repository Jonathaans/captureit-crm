<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('user_email_messages')) {
            return;
        }

        Schema::table(
            'user_email_messages',
            function (Blueprint $table) {
                if (
                    ! Schema::hasColumn(
                        'user_email_messages',
                        'reply_to_message_id'
                    )
                ) {
                    $table->unsignedInteger('reply_to_message_id')
                        ->nullable()
                        ->index();
                }

                if (
                    ! Schema::hasColumn(
                        'user_email_messages',
                        'in_reply_to'
                    )
                ) {
                    $table->string('in_reply_to', 500)
                        ->nullable()
                        ->index();
                }

                if (
                    ! Schema::hasColumn(
                        'user_email_messages',
                        'references_header'
                    )
                ) {
                    $table->text('references_header')
                        ->nullable();
                }

                if (
                    ! Schema::hasColumn(
                        'user_email_messages',
                        'sent_at'
                    )
                ) {
                    $table->timestamp('sent_at')
                        ->nullable()
                        ->index();
                }
            }
        );
    }

    public function down(): void
    {
        if (! Schema::hasTable('user_email_messages')) {
            return;
        }

        Schema::table(
            'user_email_messages',
            function (Blueprint $table) {
                foreach (
                    [
                        'reply_to_message_id',
                        'in_reply_to',
                        'references_header',
                        'sent_at',
                    ]
                    as $column
                ) {
                    if (
                        Schema::hasColumn(
                            'user_email_messages',
                            $column
                        )
                    ) {
                        $table->dropColumn(
                            $column
                        );
                    }
                }
            }
        );
    }
};
