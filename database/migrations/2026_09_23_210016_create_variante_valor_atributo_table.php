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
        Schema::create('variante_valor_atributo', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('variante_id');
            $table->integer('valor_atributo_id');
            $table->unique(['variante_id', 'valor_atributo_id'], 'uq_variante_valor');
            $table->foreign('variante_id', 'variante_valor_atributo_variante_id_fkey')->references('id')->on('variante_producto')->cascadeOnDelete();
            $table->foreign('valor_atributo_id', 'variante_valor_atributo_valor_atributo_id_fkey')->references('id')->on('valor_atributo')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('variante_valor_atributo');
    }
};
