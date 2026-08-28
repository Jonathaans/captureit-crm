<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_items', function (Blueprint $table) {
            $table->increments('id');

            $table->string('code', 50)->unique();
            $table->string('name');
            $table->string('category', 100)->nullable();
            $table->text('description')->nullable();

            $table->string('tracking_type', 20)->default('serialized');
            $table->string('unit', 30)->default('unit');

            $table->decimal('quantity_on_hand', 15, 2)->default(0);
            $table->decimal('minimum_stock', 15, 2)->default(0);

            $table->unsignedInteger('warehouse_id');
            $table->unsignedInteger('warehouse_location_id')->nullable();

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index('tracking_type');
            $table->index('category');
            $table->index(['warehouse_id', 'is_active']);

            $table->foreign('warehouse_id')
                ->references('id')
                ->on('warehouses')
                ->onDelete('restrict');

            $table->foreign('warehouse_location_id')
                ->references('id')
                ->on('warehouse_locations')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_items');
    }
};
