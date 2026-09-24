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
        Schema::create('usuario', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('personal_id')->nullable();
            $table->integer('rol_id');
            $table->string('email', 150)->unique('usuario_email_key');
            $table->string('password_hash', 255);
            $table->string('estado', 20)->nullable()->default('ACTIVO');
            $table->integer('intentos_fallidos')->nullable()->default(0);
            $table->boolean('bloqueado')->nullable()->default(false);
            $table->boolean('primer_login')->nullable()->default(true);
            $table->timestamp('fecha_ultimo_login', 6)->nullable();
            $table->timestamp('fecha_creacion', 6)->nullable()->useCurrent();
            $table->timestamp('fecha_bloqueo', 6)->nullable();
            $table->integer('nivel_bloqueo')->nullable()->default(0);
            $table->foreign('personal_id', 'usuario_personal_id_fkey')->references('id')->on('personal')->nullOnDelete();
            $table->foreign('rol_id', 'usuario_rol_id_fkey')->references('id')->on('rol');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('usuario');
    }
};
