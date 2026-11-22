<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * CU19 y CU20: de dónde viene la cotización y quién cambió su estado, y cuándo.
     * `cliente_id` y `usuario_id` siguen siendo obligatorios: cotizar requiere cuenta.
     */
    public function up(): void
    {
        Schema::table('cotizacion', function (Blueprint $table) {
            $table->string('origen', 20)->default('TIENDA_WEB');
            $table->timestamp('fecha_estado', 6)->nullable();
            $table->integer('actualizado_por')->nullable();
            $table->index('estado', 'cotizacion_estado_idx');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE cotizacion ADD CONSTRAINT cotizacion_actualizado_por_fkey FOREIGN KEY (actualizado_por) REFERENCES usuario(id) ON DELETE SET NULL');
            DB::statement("ALTER TABLE cotizacion ADD CONSTRAINT cotizacion_origen_check CHECK (origen IN ('TIENDA_WEB', 'MOSTRADOR'))");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE cotizacion DROP CONSTRAINT IF EXISTS cotizacion_origen_check');
            DB::statement('ALTER TABLE cotizacion DROP CONSTRAINT IF EXISTS cotizacion_actualizado_por_fkey');
        }

        Schema::table('cotizacion', function (Blueprint $table) {
            $table->dropIndex('cotizacion_estado_idx');
            $table->dropColumn(['origen', 'fecha_estado', 'actualizado_por']);
        });
    }
};
