<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_orders', function (Blueprint $table) {
            /*
            |--------------------------------------------------------------------------
            | Primary Key
            |--------------------------------------------------------------------------
            |
            | Krayin CRM 2.2 menggunakan increments('id')
            | pada users, persons, quotes, invoices.
            | Kita ikuti pola yang sama: INT UNSIGNED.
            |
            */

            $table->increments('id');

            /*
            |--------------------------------------------------------------------------
            | Document Identity
            |--------------------------------------------------------------------------
            */

            $table->string('delivery_order_number', 50)->unique();

            /*
            |--------------------------------------------------------------------------
            | Invoice Reference
            |--------------------------------------------------------------------------
            */

            $table->unsignedInteger('invoice_id');

            $table->foreign('invoice_id')
                ->references('id')
                ->on('invoices')
                ->onDelete('cascade');

            /*
            |--------------------------------------------------------------------------
            | Quote Reference
            |--------------------------------------------------------------------------
            */

            $table->unsignedInteger('quote_id')->nullable();

            $table->foreign('quote_id')
                ->references('id')
                ->on('quotes')
                ->onDelete('set null');

            /*
            |--------------------------------------------------------------------------
            | Snapshot References
            |--------------------------------------------------------------------------
            |
            | Data text tetap disimpan agar Surat Jalan lama
            | tidak berubah apabila Invoice / Quote diedit.
            |
            */

            $table->string('invoice_number', 50)->nullable();

            $table->string('quote_number', 50)->nullable();

            $table->string('project_code', 100)->nullable();

            $table->string('project_name')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Customer
            |--------------------------------------------------------------------------
            */

            $table->unsignedInteger('person_id')->nullable();

            $table->foreign('person_id')
                ->references('id')
                ->on('persons')
                ->onDelete('set null');

            $table->string('customer_name')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Sales Person
            |--------------------------------------------------------------------------
            */

            $table->unsignedInteger('user_id')->nullable();

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            $table->string('sales_person_name')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Recipient / PIC
            |--------------------------------------------------------------------------
            */

            $table->string('recipient_name')->nullable();

            $table->string('recipient_phone', 50)->nullable();

            $table->string('pic_name')->nullable();

            $table->string('pic_phone', 50)->nullable();

            /*
            |--------------------------------------------------------------------------
            | Event Information
            |--------------------------------------------------------------------------
            */

            $table->date('event_date')->nullable();

            $table->time('event_time')->nullable();

            $table->string('location')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Delivery Information
            |--------------------------------------------------------------------------
            */

            $table->text('delivery_address')->nullable();

            $table->date('delivery_date')->nullable();

            $table->time('delivery_time')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Status
            |--------------------------------------------------------------------------
            |
            | draft
            | issued
            | delivered
            | returned
            | cancelled
            |
            */

            $table->string('status', 30)
                ->default('draft')
                ->index();

            /*
            |--------------------------------------------------------------------------
            | Notes
            |--------------------------------------------------------------------------
            */

            $table->text('notes')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Important Timestamps
            |--------------------------------------------------------------------------
            */

            $table->timestamp('issued_at')->nullable();

            $table->timestamp('delivered_at')->nullable();

            $table->timestamp('returned_at')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Audit
            |--------------------------------------------------------------------------
            */

            $table->unsignedInteger('created_by')->nullable();

            $table->foreign('created_by')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            /*
            |--------------------------------------------------------------------------
            | Laravel Timestamps
            |--------------------------------------------------------------------------
            */

            $table->timestamps();

            /*
            |--------------------------------------------------------------------------
            | Additional Indexes
            |--------------------------------------------------------------------------
            */

            $table->index('project_code');

            $table->index('event_date');

            $table->index('delivery_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_orders');
    }
};