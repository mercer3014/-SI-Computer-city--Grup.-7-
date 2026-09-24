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
        Schema::create('cliente', function (Blueprint $table) {
            $table->increments('id');
            $table->string('tipo_documento', 30)->nullable();
            $table->string('numero_documento', 50)->nullable()->unique('cliente_numero_documento_key');
            $table->string('nombre', 200);
            $table->string('razon_social', 200)->nullable();
            $table->string('telefono', 50)->nullable();
            $table->string('email', 150)->nullable();
            $table->text('direccion')->nullable();
            $table->text('observacion')->nullable();
            $table->string('estado', 20)->nullable()->default('ACTIVO');
            $table->timestamp('fecha_registro', 6)->nullable()->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cliente');
    }
};
