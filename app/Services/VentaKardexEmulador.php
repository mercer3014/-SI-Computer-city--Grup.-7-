<?php

namespace App\Services;

use App\Models\DetalleVenta;
use App\Models\MovimientoInventario;
use App\Models\VarianteProducto;
use App\Models\Venta;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Replica en SQLite (tests) lo que en PostgreSQL hacen los triggers:
 * trg_venta_generar_movimiento, trg_detalle_venta_validar_y_garantia,
 * trg_detalle_venta_actualizar_stock y trg_venta_restaurar_stock_anulacion.
 *
 * En producción el controlador NO actualiza stock: lo dejan las reglas de la BD.
 */
class VentaKardexEmulador
{
    public static function activo(): bool
    {
        return DB::getDriverName() !== 'pgsql';
    }

    public static function alCrearVenta(Venta $venta): void
    {
        if (! self::activo()) {
            return;
        }

        MovimientoInventario::query()->create([
            'usuario_id' => $venta->usuario_id,
            'venta_id' => $venta->id,
            'tipo' => 'SALIDA',
            'fecha' => $venta->fecha ?? now(),
            'referencia' => $venta->numero_venta,
        ]);
    }

    public static function alCrearDetalle(DetalleVenta $detalle): void
    {
        if (! self::activo()) {
            return;
        }

        $variante = VarianteProducto::query()->find($detalle->variante_id);

        if ($variante === null) {
            throw ValidationException::withMessages([
                'items' => 'La variante de producto '.$detalle->variante_id.' no existe.',
            ]);
        }

        if ($variante->stock_actual < $detalle->cantidad) {
            throw ValidationException::withMessages([
                'items' => 'Stock insuficiente para el producto (disponible: '.$variante->stock_actual.', solicitado: '.$detalle->cantidad.').',
            ]);
        }

        $fechaVenta = $detalle->venta?->fecha
            ?? Venta::query()->whereKey($detalle->venta_id)->value('fecha')
            ?? now();
        $meses = max(0, (int) $detalle->meses_garantia);

        if ($detalle->fecha_fin_garantia === null) {
            $detalle->fecha_fin_garantia = Carbon::parse($fechaVenta)->addMonths($meses)->toDateString();
        }

        if ($detalle->costo_unitario_snapshot === null) {
            $costo = DB::table('detalle_compra')
                ->where('variante_id', $detalle->variante_id)
                ->orderByDesc('id')
                ->value('costo_unitario');

            $detalle->costo_unitario_snapshot = $costo !== null ? (float) $costo : null;
        }
    }

    public static function despuesDeCrearDetalle(DetalleVenta $detalle): void
    {
        if (! self::activo()) {
            return;
        }

        $movimientoId = MovimientoInventario::query()
            ->where('venta_id', $detalle->venta_id)
            ->orderByDesc('id')
            ->value('id');

        if ($movimientoId === null) {
            return;
        }

        DB::table('detalle_movimiento_inventario')->insert([
            'movimiento_id' => $movimientoId,
            'variante_id' => $detalle->variante_id,
            'cantidad' => $detalle->cantidad,
            'costo_unitario' => $detalle->costo_unitario_snapshot ?? 0,
        ]);

        VarianteProducto::query()
            ->whereKey($detalle->variante_id)
            ->decrement('stock_actual', $detalle->cantidad);
    }

    public static function alAnular(Venta $venta): void
    {
        if (! self::activo()) {
            return;
        }

        if ($venta->estado !== 'ANULADA' || $venta->getOriginal('estado') === 'ANULADA') {
            return;
        }

        $venta->loadMissing('detalles');

        $movimiento = MovimientoInventario::query()->create([
            'usuario_id' => $venta->anulado_por,
            'venta_id' => $venta->id,
            'tipo' => 'AJUSTE',
            'fecha' => now(),
            'referencia' => $venta->numero_venta,
            'observacion' => 'Reingreso de stock por anulación de venta: '.($venta->motivo_anulacion ?? ''),
        ]);

        foreach ($venta->detalles as $linea) {
            DB::table('detalle_movimiento_inventario')->insert([
                'movimiento_id' => $movimiento->id,
                'variante_id' => $linea->variante_id,
                'cantidad' => $linea->cantidad,
                'costo_unitario' => 0,
            ]);

            VarianteProducto::query()
                ->whereKey($linea->variante_id)
                ->increment('stock_actual', $linea->cantidad);
        }

        DetalleVenta::query()
            ->where('venta_id', $venta->id)
            ->update(['estado' => 'DEVUELTO']);
    }
}
