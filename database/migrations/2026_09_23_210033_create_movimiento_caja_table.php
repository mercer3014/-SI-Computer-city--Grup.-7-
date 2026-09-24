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
        Schema::create('movimiento_caja', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('usuario_id');
            $table->string('tipo', 50);
            $table->string('categoria', 100)->nullable();
            $table->decimal('monto', 12, 2);
            $table->timestamp('fecha', 6)->nullable()->useCurrent();
            $table->text('descripcion')->nullable();
            $table->foreign('usuario_id', 'movimiento_caja_usuario_id_fkey')->references('id')->on('usuario');
        });

        DB::statement('ALTER TABLE movimiento_caja ADD CONSTRAINT movimiento_caja_monto_check CHECK (monto > 0)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('movimiento_caja');
    }
};
