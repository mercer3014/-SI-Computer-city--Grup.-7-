<?php

namespace App\Http\Controllers;

use App\Http\Requests\AjusteStockRequest;
use App\Models\MovimientoInventario;
use App\Models\VarianteProducto;
use App\Services\BitacoraService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class InventarioController extends Controller
{
    /**
     * CU09-FR1: Consultar existencias, alertas, kardex y trazabilidad por serie.
     * Acceso: Administrador y Vendedor (inventario.ver).
     */
    public function consultar(Request $request): Response
    {
        $like = DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';
        $q = $request->string('q')->trim()->value();
        $alerta = $request->string('alerta')->value();
        $tab = $request->string('tab')->value();
        $varianteId = $request->integer('variante');
        $desde = $request->string('desde')->value();
        $hasta = $request->string('hasta')->value();
        $serie = $request->string('serie')->trim()->value();

        if (! in_array($alerta, ['bajo', 'cero', ''], true)) {
            $alerta = '';
        }

        if (! in_array($tab, ['stock', 'movimientos', 'kardex', 'serie'], true)) {
            $tab = 'stock';
        }

        $query = VarianteProducto::query()
            ->with(['producto:id,nombre,estado', 'valores.atributo'])
            ->where('variante_producto.estado', 'ACTIVO');

        if ($q !== '') {
            $busqueda = '%'.addcslashes($q, '%_\\').'%';
            $query->where(function ($sub) use ($busqueda, $like): void {
                $sub->where('sku', $like, $busqueda)
                    ->orWhere('codigo_barras', $like, $busqueda)
                    ->orWhereHas('producto', fn ($p) => $p->where('nombre', $like, $busqueda));
            });
        }

        if ($alerta === 'bajo') {
            $query->whereColumn('stock_actual', '<=', 'stock_minimo');
        } elseif ($alerta === 'cero') {
            $query->where('stock_actual', 0);
        }

        $variantes = $query
            ->orderByRaw('case when stock_actual <= stock_minimo then 0 else 1 end')
            ->orderBy('sku')
            ->paginate(20)
            ->withQueryString();

        $alertas = VarianteProducto::query()
            ->where('estado', 'ACTIVO')
            ->whereColumn('stock_actual', '<=', 'stock_minimo')
            ->count();

        $sinStock = VarianteProducto::query()
            ->where('estado', 'ACTIVO')
            ->where('stock_actual', 0)
            ->count();

        return Inertia::render('Inventario', [
            'variantes' => [
                'data' => $variantes->getCollection()
                    ->map(fn (VarianteProducto $v) => $this->formatVariante($v))
                    ->values(),
                'meta' => [
                    'current_page' => $variantes->currentPage(),
                    'last_page' => $variantes->lastPage(),
                    'total' => $variantes->total(),
                    'from' => $variantes->firstItem(),
                    'to' => $variantes->lastItem(),
                ],
            ],
            'movimientos' => $this->ultimosMovimientos(),
            'kardex' => $this->kardex($varianteId, $desde, $hasta),
            'serie' => $this->trazarSerie($serie),
            'kpis' => [
                'alertas' => $alertas,
                'sin_stock' => $sinStock,
                'total' => VarianteProducto::query()->where('estado', 'ACTIVO')->count(),
            ],
            'filtros' => [
                'q' => $q,
                'alerta' => $alerta,
                'tab' => $tab,
                'variante' => $varianteId > 0 ? (string) $varianteId : '',
                'desde' => $desde,
                'hasta' => $hasta,
                'serie' => $serie,
            ],
            'opciones' => [
                'variantes' => VarianteProducto::query()
                    ->where('estado', 'ACTIVO')
                    ->orderBy('sku')
                    ->get(['id', 'sku', 'producto_id'])
                    ->map(fn (VarianteProducto $v) => [
                        'id' => $v->id,
                        'sku' => $v->sku,
                    ]),
            ],
            'puedeAjustar' => $request->user()?->tienePermiso('inventario.ajustar') ?? false,
        ]);
    }

    /**
     * CU09-FR2: Emula sp_ajustar_stock() en una transacción Laravel.
     * Inserta movimiento_inventario + detalle y actualiza stock_actual.
     * Acceso: solo Administrador (inventario.ajustar). EX-05.
     */
    public function ajustar(AjusteStockRequest $request): RedirectResponse
    {
        $datos = $request->validated();
        $usuarioId = (int) $request->user()->id;

        DB::transaction(function () use ($datos, $usuarioId): void {
            $variante = VarianteProducto::query()
                ->whereRaw('upper(sku) = ?', [$datos['sku']])
                ->lockForUpdate()
                ->first();

            if ($variante === null) {
                throw ValidationException::withMessages([
                    'sku' => 'No existe variante con el SKU indicado.',
                ]);
            }

            $cantidad = (int) $datos['cantidad'];
            $esSalida = $datos['tipo'] === 'SALIDA';

            if ($esSalida && $cantidad > $variante->stock_actual) {
                throw ValidationException::withMessages([
                    'cantidad' => 'El ajuste de salida supera el stock disponible ('.$variante->stock_actual.' uds.).',
                ]);
            }

            $stockAnterior = $variante->stock_actual;
            $delta = $esSalida ? -$cantidad : $cantidad;

            $movimiento = MovimientoInventario::query()->create([
                'usuario_id' => $usuarioId,
                'tipo' => 'AJUSTE',
                'fecha' => now(),
                'referencia' => $esSalida ? 'AJUSTE-SALIDA' : 'AJUSTE-ENTRADA',
                'observacion' => $datos['motivo'],
            ]);

            $movimiento->detalles()->create([
                'variante_id' => $variante->id,
                'cantidad' => $cantidad,
                'costo_unitario' => 0,
            ]);

            $variante->forceFill([
                'stock_actual' => $stockAnterior + $delta,
            ])->save();

            BitacoraService::registrar(
                'Inventario',
                'AJUSTAR_STOCK',
                'variante_producto',
                $variante->id,
                ['sku' => $variante->sku, 'stock_actual' => $stockAnterior],
                [
                    'sku' => $variante->sku,
                    'stock_actual' => $variante->stock_actual,
                    'tipo' => $datos['tipo'],
                    'cantidad' => $cantidad,
                ],
                $datos['motivo'],
            );
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Ajuste de stock registrado.',
        ]);

        return back();
    }

    /**
     * CU09: El Administrador puede cambiar el stock mínimo de una variante.
     */
    public function minimo(Request $request, VarianteProducto $variante): RedirectResponse
    {
        if (! $request->user()?->tienePermiso('inventario.ajustar')) {
            abort(403, 'Solo el Administrador puede realizar esta acción.');
        }

        $datos = $request->validate([
            'stock_minimo' => ['required', 'integer', 'min:0', 'max:100000'],
        ], [
            'stock_minimo.required' => 'Ingresá el stock mínimo.',
            'stock_minimo.min' => 'El stock mínimo no puede ser negativo.',
        ]);

        $anterior = $variante->stock_minimo;
        $variante->forceFill(['stock_minimo' => (int) $datos['stock_minimo']])->save();

        BitacoraService::registrar(
            'Inventario',
            'ACTUALIZAR_STOCK_MINIMO',
            'variante_producto',
            $variante->id,
            ['sku' => $variante->sku, 'stock_minimo' => $anterior],
            ['sku' => $variante->sku, 'stock_minimo' => $variante->stock_minimo],
            'Cambio de stock mínimo',
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Stock mínimo de {$variante->sku} actualizado.",
        ]);

        return back();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function ultimosMovimientos(): array
    {
        return DB::table('detalle_movimiento_inventario as d')
            ->join('movimiento_inventario as m', 'm.id', '=', 'd.movimiento_id')
            ->join('variante_producto as vp', 'vp.id', '=', 'd.variante_id')
            ->join('producto as p', 'p.id', '=', 'vp.producto_id')
            ->leftJoin('usuario as u', 'u.id', '=', 'm.usuario_id')
            ->leftJoin('personal as pe', 'pe.id', '=', 'u.personal_id')
            ->orderByDesc('m.fecha')
            ->orderByDesc('m.id')
            ->limit(40)
            ->get([
                'm.id',
                'm.tipo',
                'm.fecha',
                'm.referencia',
                'm.observacion',
                'd.cantidad',
                'vp.sku',
                'p.nombre as producto',
                'pe.nombre_completo',
                'u.email',
            ])
            ->map(fn (object $fila) => $this->formatMovimiento($fila))
            ->all();
    }

    /**
     * @return array{variante: array<string, mixed>|null, lineas: list<array<string, mixed>>}
     */
    private function kardex(int $varianteId, string $desde, string $hasta): array
    {
        if ($varianteId <= 0) {
            return ['variante' => null, 'lineas' => []];
        }

        $variante = VarianteProducto::query()
            ->with('producto:id,nombre')
            ->find($varianteId);

        if ($variante === null) {
            return ['variante' => null, 'lineas' => []];
        }

        $query = DB::table('detalle_movimiento_inventario as d')
            ->join('movimiento_inventario as m', 'm.id', '=', 'd.movimiento_id')
            ->leftJoin('usuario as u', 'u.id', '=', 'm.usuario_id')
            ->leftJoin('personal as pe', 'pe.id', '=', 'u.personal_id')
            ->where('d.variante_id', $varianteId)
            ->orderBy('m.fecha')
            ->orderBy('m.id');

        if ($desde !== '') {
            $query->whereDate('m.fecha', '>=', $desde);
        }

        if ($hasta !== '') {
            $query->whereDate('m.fecha', '<=', $hasta);
        }

        $saldo = 0;
        $lineas = $query
            ->get([
                'm.id',
                'm.tipo',
                'm.fecha',
                'm.referencia',
                'm.observacion',
                'd.cantidad',
                'pe.nombre_completo',
                'u.email',
            ])
            ->map(function (object $fila) use (&$saldo): array {
                $efecto = $this->efecto($fila->tipo, $fila->referencia, (int) $fila->cantidad);
                $saldo += $efecto;
                $momento = $this->momento($fila->fecha);

                return [
                    'id' => (int) $fila->id,
                    'fecha' => $momento?->format('d/m/Y') ?? '',
                    'hora' => $momento?->format('H:i') ?? '',
                    'tipo' => (string) $fila->tipo,
                    'referencia' => $fila->referencia,
                    'motivo' => $fila->observacion,
                    'entrada' => $efecto > 0 ? $efecto : null,
                    'salida' => $efecto < 0 ? abs($efecto) : null,
                    'saldo' => $saldo,
                    'usuario' => trim((string) ($fila->nombre_completo ?? '')) ?: ($fila->email ?? 'Sistema'),
                ];
            })
            ->all();

        return [
            'variante' => [
                'id' => $variante->id,
                'sku' => $variante->sku,
                'producto' => $variante->producto?->nombre,
                'stock_actual' => $variante->stock_actual,
            ],
            'lineas' => $lineas,
        ];
    }

    /**
     * @return array{busqueda: string, encontrada: bool, compra: array<string, mixed>|null, venta: array<string, mixed>|null}
     */
    private function trazarSerie(string $serie): array
    {
        if ($serie === '') {
            return ['busqueda' => '', 'encontrada' => false, 'compra' => null, 'venta' => null];
        }

        $like = DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';
        $patron = '%'.addcslashes($serie, '%_\\').'%';

        $compra = DB::table('detalle_compra as dc')
            ->join('compra as c', 'c.id', '=', 'dc.compra_id')
            ->join('variante_producto as vp', 'vp.id', '=', 'dc.variante_id')
            ->join('producto as p', 'p.id', '=', 'vp.producto_id')
            ->leftJoin('proveedor as pr', 'pr.id', '=', 'c.proveedor_id')
            ->where('dc.numero_serie', $like, $patron)
            ->orderByDesc('c.fecha_compra')
            ->first([
                'c.numero_compra',
                'c.fecha_compra',
                'vp.sku',
                'p.nombre as producto',
                'dc.numero_serie',
                'pr.nombre_comercial',
                'pr.razon_social',
            ]);

        $venta = DB::table('detalle_venta as dv')
            ->join('venta as v', 'v.id', '=', 'dv.venta_id')
            ->join('variante_producto as vp', 'vp.id', '=', 'dv.variante_id')
            ->join('producto as p', 'p.id', '=', 'vp.producto_id')
            ->leftJoin('cliente as cl', 'cl.id', '=', 'v.cliente_id')
            ->where('dv.numero_serie', $like, $patron)
            ->orderByDesc('v.fecha')
            ->first([
                'v.numero_venta',
                'v.fecha',
                'v.estado',
                'vp.sku',
                'p.nombre as producto',
                'dv.numero_serie',
                'cl.nombre as cliente',
            ]);

        return [
            'busqueda' => $serie,
            'encontrada' => $compra !== null || $venta !== null,
            'compra' => $compra === null ? null : [
                'numero' => $compra->numero_compra,
                'fecha' => $this->momento($compra->fecha_compra)?->format('d/m/Y H:i'),
                'sku' => $compra->sku,
                'producto' => $compra->producto,
                'serie' => $compra->numero_serie,
                'origen' => $compra->nombre_comercial ?: $compra->razon_social,
            ],
            'venta' => $venta === null ? null : [
                'numero' => $venta->numero_venta,
                'fecha' => $this->momento($venta->fecha)?->format('d/m/Y H:i'),
                'sku' => $venta->sku,
                'producto' => $venta->producto,
                'serie' => $venta->numero_serie,
                'cliente' => $venta->cliente,
                'estado' => $venta->estado,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatVariante(VarianteProducto $v): array
    {
        $atributos = $v->valores
            ->map(fn ($valor) => ($valor->atributo?->nombre ?? 'Atributo').': '.$valor->valor)
            ->sort()
            ->values()
            ->all();

        return [
            'id' => $v->id,
            'sku' => $v->sku,
            'codigo_barras' => $v->codigo_barras,
            'producto_id' => $v->producto_id,
            'producto' => $v->producto?->nombre,
            'atributos' => $atributos === [] ? '—' : implode(' · ', $atributos),
            'stock_actual' => $v->stock_actual,
            'stock_minimo' => $v->stock_minimo,
            'stock_bajo' => $v->stock_actual <= $v->stock_minimo,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatMovimiento(object $fila): array
    {
        $efecto = $this->efecto($fila->tipo, $fila->referencia, (int) $fila->cantidad);
        $momento = $this->momento($fila->fecha);

        return [
            'id' => (int) $fila->id,
            'fecha' => $momento?->format('d/m/Y') ?? '',
            'hora' => $momento?->format('H:i') ?? '',
            'tipo' => (string) $fila->tipo,
            'sku' => $fila->sku ?? null,
            'producto' => $fila->producto ?? null,
            'referencia' => $fila->referencia,
            'motivo' => $fila->observacion,
            'cantidad' => abs($efecto),
            'direccion' => $efecto < 0 ? 'SALIDA' : 'ENTRADA',
            'usuario' => trim((string) ($fila->nombre_completo ?? '')) ?: ($fila->email ?? 'Sistema'),
        ];
    }

    private function efecto(string $tipo, ?string $referencia, int $cantidad): int
    {
        if ($tipo === 'SALIDA') {
            return -$cantidad;
        }

        if ($tipo === 'AJUSTE' && str_contains((string) $referencia, 'SALIDA')) {
            return -$cantidad;
        }

        return $cantidad;
    }

    private function momento(mixed $valor): ?Carbon
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        return Carbon::parse($valor)->timezone(config('app.timezone'));
    }
}
