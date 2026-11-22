<?php

namespace App\Services\Asistente;

use App\Services\Caja\FlujoCajaService;
use App\Services\Reportes\AgrupadorFecha;
use App\Services\Reportes\Fechas;
use App\Services\Reportes\Generadores\GeneradorBase;
use App\Services\Reportes\PeriodoReporte;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LogicException;

/**
 * Una consulta de solo lectura por métrica, con las dimensiones que permite el catálogo.
 * Todo entra ya validado por ValidadorEspecificacion y los textos de los filtros llegan como ids
 * (ResolutorDeFiltros): aquí nunca se arma SQL con datos de la IA.
 *
 * @phpstan-import-type Especificacion from ValidadorEspecificacion
 * @phpstan-import-type Columna from \App\Services\Reportes\ReporteResultado
 * @phpstan-import-type Fila from \App\Services\Reportes\ReporteResultado
 * @phpstan-import-type Grafico from \App\Services\Reportes\ReporteResultado
 * @phpstan-import-type Metrica from CatalogoAsistente
 *
 * @phpstan-type Punto array{d1: string|null, d2: string|null, valor: float, n: int}
 * @phpstan-type Resultado array{interpretacion: string, enlace: string, columnas: list<Columna>, filas: list<Fila>, totales: Fila, advertencias: list<string>, grafico: Grafico}
 */
class ConsultasPermitidas
{
    private const MAXIMO_FILAS = 200;

    public function __construct(
        private FlujoCajaService $flujo,
        private ResolutorDeFiltros $resolutor,
    ) {}

    /**
     * @param  Especificacion  $espec
     * @return Resultado
     */
    public function ejecutar(array $espec): array
    {
        $periodo = PeriodoReporte::resolver('personalizado', $espec['periodo']['desde'], $espec['periodo']['hasta']);
        $comparacion = $espec['comparar_con'] !== null
            ? PeriodoReporte::resolver('personalizado', $espec['comparar_con']['desde'], $espec['comparar_con']['hasta'])
            : null;
        $ids = $this->resolutor->resolver($espec['metrica'], $espec['filtros']);

        $actual = $this->serie($espec, $ids, $periodo);
        $previa = $comparacion !== null ? $this->serie($espec, $ids, $comparacion) : null;

        return $this->armar($espec, $ids, $periodo, $comparacion, $actual, $previa);
    }

    /* ── Series por métrica ───────────────────────────────────────────────── */

    /**
     * @param  Especificacion  $espec
     * @param  array<string, list<int>|string>  $ids
     * @return list<Punto>
     */
    private function serie(array $espec, array $ids, PeriodoReporte $periodo): array
    {
        $porMetodo = in_array('metodo_pago', $espec['dimensiones'], true);

        return match ($espec['metrica']) {
            'ventas_monto' => $porMetodo
                ? $this->pagosPorMetodo($espec, $ids, $periodo, 'sum(pv.monto)')
                : $this->deLineas($espec, $ids, $periodo, GeneradorBase::SUMA_INGRESO),
            'ventas_cantidad' => $porMetodo
                ? $this->pagosPorMetodo($espec, $ids, $periodo, 'count(distinct v.id)')
                : $this->deLineas($espec, $ids, $periodo, 'count(distinct v.id)'),
            'unidades_vendidas' => $this->deLineas($espec, $ids, $periodo, 'sum(dv.cantidad)'),
            'utilidad_bruta' => $this->deLineas($espec, $ids, $periodo, GeneradorBase::SUMA_UTILIDAD),
            'gastos' => $this->deGastos($espec, $ids, $periodo),
            'compras' => $this->deCompras($espec, $ids, $periodo),
            'saldo_caja' => $this->deCaja($espec, $ids, $periodo),
            'stock' => $this->deStock($espec, $ids),
            'pedidos' => $this->dePedidos($espec, $ids, $periodo),
            'cotizaciones' => $this->deCotizaciones($espec, $ids, $periodo),
            'calificacion_promedio' => $this->deCalificaciones($espec, $ids, $periodo),
            default => throw new LogicException('Métrica sin consulta: '.$espec['metrica']),
        };
    }

