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
        Schema::create('movimiento_inventario', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('usuario_id');
            $table->integer('venta_id')->nullable();
            $table->integer('compra_id')->nullable();
            $table->string('tipo', 50);
            $table->timestamp('fecha', 6)->nullable()->useCurrent();
            $table->string('referencia', 100)->nullable();
            $table->text('observacion')->nullable();
            $table->foreign('usuario_id', 'movimiento_inventario_usuario_id_fkey')->references('id')->on('usuario');
            $table->foreign('venta_id', 'movimiento_inventario_venta_id_fkey')->references('id')->on('venta')->nullOnDelete();
            $table->foreign('compra_id', 'movimiento_inventario_compra_id_fkey')->references('id')->on('compra')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('movimiento_inventario');
    }
};
