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
        Schema::create('expenses', function (Blueprint $table) {
            $table->increments('id');

            /**
             * Invoice / event terkait.
             */
            $table->integer('invoice_id')->unsigned();

            $table->foreign('invoice_id')
                ->references('id')
                ->on('invoices')
                ->onDelete('cascade');

            /**
             * Jenis pengeluaran.
             *
             * Contoh:
             * transport
             * crew
             * printing
             * equipment
             * vendor
             * consumption
             * other
             */
            $table->string('category');

            /**
             * Nama / deskripsi pengeluaran.
             *
             * Contoh:
             * Cetak photostrip
             * Transport crew
             * Sewa printer
             */
            $table->string('description');

            /**
             * Nilai pengeluaran.
             */
            $table->decimal('amount', 12, 4);

            /**
             * Tanggal pengeluaran.
             */
            $table->date('expense_date');

            /**
             * Vendor / penerima pembayaran.
             */
            $table->string('vendor_name')->nullable();

            /**
             * Nomor bon / invoice vendor / reference.
             */
            $table->string('reference_number')->nullable();

            /**
             * Path foto / scan bon.
             */
            $table->string('receipt_path')->nullable();

            /**
             * Catatan tambahan.
             */
            $table->text('notes')->nullable();

            /**
             * User CRM yang mencatat pengeluaran.
             */
            $table->integer('created_by')->unsigned()->nullable();

            $table->foreign('created_by')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            $table->timestamps();

            /**
             * Index untuk query laporan.
             */
            $table->index('invoice_id');
            $table->index('category');
            $table->index('expense_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};