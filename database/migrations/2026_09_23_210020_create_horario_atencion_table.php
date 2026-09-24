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
        Schema::create('horario_atencion', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('configuracion_tienda_id');
            $table->smallInteger('dia_semana');
            $table->time('hora_apertura', 6)->nullable();
            $table->time('hora_cierre', 6)->nullable();
            $table->boolean('cerrado')->nullable()->default(false);
            $table->foreign('configuracion_tienda_id', 'horario_atencion_configuracion_tienda_id_fkey')->references('id')->on('configuracion_tienda')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('horario_atencion');
    }
};