    /**
     * @param  Especificacion  $espec
     * @param  array<string, list<int>|string>  $ids
     * @param  literal-string  $valorSql
     * @return list<Punto>
     */
    private function deLineas(array $espec, array $ids, PeriodoReporte $periodo, string $valorSql): array
    {
        $consulta = DB::table('detalle_venta as dv')
            ->join('venta as v', 'v.id', '=', 'dv.venta_id')
            ->join('variante_producto as vp', 'vp.id', '=', 'dv.variante_id')
            ->join('producto as p', 'p.id', '=', 'vp.producto_id')
            ->leftJoin('categoria as c', 'c.id', '=', 'p.categoria_id')
            ->leftJoin('marca as m', 'm.id', '=', 'p.marca_id')
            ->where('v.estado', '<>', 'ANULADA')
            ->whereBetween('v.fecha', [$periodo->inicio(), $periodo->fin()]);

        $this->filtrar($consulta, $ids, ['categoria' => 'p.categoria_id', 'marca' => 'p.marca_id', 'producto' => 'p.id']);

        $metodos = $ids['metodo_pago'] ?? null;

        if (is_array($metodos)) {
            $consulta->whereExists(fn (Builder $pagos) => $pagos->from('pago_venta as pv')
                ->whereColumn('pv.venta_id', 'v.id')
                ->whereIn('pv.metodo_pago_id', $metodos));
        }

        return $this->agrupar($consulta, 'lineas', $espec['dimensiones'], $valorSql);
    }

    /**
     * Lo cobrado con cada método de pago (tabla de pagos).
     *
     * @param  Especificacion  $espec
     * @param  array<string, list<int>|string>  $ids
     * @param  literal-string  $valorSql
     * @return list<Punto>
     */
    private function pagosPorMetodo(array $espec, array $ids, PeriodoReporte $periodo, string $valorSql): array
    {
        $consulta = DB::table('pago_venta as pv')
            ->join('venta as v', 'v.id', '=', 'pv.venta_id')
            ->leftJoin('metodo_pago as mp', 'mp.id', '=', 'pv.metodo_pago_id')
            ->where('v.estado', '<>', 'ANULADA')
            ->whereBetween('v.fecha', [$periodo->inicio(), $periodo->fin()]);

        $this->filtrar($consulta, $ids, ['metodo_pago' => 'pv.metodo_pago_id']);

        return $this->agrupar($consulta, 'pagos', $espec['dimensiones'], $valorSql);
    }

    /**
     * @param  Especificacion  $espec
     * @param  array<string, list<int>|string>  $ids
     * @return list<Punto>
     */
    private function deGastos(array $espec, array $ids, PeriodoReporte $periodo): array
    {
        $consulta = DB::table('gasto as g')
            ->join('categoria_gasto as cg', 'cg.id', '=', 'g.categoria_gasto_id')
            ->leftJoin('metodo_pago as mp', 'mp.id', '=', 'g.metodo_pago_id')
            ->where(fn (Builder $q) => $q->whereNull('g.estado')->orWhere('g.estado', '<>', 'ANULADO'))
            ->whereBetween('g.fecha', [$periodo->inicio(), $periodo->fin()]);

        $this->filtrar($consulta, $ids, ['categoria_gasto' => 'g.categoria_gasto_id', 'metodo_pago' => 'g.metodo_pago_id']);

        return $this->agrupar($consulta, 'gastos', $espec['dimensiones'], 'sum(g.monto)');
    }

    /**
     * @param  Especificacion  $espec
     * @param  array<string, list<int>|string>  $ids
     * @return list<Punto>
     */
    private function deCompras(array $espec, array $ids, PeriodoReporte $periodo): array
    {
        $consulta = DB::table('compra as c')
            ->join('detalle_compra as dc', 'dc.compra_id', '=', 'c.id')
            ->join('proveedor as pr', 'pr.id', '=', 'c.proveedor_id')
            ->join('variante_producto as vp', 'vp.id', '=', 'dc.variante_id')
            ->join('producto as p', 'p.id', '=', 'vp.producto_id')
            ->where(fn (Builder $q) => $q->whereNull('c.estado')->orWhere('c.estado', '<>', 'ANULADA'))
            ->whereBetween('c.fecha_compra', [$periodo->inicio(), $periodo->fin()]);

        $this->filtrar($consulta, $ids, [
            'proveedor' => 'c.proveedor_id',
            'producto' => 'p.id',
            'categoria' => 'p.categoria_id',
            'marca' => 'p.marca_id',
        ]);

        return $this->agrupar($consulta, 'compras', $espec['dimensiones'], 'sum(dc.cantidad * dc.costo_unitario)');
    }

