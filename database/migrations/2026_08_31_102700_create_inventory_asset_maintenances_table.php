<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_asset_maintenances', function (Blueprint $table) {
            $table->increments('id');

            $table->unsignedInteger('inventory_asset_id');

            $table->string('reference_number', 40)->nullable()->unique();
            $table->string('status', 30)->default('in_progress');

            $table->text('problem');
            $table->string('technician_name', 150)->nullable();

            $table->text('repair_notes')->nullable();
            $table->decimal('repair_cost', 15, 2)->default(0);
            $table->string('result_condition', 30)->nullable();

            $table->text('retirement_reason')->nullable();

            $table->unsignedInteger('started_by')->nullable();
            $table->timestamp('started_at')->nullable();

            $table->unsignedInteger('completed_by')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->unsignedInteger('retired_by')->nullable();
            $table->timestamp('retired_at')->nullable();

            $table->timestamps();

            $table->index(['inventory_asset_id', 'status']);
            $table->index('started_at');
            $table->index('completed_at');

            $table->foreign('inventory_asset_id')
                ->references('id')
                ->on('inventory_assets')
                ->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_asset_maintenances');
    }
};
