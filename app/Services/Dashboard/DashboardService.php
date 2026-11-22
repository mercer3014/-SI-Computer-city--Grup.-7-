<?php

namespace App\Services\Dashboard;

use App\Models\User;
use App\Services\Caja\FlujoCajaService;
use App\Services\Reportes\AgrupadorFecha;
use App\Services\Reportes\Fechas;
use App\Services\Reportes\Generadores\GeneradorBase;
use App\Services\Reportes\Generadores\ProductosMasVendidos;
use App\Services\Reportes\Generadores\VentasPeriodo;
use App\Services\Reportes\PeriodoReporte;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * CU23: indicadores del panel de inicio.
 *
 * Cada indicador es un método que puede fallar por su cuenta. `panel()` los llama dentro de su propio
 * try/catch: si uno falla, ese indicador queda en null (la tarjeta dice "No disponible") y los demás se calculan igual.
 * Lo que el usuario no puede ver por permisos no se calcula ni se envía al navegador.
 */
class DashboardService
{
    public const DIAS_GARANTIA = 30;

    public const DIAS_COTIZACION = 3;

    public const ELEMENTOS_POR_ALERTA = 5;

    public function __construct(private FlujoCajaService $flujo) {}

    /**
     * @return array{operativos: array<string, mixed>, financieros?: array<string, mixed>}
     */
    public function panel(User $usuario, PeriodoReporte $periodo): array
    {
        $verFinanzas = $usuario->tienePermiso('reportes.ver');
        $verVentas = $verFinanzas || $usuario->tienePermiso('ventas.crear') || $usuario->tienePermiso('ventas.anular');
        $operativos = [];

        if ($verVentas) {
            $propias = $verFinanzas ? null : (int) $usuario->id;
            $operativos['ventasDelDia'] = $this->seguro('ventasDelDia', fn () => $this->ventasDelDia($propias));
        }

        if ($usuario->tienePermiso('inventario.ver')) {
            $operativos['alertasStock'] = $this->seguro('alertasStock', fn () => $this->alertasStock());
        }

        if ($usuario->tienePermiso('garantias.gestionar')) {
            $operativos['garantiasPorVencer'] = $this->seguro('garantiasPorVencer', fn () => $this->garantiasPorVencer(self::DIAS_GARANTIA));
        }

        if ($verFinanzas) {
            $operativos['pedidosPendientes'] = $this->seguro('pedidosPendientes', fn () => $this->pedidosPagadosPendientes());
        }

        if ($usuario->tienePermiso('cotizaciones.gestionar')) {
            $operativos['cotizaciones'] = $this->seguro('cotizaciones', fn () => $this->cotizacionesPendientes());
        }

        $panel = ['operativos' => $operativos];

        if ($verFinanzas) {
            $panel['financieros'] = [
                'resumen' => $this->seguro('resumen', fn () => $this->resumenDeVentas($periodo)),
                'saldoCaja' => $this->seguro('saldoCaja', fn () => $this->saldoCaja($periodo)),
                'calificacion' => $this->seguro('calificacion', fn () => $this->calificacionPromedio()),
                'ventasPorDia' => $this->seguro('ventasPorDia', fn () => $this->ventasPorDia($periodo)),
                'topProductos' => $this->seguro('topProductos', fn () => $this->topProductos($periodo, 5)),
            ];
        }

        return $panel;
    }

    /**
     * Ventas de hoy. Con `$usuarioId`, solo las de esa persona (el Vendedor ve las suyas).
     *
     * @return array{cantidad: int, monto: float, propias: bool, desde: string}
     */
    public function ventasDelDia(?int $usuarioId): array
    {
        $hoy = PeriodoReporte::resolver('diario');

        $fila = DB::table('detalle_venta as dv')
            ->join('venta as v', 'v.id', '=', 'dv.venta_id')
            ->where('v.estado', '<>', 'ANULADA')
            ->whereBetween('v.fecha', [$hoy->inicio(), $hoy->fin()])
            ->when($usuarioId !== null, fn ($q) => $q->where('v.usuario_id', $usuarioId))
            ->selectRaw('count(distinct v.id) as cantidad')
            ->selectRaw(GeneradorBase::SUMA_INGRESO.' as monto')
            ->first();

        return [
            'cantidad' => (int) ($fila->cantidad ?? 0),
            'monto' => round((float) ($fila->monto ?? 0), 2),
            'propias' => $usuarioId !== null,
            'desde' => $hoy->desde->toDateString(),
        ];
    }

