<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('financial_period_locks')) {
            Schema::create(
                'financial_period_locks',
                function (Blueprint $table) {
                    $table->increments('id');
                    $table->string('period', 7)->unique();
                    $table->date('starts_at');
                    $table->date('ends_at');
                    $table->unsignedInteger('locked_by')->nullable();
                    $table->string('locked_by_name', 255)->nullable();
                    $table->timestamp('locked_at')->nullable();
                    $table->text('notes')->nullable();
                    $table->timestamps();

                    $table->index(['starts_at', 'ends_at']);
                }
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'financial_period_locks'
        );
    }
};
