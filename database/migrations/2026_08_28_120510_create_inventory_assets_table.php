<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_assets', function (Blueprint $table) {
            $table->increments('id');

            $table->unsignedInteger('inventory_item_id');

            $table->string('asset_code', 50)->unique();
            $table->string('barcode_value', 100)->nullable()->unique();
            $table->string('serial_number', 150)->nullable();

            $table->unsignedInteger('warehouse_id');
            $table->unsignedInteger('warehouse_location_id')->nullable();

            $table->string('status', 30)->default('available');
            $table->string('condition', 30)->default('good');

            $table->date('purchase_date')->nullable();
            $table->decimal('purchase_price', 15, 2)->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index('serial_number');
            $table->index(['inventory_item_id', 'status']);
            $table->index(['warehouse_id', 'status']);
            $table->index('condition');

            $table->foreign('inventory_item_id')
                ->references('id')
                ->on('inventory_items')
                ->onDelete('restrict');

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
        Schema::dropIfExists('inventory_assets');
    }
};
