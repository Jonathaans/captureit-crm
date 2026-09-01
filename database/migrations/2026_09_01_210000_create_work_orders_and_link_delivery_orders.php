<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('work_orders')) {
            Schema::create(
                'work_orders',
                function (Blueprint $table) {
                    $table->increments('id');

                    $table->string('work_order_number', 50)
                        ->unique();

                    $table->unsignedInteger('invoice_id')
                        ->unique();

                    $table->string('invoice_number', 100)
                        ->nullable();

                    $table->unsignedInteger('quote_id')
                        ->nullable();

                    $table->string('quote_number', 100)
                        ->nullable();

                    $table->string('project_code', 100)
                        ->nullable();

                    $table->string('business_unit', 50)
                        ->nullable();

                    $table->string('project_name', 500)
                        ->nullable();

                    $table->unsignedInteger('person_id')
                        ->nullable();

                    $table->string('customer_name', 255)
                        ->nullable();

                    $table->unsignedInteger('user_id')
                        ->nullable();

                    $table->string('sales_person_name', 255)
                        ->nullable();

                    $table->date('event_date')
                        ->nullable();

                    $table->string('location', 500)
                        ->nullable();

                    $table->longText('notes')
                        ->nullable();

                    $table->string('status', 30)
                        ->default('draft')
                        ->index();

                    /*
                     * Signature / acknowledgement names printed on SPK PDF.
                     * Kept as snapshots so PDF remains stable even if CRM user
                     * names or roles change later.
                     */
                    $table->string('admin_sales_name', 255)
                        ->nullable();

                    $table->string('sales_name', 255)
                        ->nullable();

                    $table->string('operational_name', 255)
                        ->nullable();

                    $table->timestamp('released_at')
                        ->nullable();

                    $table->timestamp('completed_at')
                        ->nullable();

                    $table->timestamp('cancelled_at')
                        ->nullable();

                    $table->unsignedInteger('created_by')
                        ->nullable();

                    $table->timestamps();

                    $table->index('project_code');
                    $table->index('event_date');
                    $table->index('user_id');
                }
            );
        }

        if (! Schema::hasTable('work_order_items')) {
            Schema::create(
                'work_order_items',
                function (Blueprint $table) {
                    $table->increments('id');

                    $table->unsignedInteger('work_order_id')
                        ->index();

                    $table->unsignedInteger('product_id')
                        ->nullable()
                        ->index();

                    $table->string('name', 500);

                    /*
                     * Internal editable item note.
                     * V1 PDF intentionally prints product/service NAME only.
                     */
                    $table->text('notes')
                        ->nullable();

                    $table->unsignedInteger('sort_order')
                        ->default(0);

                    $table->timestamps();

                    $table->index(
                        [
                            'work_order_id',
                            'sort_order',
                        ],
                        'work_order_items_order_idx'
                    );
                }
            );
        }

        if (
            Schema::hasTable('delivery_orders')
            && ! Schema::hasColumn(
                'delivery_orders',
                'work_order_id'
            )
        ) {
            Schema::table(
                'delivery_orders',
                function (Blueprint $table) {
                    $table->unsignedInteger('work_order_id')
                        ->nullable()
                        ->index();
                }
            );
        }
    }

    public function down(): void
    {
        if (
            Schema::hasTable('delivery_orders')
            && Schema::hasColumn(
                'delivery_orders',
                'work_order_id'
            )
        ) {
            Schema::table(
                'delivery_orders',
                function (Blueprint $table) {
                    $table->dropColumn(
                        'work_order_id'
                    );
                }
            );
        }

        Schema::dropIfExists(
            'work_order_items'
        );

        Schema::dropIfExists(
            'work_orders'
        );
    }
};
