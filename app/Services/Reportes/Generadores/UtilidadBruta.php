<?php

namespace App\Services\Reportes\Generadores;

use App\Services\Reportes\GeneradorReporte;
use App\Services\Reportes\PeriodoReporte;

/**
 * Ingresos menos el costo de lo vendido, por categoría, con el costo histórico de cada venta.
 *
 * @phpstan-import-type Datos from GeneradorReporte
 */
class UtilidadBruta extends GeneradorBase
{
    private const COLUMNAS = [
        ['clave' => 'categoria', 'etiqueta' => 'Categoría', 'tipo' => 'texto'],
        ['clave' => 'ingresos', 'etiqueta' => 'Ingresos', 'tipo' => 'moneda'],
        ['clave' => 'costo', 'etiqueta' => 'Costo', 'tipo' => 'moneda'],
        ['clave' => 'utilidad', 'etiqueta' => 'Utilidad bruta', 'tipo' => 'moneda'],
        ['clave' => 'margen', 'etiqueta' => 'Margen', 'tipo' => 'porcentaje'],
    ];

    /**
     * @param  array<string, int|string>  $filtros
     * @return Datos
     */
    public function generar(PeriodoReporte $periodo, array $filtros): array
    {
        $registros = $this->lineasDeVenta($periodo, $filtros)
            ->join('categoria as c', 'c.id', '=', 'p.categoria_id')
            ->select('c.nombre as categoria')
            ->selectRaw(self::SUMA_INGRESO.' as ingresos')
            ->selectRaw(self::SUMA_COSTO.' as costo')
            ->selectRaw(self::SUMA_UTILIDAD.' as utilidad')
            ->selectRaw(self::SUMA_INGRESO_CON_COSTO.' as ingresos_con_costo')
            ->groupBy('c.nombre')
            ->orderByDesc('utilidad')
            ->orderBy('c.nombre')
            ->get();

        if ($registros->isEmpty()) {
            return $this->vacio(self::COLUMNAS);
        }

        $filas = [];
        $ingresos = 0.0;
        $costo = 0.0;
        $utilidad = 0.0;
        $costeados = 0.0;

        foreach ($registros as $registro) {
            $conCosto = $this->dinero($registro->ingresos_con_costo);

            $filas[] = [
                'categoria' => (string) $registro->categoria,
                'ingresos' => $this->dinero($registro->ingresos),
                'costo' => $this->dinero($registro->costo),
                'utilidad' => $this->dinero($registro->utilidad),
                'margen' => $this->margen($this->dinero($registro->utilidad), $conCosto),
            ];

            $ingresos += $this->dinero($registro->ingresos);
            $costo += $this->dinero($registro->costo);
            $utilidad += $this->dinero($registro->utilidad);
            $costeados += $conCosto;
        }

        return [
            'columnas' => self::COLUMNAS,
            'filas' => $filas,
            'totales' => [
                'categoria' => 'Total',
                'ingresos' => round($ingresos, 2),
                'costo' => round($costo, 2),
                'utilidad' => round($utilidad, 2),
                'margen' => $this->margen($utilidad, $costeados),
            ],
            'advertencias' => $this->advertenciaDeCosto($periodo, $filtros),
            'grafico' => [
                'tipo' => 'barra',
                'etiquetas' => array_map(fn (array $fila): string => (string) $fila['categoria'], $filas),
                'series' => [
                    ['nombre' => 'Ingresos', 'valores' => array_map(fn (array $fila): float => (float) $fila['ingresos'], $filas)],
                    ['nombre' => 'Utilidad bruta', 'valores' => array_map(fn (array $fila): float => (float) $fila['utilidad'], $filas)],
                ],
            ],
        ];
    }

    /** Utilidad sobre los ingresos de las líneas que sí tienen costo. */
    private function margen(float $utilidad, float $ingresosConCosto): float
    {
        return $ingresosConCosto > 0 ? round($utilidad / $ingresosConCosto * 100, 2) : 0.0;
    }
}