    /**
     * Neto de caja (ingresos menos egresos activos) con el mismo flujo que usa el módulo de caja.
     *
     * @param  Especificacion  $espec
     * @param  array<string, list<int>|string>  $ids
     * @return list<Punto>
     */
    private function deCaja(array $espec, array $ids, PeriodoReporte $periodo): array
    {
        $consulta = DB::query()
            ->fromSub($this->flujo->consulta($periodo->desde, $periodo->hasta), 'm')
            ->where('estado', 'ACTIVO');

        $metodos = $ids['metodo_pago'] ?? null;

        if (is_array($metodos)) {
            $consulta->whereIn('metodo', DB::table('metodo_pago')->whereIn('id', $metodos)->pluck('nombre')->all());
        }

        return $this->agrupar($consulta, 'flujo', $espec['dimensiones'], "sum(case when tipo = 'INGRESO' then monto else -monto end)");
    }

    /**
     * @param  Especificacion  $espec
     * @param  array<string, list<int>|string>  $ids
     * @return list<Punto>
     */
    private function deStock(array $espec, array $ids): array
    {
        $consulta = DB::table('variante_producto as vp')
            ->join('producto as p', 'p.id', '=', 'vp.producto_id')
            ->leftJoin('categoria as c', 'c.id', '=', 'p.categoria_id')
            ->leftJoin('marca as m', 'm.id', '=', 'p.marca_id')
            ->where('vp.estado', 'ACTIVO');

        $this->filtrar($consulta, $ids, ['categoria' => 'p.categoria_id', 'marca' => 'p.marca_id', 'producto' => 'p.id']);

        $estado = $ids['estado'] ?? null;

        if ($estado === 'bajo_minimo') {
            $consulta->whereColumn('vp.stock_actual', '<=', 'vp.stock_minimo');
        } elseif ($estado === 'agotado') {
            $consulta->where('vp.stock_actual', '<=', 0);
        }

        return $this->agrupar($consulta, 'stock', $espec['dimensiones'], 'sum(vp.stock_actual)');
    }

    /**
     * @param  Especificacion  $espec
     * @param  array<string, list<int>|string>  $ids
     * @return list<Punto>
     */
    private function dePedidos(array $espec, array $ids, PeriodoReporte $periodo): array
    {
        $consulta = DB::table('pedido as pe')->whereBetween('pe.fecha', [$periodo->inicio(), $periodo->fin()]);

        if (is_string($ids['estado'] ?? null)) {
            $consulta->where('pe.estado', $ids['estado']);
        }

        return $this->agrupar($consulta, 'pedidos', $espec['dimensiones'], 'count(*)');
    }

    /**
     * @param  Especificacion  $espec
     * @param  array<string, list<int>|string>  $ids
     * @return list<Punto>
     */
    private function deCotizaciones(array $espec, array $ids, PeriodoReporte $periodo): array
    {
        $consulta = DB::table('cotizacion as ct')->whereBetween('ct.fecha_emision', [$periodo->inicio(), $periodo->fin()]);

        if (is_string($ids['estado'] ?? null)) {
            $consulta->where('ct.estado', $ids['estado']);
        }

        return $this->agrupar($consulta, 'cotizaciones', $espec['dimensiones'], 'count(*)');
    }

    /**
     * @param  Especificacion  $espec
     * @param  array<string, list<int>|string>  $ids
     * @return list<Punto>
     */
    private function deCalificaciones(array $espec, array $ids, PeriodoReporte $periodo): array
    {
        if (! Schema::hasTable('calificacion')) {
            return [];
        }

        $consulta = DB::table('calificacion as cal')
            ->join('producto as p', 'p.id', '=', 'cal.producto_id')
            ->where('cal.estado', 'PUBLICADA')
            ->whereBetween('cal.fecha', [$periodo->inicio(), $periodo->fin()]);

        $this->filtrar($consulta, $ids, ['producto' => 'p.id', 'categoria' => 'p.categoria_id', 'marca' => 'p.marca_id']);

        return $this->agrupar($consulta, 'calificaciones', $espec['dimensiones'], 'avg(cal.puntuacion)');
    }

    /**
     * @param  array<string, list<int>|string>  $ids
     * @param  array<string, literal-string>  $columnas
     */
    private function filtrar(Builder $consulta, array $ids, array $columnas): void
    {
        foreach ($columnas as $clave => $columna) {
            $valores = $ids[$clave] ?? null;

            if (is_array($valores)) {
                $consulta->whereIn($columna, $valores);
            }
        }
    }

