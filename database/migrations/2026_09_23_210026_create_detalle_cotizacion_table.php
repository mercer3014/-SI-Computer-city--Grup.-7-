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
        Schema::create('detalle_cotizacion', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('cotizacion_id');
            $table->integer('variante_id');
            $table->integer('cantidad');
            $table->decimal('precio_unitario', 12, 2);
            $table->decimal('descuento', 12, 2)->nullable()->default(0);
            $table->text('descripcion')->nullable();
            $table->foreign('cotizacion_id', 'detalle_cotizacion_cotizacion_id_fkey')->references('id')->on('cotizacion')->cascadeOnDelete();
            $table->foreign('variante_id', 'detalle_cotizacion_variante_id_fkey')->references('id')->on('variante_producto');
        });

        DB::statement('ALTER TABLE detalle_cotizacion ADD CONSTRAINT detalle_cotizacion_cantidad_check CHECK (cantidad > 0)');
        DB::statement('ALTER TABLE detalle_cotizacion ADD CONSTRAINT detalle_cotizacion_precio_unitario_check CHECK (precio_unitario >= 0)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('detalle_cotizacion');
    }
};
