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
        Schema::create('compra', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('proveedor_id');
            $table->integer('usuario_id');
            $table->integer('metodo_pago_id')->nullable();
            $table->string('numero_compra', 50)->unique('compra_numero_compra_key');
            $table->timestamp('fecha_compra', 6);
            $table->string('tipo_documento', 50)->nullable();
            $table->string('numero_documento', 100)->nullable();
            $table->string('estado', 30)->nullable()->default('COMPLETADO');
            $table->text('observacion')->nullable();
            $table->timestamp('fecha_registro', 6)->nullable()->useCurrent();
            $table->foreign('proveedor_id', 'compra_proveedor_id_fkey')->references('id')->on('proveedor');
            $table->foreign('usuario_id', 'compra_usuario_id_fkey')->references('id')->on('usuario');
            $table->foreign('metodo_pago_id', 'compra_metodo_pago_id_fkey')->references('id')->on('metodo_pago');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('compra');
    }
};
