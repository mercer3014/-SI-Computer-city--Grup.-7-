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
        Schema::create('valor_atributo', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('atributo_id');
            $table->string('valor', 150);
            $table->integer('orden_visualizacion')->nullable()->default(0);
            $table->string('estado', 20)->nullable()->default('ACTIVO');
            $table->foreign('atributo_id', 'valor_atributo_atributo_id_fkey')->references('id')->on('atributo')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('valor_atributo');
    }
};
