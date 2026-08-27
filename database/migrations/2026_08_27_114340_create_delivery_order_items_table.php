<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_order_items', function (Blueprint $table) {
            /*
            |--------------------------------------------------------------------------
            | Primary Key
            |--------------------------------------------------------------------------
            */

            $table->increments('id');

            /*
            |--------------------------------------------------------------------------
            | Delivery Order Reference
            |--------------------------------------------------------------------------
            */

            $table->unsignedInteger('delivery_order_id');

            $table->foreign('delivery_order_id')
                ->references('id')
                ->on('delivery_orders')
                ->onDelete('cascade');

            /*
            |--------------------------------------------------------------------------
            | Product Reference
            |--------------------------------------------------------------------------
            |
            | Product tidak kita beri FK dulu.
            | Dengan begitu histori Surat Jalan tetap aman apabila
            | product master suatu hari dihapus.
            |
            */

            $table->unsignedInteger('product_id')
                ->nullable()
                ->index();

            $table->string('sku')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Equipment Snapshot
            |--------------------------------------------------------------------------
            */

            $table->string('name');

            $table->text('description')->nullable();

            $table->decimal('quantity', 12, 2)
                ->default(1);

            $table->string('unit', 30)
                ->default('unit');

            $table->text('notes')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Sorting
            |--------------------------------------------------------------------------
            */

            $table->unsignedInteger('sort_order')
                ->default(0);

            /*
            |--------------------------------------------------------------------------
            | Laravel Timestamps
            |--------------------------------------------------------------------------
            */

            $table->timestamps();

            /*
            |--------------------------------------------------------------------------
            | Indexes
            |--------------------------------------------------------------------------
            */

            $table->index([
                'delivery_order_id',
                'sort_order',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_order_items');
    }
};