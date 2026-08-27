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
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex([
                'payment_status',
            ]);

            $table->dropColumn(
                'payment_status'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('payment_status', 30)
                ->default('unpaid')
                ->after('event_status');

            $table->index(
                'payment_status'
            );
        });
    }
};