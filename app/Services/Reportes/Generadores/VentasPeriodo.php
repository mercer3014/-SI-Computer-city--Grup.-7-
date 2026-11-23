<?php

namespace App\Services\Reportes\Generadores;

use App\Services\Reportes\AgrupadorFecha;
use App\Services\Reportes\Fechas;
use App\Services\Reportes\GeneradorReporte;
use App\Services\Reportes\PeriodoReporte;
use Illuminate\Support\Facades\DB;

/**
 * Ventas por período: cantidad, ingresos, costo y utilidad bruta, con desglose por día, semana o mes.
 * Sin filtros, en PostgreSQL el total sale de fn_reporte_ventas_periodo.
 *
 * @phpstan-import-type Datos from GeneradorReporte
 */
class VentasPeriodo extends GeneradorBase
{
    private const COLUMNAS = [
        ['clave' => 'periodo', 'etiqueta' => 'Período', 'tipo' => 'texto'],
        ['clave' => 'ventas', 'etiqueta' => 'Ventas', 'tipo' => 'entero'],
        ['clave' => 'ingresos', 'etiqueta' => 'Ingresos', 'tipo' => 'moneda'],
        ['clave' => 'costo', 'etiqueta' => 'Costo', 'tipo' => 'moneda'],
        ['clave' => 'utilidad', 'etiqueta' => 'Utilidad bruta', 'tipo' => 'moneda'],
    ];

    /**
     * @param  array<string, int|string>  $filtros
     * @return Datos
     */
    public function generar(PeriodoReporte $periodo, array $filtros): array
    {
        $pedido = $filtros['agrupar'] ?? null;
        $nivel = is_string($pedido) && in_array($pedido, AgrupadorFecha::NIVELES, true)
            ? $pedido
            : AgrupadorFecha::sugerido($periodo->dias());
        $grupo = AgrupadorFecha::expresion('v.fecha', $nivel);

        $registros = $this->lineasDeVenta($periodo, $filtros)
            ->selectRaw("$grupo as grupo")
            ->selectRaw('count(distinct v.id) as ventas')
            ->selectRaw(self::SUMA_INGRESO.' as ingresos')
            ->selectRaw(self::SUMA_COSTO.' as costo')
            ->selectRaw(self::SUMA_UTILIDAD.' as utilidad')
            ->groupBy('grupo')
            ->orderBy('grupo')
            ->get();

        if ($registros->isEmpty()) {
            return $this->vacio(self::COLUMNAS);
        }

        $filas = [];

        foreach ($registros as $registro) {
            $filas[] = [
                'periodo' => Fechas::etiquetaGrupo((string) $registro->grupo, $nivel),
                'ventas' => (int) $registro->ventas,
                'ingresos' => $this->dinero($registro->ingresos),
                'costo' => $this->dinero($registro->costo),
                'utilidad' => $this->dinero($registro->utilidad),
            ];
        }

        return [
            'columnas' => self::COLUMNAS,
            'filas' => $filas,
            'totales' => $this->totales($periodo, $filtros, $filas),
            'advertencias' => $this->advertenciaDeCosto($periodo, $filtros),
            'grafico' => [
                'tipo' => count($filas) > 1 ? 'linea' : 'barra',
                'etiquetas' => array_map(fn (array $fila): string => (string) $fila['periodo'], $filas),
                'series' => [
                    ['nombre' => 'Ingresos', 'valores' => array_map(fn (array $fila): float => (float) $fila['ingresos'], $filas)],
                    ['nombre' => 'Utilidad bruta', 'valores' => array_map(fn (array $fila): float => (float) $fila['utilidad'], $filas)],
                ],
            ],
        ];
    }

    /**
     * @param  array<string, int|string>  $filtros
     * @param  list<array<string, string|int|float|null>>  $filas
     * @return array<string, string|int|float|null>
     */
    private function totales(PeriodoReporte $periodo, array $filtros, array $filas): array
    {
        $filtrado = array_intersect_key($filtros, array_flip(['categoria_id', 'marca_id', 'metodo_pago_id'])) !== [];

        if ($this->puedeUsarFuncion() && ! $filtrado) {
            $total = DB::selectOne(
                'select cantidad_ventas, total_ingresos, total_costo, utilidad_bruta from fn_reporte_ventas_periodo(?, ?)',
                [$periodo->desde->toDateString(), $periodo->hasta->toDateString()],
            );

            if ($total !== null) {
                return [
                    'periodo' => 'Total',
                    'ventas' => (int) $total->cantidad_ventas,
                    'ingresos' => $this->dinero($total->total_ingresos),
                    'costo' => $this->dinero($total->total_costo),
                    'utilidad' => $this->dinero($total->utilidad_bruta),
                ];
            }
        }

        return [
            'periodo' => 'Total',
            'ventas' => (int) array_sum(array_column($filas, 'ventas')),
            'ingresos' => round(array_sum(array_column($filas, 'ingresos')), 2),
            'costo' => round(array_sum(array_column($filas, 'costo')), 2),
            'utilidad' => round(array_sum(array_column($filas, 'utilidad')), 2),
        ];
    }
}
