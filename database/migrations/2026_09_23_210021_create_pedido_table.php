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
        Schema::create('pedido', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('cliente_id');
            $table->string('numero_pedido', 50)->unique('pedido_numero_pedido_key');
            $table->string('nombre_contacto', 150)->nullable();
            $table->string('telefono_contacto', 50)->nullable();
            $table->string('metodo_entrega', 100)->nullable();
            $table->text('direccion_entrega')->nullable();
            $table->string('estado', 30)->nullable()->default('PENDIENTE');
            $table->string('origen', 50)->nullable();
            $table->text('observacion')->nullable();
            $table->timestamp('fecha', 6)->nullable()->useCurrent();
            $table->foreign('cliente_id', 'pedido_cliente_id_fkey')->references('id')->on('cliente');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pedido');
    }
};
