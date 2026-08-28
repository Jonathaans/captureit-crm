<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_order_items', function (Blueprint $table) {
            $table->unsignedInteger('inventory_item_id')
                ->nullable()
                ->after('product_id');

            $table->index('inventory_item_id');

            $table->foreign('inventory_item_id')
                ->references('id')
                ->on('inventory_items')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('delivery_order_items', function (Blueprint $table) {
            $table->dropForeign(['inventory_item_id']);
            $table->dropIndex(['inventory_item_id']);
            $table->dropColumn('inventory_item_id');
        });
    }
};
