<?php

namespace App\Http\Controllers;

use App\Http\Requests\CompraRequest;
use App\Models\Compra;
use App\Models\MetodoPago;
use App\Models\Proveedor;
use App\Models\VarianteProducto;
use App\Services\BitacoraService;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Throwable;

class CompraController extends Controller
{
    /**
     * Consulta de compras y productos a reponer. El alta sigue siendo store.
     */
    public function index(Request $request): Response
    {
        $like = DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';
        $q = $request->string('q')->trim()->value();
        $proveedorId = $request->integer('proveedor');
        $catalogo = $this->catalogo();

        $compras = DB::table('compra as c')
            ->join('proveedor as pr', 'pr.id', '=', 'c.proveedor_id')
            ->select([
                'c.id',
                'c.numero_compra',
                'c.fecha_compra',
                'c.estado',
                'c.proveedor_id',
                'pr.razon_social',
                'pr.nombre_comercial',
            ])
            ->selectRaw('(select count(*) from detalle_compra dc where dc.compra_id = c.id) as lineas')
            ->selectRaw('(select coalesce(sum(dc.cantidad), 0) from detalle_compra dc where dc.compra_id = c.id) as unidades')
            ->selectRaw('(select coalesce(sum(dc.cantidad * dc.costo_unitario), 0) from detalle_compra dc where dc.compra_id = c.id) as total');

        if ($q !== '') {
            $busqueda = '%'.addcslashes($q, '%_\\').'%';
            $compras->where(function ($sub) use ($busqueda, $like): void {
                $sub->where('c.numero_compra', $like, $busqueda)
                    ->orWhere('pr.razon_social', $like, $busqueda)
                    ->orWhere('pr.nombre_comercial', $like, $busqueda)
                    ->orWhereExists(function ($existe) use ($busqueda, $like): void {
                        $existe->selectRaw('1')
                            ->from('detalle_compra as dc')
                            ->join('variante_producto as vp', 'vp.id', '=', 'dc.variante_id')
                            ->whereColumn('dc.compra_id', 'c.id')
                            ->where('vp.sku', $like, $busqueda);
                    });
            });
        }

        if ($proveedorId > 0) {
            $compras->where('c.proveedor_id', $proveedorId);
        }

        $pagina = $compras
            ->orderByDesc('c.fecha_compra')
            ->orderByDesc('c.id')
            ->paginate(15)
            ->withQueryString();

        $ids = $pagina->getCollection()->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $lineas = $this->lineasDe($ids);

        return Inertia::render('NuevaCompra', [
            'compras' => [
                'data' => $pagina->getCollection()->map(function (object $compra) use ($lineas): array {
                    $id = (int) $compra->id;

                    return [
                        'id' => $id,
                        'numero_compra' => $compra->numero_compra,
                        'fecha' => $this->fecha($compra->fecha_compra),
                        'estado' => $compra->estado ?? 'COMPLETADO',
                        'proveedor_id' => (int) $compra->proveedor_id,
                        'proveedor' => $compra->razon_social,
                        'nombre_comercial' => $compra->nombre_comercial,
                        'lineas' => (int) $compra->lineas,
                        'unidades' => (int) $compra->unidades,
                        'total' => round((float) $compra->total, 2),
                        'items' => $lineas[$id] ?? [],
                    ];
                })->values(),
                'meta' => [
                    'current_page' => $pagina->currentPage(),
                    'last_page' => $pagina->lastPage(),
                    'total' => $pagina->total(),
                    'from' => $pagina->firstItem(),
                    'to' => $pagina->lastItem(),
                ],
            ],
            'reposicion' => array_values(array_filter(
                $catalogo,
                fn (array $item): bool => $item['stock_actual'] <= $item['stock_minimo'],
            )),
            'proveedores' => Proveedor::query()
                ->where('estado', 'ACTIVO')
                ->orderBy('razon_social')
                ->get(['id', 'razon_social', 'nombre_comercial', 'nit'])
                ->map(fn (Proveedor $proveedor): array => [
                    'id' => $proveedor->id,
                    'razon_social' => $proveedor->razon_social,
                    'nombre_comercial' => $proveedor->nombre_comercial,
                    'nit' => $proveedor->nit,
                ])
                ->all(),
            'metodos' => MetodoPago::query()
                ->where('estado', 'ACTIVO')
                ->where('codigo', '<>', 'TARJETA')
                ->orderBy('nombre')
                ->get(['id', 'codigo', 'nombre'])
                ->map(fn (MetodoPago $metodo): array => [
                    'id' => $metodo->id,
                    'codigo' => $metodo->codigo,
                    'nombre' => $metodo->nombre,
                ])
                ->all(),
            'catalogo' => $catalogo,
            'kpis' => [
                'compras' => (int) DB::table('compra')->count(),
                'agotados' => (int) VarianteProducto::query()
                    ->where('estado', 'ACTIVO')
                    ->where('stock_actual', 0)
                    ->count(),
                'bajos' => (int) VarianteProducto::query()
                    ->where('estado', 'ACTIVO')
                    ->where('stock_actual', '>', 0)
                    ->whereColumn('stock_actual', '<=', 'stock_minimo')
                    ->count(),
                'total_mes' => round((float) DB::table('detalle_compra as dc')
                    ->join('compra as c', 'c.id', '=', 'dc.compra_id')
                    ->whereBetween('c.fecha_compra', [now()->startOfMonth(), now()->endOfMonth()])
                    ->sum(DB::raw('dc.cantidad * dc.costo_unitario')), 2),
            ],
            'filtros' => [
                'q' => $q,
                'proveedor' => $proveedorId > 0 ? (string) $proveedorId : '',
            ],
            'numeroSugerido' => $this->numeroSugerido(),
            'fechaHoy' => now()->toDateString(),
        ]);
    }

