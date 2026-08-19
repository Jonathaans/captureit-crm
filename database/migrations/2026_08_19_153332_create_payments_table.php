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
        Schema::create('payments', function (Blueprint $table) {
            $table->increments('id');

            // Invoice yang dibayar
            $table->integer('invoice_id')->unsigned();

            $table->foreign('invoice_id')
                ->references('id')
                ->on('invoices')
                ->onDelete('cascade');

            // Jumlah pembayaran
            $table->decimal('amount', 12, 4);

            // Contoh:
            // bank_transfer, cash, credit_card, other
            $table->string('payment_method')->nullable();

            // Nomor transfer / reference bank / transaction ID
            $table->string('reference_number')->nullable();

            // Catatan pembayaran
            $table->text('notes')->nullable();

            // Tanggal pembayaran diterima
            $table->dateTime('paid_at');

            // User CRM yang mencatat pembayaran
            $table->integer('created_by')->unsigned()->nullable();

            $table->foreign('created_by')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};