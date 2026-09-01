<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('user_email_accounts')) {
            Schema::create(
                'user_email_accounts',
                function (Blueprint $table) {
                    $table->increments('id');
                    $table->unsignedInteger('user_id')->unique();

                    $table->string('email_address', 255);

                    $table->string('imap_host', 255);
                    $table->unsignedInteger('imap_port')->default(993);
                    $table->string('imap_encryption', 20)->default('ssl');
                    $table->boolean('imap_validate_certificate')->default(true);
                    $table->string('imap_username', 255);
                    $table->longText('imap_password')->nullable();

                    $table->string('smtp_host', 255);
                    $table->unsignedInteger('smtp_port')->default(465);
                    $table->string('smtp_encryption', 20)->default('ssl');
                    $table->string('smtp_username', 255)->nullable();
                    $table->longText('smtp_password')->nullable();

                    $table->boolean('sync_enabled')->default(true);

                    $table->string('imap_status', 30)->default('untested');
                    $table->string('smtp_status', 30)->default('untested');

                    $table->unsignedBigInteger('imap_last_uid')->nullable();
                    $table->timestamp('last_tested_at')->nullable();
                    $table->timestamp('last_synced_at')->nullable();
                    $table->text('last_sync_error')->nullable();

                    $table->timestamps();

                    $table->index('email_address');
                    $table->index('sync_enabled');
                    $table->index('imap_status');
                    $table->index('smtp_status');
                }
            );
        }

        if (! Schema::hasTable('user_email_messages')) {
            Schema::create(
                'user_email_messages',
                function (Blueprint $table) {
                    $table->increments('id');
                    $table->unsignedInteger('user_id')->index();
                    $table->unsignedInteger('account_id')->index();

                    $table->string('folder', 120)->default('INBOX');
                    $table->unsignedBigInteger('imap_uid');
                    $table->string('message_id', 500)->nullable();

                    $table->string('direction', 20)->default('incoming');
                    $table->string('from_name', 255)->nullable();
                    $table->string('from_email', 255)->nullable();
                    $table->text('to_emails')->nullable();
                    $table->text('cc_emails')->nullable();

                    $table->text('subject')->nullable();
                    $table->mediumText('text_body')->nullable();
                    $table->mediumText('html_body')->nullable();

                    $table->timestamp('received_at')->nullable();
                    $table->timestamp('read_at')->nullable();

                    $table->timestamps();

                    $table->unique(
                        [
                            'account_id',
                            'folder',
                            'imap_uid',
                        ],
                        'user_email_messages_account_folder_uid_unique'
                    );

                    $table->index(
                        [
                            'user_id',
                            'received_at',
                        ],
                        'user_email_messages_user_received_idx'
                    );

                    $table->index('from_email');
                }
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_email_messages');
        Schema::dropIfExists('user_email_accounts');
    }
};
