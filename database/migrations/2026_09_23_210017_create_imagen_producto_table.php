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
        Schema::create('imagen_producto', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('producto_id');
            $table->text('url_webp');
            $table->integer('orden_visualizacion')->nullable()->default(0);
            $table->boolean('es_principal')->nullable()->default(false);
            $table->string('texto_alternativo', 255)->nullable();
            $table->foreign('producto_id', 'imagen_producto_producto_id_fkey')->references('id')->on('producto')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('imagen_producto');
    }
};
