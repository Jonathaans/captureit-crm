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
                $table->unsignedInteger('return_pending_by')
                    ->nullable()
                    ->after('out_at')
                    ->index();

                $table->timestamp('return_pending_at')
                    ->nullable()
                    ->after('return_pending_by');

                $table->unsignedInteger('checked_in_by')
                    ->nullable()
                    ->after('return_pending_at')
                    ->index();

                $table->timestamp('checked_in_at')
                    ->nullable()
                    ->after('checked_in_by');

                $table->string('return_condition', 30)
                    ->nullable()
                    ->after('checked_in_at');

                $table->decimal('returned_quantity', 12, 2)
                    ->nullable()
                    ->after('return_condition');

                $table->text('return_notes')
                    ->nullable()
                    ->after('returned_quantity');
            }
        );
    }

    public function down(): void
    {
        Schema::table(
            'delivery_order_inventory_allocations',
            function (Blueprint $table) {
                $table->dropColumn([
                    'return_pending_by',
                    'return_pending_at',
                    'checked_in_by',
                    'checked_in_at',
                    'return_condition',
                    'returned_quantity',
                    'return_notes',
                ]);
            }
        );
    }
};