    /**
     * Ingresos y utilidad bruta del período, con el mismo motor que los reportes
     * (en PostgreSQL, fn_reporte_ventas_periodo).
     *
     * @return array{ingresos: float, utilidad: float, utilidadIncompleta: bool}
     */
    public function resumenDeVentas(PeriodoReporte $periodo): array
    {
        $datos = app(VentasPeriodo::class)->generar($periodo, []);

        return [
            'ingresos' => (float) ($datos['totales']['ingresos'] ?? 0),
            'utilidad' => (float) ($datos['totales']['utilidad'] ?? 0),
            'utilidadIncompleta' => $datos['advertencias'] !== [],
        ];
    }

    /**
     * Saldo acumulado de la caja hasta el fin del período y neto del período, con el flujo que usa el módulo de caja.
     *
     * @return array{saldo: float, neto: float}
     */
    public function saldoCaja(PeriodoReporte $periodo): array
    {
        $suma = "coalesce(sum(case when tipo = 'INGRESO' then monto else -monto end), 0)";

        $saldo = DB::query()
            ->fromSub($this->flujo->consulta(CarbonImmutable::create(2000), $periodo->hasta), 'h')
            ->where('estado', 'ACTIVO')
            ->selectRaw("$suma as total")
            ->value('total');

        $neto = DB::query()
            ->fromSub($this->flujo->consulta($periodo->desde, $periodo->hasta), 'p')
            ->where('estado', 'ACTIVO')
            ->selectRaw("$suma as total")
            ->value('total');

        return ['saldo' => round((float) $saldo, 2), 'neto' => round((float) $neto, 2)];
    }

    /**
     * Ventas por día del período (por semana o mes si el rango es largo), con los días sin ventas en cero.
     *
     * @return array{nivel: string, etiquetas: list<string>, valores: list<float>}
     */
    public function ventasPorDia(PeriodoReporte $periodo): array
    {
        $nivel = match (true) {
            $periodo->dias() <= 62 => AgrupadorFecha::DIA,
            $periodo->dias() <= 180 => AgrupadorFecha::SEMANA,
            default => AgrupadorFecha::MES,
        };
        $grupo = AgrupadorFecha::expresion('v.fecha', $nivel);

        $montos = DB::table('detalle_venta as dv')
            ->join('venta as v', 'v.id', '=', 'dv.venta_id')
            ->where('v.estado', '<>', 'ANULADA')
            ->whereBetween('v.fecha', [$periodo->inicio(), $periodo->fin()])
            ->selectRaw("$grupo as grupo")
            ->selectRaw(GeneradorBase::SUMA_INGRESO.' as monto')
            ->groupBy('grupo')
            ->pluck('monto', 'grupo');

        $etiquetas = [];
        $valores = [];

        foreach ($this->claves($periodo, $nivel) as $clave) {
            $etiquetas[] = $nivel === AgrupadorFecha::DIA
                ? Fechas::corta(CarbonImmutable::parse($clave))
                : Fechas::etiquetaGrupo($clave, $nivel);
            $valores[] = round((float) ($montos[$clave] ?? 0), 2);
        }

        return ['nivel' => $nivel, 'etiquetas' => $etiquetas, 'valores' => $valores];
    }

    /**
     * @return list<array{sku: string, producto: string, unidades: int, total: float}>
     */
    public function topProductos(PeriodoReporte $periodo, int $limite): array
    {
        $filas = app(ProductosMasVendidos::class)->generar($periodo, ['limite' => $limite])['filas'];
        $lista = [];

        foreach ($filas as $fila) {
            $lista[] = [
                'sku' => (string) $fila['sku'],
                'producto' => (string) $fila['producto'],
                'unidades' => (int) $fila['unidades'],
                'total' => (float) $fila['total'],
            ];
        }

        return $lista;
    }

