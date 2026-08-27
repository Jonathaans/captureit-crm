<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->string('project_code', 50)
                ->nullable()
                ->after('id');

            $table->index('project_code');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->string('project_code', 50)
                ->nullable()
                ->after('invoice_number');

            $table->index('project_code');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex(['project_code']);
            $table->dropColumn('project_code');
        });

        Schema::table('quotes', function (Blueprint $table) {
            $table->dropIndex(['project_code']);
            $table->dropColumn('project_code');
        });
    }
};