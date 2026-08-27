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
        Schema::table('quote_items', function (Blueprint $table) {
            $table
                ->text('description')
                ->nullable()
                ->after('name');

            $table
                ->unsignedInteger('day')
                ->default(1)
                ->after('description');
        });

        Schema::table('invoice_items', function (Blueprint $table) {
            $table
                ->text('description')
                ->nullable()
                ->after('name');

            $table
                ->unsignedInteger('day')
                ->default(1)
                ->after('description');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('quote_items', function (Blueprint $table) {
            $table->dropColumn([
                'description',
                'day',
            ]);
        });

        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropColumn([
                'description',
                'day',
            ]);
        });
    }
};