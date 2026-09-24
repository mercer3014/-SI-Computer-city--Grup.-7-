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
        Schema::create('categoria_gasto', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nombre', 150);
            $table->text('descripcion')->nullable();
            $table->string('estado', 20)->nullable()->default('ACTIVO');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('categoria_gasto');
    }
};
