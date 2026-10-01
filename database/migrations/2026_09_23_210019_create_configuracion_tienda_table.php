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
        Schema::create('configuracion_tienda', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nombre_tienda', 150);
            $table->string('centro_comercial', 150)->nullable();
            $table->text('direccion')->nullable();
            $table->string('numero_pasillo', 50)->nullable();
            $table->string('numero_local', 50)->nullable();
            $table->string('telefono', 30)->nullable();
            $table->string('whatsapp', 30)->nullable();
            $table->string('email', 150)->nullable();
            $table->decimal('latitud', 10, 8)->nullable();
            $table->decimal('longitud', 11, 8)->nullable();
            $table->text('google_maps_url')->nullable();
            $table->text('logo_url')->nullable();
            $table->string('moneda', 10)->nullable()->default('BOB');
            $table->text('anuncio')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('configuracion_tienda');
    }
};
