<?php

namespace App\Services\Reportes\Generadores;

use App\Services\Caja\FlujoCajaService;
use App\Services\Reportes\GeneradorReporte;
use App\Services\Reportes\PeriodoReporte;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Flujo de caja (ingresos, egresos y saldo) y gastos por categoría.
 * Usa el mismo consolidado que el módulo de caja (CU15) y solo cuenta lo que está ACTIVO:
 * los gastos y movimientos anulados no suman.
 *
 * @phpstan-import-type Datos from GeneradorReporte
 */
class GastosFlujoCaja extends GeneradorBase
{
    private const COLUMNAS = [
        ['clave' => 'seccion', 'etiqueta' => 'Sección', 'tipo' => 'texto'],
        ['clave' => 'concepto', 'etiqueta' => 'Concepto', 'tipo' => 'texto'],
        ['clave' => 'movimientos', 'etiqueta' => 'Movimientos', 'tipo' => 'entero'],
        ['clave' => 'monto', 'etiqueta' => 'Monto', 'tipo' => 'moneda'],
    ];

    /** @var array<string, array{0: string, 1: string, 2: string}> origen y tipo => [origen, tipo, concepto] */
    private const CONCEPTOS = [
        'VENTA|INGRESO' => ['VENTA', 'INGRESO', 'Ingresos por ventas'],
        'MOVIMIENTO|INGRESO' => ['MOVIMIENTO', 'INGRESO', 'Otros ingresos'],
        'COMPRA|EGRESO' => ['COMPRA', 'EGRESO', 'Compras a proveedores'],
        'GASTO|EGRESO' => ['GASTO', 'EGRESO', 'Gastos operativos'],
        'MOVIMIENTO|EGRESO' => ['MOVIMIENTO', 'EGRESO', 'Otros egresos'],
    ];

    public function __construct(private FlujoCajaService $flujo) {}

    /**
     * @param  array<string, int|string>  $filtros
     * @return Datos
     */
    public function generar(PeriodoReporte $periodo, array $filtros): array
    {
        $movimientos = $this->movimientos($periodo, $filtros);

        $porConcepto = (clone $movimientos)
            ->select('origen', 'tipo')
            ->selectRaw('sum(monto) as total, count(*) as cantidad')
            ->groupBy('origen', 'tipo')
            ->get();

        if ($porConcepto->isEmpty()) {
            return $this->vacio(self::COLUMNAS);
        }

        $montos = [];

        foreach ($porConcepto as $registro) {
            $montos[$registro->origen.'|'.$registro->tipo] = [(int) $registro->cantidad, $this->dinero($registro->total)];
        }

        $filas = [];
        $ingresos = 0.0;
        $egresos = 0.0;
        $cantidad = 0;

        foreach (self::CONCEPTOS as $clave => [, $tipo, $concepto]) {
            [$movs, $monto] = $montos[$clave] ?? [0, 0.0];

            $filas[] = ['seccion' => 'Flujo de caja', 'concepto' => $concepto, 'movimientos' => $movs, 'monto' => $monto];
            $cantidad += $movs;

            if ($tipo === 'INGRESO') {
                $ingresos += $monto;
            } else {
                $egresos += $monto;
            }
        }

        $categorias = (clone $movimientos)
            ->where('origen', 'GASTO')
            ->select('categoria')
            ->selectRaw('sum(monto) as total, count(*) as cantidad')
            ->groupBy('categoria')
            ->orderByDesc('total')
            ->orderBy('categoria')
            ->get();

        foreach ($categorias as $categoria) {
            $filas[] = [
                'seccion' => 'Gastos por categoría',
                'concepto' => (string) $categoria->categoria,
                'movimientos' => (int) $categoria->cantidad,
                'monto' => $this->dinero($categoria->total),
            ];
        }

        $etiquetas = [];
        $valores = [];

        foreach ($categorias as $categoria) {
            $etiquetas[] = (string) $categoria->categoria;
            $valores[] = $this->dinero($categoria->total);
        }

        $conGastos = $etiquetas !== [];

        return [
            'columnas' => self::COLUMNAS,
            'filas' => $filas,
            'totales' => [
                'seccion' => null,
                'concepto' => 'Saldo del período (ingresos − egresos)',
                'movimientos' => $cantidad,
                'monto' => round($ingresos - $egresos, 2),
            ],
            'advertencias' => [],
            'grafico' => [
                'tipo' => $conGastos ? 'torta' : 'barra',
                'etiquetas' => $conGastos ? $etiquetas : ['Ingresos', 'Egresos'],
                'series' => [[
                    'nombre' => $conGastos ? 'Gastos' : 'Flujo',
                    'valores' => $conGastos ? $valores : [round($ingresos, 2), round($egresos, 2)],
                ]],
            ],
        ];
    }

    /**
     * @param  array<string, int|string>  $filtros
     */
    private function movimientos(PeriodoReporte $periodo, array $filtros): Builder
    {
        $consulta = DB::query()
            ->fromSub($this->flujo->consulta($periodo->desde, $periodo->hasta), 'm')
            ->where('estado', 'ACTIVO');

        $metodo = $this->entero($filtros, 'metodo_pago_id');

        if ($metodo !== null) {
            $consulta->where('metodo', DB::table('metodo_pago')->where('id', $metodo)->value('nombre'));
        }

        $categoria = $this->entero($filtros, 'categoria_gasto_id');

        if ($categoria !== null) {
            $nombre = DB::table('categoria_gasto')->where('id', $categoria)->value('nombre');

            $consulta->where(fn (Builder $q) => $q->where('origen', '<>', 'GASTO')->orWhere('categoria', $nombre));
        }

        return $consulta;
    }
}
