<?php

namespace App\Services\Reportes\Generadores;

use App\Services\Reportes\GeneradorReporte;
use App\Services\Reportes\PeriodoReporte;
use Illuminate\Support\Facades\DB;

/**
 * Ranking por unidades vendidas. Sin filtros de categoría ni marca, en PostgreSQL usa fn_productos_mas_vendidos.
 *
 * @phpstan-import-type Datos from GeneradorReporte
 */
class ProductosMasVendidos extends GeneradorBase
{
    public const LIMITE_POR_DEFECTO = 10;

    private const COLUMNAS = [
        ['clave' => 'posicion', 'etiqueta' => '#', 'tipo' => 'entero'],
        ['clave' => 'sku', 'etiqueta' => 'SKU', 'tipo' => 'texto'],
        ['clave' => 'producto', 'etiqueta' => 'Producto', 'tipo' => 'texto'],
        ['clave' => 'unidades', 'etiqueta' => 'Unidades', 'tipo' => 'entero'],
        ['clave' => 'total', 'etiqueta' => 'Monto vendido', 'tipo' => 'moneda'],
    ];

    /**
     * @param  array<string, int|string>  $filtros
     * @return Datos
     */
    public function generar(PeriodoReporte $periodo, array $filtros): array
    {
        $limite = min(50, max(1, $this->entero($filtros, 'limite') ?? self::LIMITE_POR_DEFECTO));
        $registros = $this->ranking($periodo, $filtros, $limite);

        if ($registros === []) {
            return $this->vacio(self::COLUMNAS);
        }

        $filas = [];

        foreach ($registros as $indice => $registro) {
            $filas[] = [
                'posicion' => $indice + 1,
                'sku' => $registro['sku'],
                'producto' => $registro['producto'],
                'unidades' => $registro['unidades'],
                'total' => $registro['total'],
            ];
        }

        return [
            'columnas' => self::COLUMNAS,
            'filas' => $filas,
            'totales' => [
                'posicion' => null,
                'sku' => null,
                'producto' => 'Total',
                'unidades' => (int) array_sum(array_column($filas, 'unidades')),
                'total' => round(array_sum(array_column($filas, 'total')), 2),
            ],
            'advertencias' => [],
            'grafico' => [
                'tipo' => 'barra',
                'etiquetas' => array_map(fn (array $fila): string => (string) $fila['producto'], $filas),
                'series' => [[
                    'nombre' => 'Unidades vendidas',
                    'valores' => array_map(fn (array $fila): float => (float) $fila['unidades'], $filas),
                ]],
            ],
        ];
    }

    /**
     * @param  array<string, int|string>  $filtros
     * @return list<array{sku: string, producto: string, unidades: int, total: float}>
     */
    private function ranking(PeriodoReporte $periodo, array $filtros, int $limite): array
    {
        $filtrado = $this->entero($filtros, 'categoria_id') !== null || $this->entero($filtros, 'marca_id') !== null;
        $lista = [];

        if ($this->puedeUsarFuncion() && ! $filtrado) {
            $registros = DB::select(
                'select sku, producto, unidades_vendidas, total_vendido from fn_productos_mas_vendidos(?, ?, ?)',
                [$periodo->desde->toDateString(), $periodo->hasta->toDateString(), $limite],
            );

            foreach ($registros as $registro) {
                $lista[] = [
                    'sku' => (string) $registro->sku,
                    'producto' => (string) $registro->producto,
                    'unidades' => (int) $registro->unidades_vendidas,
                    'total' => $this->dinero($registro->total_vendido),
                ];
            }

            return $lista;
        }

        $registros = $this->lineasDeVenta($periodo, $filtros)
            ->select('vp.sku', 'p.nombre as producto')
            ->selectRaw('sum(dv.cantidad) as unidades')
            ->selectRaw(self::SUMA_INGRESO.' as total')
            ->groupBy('vp.sku', 'p.nombre')
            ->orderByDesc('unidades')
            ->orderBy('vp.sku')
            ->limit($limite)
            ->get();

        foreach ($registros as $registro) {
            $lista[] = [
                'sku' => (string) $registro->sku,
                'producto' => (string) $registro->producto,
                'unidades' => (int) $registro->unidades,
                'total' => $this->dinero($registro->total),
            ];
        }

        return $lista;
    }
}