    /**
     * CU14: registra la compra. El stock y el kardex los actualizan
     * trg_compra_generar_movimiento y trg_detalle_compra_actualizar_stock.
     */
    public function store(CompraRequest $request): RedirectResponse
    {
        $datos = $request->datos();
        $usuarioId = (int) $request->user()->id;
        $items = json_encode(
            $datos['items'],
            JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        );

        try {
            $compra = DB::transaction(function () use ($datos, $usuarioId, $items): Compra {
                if (DB::getDriverName() === 'pgsql') {
                    DB::statement(
                        'CALL public.sp_registrar_compra(?, ?, ?, ?, ?, CAST(? AS jsonb))',
                        [
                            $datos['proveedor_id'],
                            $usuarioId,
                            $datos['metodo_pago_id'],
                            $datos['numero_compra'],
                            $datos['fecha_compra'],
                            $items,
                        ],
                    );

                    $id = (int) DB::table('compra')
                        ->where('numero_compra', $datos['numero_compra'])
                        ->value('id');
                } else {
                    $id = $this->insertarComoElProcedimiento($datos, $usuarioId);
                }

                $compra = Compra::query()->findOrFail($id);

                BitacoraService::registrar(
                    'Compras',
                    'REGISTRAR_COMPRA',
                    'compra',
                    $compra->id,
                    null,
                    [
                        'numero_compra' => $compra->numero_compra,
                        'proveedor_id' => $compra->proveedor_id,
                        'items' => count($datos['items']),
                        'total' => $this->total($datos['items']),
                    ],
                    'Compra '.$compra->numero_compra,
                );

                return $compra;
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'numero_compra' => 'Ya existe una compra con ese número.',
            ]);
        } catch (QueryException|RuntimeException $e) {
            $this->relanzarSku($e);
            throw $e;
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Compra '.$compra->numero_compra.' registrada.',
        ]);

