<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            Schema::hasTable('purchase_orders')
            && ! Schema::hasColumn(
                'purchase_orders',
                'payment_terms'
            )
        ) {
            Schema::table(
                'purchase_orders',
                function (Blueprint $table) {
                    $table->string(
                        'payment_terms',
                        50
                    )
                        ->default('7_days')
                        ->after('order_date');
                }
            );
        }
    }

    public function down(): void
    {
        if (
            Schema::hasTable('purchase_orders')
            && Schema::hasColumn(
                'purchase_orders',
                'payment_terms'
            )
        ) {
            Schema::table(
                'purchase_orders',
                function (Blueprint $table) {
                    $table->dropColumn(
                        'payment_terms'
                    );
                }
            );
        }
    }
};
