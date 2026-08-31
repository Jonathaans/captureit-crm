<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_order_sequences', function (Blueprint $table) {
            $table->increments('id');
            $table->string('period', 4)->unique(); // YYMM
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();
        });

        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->increments('id');
            $table->string('po_number', 50)->unique();

            $table->unsignedInteger('invoice_id')->index();
            $table->string('invoice_number', 100)->nullable();
            $table->string('project_code', 100)->nullable()->index();
            $table->string('project_name')->nullable();
            $table->string('business_unit', 50)->nullable()->index();

            $table->string('vendor_name');
            $table->string('vendor_phone', 100)->nullable();
            $table->string('vendor_email')->nullable();
            $table->text('vendor_address')->nullable();

            $table->date('order_date')->index();

            $table->enum('status', [
                'draft',
                'released',
                'completed',
                'cancelled',
            ])->default('draft')->index();

            $table->decimal('sub_total', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('adjustment_amount', 15, 2)->default(0);
            $table->decimal('grand_total', 15, 2)->default(0);

            $table->text('notes')->nullable();

            /*
             * Expense generated when this PO is RELEASED.
             * No FK is added because the existing Expense table is customized.
             */
            $table->unsignedInteger('expense_id')->nullable()->unique();

            $table->unsignedInteger('created_by')->nullable();
            $table->string('created_by_name')->nullable();

            $table->unsignedInteger('released_by')->nullable();
            $table->string('released_by_name')->nullable();
            $table->timestamp('released_at')->nullable();

            $table->unsignedInteger('completed_by')->nullable();
            $table->string('completed_by_name')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->unsignedInteger('cancelled_by')->nullable();
            $table->string('cancelled_by_name')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            $table->timestamps();

            $table->foreign('invoice_id', 'purchase_orders_invoice_fk')
                ->references('id')
                ->on('invoices')
                ->onDelete('restrict');
        });

        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->increments('id');

            $table->unsignedInteger('purchase_order_id')->index();
            $table->unsignedInteger('invoice_item_id')->nullable()->index();

            $table->string('name');
            $table->text('description')->nullable();

            $table->decimal('quantity', 12, 2)->default(1);
            $table->string('unit', 30)->default('job');
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);

            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('purchase_order_id', 'purchase_order_items_po_fk')
                ->references('id')
                ->on('purchase_orders')
                ->onDelete('cascade');

            $table->foreign('invoice_item_id', 'purchase_order_items_invoice_item_fk')
                ->references('id')
                ->on('invoice_items')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_items');
        Schema::dropIfExists('purchase_orders');
        Schema::dropIfExists('purchase_order_sequences');
    }
};
