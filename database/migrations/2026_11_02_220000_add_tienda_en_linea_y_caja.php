<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * CU15 (caja), CU18 (catálogo público) y CU21 (pasarela de pagos).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('usuario', function (Blueprint $table) {
            $table->integer('cliente_id')->nullable()->unique('usuario_cliente_id_key');
        });

        Schema::table('movimiento_caja', function (Blueprint $table) {
            $table->integer('gasto_id')->nullable();
            $table->integer('metodo_pago_id')->nullable();
            $table->string('referencia', 100)->nullable();
            $table->string('estado', 20)->nullable()->default('ACTIVO');
            $table->text('motivo_anulacion')->nullable();
        });

        Schema::table('gasto', function (Blueprint $table) {
            $table->text('motivo_anulacion')->nullable();
        });

        Schema::table('pedido', function (Blueprint $table) {
            $table->integer('usuario_id')->nullable();
            $table->decimal('total', 12, 2)->nullable();
            $table->string('stripe_payment_intent_id', 100)->nullable()->unique('pedido_stripe_payment_intent_id_key');
            $table->timestamp('fecha_pago', 6)->nullable();
            $table->text('error_pago')->nullable();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE usuario ADD CONSTRAINT usuario_cliente_id_fkey FOREIGN KEY (cliente_id) REFERENCES cliente(id) ON DELETE SET NULL');
            DB::statement('ALTER TABLE movimiento_caja ADD CONSTRAINT movimiento_caja_gasto_id_fkey FOREIGN KEY (gasto_id) REFERENCES gasto(id) ON DELETE SET NULL');
            DB::statement('ALTER TABLE movimiento_caja ADD CONSTRAINT movimiento_caja_metodo_pago_id_fkey FOREIGN KEY (metodo_pago_id) REFERENCES metodo_pago(id)');
            DB::statement('ALTER TABLE pedido ADD CONSTRAINT pedido_usuario_id_fkey FOREIGN KEY (usuario_id) REFERENCES usuario(id) ON DELETE SET NULL');
        }

        if (! DB::table('metodo_pago')->where('codigo', 'TARJETA')->exists()) {
            DB::table('metodo_pago')->insert([
                'codigo' => 'TARJETA',
                'nombre' => 'Tarjeta (Stripe)',
                'estado' => 'ACTIVO',
            ]);
        }

        if (! DB::table('rol')->where('nombre', 'Cliente Web')->exists()) {
            DB::table('rol')->insert([
                'nombre' => 'Cliente Web',
                'descripcion' => 'Cliente registrado desde la tienda en línea. Sin acceso al POS.',
                'es_sistema' => true,
                'estado' => 'ACTIVO',
            ]);
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE usuario DROP CONSTRAINT IF EXISTS usuario_cliente_id_fkey');
            DB::statement('ALTER TABLE movimiento_caja DROP CONSTRAINT IF EXISTS movimiento_caja_gasto_id_fkey');
            DB::statement('ALTER TABLE movimiento_caja DROP CONSTRAINT IF EXISTS movimiento_caja_metodo_pago_id_fkey');
            DB::statement('ALTER TABLE pedido DROP CONSTRAINT IF EXISTS pedido_usuario_id_fkey');
        }

        Schema::table('pedido', function (Blueprint $table) {
            $table->dropUnique('pedido_stripe_payment_intent_id_key');
            $table->dropColumn(['usuario_id', 'total', 'stripe_payment_intent_id', 'fecha_pago', 'error_pago']);
        });

        Schema::table('gasto', function (Blueprint $table) {
            $table->dropColumn('motivo_anulacion');
        });

        Schema::table('movimiento_caja', function (Blueprint $table) {
            $table->dropColumn(['gasto_id', 'metodo_pago_id', 'referencia', 'estado', 'motivo_anulacion']);
        });

        Schema::table('usuario', function (Blueprint $table) {
            $table->dropUnique('usuario_cliente_id_key');
            $table->dropColumn('cliente_id');
        });

        DB::table('rol')->where('nombre', 'Cliente Web')->delete();
    }
};
