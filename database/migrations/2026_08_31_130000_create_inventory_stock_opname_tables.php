<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_stock_opname_sessions', function (Blueprint $table) {
            $table->increments('id');

            $table->string('reference_number', 40)->nullable();
            $table->unsignedInteger('warehouse_id');
            $table->string('status', 20)->default('draft');
            $table->text('notes')->nullable();

            $table->unsignedInteger('created_by')->nullable();

            $table->unsignedInteger('started_by')->nullable();
            $table->timestamp('started_at')->nullable();

            $table->unsignedInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();

            $table->unsignedInteger('finalized_by')->nullable();
            $table->timestamp('finalized_at')->nullable();

            $table->timestamps();

            $table->unique(
                'reference_number',
                'stock_opname_session_reference_uq'
            );

            $table->index(
                ['warehouse_id', 'status'],
                'stock_opname_session_wh_status_idx'
            );

            $table->index(
                'started_at',
                'stock_opname_session_started_idx'
            );

            $table->foreign(
                'warehouse_id',
                'stock_opname_session_warehouse_fk'
            )
                ->references('id')
                ->on('warehouses')
                ->onDelete('restrict');

            $table->foreign(
                'created_by',
                'stock_opname_session_created_by_fk'
            )
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->foreign(
                'started_by',
                'stock_opname_session_started_by_fk'
            )
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->foreign(
                'reviewed_by',
                'stock_opname_session_reviewed_by_fk'
            )
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->foreign(
                'finalized_by',
                'stock_opname_session_finalized_by_fk'
            )
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });

        Schema::create('inventory_stock_opname_entries', function (Blueprint $table) {
            $table->increments('id');

            $table->unsignedInteger('stock_opname_session_id');

            $table->string('entry_type', 20);
            $table->unsignedInteger('inventory_item_id')->nullable();
            $table->unsignedInteger('inventory_asset_id')->nullable();

            $table->string('scan_value', 100)->nullable();

            $table->boolean('expected_presence')->default(false);
            $table->string('expected_status', 30)->nullable();
            $table->string('observed_status', 30)->nullable();
            $table->string('expected_condition', 30)->nullable();

            $table->decimal('system_quantity', 15, 2)->nullable();
            $table->decimal('actual_quantity', 15, 2)->nullable();
            $table->decimal('variance', 15, 2)->nullable();

            $table->string('result', 30)->default('pending');

            $table->unsignedInteger('scanned_by')->nullable();
            $table->timestamp('scanned_at')->nullable();

            $table->unsignedInteger('counted_by')->nullable();
            $table->timestamp('counted_at')->nullable();

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->unique(
                ['stock_opname_session_id', 'inventory_asset_id'],
                'stock_opname_session_asset_uq'
            );

            $table->index(
                ['stock_opname_session_id', 'result'],
                'stock_opname_session_result_idx'
            );

            $table->index(
                ['stock_opname_session_id', 'entry_type'],
                'stock_opname_session_type_idx'
            );

            $table->index(
                'inventory_item_id',
                'stock_opname_entry_item_idx'
            );

            $table->index(
                'scan_value',
                'stock_opname_entry_scan_idx'
            );

            $table->foreign(
                'stock_opname_session_id',
                'stock_opname_entry_session_fk'
            )
                ->references('id')
                ->on('inventory_stock_opname_sessions')
                ->cascadeOnDelete();

            $table->foreign(
                'inventory_item_id',
                'stock_opname_entry_item_fk'
            )
                ->references('id')
                ->on('inventory_items')
                ->onDelete('restrict');

            $table->foreign(
                'inventory_asset_id',
                'stock_opname_entry_asset_fk'
            )
                ->references('id')
                ->on('inventory_assets')
                ->onDelete('restrict');

            $table->foreign(
                'scanned_by',
                'stock_opname_entry_scanned_by_fk'
            )
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->foreign(
                'counted_by',
                'stock_opname_entry_counted_by_fk'
            )
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_stock_opname_entries');
        Schema::dropIfExists('inventory_stock_opname_sessions');
    }
};
