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
        Schema::create('venta', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('cliente_id');
            $table->integer('pedido_id')->nullable();
            $table->integer('cotizacion_id')->nullable();
            $table->integer('usuario_id');
            $table->string('numero_venta', 50)->unique('venta_numero_venta_key');
            $table->timestamp('fecha', 6)->nullable()->useCurrent();
            $table->string('estado', 30)->nullable()->default('COMPLETADA');
            $table->decimal('descuento_global', 12, 2)->nullable()->default(0);
            $table->text('observacion')->nullable();
            $table->timestamp('fecha_anulacion', 6)->nullable();
            $table->integer('anulado_por')->nullable();
            $table->text('motivo_anulacion')->nullable();
            $table->foreign('cliente_id', 'venta_cliente_id_fkey')->references('id')->on('cliente');
            $table->foreign('pedido_id', 'venta_pedido_id_fkey')->references('id')->on('pedido')->nullOnDelete();
            $table->foreign('cotizacion_id', 'venta_cotizacion_id_fkey')->references('id')->on('cotizacion')->nullOnDelete();
            $table->foreign('usuario_id', 'venta_usuario_id_fkey')->references('id')->on('usuario');
            $table->foreign('anulado_por', 'venta_anulado_por_fkey')->references('id')->on('usuario')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('venta');
    }
};
