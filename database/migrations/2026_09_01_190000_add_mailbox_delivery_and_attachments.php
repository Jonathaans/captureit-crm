<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('user_email_messages')) {
            Schema::table(
                'user_email_messages',
                function (Blueprint $table) {
                    if (! Schema::hasColumn('user_email_messages', 'delivery_status')) {
                        $table->string('delivery_status', 30)
                            ->nullable()
                            ->index();
                    }

                    if (! Schema::hasColumn('user_email_messages', 'delivery_error')) {
                        $table->text('delivery_error')
                            ->nullable();
                    }

                    if (! Schema::hasColumn('user_email_messages', 'delivery_attempts')) {
                        $table->unsignedInteger('delivery_attempts')
                            ->default(0);
                    }

                    if (! Schema::hasColumn('user_email_messages', 'failed_at')) {
                        $table->timestamp('failed_at')
                            ->nullable()
                            ->index();
                    }

                    if (! Schema::hasColumn('user_email_messages', 'original_folder')) {
                        $table->string('original_folder', 120)
                            ->nullable();
                    }
                }
            );
        }

        if (! Schema::hasTable('user_email_attachments')) {
            Schema::create(
                'user_email_attachments',
                function (Blueprint $table) {
                    $table->increments('id');
                    $table->unsignedInteger('user_id')->index();
                    $table->unsignedInteger('message_id')->index();
                    $table->string('direction', 20)->default('incoming');
                    $table->string('original_name', 500);
                    $table->string('mime_type', 255)->nullable();
                    $table->unsignedBigInteger('size')->default(0);
                    $table->string('storage_path', 1000);
                    $table->string('disposition', 30)->nullable();
                    $table->string('content_id', 500)->nullable();
                    $table->timestamps();

                    $table->index(
                        ['user_id', 'message_id'],
                        'user_email_attachments_user_message_idx'
                    );
                }
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_email_attachments');

        if (! Schema::hasTable('user_email_messages')) {
            return;
        }

        Schema::table(
            'user_email_messages',
            function (Blueprint $table) {
                foreach (
                    [
                        'delivery_status',
                        'delivery_error',
                        'delivery_attempts',
                        'failed_at',
                        'original_folder',
                    ]
                    as $column
                ) {
                    if (Schema::hasColumn('user_email_messages', $column)) {
                        $table->dropColumn($column);
                    }
                }
            }
        );
    }
};
