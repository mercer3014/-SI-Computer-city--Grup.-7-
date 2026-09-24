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
        Schema::create('rol_permiso', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('rol_id');
            $table->integer('permiso_id');
            $table->unique(['rol_id', 'permiso_id'], 'uq_rol_permiso');
            $table->foreign('rol_id', 'rol_permiso_rol_id_fkey')->references('id')->on('rol')->cascadeOnDelete();
            $table->foreign('permiso_id', 'rol_permiso_permiso_id_fkey')->references('id')->on('permiso')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rol_permiso');
    }
};
