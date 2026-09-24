<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('gasto', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('categoria_gasto_id');
            $table->integer('metodo_pago_id')->nullable();
            $table->integer('usuario_id');
            $table->timestamp('fecha', 6);
            $table->string('concepto', 255);
            $table->decimal('monto', 12, 2);
            $table->string('referencia', 100)->nullable();
            $table->text('observacion')->nullable();
            $table->string('estado', 20)->nullable()->default('ACTIVO');
            $table->timestamp('fecha_registro', 6)->nullable()->useCurrent();
            $table->foreign('categoria_gasto_id', 'gasto_categoria_gasto_id_fkey')->references('id')->on('categoria_gasto');
            $table->foreign('metodo_pago_id', 'gasto_metodo_pago_id_fkey')->references('id')->on('metodo_pago');
            $table->foreign('usuario_id', 'gasto_usuario_id_fkey')->references('id')->on('usuario');
        });

        DB::statement('ALTER TABLE gasto ADD CONSTRAINT gasto_monto_check CHECK (monto > 0)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gasto');
    }
};