        return back();
    }

    /**
     * Misma inserción que sp_registrar_compra, para SQLite (tests).
     * No toca stock ni kardex: eso solo existe en los triggers de PostgreSQL.
     *
     * @param  array{
     *     proveedor_id: int,
     *     metodo_pago_id: int,
     *     numero_compra: string,
     *     fecha_compra: string,
     *     items: list<array{sku: string, cantidad: int, costo_unitario: float, numero_serie: string|null}>
     * }  $datos
     */
    private function insertarComoElProcedimiento(array $datos, int $usuarioId): int
    {
        $id = (int) DB::table('compra')->insertGetId([
            'proveedor_id' => $datos['proveedor_id'],
            'usuario_id' => $usuarioId,
            'metodo_pago_id' => $datos['metodo_pago_id'],
            'numero_compra' => $datos['numero_compra'],
            'fecha_compra' => $datos['fecha_compra'],
        ]);

        foreach ($datos['items'] as $item) {
            $varianteId = DB::table('variante_producto')->where('sku', $item['sku'])->value('id');

            if ($varianteId === null) {
                throw new RuntimeException(
                    'No existe ninguna variante de producto con el SKU '.$item['sku'],
                );
            }

            DB::table('detalle_compra')->insert([
                'compra_id' => $id,
                'variante_id' => $varianteId,
                'cantidad' => $item['cantidad'],
                'costo_unitario' => $item['costo_unitario'],
                'numero_serie' => $item['numero_serie'],
            ]);
        }

        return $id;
    }

    /**
     * @param  list<int>  $compraIds
     * @return array<int, list<array<string, mixed>>>
     */
    private function lineasDe(array $compraIds): array
    {
        if ($compraIds === []) {
            return [];
        }

        $filas = DB::table('detalle_compra as dc')
            ->join('variante_producto as vp', 'vp.id', '=', 'dc.variante_id')
            ->join('producto as p', 'p.id', '=', 'vp.producto_id')
            ->whereIn('dc.compra_id', $compraIds)
            ->orderBy('dc.id')
            ->get([
                'dc.compra_id',
                'vp.sku',
                'p.nombre as producto',
                'dc.cantidad',
                'dc.costo_unitario',
                'dc.numero_serie',
            ]);

        $grupos = [];

        foreach ($filas as $fila) {
            $grupos[(int) $fila->compra_id][] = [
                'sku' => $fila->sku,
                'producto' => $fila->producto,
                'cantidad' => (int) $fila->cantidad,
                'costo_unitario' => round((float) $fila->costo_unitario, 2),
                'numero_serie' => $fila->numero_serie,
                'subtotal' => round((int) $fila->cantidad * (float) $fila->costo_unitario, 2),
            ];
        }

        return $grupos;
    }

    /**
     * @return array<int, array{proveedor_id: int, proveedor: string, costo_unitario: float, fecha: string|null, numero_compra: string}>
     */
    private function ultimasPorVariante(): array
    {
        $filas = DB::table('detalle_compra as dc')
            ->join('compra as c', 'c.id', '=', 'dc.compra_id')
            ->join('proveedor as pr', 'pr.id', '=', 'c.proveedor_id')
            ->orderByDesc('c.fecha_compra')
            ->orderByDesc('dc.id')
            ->get([
                'dc.variante_id',
                'pr.id as proveedor_id',
                'pr.razon_social',
                'dc.costo_unitario',
                'c.fecha_compra',
                'c.numero_compra',
            ]);

        $mapa = [];

        foreach ($filas as $fila) {
            $id = (int) $fila->variante_id;

            if (isset($mapa[$id])) {
                continue;
            }

            $mapa[$id] = [
                'proveedor_id' => (int) $fila->proveedor_id,
                'proveedor' => $fila->razon_social,
                'costo_unitario' => round((float) $fila->costo_unitario, 2),
                'fecha' => $this->fecha($fila->fecha_compra),
                'numero_compra' => $fila->numero_compra,
            ];
        }

        return $mapa;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function catalogo(): array
    {
        $ultimas = $this->ultimasPorVariante();

        return VarianteProducto::query()
            ->with(['producto:id,nombre,estado', 'valores.atributo'])
            ->where('variante_producto.estado', 'ACTIVO')
            ->whereHas('producto', fn ($producto) => $producto->where('estado', 'ACTIVO'))
            ->orderBy('stock_actual')
            ->orderBy('sku')
            ->limit(400)
            ->get()
            ->map(function (VarianteProducto $variante) use ($ultimas): array {
                $ultima = $ultimas[$variante->id] ?? null;
                $stock = (int) $variante->stock_actual;
                $minimo = (int) $variante->stock_minimo;

                return [
                    'sku' => $variante->sku,
                    'codigo_barras' => $variante->codigo_barras,
                    'producto' => $variante->producto?->nombre,
                    'atributos' => $variante->etiquetaAtributos(),
                    'stock_actual' => $stock,
                    'stock_minimo' => $minimo,
                    'agotado' => $stock === 0,
                    'sugerido' => max($minimo - $stock, 1),
                    'proveedor_id' => $ultima['proveedor_id'] ?? null,
                    'proveedor' => $ultima['proveedor'] ?? null,
                    'costo_ultimo' => $ultima['costo_unitario'] ?? null,
                    'ultima_compra' => $ultima['numero_compra'] ?? null,
                    'ultima_fecha' => $ultima['fecha'] ?? null,
                ];
            })
            ->all();
    }

    private function fecha(mixed $valor): ?string
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        return Carbon::parse($valor)->timezone(config('app.timezone'))->format('d/m/Y');
    }

    private function numeroSugerido(): string
    {
        $anio = (int) now()->year;
        $prefijo = 'C-'.$anio.'-';
        $ultimo = DB::table('compra')
            ->where('numero_compra', 'like', $prefijo.'%')
            ->orderByDesc('numero_compra')
            ->value('numero_compra');

        $secuencia = 1;

        if (is_string($ultimo) && preg_match('/^C-\d{4}-(\d+)$/', $ultimo, $partes) === 1) {
            $secuencia = (int) $partes[1] + 1;
        }

        return sprintf('C-%d-%04d', $anio, $secuencia);
    }

    /**
     * @param  list<array{cantidad: int, costo_unitario: float}>  $items
     */
    private function total(array $items): float
    {
        $total = 0.0;

        foreach ($items as $item) {
            $total += $item['cantidad'] * $item['costo_unitario'];
        }

        return round($total, 2);
    }

    private function relanzarSku(Throwable $e): void
    {
        if (! str_contains($e->getMessage(), 'No existe ninguna variante')) {
            return;
        }

        throw ValidationException::withMessages([
            'items' => 'Hay un SKU que no existe en el catálogo.',
        ]);
    }
}
