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
        Schema::create('detalle_pedido', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('pedido_id');
            $table->integer('variante_id');
            $table->integer('cantidad');
            $table->decimal('precio_unitario', 12, 2);
            $table->decimal('descuento', 12, 2)->nullable()->default(0);
            $table->string('nombre_producto_snapshot', 200)->nullable();
            $table->string('variante_snapshot', 200)->nullable();
            $table->foreign('pedido_id', 'detalle_pedido_pedido_id_fkey')->references('id')->on('pedido')->cascadeOnDelete();
            $table->foreign('variante_id', 'detalle_pedido_variante_id_fkey')->references('id')->on('variante_producto');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE detalle_pedido ADD CONSTRAINT detalle_pedido_cantidad_check CHECK (cantidad > 0)');
            DB::statement('ALTER TABLE detalle_pedido ADD CONSTRAINT detalle_pedido_precio_unitario_check CHECK (precio_unitario >= 0)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('detalle_pedido');
    }
};
