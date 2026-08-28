<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_stock_movements', function (Blueprint $table) {
            $table->increments('id');

            $table->unsignedInteger('inventory_item_id');
            $table->unsignedInteger('inventory_asset_id')->nullable();

            $table->unsignedInteger('warehouse_id');
            $table->unsignedInteger('warehouse_location_id')->nullable();

            $table->string('movement_type', 40);
            $table->decimal('quantity', 15, 2)->default(1);

            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30)->nullable();

            $table->string('reference_type', 50)->nullable();
            $table->unsignedInteger('reference_id')->nullable();
            $table->string('reference_number', 100)->nullable();

            $table->unsignedInteger('performed_by')->nullable();

            $table->text('notes')->nullable();
            $table->dateTime('occurred_at');

            $table->timestamps();

            $table->index(['inventory_item_id', 'occurred_at']);
            $table->index(['inventory_asset_id', 'occurred_at']);
            $table->index(['warehouse_id', 'occurred_at']);
            $table->index('movement_type');
            $table->index(['reference_type', 'reference_id']);

            $table->foreign('inventory_item_id')
                ->references('id')
                ->on('inventory_items')
                ->onDelete('restrict');

            $table->foreign('inventory_asset_id')
                ->references('id')
                ->on('inventory_assets')
                ->onDelete('restrict');

            $table->foreign('warehouse_id')
                ->references('id')
                ->on('warehouses')
                ->onDelete('restrict');

            $table->foreign('warehouse_location_id')
                ->references('id')
                ->on('warehouse_locations')
                ->nullOnDelete();

            $table->foreign('performed_by')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_stock_movements');
    }
};
