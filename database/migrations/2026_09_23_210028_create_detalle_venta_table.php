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
        Schema::create('detalle_venta', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('venta_id');
            $table->integer('variante_id');
            $table->integer('cantidad');
            $table->decimal('precio_unitario', 12, 2);
            $table->decimal('descuento', 12, 2)->nullable()->default(0);
            $table->decimal('costo_unitario_snapshot', 12, 2)->nullable();
            $table->string('nombre_producto_snapshot', 200)->nullable();
            $table->string('variante_snapshot', 200)->nullable();
            $table->string('numero_serie', 100)->nullable();
            $table->integer('meses_garantia')->default(3);
            $table->date('fecha_fin_garantia')->nullable();
            $table->string('estado', 30)->default('VIGENTE');
            $table->foreign('venta_id', 'detalle_venta_venta_id_fkey')->references('id')->on('venta')->cascadeOnDelete();
            $table->foreign('variante_id', 'detalle_venta_variante_id_fkey')->references('id')->on('variante_producto');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE detalle_venta ADD CONSTRAINT detalle_venta_cantidad_check CHECK (cantidad > 0)');
            DB::statement('ALTER TABLE detalle_venta ADD CONSTRAINT detalle_venta_precio_unitario_check CHECK (precio_unitario >= 0)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('detalle_venta');
    }
};
