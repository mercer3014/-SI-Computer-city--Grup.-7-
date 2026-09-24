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
        Schema::create('evento_producto', function (Blueprint $table) {
            $table->id();
            $table->integer('producto_id');
            $table->string('tipo_evento', 50);
            $table->string('session_id', 100)->nullable();
            $table->timestamp('fecha_hora', 6)->nullable()->useCurrent();
            $table->foreign('producto_id', 'evento_producto_producto_id_fkey')->references('id')->on('producto')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('evento_producto');
    }
};
