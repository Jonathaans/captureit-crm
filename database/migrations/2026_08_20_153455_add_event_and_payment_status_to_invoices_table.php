<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            /**
             * Status event/project.
             *
             * prospect = masih prospek
             * confirm  = event confirmed
             * cancel   = event dibatalkan
             */
            $table->string('event_status', 30)
                ->default('confirm')
                ->after('status');

            /**
             * Status pembayaran.
             *
             * unpaid
             * partial
             * paid
             */
            $table->string('payment_status', 30)
                ->default('unpaid')
                ->after('event_status');

            $table->index('event_status');
            $table->index('payment_status');
        });

        /**
         * Copy status pembayaran lama
         * ke payment_status baru.
         *
         * Contoh:
         *
         * status = paid
         * menjadi
         * payment_status = paid
         */
        DB::table('invoices')
            ->update([
                'payment_status' => DB::raw('status'),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex([
                'event_status',
            ]);

            $table->dropIndex([
                'payment_status',
            ]);

            $table->dropColumn([
                'event_status',
                'payment_status',
            ]);
        });
    }
};