    /**
     * @param  list<string>  $dimensiones
     * @param  literal-string  $valorSql
     * @return list<Punto>
     */
    private function agrupar(Builder $consulta, string $fuente, array $dimensiones, string $valorSql): array
    {
        foreach ($dimensiones as $indice => $dimension) {
            $alias = $indice === 0 ? 'd1' : 'd2';

            $consulta->selectRaw($this->expresion($fuente, $dimension).' as '.$alias)->groupBy($alias);
        }

        $filas = $consulta->selectRaw($valorSql.' as valor')->selectRaw('count(*) as n')->get();
        $puntos = [];

        foreach ($filas as $fila) {
            $puntos[] = [
                'd1' => isset($fila->d1) ? (string) $fila->d1 : null,
                'd2' => isset($fila->d2) ? (string) $fila->d2 : null,
                'valor' => (float) ($fila->valor ?? 0),
                'n' => (int) $fila->n,
            ];
        }

        return $puntos;
    }

    /**
     * Expresión SQL de cada dimensión según la tabla de origen. Son literales del código.
     *
     * @return literal-string
     */
    private function expresion(string $fuente, string $dimension): string
    {
        if (CatalogoAsistente::esTemporal($dimension)) {
            return match ($fuente) {
                'lineas', 'pagos' => AgrupadorFecha::expresion('v.fecha', $dimension),
                'gastos' => AgrupadorFecha::expresion('g.fecha', $dimension),
                'compras' => AgrupadorFecha::expresion('c.fecha_compra', $dimension),
                'flujo' => AgrupadorFecha::expresion('fecha', $dimension),
                'pedidos' => AgrupadorFecha::expresion('pe.fecha', $dimension),
                'cotizaciones' => AgrupadorFecha::expresion('ct.fecha_emision', $dimension),
                'calificaciones' => AgrupadorFecha::expresion('cal.fecha', $dimension),
                default => throw new LogicException("La fuente $fuente no tiene dimensión de tiempo."),
            };
        }

        return match ([$fuente, $dimension]) {
            ['lineas', 'producto'], ['compras', 'producto'], ['stock', 'producto'], ['calificaciones', 'producto'] => 'p.nombre',
            ['lineas', 'categoria'], ['stock', 'categoria'] => "coalesce(c.nombre, 'Sin categoría')",
            ['lineas', 'marca'], ['stock', 'marca'] => "coalesce(m.nombre, 'Sin marca')",
            ['pagos', 'metodo_pago'] => "coalesce(mp.nombre, 'Sin método')",
            ['gastos', 'metodo_pago'] => "coalesce(mp.nombre, 'Sin método')",
            ['gastos', 'categoria_gasto'] => 'cg.nombre',
            ['compras', 'proveedor'] => 'pr.razon_social',
            ['pedidos', 'estado'] => 'pe.estado',
            ['cotizaciones', 'estado'] => 'ct.estado',
            default => throw new LogicException("La fuente $fuente no tiene la dimensión $dimension."),
        };
    }

    /* ── Armado del resultado ─────────────────────────────────────────────── */

    /**
     * @param  Especificacion  $espec
     * @param  array<string, list<int>|string>  $ids
     * @param  list<Punto>  $actual
     * @param  list<Punto>|null  $previa
     * @return Resultado
     */
    private function armar(array $espec, array $ids, PeriodoReporte $periodo, ?PeriodoReporte $comparacion, array $actual, ?array $previa): array
    {
        $metrica = CatalogoAsistente::metricas()[$espec['metrica']];
        $dimensiones = $espec['dimensiones'];
        $conComparacion = $previa !== null;
        $advertencias = $this->advertencias($espec, $ids, $periodo);

        $resultado = [
            'interpretacion' => $this->interpretacion($espec, $metrica['etiqueta'], $periodo, $comparacion),
            'enlace' => $this->enlace($espec, $periodo),
            'advertencias' => $advertencias,
        ];

        $columnas = $this->columnas($dimensiones, $metrica['tipo'], $conComparacion);

        if ($dimensiones === []) {
            $filas = $this->filasSinDimension($espec, $metrica, $periodo, $actual, $previa);
            $totales = [];
            $grafico = $this->graficoSinDimension($filas, $conComparacion);
        } elseif (count($dimensiones) === 1) {
            $filas = $this->filasConUnaDimension($espec, $metrica['tipo'], $periodo, $comparacion, $actual, $previa);
            $totales = $this->totales($filas, $metrica, $dimensiones, $conComparacion);
            $grafico = $this->graficoConUnaDimension($espec, $filas, $conComparacion);
        } else {
            $filas = $this->filasConDosDimensiones($espec, $metrica['tipo'], $actual);

            if (count($filas) > self::MAXIMO_FILAS) {
                $filas = array_slice($filas, 0, self::MAXIMO_FILAS);
                $resultado['advertencias'][] = 'Se muestran solo las primeras '.self::MAXIMO_FILAS.' filas.';
            }

            $totales = $this->totales($filas, $metrica, $dimensiones, false);
            $grafico = $this->graficoConDosDimensiones($espec, $filas);
        }

        return [
            ...$resultado,
            'columnas' => $columnas,
            'filas' => $filas,
            'totales' => $filas === [] ? [] : $totales,
            'grafico' => $filas === [] ? $this->sinGrafico() : $grafico,
        ];
    }

