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
        Schema::create('cotizacion', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('cliente_id');
            $table->integer('usuario_id');
            $table->string('codigo_cotizacion', 50)->unique('cotizacion_codigo_cotizacion_key');
            $table->timestamp('fecha_emision', 6)->nullable()->useCurrent();
            $table->date('fecha_vencimiento')->nullable();
            $table->string('estado', 30)->nullable()->default('PENDIENTE');
            $table->text('observacion')->nullable();
            $table->timestamp('fecha_creacion', 6)->nullable()->useCurrent();
            $table->foreign('cliente_id', 'cotizacion_cliente_id_fkey')->references('id')->on('cliente');
            $table->foreign('usuario_id', 'cotizacion_usuario_id_fkey')->references('id')->on('usuario');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cotizacion');
    }
};
