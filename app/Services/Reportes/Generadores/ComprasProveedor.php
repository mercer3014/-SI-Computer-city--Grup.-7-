<?php

namespace App\Services\Reportes\Generadores;

use App\Services\Reportes\GeneradorReporte;
use App\Services\Reportes\PeriodoReporte;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Cantidad de compras y monto comprado a cada proveedor, sin las compras anuladas.
 *
 * @phpstan-import-type Datos from GeneradorReporte
 */
class ComprasProveedor extends GeneradorBase
{
    private const COLUMNAS = [
        ['clave' => 'proveedor', 'etiqueta' => 'Proveedor', 'tipo' => 'texto'],
        ['clave' => 'compras', 'etiqueta' => 'Compras', 'tipo' => 'entero'],
        ['clave' => 'monto', 'etiqueta' => 'Monto comprado', 'tipo' => 'moneda'],
    ];

    /**
     * @param  array<string, int|string>  $filtros
     * @return Datos
     */
    public function generar(PeriodoReporte $periodo, array $filtros): array
    {
        $proveedor = $this->entero($filtros, 'proveedor_id');

        $totales = DB::table('detalle_compra')
            ->select('compra_id')
            ->selectRaw('sum(cantidad * costo_unitario) as total')
            ->groupBy('compra_id');

        $registros = DB::table('compra as c')
            ->joinSub($totales, 'dc', 'dc.compra_id', '=', 'c.id')
            ->join('proveedor as p', 'p.id', '=', 'c.proveedor_id')
            ->where(fn (Builder $q) => $q->whereNull('c.estado')->orWhere('c.estado', '<>', 'ANULADA'))
            ->whereBetween('c.fecha_compra', [$periodo->inicio(), $periodo->fin()])
            ->when($proveedor !== null, fn (Builder $q) => $q->where('p.id', $proveedor))
            ->select('p.razon_social as proveedor')
            ->selectRaw('count(c.id) as compras')
            ->selectRaw('sum(dc.total) as monto')
            ->groupBy('p.id', 'p.razon_social')
            ->orderByDesc('monto')
            ->orderBy('p.razon_social')
            ->get();

        if ($registros->isEmpty()) {
            return $this->vacio(self::COLUMNAS);
        }

        $filas = [];

        foreach ($registros as $registro) {
            $filas[] = [
                'proveedor' => (string) $registro->proveedor,
                'compras' => (int) $registro->compras,
                'monto' => $this->dinero($registro->monto),
            ];
        }

        return [
            'columnas' => self::COLUMNAS,
            'filas' => $filas,
            'totales' => [
                'proveedor' => 'Total',
                'compras' => (int) array_sum(array_column($filas, 'compras')),
                'monto' => round(array_sum(array_column($filas, 'monto')), 2),
            ],
            'advertencias' => [],
            'grafico' => [
                'tipo' => 'barra',
                'etiquetas' => array_map(fn (array $fila): string => (string) $fila['proveedor'], $filas),
                'series' => [[
                    'nombre' => 'Monto comprado',
                    'valores' => array_map(fn (array $fila): float => (float) $fila['monto'], $filas),
                ]],
            ],
        ];
    }
}
