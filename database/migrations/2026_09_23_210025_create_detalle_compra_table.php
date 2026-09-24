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
        Schema::create('detalle_compra', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('compra_id');
            $table->integer('variante_id');
            $table->integer('cantidad');
            $table->decimal('costo_unitario', 12, 2);
            $table->decimal('descuento', 12, 2)->nullable()->default(0);
            $table->string('numero_serie', 100)->nullable();
            $table->foreign('compra_id', 'detalle_compra_compra_id_fkey')->references('id')->on('compra')->cascadeOnDelete();
            $table->foreign('variante_id', 'detalle_compra_variante_id_fkey')->references('id')->on('variante_producto');
        });

        DB::statement('ALTER TABLE detalle_compra ADD CONSTRAINT detalle_compra_cantidad_check CHECK (cantidad > 0)');
        DB::statement('ALTER TABLE detalle_compra ADD CONSTRAINT detalle_compra_costo_unitario_check CHECK (costo_unitario >= 0)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('detalle_compra');
    }
};