    /**
     * @param  list<string>  $dimensiones
     * @param  'entero'|'decimal'|'moneda'  $tipoValor
     * @return list<Columna>
     */
    private function columnas(array $dimensiones, string $tipoValor, bool $conComparacion): array
    {
        $columnas = [];

        if ($dimensiones === []) {
            $columnas[] = ['clave' => 'concepto', 'etiqueta' => 'Concepto', 'tipo' => 'texto'];
        }

        foreach ($dimensiones as $indice => $dimension) {
            $columnas[] = [
                'clave' => $indice === 0 ? 'dim1' : 'dim2',
                'etiqueta' => ucfirst(CatalogoAsistente::ETIQUETAS_DIMENSION[$dimension] ?? $dimension),
                'tipo' => 'texto',
            ];
        }

        if ($conComparacion) {
            $columnas[] = ['clave' => 'actual', 'etiqueta' => 'Período actual', 'tipo' => $tipoValor];
            $columnas[] = ['clave' => 'comparacion', 'etiqueta' => 'Comparación', 'tipo' => $tipoValor];
            $columnas[] = ['clave' => 'variacion', 'etiqueta' => 'Variación', 'tipo' => 'porcentaje'];
        } else {
            $columnas[] = ['clave' => 'valor', 'etiqueta' => $dimensiones === [] ? 'Valor' : 'Total', 'tipo' => $tipoValor];
        }

        return $columnas;
    }

    /**
     * @param  Especificacion  $espec
     * @param  Metrica  $metrica
     * @param  list<Punto>  $actual
     * @param  list<Punto>|null  $previa
     * @return list<Fila>
     */
    private function filasSinDimension(array $espec, array $metrica, PeriodoReporte $periodo, array $actual, ?array $previa): array
    {
        $registros = $actual[0]['n'] ?? 0;

        if ($registros === 0) {
            return [];
        }

        $valor = $this->redondear($actual[0]['valor'], $metrica['tipo']);

        if ($previa !== null) {
            $base = $this->redondear($previa[0]['valor'] ?? 0.0, $metrica['tipo']);

            return [[
                'concepto' => $metrica['etiqueta'],
                'actual' => $valor,
                'comparacion' => $base,
                'variacion' => $this->variacion((float) $valor, (float) $base),
            ]];
        }

        if ($espec['metrica'] === 'saldo_caja') {
            return [
                ['concepto' => 'Saldo acumulado al '.Fechas::corta($periodo->hasta), 'valor' => $this->saldoAcumulado($periodo)],
                ['concepto' => 'Neto del período', 'valor' => $valor],
            ];
        }

        return [['concepto' => $metrica['etiqueta'], 'valor' => $valor]];
    }

