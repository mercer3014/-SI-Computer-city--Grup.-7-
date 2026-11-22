<?php

namespace App\Services\Caja;

use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * CU15 y CU16: consolida los movimientos de dinero en columnas homogéneas.
 *
 * Reúne cuatro orígenes: cobros de ventas (pago_venta), compras a proveedores, gastos
 * operativos y movimientos manuales de caja. Los gastos también dejan su EGRESO en
 * movimiento_caja, pero se cuentan desde la tabla gasto para no duplicarlos.
 * Las ventas y compras anuladas no entran; los gastos y movimientos anulados sí aparecen,
 * con estado ANULADO, para que quien consume el flujo decida si los suma.
 */
class FlujoCajaService
{
    public function consulta(CarbonImmutable $desde, CarbonImmutable $hasta): Builder
    {
        $inicio = $desde->startOfDay()->toDateTimeString();
        $fin = $hasta->endOfDay()->toDateTimeString();

        $ventas = DB::table('pago_venta as pv')
            ->join('venta as v', 'v.id', '=', 'pv.venta_id')
            ->leftJoin('metodo_pago as mp', 'mp.id', '=', 'pv.metodo_pago_id')
            ->leftJoin('cliente as cl', 'cl.id', '=', 'v.cliente_id')
            ->where('v.estado', '<>', 'ANULADA')
            ->whereBetween('pv.fecha', [$inicio, $fin])
            ->selectRaw("'VENTA' as origen")
            ->selectRaw('pv.id as origen_id')
            ->selectRaw("'INGRESO' as tipo")
            ->selectRaw("case when v.pedido_id is null then 'Ventas en tienda' else 'Ventas en línea' end as categoria")
            ->selectRaw("('Venta ' || v.numero_venta || ' · ' || coalesce(cl.nombre, 'Cliente Final')) as descripcion")
            ->selectRaw('mp.nombre as metodo')
            ->selectRaw('coalesce(pv.referencia, v.numero_venta) as referencia')
            ->selectRaw('pv.monto as monto')
            ->selectRaw('pv.fecha as fecha')
            ->selectRaw("'ACTIVO' as estado");

        $totalesCompra = DB::table('detalle_compra')
            ->select('compra_id')
            ->selectRaw('sum(cantidad * costo_unitario) as total')
            ->groupBy('compra_id');

        $compras = DB::table('compra as c')
            ->joinSub($totalesCompra, 'dc', 'dc.compra_id', '=', 'c.id')
            ->leftJoin('proveedor as p', 'p.id', '=', 'c.proveedor_id')
            ->leftJoin('metodo_pago as mp', 'mp.id', '=', 'c.metodo_pago_id')
            ->where(fn (Builder $q) => $q->whereNull('c.estado')->orWhere('c.estado', '<>', 'ANULADA'))
            ->whereBetween('c.fecha_compra', [$inicio, $fin])
            ->selectRaw("'COMPRA' as origen")
            ->selectRaw('c.id as origen_id')
            ->selectRaw("'EGRESO' as tipo")
            ->selectRaw("'Compras a proveedores' as categoria")
            ->selectRaw("('Compra ' || c.numero_compra || ' · ' || coalesce(p.razon_social, 'Proveedor')) as descripcion")
            ->selectRaw('mp.nombre as metodo')
            ->selectRaw('c.numero_documento as referencia')
            ->selectRaw('dc.total as monto')
            ->selectRaw('c.fecha_compra as fecha')
            ->selectRaw("'ACTIVO' as estado");

        $gastos = DB::table('gasto as g')
            ->leftJoin('categoria_gasto as cg', 'cg.id', '=', 'g.categoria_gasto_id')
            ->leftJoin('metodo_pago as mp', 'mp.id', '=', 'g.metodo_pago_id')
            ->whereBetween('g.fecha', [$inicio, $fin])
            ->selectRaw("'GASTO' as origen")
            ->selectRaw('g.id as origen_id')
            ->selectRaw("'EGRESO' as tipo")
            ->selectRaw("coalesce(cg.nombre, 'Gasto operativo') as categoria")
            ->selectRaw('g.concepto as descripcion')
            ->selectRaw('mp.nombre as metodo')
            ->selectRaw('g.referencia as referencia')
            ->selectRaw('g.monto as monto')
            ->selectRaw('g.fecha as fecha')
            ->selectRaw("coalesce(g.estado, 'ACTIVO') as estado");

        $manuales = DB::table('movimiento_caja as mc')
            ->leftJoin('metodo_pago as mp', 'mp.id', '=', 'mc.metodo_pago_id')
            ->whereNull('mc.gasto_id')
            ->whereBetween('mc.fecha', [$inicio, $fin])
            ->selectRaw("'MOVIMIENTO' as origen")
            ->selectRaw('mc.id as origen_id')
            ->selectRaw('mc.tipo as tipo')
            ->selectRaw("coalesce(mc.categoria, 'Sin categoría') as categoria")
            ->selectRaw('mc.descripcion as descripcion')
            ->selectRaw('mp.nombre as metodo')
            ->selectRaw('mc.referencia as referencia')
            ->selectRaw('mc.monto as monto')
            ->selectRaw('mc.fecha as fecha')
            ->selectRaw("coalesce(mc.estado, 'ACTIVO') as estado");

        return $ventas->unionAll($compras)->unionAll($gastos)->unionAll($manuales);
    }
}
