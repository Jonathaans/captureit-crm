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
        Schema::create('invoices', function (Blueprint $table) {
            $table->increments('id');

            // Nomor invoice
            $table->string('invoice_number')->unique();

            // Sumber invoice dari quotation
            $table->integer('quote_id')->unsigned()->nullable()->unique();

            $table->foreign('quote_id')
                ->references('id')
                ->on('quotes')
                ->onDelete('set null');

            // Snapshot pemilik quotation
            $table->integer('person_id')->unsigned()->nullable();

            $table->foreign('person_id')
                ->references('id')
                ->on('persons')
                ->onDelete('set null');

            $table->integer('user_id')->unsigned()->nullable();

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            // Informasi invoice
            $table->string('subject');
            $table->text('description')->nullable();

            // Snapshot address dari quotation
            $table->json('billing_address')->nullable();
            $table->json('shipping_address')->nullable();

            // Nilai invoice
            $table->decimal('discount_percent', 12, 4)->default(0);
            $table->decimal('discount_amount', 12, 4)->default(0);
            $table->decimal('tax_amount', 12, 4)->default(0);
            $table->decimal('adjustment_amount', 12, 4)->default(0);

            $table->decimal('sub_total', 12, 4)->default(0);
            $table->decimal('grand_total', 12, 4)->default(0);

            // Payment summary
            $table->decimal('paid_amount', 12, 4)->default(0);
            $table->decimal('balance_due', 12, 4)->default(0);

            // unpaid | partial | paid
            $table->string('status')->default('unpaid');

            // Tanggal invoice
            $table->dateTime('issued_at')->nullable();
            $table->dateTime('due_at')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};