    /**
     * @param  Especificacion  $espec
     * @param  'entero'|'decimal'|'moneda'  $tipo
     * @param  list<Punto>  $actual
     * @param  list<Punto>|null  $previa
     * @return list<Fila>
     */
    private function filasConUnaDimension(array $espec, string $tipo, PeriodoReporte $periodo, ?PeriodoReporte $comparacion, array $actual, ?array $previa): array
    {
        if ($actual === []) {
            return [];
        }

        $dimension = $espec['dimensiones'][0];
        $valoresActual = [];

        foreach ($actual as $punto) {
            $valoresActual[(string) $punto['d1']] = $punto['valor'];
        }

        if (CatalogoAsistente::esTemporal($dimension)) {
            $clavesActual = AgrupadorFecha::claves($periodo, $dimension);
            $clavesPrevias = $comparacion !== null ? AgrupadorFecha::claves($comparacion, $dimension) : [];
            $valoresPrevios = [];

            foreach ($previa ?? [] as $punto) {
                $valoresPrevios[(string) $punto['d1']] = $punto['valor'];
            }

            $filas = [];

            foreach ($clavesActual as $indice => $clave) {
                $etiqueta = Fechas::etiquetaGrupo($clave, $dimension);
                $valor = $this->redondear($valoresActual[$clave] ?? 0.0, $tipo);

                if ($previa === null) {
                    $filas[] = ['dim1' => $etiqueta, 'valor' => $valor];

                    continue;
                }

                $claveBase = $clavesPrevias[$indice] ?? null;
                $base = $claveBase !== null ? $this->redondear($valoresPrevios[$claveBase] ?? 0.0, $tipo) : null;

                $filas[] = [
                    'dim1' => $etiqueta,
                    'actual' => $valor,
                    'comparacion' => $base,
                    'variacion' => $base === null ? null : $this->variacion((float) $valor, (float) $base),
                ];
            }

            return $filas;
        }

        $valoresPrevios = [];

        foreach ($previa ?? [] as $punto) {
            $valoresPrevios[(string) $punto['d1']] = $punto['valor'];
        }

        $etiquetas = array_keys($valoresActual);
        usort($etiquetas, fn (string $a, string $b): int => $espec['orden'] === 'asc'
            ? [$valoresActual[$a], $a] <=> [$valoresActual[$b], $b]
            : [$valoresActual[$b], $a] <=> [$valoresActual[$a], $b]);

        $filas = [];

        foreach (array_slice($etiquetas, 0, $espec['limite']) as $etiqueta) {
            $valor = $this->redondear($valoresActual[$etiqueta], $tipo);

            if ($previa === null) {
                $filas[] = ['dim1' => $etiqueta, 'valor' => $valor];

                continue;
            }

            $base = $this->redondear($valoresPrevios[$etiqueta] ?? 0.0, $tipo);
            $filas[] = [
                'dim1' => $etiqueta,
                'actual' => $valor,
                'comparacion' => $base,
                'variacion' => $this->variacion((float) $valor, (float) $base),
            ];
        }

        return $filas;
    }

    /**
     * @param  Especificacion  $espec
     * @param  'entero'|'decimal'|'moneda'  $tipo
     * @param  list<Punto>  $actual
     * @return list<Fila>
     */
    private function filasConDosDimensiones(array $espec, string $tipo, array $actual): array
    {
        $niveles = $espec['dimensiones'];
        $filas = [];

        usort($actual, function (array $a, array $b) use ($niveles): int {
            $primera = ($a['d1'] ?? '') <=> ($b['d1'] ?? '');

            if ($primera !== 0) {
                return CatalogoAsistente::esTemporal($niveles[0]) ? $primera : $b['valor'] <=> $a['valor'];
            }

            return $b['valor'] <=> $a['valor'];
        });

        foreach ($actual as $punto) {
            $filas[] = [
                'dim1' => $this->etiqueta($niveles[0], $punto['d1']),
                'dim2' => $this->etiqueta($niveles[1], $punto['d2']),
                'valor' => $this->redondear($punto['valor'], $tipo),
            ];
        }

        return $filas;
    }

    private function etiqueta(string $dimension, ?string $clave): string
    {
        if ($clave === null) {
            return '';
        }

        return CatalogoAsistente::esTemporal($dimension) ? Fechas::etiquetaGrupo($clave, $dimension) : $clave;
    }

    /**
     * Fila de totales solo para las métricas que se pueden sumar sin contar dos veces.
     *
     * @param  list<Fila>  $filas
     * @param  Metrica  $metrica
     * @param  list<string>  $dimensiones
     * @return Fila
     */
    private function totales(array $filas, array $metrica, array $dimensiones, bool $conComparacion): array
    {
        $sumable = $metrica['aditiva'] && ! (
            $metrica['tipo'] === 'entero'
            && $metrica['etiqueta'] === 'Cantidad de ventas'
            && array_filter($dimensiones, fn (string $d): bool => ! CatalogoAsistente::esTemporal($d)) !== []
        );

        if (! $sumable || $filas === []) {
            return [];
        }

        $totales = ['dim1' => 'Total'];

        if (count($dimensiones) > 1) {
            $totales['dim2'] = null;
        }

        $suma = fn (string $clave): float => array_sum(array_map(fn (array $f): float => (float) ($f[$clave] ?? 0), $filas));

        if ($conComparacion) {
            $actual = $suma('actual');
            $base = $suma('comparacion');
            $totales['actual'] = $this->redondear($actual, $metrica['tipo']);
            $totales['comparacion'] = $this->redondear($base, $metrica['tipo']);
            $totales['variacion'] = $this->variacion($actual, $base);

            return $totales;
        }

        $totales['valor'] = $this->redondear($suma('valor'), $metrica['tipo']);

        return $totales;
    }

