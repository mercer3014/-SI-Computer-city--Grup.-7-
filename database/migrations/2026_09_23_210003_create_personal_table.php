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
        Schema::create('personal', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nombre_completo', 200);
            $table->string('ci', 30)->nullable()->unique('personal_ci_key');
            $table->string('telefono', 30)->nullable();
            $table->string('cargo', 100)->nullable();
            $table->string('estado', 20)->nullable()->default('ACTIVO');
            $table->timestamp('fecha_registro', 6)->nullable()->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('personal');
    }
};
