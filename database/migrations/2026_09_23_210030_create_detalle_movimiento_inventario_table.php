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
        Schema::create('detalle_movimiento_inventario', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('movimiento_id');
            $table->integer('variante_id');
            $table->integer('cantidad');
            $table->decimal('costo_unitario', 12, 2)->nullable()->default(0);
            $table->foreign('movimiento_id', 'detalle_movimiento_inventario_movimiento_id_fkey')->references('id')->on('movimiento_inventario')->cascadeOnDelete();
            $table->foreign('variante_id', 'detalle_movimiento_inventario_variante_id_fkey')->references('id')->on('variante_producto');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE detalle_movimiento_inventario ADD CONSTRAINT detalle_movimiento_inventario_cantidad_check CHECK (cantidad > 0)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('detalle_movimiento_inventario');
    }
};