    /**
     * @param  list<Fila>  $filas
     * @return Grafico
     */
    private function graficoSinDimension(array $filas, bool $conComparacion): array
    {
        if (! $conComparacion || $filas === []) {
            return $this->sinGrafico();
        }

        return [
            'tipo' => 'barra',
            'etiquetas' => ['Período actual', 'Comparación'],
            'series' => [['nombre' => (string) $filas[0]['concepto'], 'valores' => [(float) $filas[0]['actual'], (float) $filas[0]['comparacion']]]],
        ];
    }

    /**
     * @param  Especificacion  $espec
     * @param  list<Fila>  $filas
     * @return Grafico
     */
    private function graficoConUnaDimension(array $espec, array $filas, bool $conComparacion): array
    {
        if ($espec['grafico'] === 'tabla') {
            return $this->sinGrafico();
        }

        $tipo = $espec['grafico'] === 'torta' && $conComparacion ? 'barra' : $espec['grafico'];
        $series = [];

        if ($conComparacion) {
            $series[] = ['nombre' => 'Período actual', 'valores' => array_map(fn (array $f): float => (float) $f['actual'], $filas)];
            $series[] = ['nombre' => 'Comparación', 'valores' => array_map(fn (array $f): float => (float) ($f['comparacion'] ?? 0), $filas)];
        } else {
            $series[] = [
                'nombre' => CatalogoAsistente::metricas()[$espec['metrica']]['etiqueta'],
                'valores' => array_map(fn (array $f): float => (float) $f['valor'], $filas),
            ];
        }

        return [
            'tipo' => $tipo,
            'etiquetas' => array_map(fn (array $f): string => (string) $f['dim1'], $filas),
            'series' => $series,
        ];
    }

    /**
     * Con tiempo y categoría, una serie por categoría (las 6 mayores); con dos categorías, una por la segunda.
     *
     * @param  Especificacion  $espec
     * @param  list<Fila>  $filas
     * @return Grafico
     */
    private function graficoConDosDimensiones(array $espec, array $filas): array
    {
        if ($espec['grafico'] === 'tabla') {
            return $this->sinGrafico();
        }

        $etiquetas = [];
        $totalesPorSerie = [];
        $celdas = [];

        foreach ($filas as $fila) {
            $x = (string) $fila['dim1'];
            $serie = (string) $fila['dim2'];

            $etiquetas[$x] = $x;
            $totalesPorSerie[$serie] = ($totalesPorSerie[$serie] ?? 0.0) + (float) $fila['valor'];
            $celdas[$serie][$x] = (float) $fila['valor'];
        }

        arsort($totalesPorSerie);
        $series = [];

        foreach (array_slice(array_keys($totalesPorSerie), 0, 6) as $serie) {
            $series[] = [
                'nombre' => (string) $serie,
                'valores' => array_values(array_map(fn (string $x): float => $celdas[$serie][$x] ?? 0.0, $etiquetas)),
            ];
        }

        return [
            'tipo' => $espec['grafico'] === 'torta' ? 'barra' : $espec['grafico'],
            'etiquetas' => array_values($etiquetas),
            'series' => $series,
        ];
    }

    /**
     * @return Grafico
     */
    private function sinGrafico(): array
    {
        return ['tipo' => 'ninguno', 'etiquetas' => [], 'series' => []];
    }

    /**
     * @param  Especificacion  $espec
     * @param  array<string, list<int>|string>  $ids
     * @return list<string>
     */
    private function advertencias(array $espec, array $ids, PeriodoReporte $periodo): array
    {
        $plurales = [
            'categoria' => 'las categorías',
            'marca' => 'las marcas',
            'proveedor' => 'los proveedores',
            'producto' => 'los productos',
            'metodo_pago' => 'los métodos de pago',
        ];
        $avisos = [];

        foreach ($espec['filtros'] as $clave => $texto) {
            $deGasto = $clave === 'categoria' && $espec['metrica'] === 'gastos';

            if ($clave !== 'estado' && ($ids[$deGasto ? 'categoria_gasto' : $clave] ?? null) === []) {
                $avisos[] = 'No encontré «'.$texto.'» entre '.($deGasto ? 'las categorías de gasto' : $plurales[$clave]).'.';
            }
        }

        if ($espec['metrica'] === 'utilidad_bruta') {
            $sinCosto = $this->lineasSinCosto($espec, $ids, $periodo);

            if ($sinCosto > 0) {
                $avisos[] = sprintf('%d %s de venta sin costo registrado: la utilidad puede estar incompleta.', $sinCosto, $sinCosto === 1 ? 'línea' : 'líneas');
            }
        }

        return $avisos;
    }

