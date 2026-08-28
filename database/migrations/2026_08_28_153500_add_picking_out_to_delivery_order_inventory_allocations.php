<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(
            'delivery_order_inventory_allocations',
            function (Blueprint $table) {
                $table->unsignedInteger('picked_by')
                    ->nullable()
                    ->after('allocated_at')
                    ->index();

                $table->timestamp('picked_at')
                    ->nullable()
                    ->after('picked_by');

                $table->unsignedInteger('out_by')
                    ->nullable()
                    ->after('picked_at')
                    ->index();

                $table->timestamp('out_at')
                    ->nullable()
                    ->after('out_by');
            }
        );
    }

    public function down(): void
    {
        Schema::table(
            'delivery_order_inventory_allocations',
            function (Blueprint $table) {
                $table->dropColumn([
                    'picked_by',
                    'picked_at',
                    'out_by',
                    'out_at',
                ]);
            }
        );
    }
};
