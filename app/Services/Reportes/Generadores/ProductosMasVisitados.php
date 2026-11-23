<?php

namespace App\Services\Reportes\Generadores;

use App\Models\EventoProducto;
use App\Services\Reportes\GeneradorReporte;
use App\Services\Reportes\PeriodoReporte;
use Illuminate\Support\Facades\DB;

/**
 * CU20: ranking de productos por visitas al catálogo público (`evento_producto`),
 * con las unidades vendidas del mismo período como columna comparativa.
 *
 * @phpstan-import-type Datos from GeneradorReporte
 */
class ProductosMasVisitados extends GeneradorBase
{
    public const LIMITE = 20;

    private const COLUMNAS = [
        ['clave' => 'posicion', 'etiqueta' => '#', 'tipo' => 'entero'],
        ['clave' => 'producto', 'etiqueta' => 'Producto', 'tipo' => 'texto'],
        ['clave' => 'visitas', 'etiqueta' => 'Visitas', 'tipo' => 'entero'],
        ['clave' => 'unidades', 'etiqueta' => 'Unidades vendidas', 'tipo' => 'entero'],
    ];

    /**
     * @param  array<string, int|string>  $filtros
     * @return Datos
     */
    public function generar(PeriodoReporte $periodo, array $filtros): array
    {
        $visitas = DB::table('evento_producto as e')
            ->join('producto as p', 'p.id', '=', 'e.producto_id')
            ->where('e.tipo_evento', EventoProducto::VISTA)
            ->whereBetween('e.fecha_hora', [$periodo->inicio(), $periodo->fin()])
            ->select('p.id', 'p.nombre')
            ->selectRaw('count(*) as visitas')
            ->groupBy('p.id', 'p.nombre')
            ->orderByDesc('visitas')
            ->orderBy('p.nombre')
            ->limit(self::LIMITE)
            ->get();

        if ($visitas->isEmpty()) {
            return $this->vacio(self::COLUMNAS);
        }

        $vendidas = $this->lineasDeVenta($periodo, [])
            ->whereIn('p.id', $visitas->pluck('id')->all())
            ->select('p.id')
            ->selectRaw('sum(dv.cantidad) as unidades')
            ->groupBy('p.id')
            ->pluck('unidades', 'id');

        $filas = [];

        foreach ($visitas as $indice => $registro) {
            $filas[] = [
                'posicion' => $indice + 1,
                'producto' => (string) $registro->nombre,
                'visitas' => (int) $registro->visitas,
                'unidades' => (int) ($vendidas[$registro->id] ?? 0),
            ];
        }

        return [
            'columnas' => self::COLUMNAS,
            'filas' => $filas,
            'totales' => [
                'posicion' => null,
                'producto' => 'Total',
                'visitas' => (int) array_sum(array_column($filas, 'visitas')),
                'unidades' => (int) array_sum(array_column($filas, 'unidades')),
            ],
            'advertencias' => [],
            'grafico' => [
                'tipo' => 'barra',
                'etiquetas' => array_map(fn (array $fila): string => (string) $fila['producto'], $filas),
                'series' => [
                    [
                        'nombre' => 'Visitas',
                        'valores' => array_map(fn (array $fila): float => (float) $fila['visitas'], $filas),
                    ],
                    [
                        'nombre' => 'Unidades vendidas',
                        'valores' => array_map(fn (array $fila): float => (float) $fila['unidades'], $filas),
                    ],
                ],
            ],
        ];
    }
}
