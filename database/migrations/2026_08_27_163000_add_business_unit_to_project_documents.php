<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->string('business_unit', 30)
                ->nullable()
                ->after('project_code')
                ->index();
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->string('business_unit', 30)
                ->nullable()
                ->after('project_code')
                ->index();
        });

        Schema::table('delivery_orders', function (Blueprint $table) {
            $table->string('business_unit', 30)
                ->nullable()
                ->after('project_code')
                ->index();
        });
    }

    public function down(): void
    {
        Schema::table('delivery_orders', function (Blueprint $table) {
            $table->dropIndex('delivery_orders_business_unit_index');
            $table->dropColumn('business_unit');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex('invoices_business_unit_index');
            $table->dropColumn('business_unit');
        });

        Schema::table('quotes', function (Blueprint $table) {
            $table->dropIndex('quotes_business_unit_index');
            $table->dropColumn('business_unit');
        });
    }
};
