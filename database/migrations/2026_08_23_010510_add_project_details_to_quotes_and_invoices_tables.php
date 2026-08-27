<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->date('event_date')
                ->nullable();

            $table->string('location')
                ->nullable();

            $table->string('payment_term', 100)
                ->nullable();
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->date('event_date')
                ->nullable();

            $table->string('location')
                ->nullable();

            $table->string('payment_term', 100)
                ->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn([
                'event_date',
                'location',
                'payment_term',
            ]);
        });

        Schema::table('quotes', function (Blueprint $table) {
            $table->dropColumn([
                'event_date',
                'location',
                'payment_term',
            ]);
        });
    }
};