<?php

namespace App\Services\Reportes\Generadores;

use App\Services\Reportes\GeneradorReporte;
use App\Services\Reportes\PeriodoReporte;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Piezas comunes de los reportes de ventas.
 *
 * Reglas de negocio de CU16:
 *  - las ventas ANULADAS no suman;
 *  - el costo es el histórico (detalle_venta.costo_unitario_snapshot);
 *  - una línea sin costo cuenta en los ingresos, pero no en el costo ni en la utilidad,
 *    y el reporte lo avisa (EX-04). Es lo mismo que hacen las funciones fn_* de PostgreSQL.
 *
 * @phpstan-import-type Datos from GeneradorReporte
 */
abstract class GeneradorBase implements GeneradorReporte
{
    public const SUMA_INGRESO = 'sum(dv.cantidad * dv.precio_unitario - coalesce(dv.descuento, 0))';

    public const SUMA_COSTO = 'sum(case when dv.costo_unitario_snapshot is null then 0 else dv.cantidad * dv.costo_unitario_snapshot end)';

    public const SUMA_UTILIDAD = 'sum(case when dv.costo_unitario_snapshot is null then 0 else dv.cantidad * dv.precio_unitario - coalesce(dv.descuento, 0) - dv.cantidad * dv.costo_unitario_snapshot end)';

    public const SUMA_INGRESO_CON_COSTO = 'sum(case when dv.costo_unitario_snapshot is null then 0 else dv.cantidad * dv.precio_unitario - coalesce(dv.descuento, 0) end)';

    private bool $usarFunciones = true;

    /** Para las pruebas de paridad: fuerza el Query Builder aunque PostgreSQL tenga la función. */
    public function usarFunciones(bool $usar): static
    {
        $this->usarFunciones = $usar;

        return $this;
    }

    protected function puedeUsarFuncion(): bool
    {
        return $this->usarFunciones && DB::getDriverName() === 'pgsql';
    }

    /**
     * Líneas de venta del período (sin las ventas anuladas), con los filtros de categoría, marca y método de pago.
     *
     * @param  array<string, int|string>  $filtros
     */
    protected function lineasDeVenta(PeriodoReporte $periodo, array $filtros): Builder
    {
        $categoria = $this->entero($filtros, 'categoria_id');
        $marca = $this->entero($filtros, 'marca_id');
        $metodo = $this->entero($filtros, 'metodo_pago_id');

        return DB::table('detalle_venta as dv')
            ->join('venta as v', 'v.id', '=', 'dv.venta_id')
            ->join('variante_producto as vp', 'vp.id', '=', 'dv.variante_id')
            ->join('producto as p', 'p.id', '=', 'vp.producto_id')
            ->where('v.estado', '<>', 'ANULADA')
            ->whereBetween('v.fecha', [$periodo->inicio(), $periodo->fin()])
            ->when($categoria !== null, fn (Builder $q) => $q->where('p.categoria_id', $categoria))
            ->when($marca !== null, fn (Builder $q) => $q->where('p.marca_id', $marca))
            ->when($metodo !== null, fn (Builder $q) => $q->whereExists(
                fn (Builder $pagos) => $pagos->from('pago_venta as pv')
                    ->whereColumn('pv.venta_id', 'v.id')
                    ->where('pv.metodo_pago_id', $metodo),
            ));
    }

    /**
     * Aviso de la EX-04: cuántas líneas de venta del período no tienen costo registrado.
     *
     * @param  array<string, int|string>  $filtros
     * @return list<string>
     */
    protected function advertenciaDeCosto(PeriodoReporte $periodo, array $filtros): array
    {
        $sinCosto = $this->lineasDeVenta($periodo, $filtros)->whereNull('dv.costo_unitario_snapshot')->count();

        if ($sinCosto === 0) {
            return [];
        }

        return [sprintf(
            '%d %s de venta sin costo registrado: la utilidad puede estar incompleta.',
            $sinCosto,
            $sinCosto === 1 ? 'línea' : 'líneas',
        )];
    }

    /**
     * @param  array<string, int|string>  $filtros
     */
    protected function entero(array $filtros, string $clave): ?int
    {
        $valor = $filtros[$clave] ?? null;

        return is_numeric($valor) && (int) $valor > 0 ? (int) $valor : null;
    }

    protected function dinero(mixed $valor): float
    {
        return round(is_numeric($valor) ? (float) $valor : 0.0, 2);
    }

    /**
     * Gráfico vacío para los reportes sin gráfico.
     *
     * @return array{tipo: 'ninguno', etiquetas: list<string>, series: list<array{nombre: string, valores: list<float>}>}
     */
    protected function sinGrafico(): array
    {
        return ['tipo' => 'ninguno', 'etiquetas' => [], 'series' => []];
    }

    /**
     * Resultado de un período sin operaciones: sin filas ni totales (EX-01).
     *
     * @param  list<array{clave: string, etiqueta: string, tipo: 'texto'|'entero'|'moneda'|'porcentaje'|'fecha'}>  $columnas
     * @return Datos
     */
    protected function vacio(array $columnas): array
    {
        return [
            'columnas' => $columnas,
            'filas' => [],
            'totales' => [],
            'advertencias' => [],
            'grafico' => $this->sinGrafico(),
        ];
    }
}
