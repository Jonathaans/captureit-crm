<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'product_equipment_template_items',
            function (Blueprint $table) {
                /*
                |--------------------------------------------------------------------------
                | Primary Key
                |--------------------------------------------------------------------------
                */

                $table->increments('id');

                /*
                |--------------------------------------------------------------------------
                | Template
                |--------------------------------------------------------------------------
                */

                $table->unsignedInteger('template_id');

                $table->foreign('template_id')
                    ->references('id')
                    ->on('product_equipment_templates')
                    ->onDelete('cascade');

                /*
                |--------------------------------------------------------------------------
                | Equipment
                |--------------------------------------------------------------------------
                */

                $table->string('name');

                $table->text('description')->nullable();

                $table->decimal('quantity', 12, 2)
                    ->default(1);

                $table->string('unit', 30)
                    ->default('unit');

                $table->text('notes')->nullable();

                $table->unsignedInteger('sort_order')
                    ->default(0);

                $table->timestamps();

                $table->index([
                    'template_id',
                    'sort_order',
                ]);
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'product_equipment_template_items'
        );
    }
};