    /**
     * Variantes activas sin stock o por debajo de su mínimo. En PostgreSQL usa fn_alertas_stock_bajo.
     *
     * @return array{agotadas: int, bajoMinimo: int, items: list<array{sku: string, producto: string, stock: int, minimo: int}>}
     */
    public function alertasStock(): array
    {
        $filas = DB::getDriverName() === 'pgsql'
            ? DB::select('select sku, producto, stock_actual, stock_minimo from fn_alertas_stock_bajo()')
            : DB::table('variante_producto as vp')
                ->join('producto as p', 'p.id', '=', 'vp.producto_id')
                ->where('vp.estado', 'ACTIVO')
                ->whereColumn('vp.stock_actual', '<=', 'vp.stock_minimo')
                ->orderBy('vp.stock_actual')
                ->orderBy('vp.sku')
                ->get(['vp.sku', 'p.nombre as producto', 'vp.stock_actual', 'vp.stock_minimo'])
                ->all();

        $agotadas = 0;
        $items = [];

        foreach ($filas as $fila) {
            $stock = (int) $fila->stock_actual;
            $agotadas += $stock <= 0 ? 1 : 0;

            if (count($items) < self::ELEMENTOS_POR_ALERTA) {
                $items[] = [
                    'sku' => (string) $fila->sku,
                    'producto' => (string) $fila->producto,
                    'stock' => $stock,
                    'minimo' => (int) $fila->stock_minimo,
                ];
            }
        }

        return ['agotadas' => $agotadas, 'bajoMinimo' => count($filas) - $agotadas, 'items' => $items];
    }

    /**
     * Garantías vigentes que vencen en los próximos `$dias` días, de ventas no anuladas.
     * Se cuentan las que tienen número de serie: son las que el módulo de Garantías puede consultar y devolver.
     *
     * @return array{total: int, items: list<array{producto: string, serie: string, venta: string, cliente: string, vence: string}>}
     */
    public function garantiasPorVencer(int $dias): array
    {
        $hoy = PeriodoReporte::hoy();

        $consulta = DB::table('detalle_venta as dv')
            ->join('venta as v', 'v.id', '=', 'dv.venta_id')
            ->join('variante_producto as vp', 'vp.id', '=', 'dv.variante_id')
            ->join('producto as p', 'p.id', '=', 'vp.producto_id')
            ->leftJoin('cliente as c', 'c.id', '=', 'v.cliente_id')
            ->where('v.estado', '<>', 'ANULADA')
            ->where('dv.estado', 'VIGENTE')
            ->whereNotNull('dv.numero_serie')
            ->where('dv.numero_serie', '<>', '')
            ->whereDate('dv.fecha_fin_garantia', '>=', $hoy->toDateString())
            ->whereDate('dv.fecha_fin_garantia', '<=', $hoy->addDays($dias)->toDateString());

        $items = [];

        foreach ((clone $consulta)
            ->orderBy('dv.fecha_fin_garantia')
            ->orderBy('dv.id')
            ->limit(self::ELEMENTOS_POR_ALERTA)
            ->get(['p.nombre as producto', 'dv.numero_serie', 'v.numero_venta', 'c.nombre as cliente', 'dv.fecha_fin_garantia']) as $fila) {
            $items[] = [
                'producto' => (string) $fila->producto,
                'serie' => (string) $fila->numero_serie,
                'venta' => (string) $fila->numero_venta,
                'cliente' => (string) ($fila->cliente ?? 'Cliente Final'),
                'vence' => Fechas::corta(CarbonImmutable::parse((string) $fila->fecha_fin_garantia)),
            ];
        }

        return ['total' => $consulta->count(), 'items' => $items];
    }

    /**
     * Pedidos de la tienda en línea ya pagados. No existe un estado ENTREGADO, así que todo pedido PAGADO
     * se considera pendiente de entrega hasta que el sistema registre la entrega.
     *
     * @return array{total: int, items: list<array{pedido: string, cliente: string, fecha: string, total: float}>}
     */
    public function pedidosPagadosPendientes(): array
    {
        $consulta = DB::table('pedido as pe')
            ->leftJoin('cliente as c', 'c.id', '=', 'pe.cliente_id')
            ->where('pe.estado', 'PAGADO');

        $items = [];

        foreach ((clone $consulta)
            ->orderByDesc('pe.fecha_pago')
            ->orderByDesc('pe.id')
            ->limit(self::ELEMENTOS_POR_ALERTA)
            ->get(['pe.numero_pedido', 'c.nombre as cliente', 'pe.fecha_pago', 'pe.total']) as $fila) {
            $items[] = [
                'pedido' => (string) $fila->numero_pedido,
                'cliente' => (string) ($fila->cliente ?? ''),
                'fecha' => $fila->fecha_pago !== null ? Fechas::corta(CarbonImmutable::parse((string) $fila->fecha_pago)) : '',
                'total' => round((float) $fila->total, 2),
            ];
        }

        return ['total' => $consulta->count(), 'items' => $items];
    }

