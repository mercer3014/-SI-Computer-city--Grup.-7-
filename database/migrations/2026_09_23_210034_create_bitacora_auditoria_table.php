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
        Schema::create('bitacora_auditoria', function (Blueprint $table) {
            $table->id();
            $table->integer('usuario_id')->nullable();
            $table->string('modulo', 100);
            $table->string('accion', 100);
            $table->string('tipo_entidad', 100)->nullable();
            $table->bigInteger('entidad_id')->nullable();
            $table->jsonb('valor_anterior')->nullable();
            $table->jsonb('valor_nuevo')->nullable();
            $table->text('motivo')->nullable();
            $table->string('direccion_ip', 45)->nullable();
            $table->timestamp('fecha_hora', 6)->nullable()->useCurrent();
            $table->foreign('usuario_id', 'bitacora_auditoria_usuario_id_fkey')->references('id')->on('usuario')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bitacora_auditoria');
    }
};
