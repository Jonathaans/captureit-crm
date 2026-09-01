<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('vendors')) {
            Schema::create(
                'vendors',
                function (Blueprint $table) {
                    $table->increments('id');
                    $table->string('name', 255);
                    $table->string('normalized_name', 255)->unique();
                    $table->string('npwp', 100)->nullable();
                    $table->string('pic_name', 255)->nullable();
                    $table->string('phone', 100)->nullable();
                    $table->string('email', 255)->nullable();
                    $table->text('address')->nullable();
                    $table->string('bank_name', 255)->nullable();
                    $table->string('bank_account_name', 255)->nullable();
                    $table->string('bank_account_number', 255)->nullable();
                    $table->string('payment_terms', 100)->nullable();
                    $table->text('notes')->nullable();
                    $table->boolean('is_active')->default(true);
                    $table->timestamps();

                    $table->index('is_active');
                    $table->index('email');
                }
            );
        }

        if (
            Schema::hasTable('purchase_orders')
            && ! Schema::hasColumn(
                'purchase_orders',
                'vendor_id'
            )
        ) {
            Schema::table(
                'purchase_orders',
                function (Blueprint $table) {
                    $table->unsignedInteger('vendor_id')
                        ->nullable()
                        ->index();
                }
            );
        }

        if (! Schema::hasTable('crm_notifications')) {
            Schema::create(
                'crm_notifications',
                function (Blueprint $table) {
                    $table->increments('id');
                    $table->unsignedInteger('user_id')->nullable();
                    $table->string('type', 80);
                    $table->string('severity', 20)->default('info');
                    $table->string('title', 255);
                    $table->text('message')->nullable();
                    $table->string('action_url', 1000)->nullable();
                    $table->string('source_type', 120)->nullable();
                    $table->string('source_id', 120)->nullable();
                    $table->string('dedupe_key', 255)->unique();
                    $table->timestamp('due_at')->nullable();
                    $table->timestamp('read_at')->nullable();
                    $table->timestamp('resolved_at')->nullable();
                    $table->timestamps();

                    $table->index(['user_id', 'read_at']);
                    $table->index(['severity', 'due_at']);
                    $table->index(['source_type', 'source_id']);
                }
            );
        }

        if (! Schema::hasTable('jobs')) {
            Schema::create(
                'jobs',
                function (Blueprint $table) {
                    $table->bigIncrements('id');
                    $table->string('queue')->index();
                    $table->longText('payload');
                    $table->unsignedTinyInteger('attempts');
                    $table->unsignedInteger('reserved_at')->nullable();
                    $table->unsignedInteger('available_at');
                    $table->unsignedInteger('created_at');
                }
            );
        }

        if (! Schema::hasTable('failed_jobs')) {
            Schema::create(
                'failed_jobs',
                function (Blueprint $table) {
                    $table->bigIncrements('id');
                    $table->string('uuid')->unique();
                    $table->text('connection');
                    $table->text('queue');
                    $table->longText('payload');
                    $table->longText('exception');
                    $table->timestamp('failed_at')->useCurrent();
                }
            );
        }
    }

    public function down(): void
    {
        /*
         * jobs / failed_jobs are intentionally retained on rollback because
         * they may be shared by other application queues.
         */
        Schema::dropIfExists('crm_notifications');

        if (
            Schema::hasTable('purchase_orders')
            && Schema::hasColumn(
                'purchase_orders',
                'vendor_id'
            )
        ) {
            Schema::table(
                'purchase_orders',
                function (Blueprint $table) {
                    $table->dropColumn('vendor_id');
                }
            );
        }

        Schema::dropIfExists('vendors');
    }
};
