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
        Schema::create('variante_producto', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('producto_id');
            $table->string('sku', 100)->unique('variante_producto_sku_key');
            $table->string('codigo_barras', 100)->nullable()->unique('variante_producto_codigo_barras_key');
            $table->decimal('precio_venta', 12, 2);
            $table->decimal('precio_anterior', 12, 2)->nullable();
            $table->string('estado', 20)->nullable()->default('ACTIVO');
            $table->timestamp('fecha_creacion', 6)->nullable()->useCurrent();
            $table->timestamp('fecha_actualizacion', 6)->nullable()->useCurrent();
            $table->integer('stock_actual')->default(0);
            $table->integer('stock_minimo')->default(5);
            $table->foreign('producto_id', 'variante_producto_producto_id_fkey')->references('id')->on('producto')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('variante_producto');
    }
};
