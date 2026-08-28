<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_order_inventory_allocations', function (Blueprint $table) {
            $table->increments('id');

            $table->unsignedInteger('delivery_order_id')->index();
            $table->unsignedInteger('delivery_order_item_id')->index();

            $table->unsignedInteger('inventory_item_id')->index();
            $table->unsignedInteger('inventory_asset_id')->nullable()->index();

            $table->string('tracking_type', 20);
            $table->decimal('quantity', 12, 2)->default(1);

            $table->string('status', 30)->default('allocated')->index();

            $table->unsignedInteger('allocated_by')->nullable()->index();
            $table->timestamp('allocated_at')->nullable();

            $table->unsignedInteger('released_by')->nullable()->index();
            $table->timestamp('released_at')->nullable();

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(
                ['delivery_order_id', 'status'],
                'do_inventory_allocations_do_status_idx'
            );

            $table->index(
                ['inventory_item_id', 'status'],
                'do_inventory_allocations_item_status_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_order_inventory_allocations');
    }
};
