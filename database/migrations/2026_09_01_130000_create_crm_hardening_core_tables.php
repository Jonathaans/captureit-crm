<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('crm_audit_logs')) {
            Schema::create(
                'crm_audit_logs',
                function (Blueprint $table) {
                    $table->increments('id');
                    $table->unsignedInteger('user_id')->nullable();
                    $table->string('user_name', 255)->nullable();
                    $table->string('action', 30);
                    $table->string('model_type', 255);
                    $table->string('table_name', 120);
                    $table->string('record_id', 120)->nullable();
                    $table->string('route_name', 255)->nullable();
                    $table->text('url')->nullable();
                    $table->string('ip_address', 64)->nullable();
                    $table->string('user_agent', 500)->nullable();
                    $table->longText('old_values')->nullable();
                    $table->longText('new_values')->nullable();
                    $table->timestamp('created_at')->useCurrent();

                    $table->index(['table_name', 'record_id']);
                    $table->index(['user_id', 'created_at']);
                    $table->index(['action', 'created_at']);
                }
            );
        }

        if (! Schema::hasTable('crm_system_incidents')) {
            Schema::create(
                'crm_system_incidents',
                function (Blueprint $table) {
                    $table->increments('id');
                    $table->string('fingerprint', 64)->unique();
                    $table->string('level', 20)->default('error');
                    $table->text('message');
                    $table->longText('context')->nullable();
                    $table->string('file', 500)->nullable();
                    $table->unsignedInteger('line')->nullable();
                    $table->string('route_name', 255)->nullable();
                    $table->text('url')->nullable();
                    $table->unsignedInteger('user_id')->nullable();
                    $table->unsignedInteger('occurrence_count')->default(1);
                    $table->timestamp('first_seen_at')->nullable();
                    $table->timestamp('last_seen_at')->nullable();
                    $table->timestamp('resolved_at')->nullable();
                    $table->timestamps();

                    $table->index(['level', 'last_seen_at']);
                    $table->index(['resolved_at', 'last_seen_at']);
                }
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_system_incidents');
        Schema::dropIfExists('crm_audit_logs');
    }
};
