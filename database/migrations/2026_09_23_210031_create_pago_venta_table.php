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
        Schema::create('pago_venta', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('venta_id');
            $table->integer('metodo_pago_id');
            $table->decimal('monto', 12, 2);
            $table->string('referencia', 100)->nullable();
            $table->timestamp('fecha', 6)->nullable()->useCurrent();
            $table->text('observacion')->nullable();
            $table->foreign('venta_id', 'pago_venta_venta_id_fkey')->references('id')->on('venta')->cascadeOnDelete();
            $table->foreign('metodo_pago_id', 'pago_venta_metodo_pago_id_fkey')->references('id')->on('metodo_pago');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE pago_venta ADD CONSTRAINT pago_venta_monto_check CHECK (monto > 0)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pago_venta');
    }
};
