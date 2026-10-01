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
        Schema::create('categoria', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('categoria_padre_id')->nullable();
            $table->string('nombre', 150);
            $table->string('slug', 180)->unique('categoria_slug_key');
            $table->text('descripcion')->nullable();
            $table->boolean('es_promocion')->nullable()->default(false);
            $table->text('texto_promocion')->nullable();
            $table->integer('orden_visualizacion')->nullable()->default(0);
            $table->boolean('activo')->nullable()->default(true);
            $table->string('icono', 255)->nullable();
            $table->timestamp('fecha_creacion', 6)->nullable()->useCurrent();
            $table->foreign('categoria_padre_id', 'categoria_categoria_padre_id_fkey')->references('id')->on('categoria')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('categoria');
    }
};
