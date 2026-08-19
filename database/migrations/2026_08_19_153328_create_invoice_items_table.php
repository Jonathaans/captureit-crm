<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('invoice_items', function (Blueprint $table) {
            $table->increments('id');

            // Invoice pemilik item
            $table->integer('invoice_id')->unsigned();

            $table->foreign('invoice_id')
                ->references('id')
                ->on('invoices')
                ->onDelete('cascade');

            // Referensi produk.
            // Dibuat nullable karena invoice merupakan snapshot transaksi.
            $table->integer('product_id')->unsigned()->nullable();

            // Snapshot item dari quotation
            $table->string('sku')->nullable();
            $table->string('name')->nullable();

            $table->integer('quantity')->default(0);

            $table->decimal('price', 12, 4)->default(0);

            // Discount
            $table->string('coupon_code')->nullable();

            $table->decimal('discount_percent', 12, 4)
                ->default(0)
                ->nullable();

            $table->decimal('discount_amount', 12, 4)
                ->default(0)
                ->nullable();

            // Tax
            $table->decimal('tax_percent', 12, 4)
                ->default(0)
                ->nullable();

            $table->decimal('tax_amount', 12, 4)
                ->default(0)
                ->nullable();

            // Total item
            $table->decimal('total', 12, 4)->default(0);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
    }
};