    /**
     * Cotizaciones PENDIENTES y las que vencen en `DIAS_COTIZACION` días o menos.
     *
     * @return array{total: int, porVencer: int, items: list<array{codigo: string, cliente: string, vence: string}>}
     */
    public function cotizacionesPendientes(): array
    {
        if (! Schema::hasTable('cotizacion')) {
            return ['total' => 0, 'porVencer' => 0, 'items' => []];
        }

        $limite = PeriodoReporte::hoy()->addDays(self::DIAS_COTIZACION)->toDateString();

        $consulta = DB::table('cotizacion as ct')
            ->leftJoin('cliente as c', 'c.id', '=', 'ct.cliente_id')
            ->where('ct.estado', 'PENDIENTE');

        $items = [];

        foreach ((clone $consulta)
            ->orderBy('ct.fecha_vencimiento')
            ->orderBy('ct.id')
            ->limit(self::ELEMENTOS_POR_ALERTA)
            ->get(['ct.codigo_cotizacion', 'c.nombre as cliente', 'ct.fecha_vencimiento']) as $fila) {
            $items[] = [
                'codigo' => (string) $fila->codigo_cotizacion,
                'cliente' => (string) ($fila->cliente ?? ''),
                'vence' => $fila->fecha_vencimiento !== null ? Fechas::corta(CarbonImmutable::parse((string) $fila->fecha_vencimiento)) : '',
            ];
        }

        return [
            'total' => $consulta->count(),
            'porVencer' => (clone $consulta)->whereDate('ct.fecha_vencimiento', '<=', $limite)->count(),
            'items' => $items,
        ];
    }

    /**
     * Promedio de las calificaciones PUBLICADAS (CU24). Sin calificaciones, el promedio es null.
     *
     * @return array{promedio: float|null, total: int}
     */
    public function calificacionPromedio(): array
    {
        if (! Schema::hasTable('calificacion')) {
            return ['promedio' => null, 'total' => 0];
        }

        $fila = DB::table('calificacion')
            ->where('estado', 'PUBLICADA')
            ->selectRaw('count(*) as total, avg(estrellas) as promedio')
            ->first();

        $total = (int) ($fila->total ?? 0);

        return [
            'promedio' => $total > 0 ? round((float) $fila->promedio, 2) : null,
            'total' => $total,
        ];
    }

    /**
     * Claves ordenadas de cada grupo del período, para rellenar con ceros los que no tuvieron ventas.
     *
     * @return list<string>
     */
    private function claves(PeriodoReporte $periodo, string $nivel): array
    {
        $claves = [];
        $cursor = match ($nivel) {
            AgrupadorFecha::MES => $periodo->desde->startOfMonth(),
            AgrupadorFecha::SEMANA => $periodo->desde->startOfWeek(),
            default => $periodo->desde,
        };

        while ($cursor->lessThanOrEqualTo($periodo->hasta)) {
            $claves[] = $nivel === AgrupadorFecha::MES ? $cursor->format('Y-m') : $cursor->toDateString();
            $cursor = match ($nivel) {
                AgrupadorFecha::MES => $cursor->addMonth(),
                AgrupadorFecha::SEMANA => $cursor->addWeek(),
                default => $cursor->addDay(),
            };
        }

        return $claves;
    }

    /**
     * EX-02: un indicador que falla devuelve null y deja el motivo en el log; el resto del panel sigue.
     *
     * @template T
     *
     * @param  Closure(): T  $calculo
     * @return T|null
     */
    private function seguro(string $indicador, Closure $calculo): mixed
    {
        try {
            return $calculo();
        } catch (Throwable $e) {
            Log::error("Dashboard: no se pudo calcular «{$indicador}»: {$e->getMessage()}");

            return null;
        }
    }
}
