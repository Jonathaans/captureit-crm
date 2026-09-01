<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            Schema::hasTable('persons')
            && ! Schema::hasColumn(
                'persons',
                'ktp_image_path'
            )
        ) {
            Schema::table(
                'persons',
                function (Blueprint $table) {
                    $table->string(
                        'ktp_image_path',
                        1024
                    )->nullable();
                }
            );
        }

        if (
            Schema::hasTable('organizations')
            && ! Schema::hasColumn(
                'organizations',
                'npwp_image_path'
            )
        ) {
            Schema::table(
                'organizations',
                function (Blueprint $table) {
                    $table->string(
                        'npwp_image_path',
                        1024
                    )->nullable();
                }
            );
        }
    }

    public function down(): void
    {
        if (
            Schema::hasTable('persons')
            && Schema::hasColumn(
                'persons',
                'ktp_image_path'
            )
        ) {
            Schema::table(
                'persons',
                function (Blueprint $table) {
                    $table->dropColumn(
                        'ktp_image_path'
                    );
                }
            );
        }

        if (
            Schema::hasTable('organizations')
            && Schema::hasColumn(
                'organizations',
                'npwp_image_path'
            )
        ) {
            Schema::table(
                'organizations',
                function (Blueprint $table) {
                    $table->dropColumn(
                        'npwp_image_path'
                    );
                }
            );
        }
    }
};
