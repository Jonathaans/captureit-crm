<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_equipment_templates', function (Blueprint $table) {
            /*
            |--------------------------------------------------------------------------
            | Primary Key
            |--------------------------------------------------------------------------
            */

            $table->increments('id');

            /*
            |--------------------------------------------------------------------------
            | Product
            |--------------------------------------------------------------------------
            |
            | Product Krayin menggunakan increments('id'),
            | jadi product_id harus unsignedInteger.
            |
            */

            $table->unsignedInteger('product_id')->unique();

            $table->foreign('product_id')
                ->references('id')
                ->on('products')
                ->onDelete('cascade');

            /*
            |--------------------------------------------------------------------------
            | Template Information
            |--------------------------------------------------------------------------
            */

            $table->string('name')->nullable();

            $table->boolean('is_active')
                ->default(true);

            $table->text('notes')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_equipment_templates');
    }
};