    /**
     * @param  Especificacion  $espec
     * @param  array<string, list<int>|string>  $ids
     */
    private function lineasSinCosto(array $espec, array $ids, PeriodoReporte $periodo): int
    {
        $consulta = DB::table('detalle_venta as dv')
            ->join('venta as v', 'v.id', '=', 'dv.venta_id')
            ->join('variante_producto as vp', 'vp.id', '=', 'dv.variante_id')
            ->join('producto as p', 'p.id', '=', 'vp.producto_id')
            ->where('v.estado', '<>', 'ANULADA')
            ->whereNull('dv.costo_unitario_snapshot')
            ->whereBetween('v.fecha', [$periodo->inicio(), $periodo->fin()]);

        $this->filtrar($consulta, $ids, ['categoria' => 'p.categoria_id', 'marca' => 'p.marca_id', 'producto' => 'p.id']);

        return $consulta->count();
    }

    private function saldoAcumulado(PeriodoReporte $periodo): float
    {
        $valor = DB::query()
            ->fromSub($this->flujo->consulta(PeriodoReporte::resolver('personalizado', '2000-01-01', '2000-01-01')->desde, $periodo->hasta), 'h')
            ->where('estado', 'ACTIVO')
            ->selectRaw("coalesce(sum(case when tipo = 'INGRESO' then monto else -monto end), 0) as total")
            ->value('total');

        return round((float) $valor, 2);
    }

    /**
     * "Ventas en Bs por semana, categoría Teclados, marca Redragon, 01/10/2026 – 31/10/2026".
     *
     * @param  Especificacion  $espec
     */
    private function interpretacion(array $espec, string $etiqueta, PeriodoReporte $periodo, ?PeriodoReporte $comparacion): string
    {
        $partes = [$etiqueta];

        if ($espec['dimensiones'] !== []) {
            $partes[0] .= ' por '.implode(' y ', array_map(
                fn (string $d): string => CatalogoAsistente::ETIQUETAS_DIMENSION[$d] ?? $d,
                $espec['dimensiones'],
            ));
        }

        foreach ($espec['filtros'] as $clave => $texto) {
            $nombre = $clave === 'categoria' && $espec['metrica'] === 'gastos'
                ? 'categoría de gasto'
                : (CatalogoAsistente::ETIQUETAS_FILTRO[$clave] ?? $clave);
            $partes[] = $nombre.' '.str_replace('_', ' ', $texto);
        }

        $partes[] = $espec['metrica'] === 'stock' ? 'stock actual' : $periodo->etiqueta();

        if ($comparacion !== null) {
            $partes[] = 'comparado con '.$comparacion->etiqueta();
        }

        return implode(', ', $partes);
    }

    /**
     * Módulo donde se ve el dato, con el filtro que ya soporta.
     *
     * @param  Especificacion  $espec
     */
    private function enlace(array $espec, PeriodoReporte $periodo): string
    {
        $rango = 'desde='.$periodo->desde->toDateString().'&hasta='.$periodo->hasta->toDateString();

        return match ($espec['metrica']) {
            'ventas_monto', 'ventas_cantidad', 'unidades_vendidas' => "/ventas?$rango",
            'utilidad_bruta' => "/reportes/generar?tipo=utilidad_bruta&periodo=personalizado&$rango",
            'gastos', 'saldo_caja' => "/caja?$rango",
            'compras' => '/compras',
            'stock' => match ($espec['filtros']['estado'] ?? null) {
                'bajo_minimo' => '/inventario?alerta=bajo',
                'agotado' => '/inventario?alerta=cero',
                default => '/inventario',
            },
            'pedidos' => '/ventas?origen=web',
            'cotizaciones' => isset($espec['filtros']['estado']) ? '/cotizaciones?estado='.$espec['filtros']['estado'] : '/cotizaciones',
            default => '/calificaciones',
        };
    }

    /**
     * @param  'entero'|'decimal'|'moneda'  $tipo
     */
    private function redondear(float $valor, string $tipo): int|float
    {
        return $tipo === 'entero' ? (int) round($valor) : round($valor, 2);
    }

    /** Variación porcentual contra la base; null cuando la base es cero. */
    private function variacion(float $actual, float $base): ?float
    {
        return abs($base) > 0.00001 ? round(($actual - $base) / abs($base) * 100, 2) : null;
    }
}
