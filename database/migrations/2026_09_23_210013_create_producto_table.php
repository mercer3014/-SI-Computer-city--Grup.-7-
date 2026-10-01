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
        Schema::create('producto', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('categoria_id');
            $table->integer('marca_id')->nullable();
            $table->string('nombre', 200);
            $table->string('slug', 250)->unique('producto_slug_key');
            $table->text('descripcion')->nullable();
            $table->string('estado', 20)->nullable()->default('ACTIVO');
            $table->timestamp('fecha_creacion', 6)->nullable()->useCurrent();
            $table->timestamp('fecha_actualizacion', 6)->nullable()->useCurrent();
            $table->foreign('categoria_id', 'producto_categoria_id_fkey')->references('id')->on('categoria');
            $table->foreign('marca_id', 'producto_marca_id_fkey')->references('id')->on('marca');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('producto');
    }
};
