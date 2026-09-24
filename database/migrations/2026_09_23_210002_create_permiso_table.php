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
        Schema::create('permiso', function (Blueprint $table) {
            $table->increments('id');
            $table->string('clave', 100)->unique('permiso_clave_key');
            $table->string('nombre', 150);
            $table->text('descripcion')->nullable();
            $table->string('modulo', 100)->nullable();
            $table->string('estado', 20)->nullable()->default('ACTIVO');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('permiso');
    }
};
