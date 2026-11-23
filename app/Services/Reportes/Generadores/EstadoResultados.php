<?php

namespace App\Services\Reportes\Generadores;

use App\Services\Reportes\AgrupadorFecha;
use App\Services\Reportes\Fechas;
use App\Services\Reportes\GeneradorReporte;
use App\Services\Reportes\PeriodoReporte;
use Illuminate\Support\Facades\DB;

/**
 * Estado de resultados mensual: ingresos operativos, costo de ventas, utilidad bruta,
 * gastos operativos (sin los anulados) y utilidad neta.
 *
 * @phpstan-import-type Datos from GeneradorReporte
 */
class EstadoResultados extends GeneradorBase
{
    private const COLUMNAS = [
        ['clave' => 'mes', 'etiqueta' => 'Mes', 'tipo' => 'texto'],
        ['clave' => 'ingresos', 'etiqueta' => 'Ingresos operativos', 'tipo' => 'moneda'],
        ['clave' => 'costo_ventas', 'etiqueta' => 'Costo de ventas', 'tipo' => 'moneda'],
        ['clave' => 'utilidad_bruta', 'etiqueta' => 'Utilidad bruta', 'tipo' => 'moneda'],
        ['clave' => 'gastos', 'etiqueta' => 'Gastos operativos', 'tipo' => 'moneda'],
        ['clave' => 'utilidad_neta', 'etiqueta' => 'Utilidad neta', 'tipo' => 'moneda'],
    ];

    /**
     * @param  array<string, int|string>  $filtros
     * @return Datos
     */
    public function generar(PeriodoReporte $periodo, array $filtros): array
    {
        $mesVenta = AgrupadorFecha::expresion('v.fecha', AgrupadorFecha::MES);
        $mesGasto = AgrupadorFecha::expresion('g.fecha', AgrupadorFecha::MES);

        $ventas = $this->lineasDeVenta($periodo, [])
            ->selectRaw("$mesVenta as mes")
            ->selectRaw(self::SUMA_INGRESO.' as ingresos')
            ->selectRaw(self::SUMA_COSTO.' as costo')
            ->selectRaw(self::SUMA_UTILIDAD.' as utilidad')
            ->groupBy('mes')
            ->get()
            ->keyBy('mes');

        $gastos = DB::table('gasto as g')
            ->where(fn ($q) => $q->whereNull('g.estado')->orWhere('g.estado', '<>', 'ANULADO'))
            ->whereBetween('g.fecha', [$periodo->inicio(), $periodo->fin()])
            ->selectRaw("$mesGasto as mes")
            ->selectRaw('sum(g.monto) as total')
            ->groupBy('mes')
            ->get()
            ->keyBy('mes');

        if ($ventas->isEmpty() && $gastos->isEmpty()) {
            return $this->vacio(self::COLUMNAS);
        }

        $filas = [];
        $mes = $periodo->desde->startOfMonth();

        while ($mes->lessThanOrEqualTo($periodo->hasta)) {
            $clave = $mes->format('Y-m');
            $venta = $ventas->get($clave);
            $utilidadBruta = $this->dinero($venta->utilidad ?? 0);
            $gasto = $this->dinero($gastos->get($clave)->total ?? 0);

            $filas[] = [
                'mes' => Fechas::etiquetaGrupo($clave, AgrupadorFecha::MES),
                'ingresos' => $this->dinero($venta->ingresos ?? 0),
                'costo_ventas' => $this->dinero($venta->costo ?? 0),
                'utilidad_bruta' => $utilidadBruta,
                'gastos' => $gasto,
                'utilidad_neta' => round($utilidadBruta - $gasto, 2),
            ];

            $mes = $mes->addMonth();
        }

        return [
            'columnas' => self::COLUMNAS,
            'filas' => $filas,
            'totales' => [
                'mes' => 'Total',
                'ingresos' => round(array_sum(array_column($filas, 'ingresos')), 2),
                'costo_ventas' => round(array_sum(array_column($filas, 'costo_ventas')), 2),
                'utilidad_bruta' => round(array_sum(array_column($filas, 'utilidad_bruta')), 2),
                'gastos' => round(array_sum(array_column($filas, 'gastos')), 2),
                'utilidad_neta' => round(array_sum(array_column($filas, 'utilidad_neta')), 2),
            ],
            'advertencias' => $this->advertenciaDeCosto($periodo, []),
            'grafico' => [
                'tipo' => 'barra',
                'etiquetas' => array_map(fn (array $fila): string => (string) $fila['mes'], $filas),
                'series' => [
                    ['nombre' => 'Ingresos', 'valores' => array_map(fn (array $fila): float => (float) $fila['ingresos'], $filas)],
                    ['nombre' => 'Gastos', 'valores' => array_map(fn (array $fila): float => (float) $fila['gastos'], $filas)],
                    ['nombre' => 'Utilidad neta', 'valores' => array_map(fn (array $fila): float => (float) $fila['utilidad_neta'], $filas)],
                ],
            ],
        ];
    }
}
