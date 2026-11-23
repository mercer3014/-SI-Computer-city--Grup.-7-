<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * CU24: calificaciones de productos de pedidos pagados, con moderación.
     */
    public function up(): void
    {
        Schema::create('calificacion', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('cliente_id');
            $table->integer('usuario_id');
            $table->integer('pedido_id');
            $table->integer('producto_id');
            $table->smallInteger('puntuacion');
            $table->string('comentario', 500)->nullable();
            $table->string('estado', 20)->default('PENDIENTE');
            $table->text('motivo_ocultamiento')->nullable();
            $table->integer('moderado_por')->nullable();
            $table->timestamp('fecha_moderacion', 6)->nullable();
            $table->timestamp('fecha', 6)->useCurrent();

            $table->foreign('cliente_id', 'calificacion_cliente_id_fkey')->references('id')->on('cliente');
            $table->foreign('usuario_id', 'calificacion_usuario_id_fkey')->references('id')->on('usuario');
            $table->foreign('pedido_id', 'calificacion_pedido_id_fkey')->references('id')->on('pedido');
            $table->foreign('producto_id', 'calificacion_producto_id_fkey')->references('id')->on('producto');
            $table->foreign('moderado_por', 'calificacion_moderado_por_fkey')->references('id')->on('usuario');

            $table->unique(['pedido_id', 'producto_id'], 'calificacion_pedido_producto_key');
            $table->index(['producto_id', 'estado'], 'calificacion_producto_estado_idx');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE calificacion ADD CONSTRAINT calificacion_puntuacion_check CHECK (puntuacion BETWEEN 1 AND 5)');
            DB::statement("ALTER TABLE calificacion ADD CONSTRAINT calificacion_estado_check CHECK (estado IN ('PENDIENTE', 'PUBLICADA', 'OCULTA'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('calificacion');
    }
};
