<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * CU22: conversaciones del asistente virtual, sus mensajes y las plantillas de reportes frecuentes.
 * `especificacion` guarda la consulta ya validada (nunca texto de la IA sin validar).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversacion_asistente', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('usuario_id');
            $table->string('titulo', 150)->nullable();
            $table->timestamp('fecha_inicio')->useCurrent();
            $table->timestamp('fecha_actualizacion')->useCurrent();
            $table->foreign('usuario_id', 'conversacion_asistente_usuario_id_fkey')->references('id')->on('usuario')->cascadeOnDelete();
            $table->index('usuario_id', 'conversacion_asistente_usuario_id_idx');
        });

        Schema::create('mensaje_asistente', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('conversacion_id');
            $table->string('rol', 15);
            $table->text('contenido');
            $table->string('origen', 10)->default('TEXTO');
            $table->string('tipo', 20)->default('TEXTO');
            $table->jsonb('especificacion')->nullable();
            $table->text('interpretacion')->nullable();
            $table->string('enlace', 255)->nullable();
            $table->timestamp('fecha')->useCurrent();
            $table->foreign('conversacion_id', 'mensaje_asistente_conversacion_id_fkey')->references('id')->on('conversacion_asistente')->cascadeOnDelete();
            $table->index('conversacion_id', 'mensaje_asistente_conversacion_id_idx');
        });

        Schema::create('plantilla_reporte', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('usuario_id');
            $table->string('nombre', 100);
            $table->jsonb('especificacion');
            $table->timestamp('fecha_creacion')->useCurrent();
            $table->foreign('usuario_id', 'plantilla_reporte_usuario_id_fkey')->references('id')->on('usuario')->cascadeOnDelete();
            $table->unique(['usuario_id', 'nombre'], 'plantilla_reporte_usuario_nombre_key');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE mensaje_asistente ADD CONSTRAINT mensaje_asistente_rol_check CHECK (rol IN ('USUARIO', 'ASISTENTE'))");
            DB::statement("ALTER TABLE mensaje_asistente ADD CONSTRAINT mensaje_asistente_origen_check CHECK (origen IN ('TEXTO', 'VOZ'))");
            DB::statement("ALTER TABLE mensaje_asistente ADD CONSTRAINT mensaje_asistente_tipo_check CHECK (tipo IN ('TEXTO', 'CONSULTA', 'REPORTE', 'ERROR', 'NO_SOPORTADO'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('plantilla_reporte');
        Schema::dropIfExists('mensaje_asistente');
        Schema::dropIfExists('conversacion_asistente');
    }
};
