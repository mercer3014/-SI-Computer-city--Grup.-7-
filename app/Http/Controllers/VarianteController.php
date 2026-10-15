<?php

namespace App\Http\Controllers;

use App\Http\Requests\VarianteRequest;
use App\Models\Atributo;
use App\Models\Producto;
use App\Models\ValorAtributo;
use App\Models\VarianteProducto;
use App\Services\BitacoraService;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class VarianteController extends Controller
{
    // ── Frontera: abrirVariantes ────────────────────────────────────────────

    /**
     * CU08-FR1: Variantes de un producto, historial de precios y variantes sin rotación.
     * Acceso: Administrador (gestión) y Vendedor (solo lectura, inventario.ver).
     */
    public function index(Request $request, Producto $producto): Response
    {
        $producto->load(['categoria:id,nombre', 'marca:id,nombre', 'imagenes']);

        $meses = (new VarianteProducto)->mesesRotacion(
            $request->integer('meses', VarianteProducto::MESES_SIN_ROTACION),
        );

        $variantes = $producto->variantes()
            ->conUltimaVenta()
            ->with('valores.atributo')
            ->orderBy('sku')
            ->get();

        $sinRotacionIds = $producto->variantes()
            ->sinRotacion($meses)
            ->pluck('variante_producto.id')
            ->all();

        $sinRotacion = VarianteProducto::query()
            ->sinRotacion($meses)
            ->conUltimaVenta()
            ->with(['producto:id,nombre', 'valores.atributo'])
            ->orderByDesc('stock_actual')
            ->orderBy('sku')
            ->limit(50)
            ->get();

        $imagen = $producto->imagenPrincipal();
        $conMovimientos = VarianteProducto::idsConMovimientos(
            $variantes->pluck('id')->merge($sinRotacion->pluck('id'))->all(),
        );

        return Inertia::render('Variantes', [
            'producto' => [
                'id' => $producto->id,
                'nombre' => $producto->nombre,
                'slug' => $producto->slug,
                'estado' => $producto->estado ?? 'ACTIVO',
                'categoria' => $producto->categoria?->nombre,
                'marca' => $producto->marca?->nombre,
                'imagen_url' => $imagen?->url_webp,
            ],
            'variantes' => $variantes
                ->map(fn (VarianteProducto $v) => $this->formatVariante($v, $conMovimientos) + [
                    'sin_rotacion' => in_array($v->id, $sinRotacionIds, true),
                ])
                ->values(),
            'historial' => $this->historialPrecios($variantes->pluck('id')->all()),
            'sinRotacion' => [
                'meses' => $meses,
                'periodos' => VarianteProducto::PERIODOS_ROTACION,
                'total' => VarianteProducto::query()->sinRotacion($meses)->count(),
                'data' => $sinRotacion
                    ->map(fn (VarianteProducto $v) => $this->formatVariante($v, $conMovimientos) + [
                        'producto_id' => $v->producto_id,
                        'producto' => $v->producto?->nombre,
                    ])
                    ->values(),
            ],
            'atributos' => Atributo::query()
                ->where(fn ($q) => $q->whereNull('estado')->orWhere('estado', 'ACTIVO'))
                ->with('valores')
                ->orderBy('nombre')
                ->get()
                ->map(fn (Atributo $a) => [
                    'id' => $a->id,
                    'nombre' => $a->nombre,
                    'valores' => $a->valores
                        ->filter(fn (ValorAtributo $v) => ($v->estado ?? 'ACTIVO') === 'ACTIVO')
                        ->map(fn (ValorAtributo $v) => ['id' => $v->id, 'valor' => $v->valor])
                        ->values(),
                ]),
            'puedeGestionar' => $request->user()?->tienePermiso('productos.gestionar') ?? false,
        ]);
    }

    // ── Control: store ──────────────────────────────────────────────────────

    /**
     * CU08-FR2: Registrar una variante (SKU, atributos, stock mínimo y precio).
     * Acceso: solo Administrador (productos.gestionar).
     */
    public function store(VarianteRequest $request, Producto $producto): RedirectResponse
    {
        if (($producto->estado ?? 'ACTIVO') !== 'ACTIVO') {
            throw ValidationException::withMessages([
                'sku' => 'El producto está INACTIVO. Reactivalo antes de agregar variantes.',
            ]);
        }

        $datos = $request->validated();

        try {
            DB::transaction(function () use ($producto, $datos): void {
                // Serializa altas concurrentes del mismo producto para que la
                // verificación de combinación duplicada no tenga carreras.
                Producto::query()->whereKey($producto->id)->lockForUpdate()->first();

                $valores = $this->resolverValores($datos['atributos']);
                $valorIds = $valores->pluck('id')->all();

                if (VarianteProducto::combinacionExiste($producto->id, $valorIds)) {
                    throw ValidationException::withMessages([
                        'atributos' => $valorIds === []
                            ? 'Este producto ya tiene una variante sin atributos. Elegí al menos un atributo que la diferencie.'
                            : 'La combinación de atributos ya existe para este producto.',
                    ]);
                }

                $variante = $producto->variantes()->create([
                    'sku' => $datos['sku'],
                    'codigo_barras' => $datos['codigo_barras'] ?? null,
                    'precio_venta' => round((float) $datos['precio_venta'], 2),
                    'stock_minimo' => (int) $datos['stock_minimo'],
                    'stock_actual' => 0,
                    'estado' => 'ACTIVO',
                ]);

                $variante->valores()->sync($valorIds);

                BitacoraService::registrar(
                    'Inventario',
                    'CREAR_VARIANTE',
                    'variante_producto',
                    $variante->id,
                    null,
                    [
                        'sku' => $variante->sku,
                        'precio_venta' => $variante->precio_venta,
                        'stock_minimo' => $variante->stock_minimo,
                        'atributos' => $this->textoAtributos($valores),
                    ],
                    "Alta de variante {$variante->sku} para {$producto->nombre}",
                );
            });
        } catch (UniqueConstraintViolationException $e) {
            $sql = $e->getMessage();
            $campo = str_contains($sql, 'codigo_barras') ? 'codigo_barras' : 'sku';

            throw ValidationException::withMessages([
                $campo => $campo === 'sku' ? 'El SKU ya existe.' : 'El código de barras ya existe.',
            ]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Variante registrada correctamente.']);

        return back();
    }

    // ── Control: updatePrecio ───────────────────────────────────────────────

    /**
     * CU08-FR3: Actualizar el precio de venta de una variante.
     *
     * El UPDATE y su registro en bitacora_auditoria ocurren en la misma
     * transacción: en PostgreSQL lo escribe trg_auditoria_cambio_precio
     * (AFTER UPDATE), así que si algo falla no queda ni precio ni bitácora.
     * Acceso: solo Administrador (productos.gestionar).
     */
    public function updatePrecio(Request $request, VarianteProducto $variante): RedirectResponse
    {
        if (! $request->user()?->tienePermiso('productos.gestionar')) {
            abort(403, 'Solo el Administrador puede realizar esta acción.');
        }

        $datos = $request->validate([
            'precio_venta' => ['required', 'numeric', 'gt:0', 'max:9999999999.99', 'decimal:0,2'],
            'motivo' => ['nullable', 'string', 'max:255'],
        ], [
            'precio_venta.required' => 'Ingresá el nuevo precio de venta.',
            'precio_venta.gt' => 'El precio de venta debe ser mayor a cero.',
            'precio_venta.decimal' => 'El precio admite como máximo 2 decimales.',
        ]);

        $nuevo = round((float) $datos['precio_venta'], 2);
        $motivo = trim((string) ($datos['motivo'] ?? ''));
        $motivo = $motivo !== ''
            ? "Cambio de precio: {$motivo}"
            : 'Actualización de precio de venta';

        $cambio = DB::transaction(function () use ($variante, $nuevo, $motivo): bool {
            $fila = VarianteProducto::query()
                ->whereKey($variante->id)
                ->lockForUpdate()
                ->firstOrFail();

            $anterior = round((float) $fila->precio_venta, 2);

            if ((int) round($anterior * 100) === (int) round($nuevo * 100)) {
                return false;
            }

            $esPostgres = DB::getDriverName() === 'pgsql';

            if ($esPostgres) {
                BitacoraService::bindContext();
                DB::statement("SELECT set_config('app.motivo_precio', ?, true)", [$motivo]);
            }

            $fila->forceFill([
                'precio_anterior' => $anterior,
                'precio_venta' => $nuevo,
            ])->save();

            if (! $esPostgres) {
                BitacoraService::registrar(
                    'Inventario',
                    'ACTUALIZAR_PRECIO',
                    'variante_producto',
                    $fila->id,
                    ['sku' => $fila->sku, 'precio_venta' => $anterior],
                    ['sku' => $fila->sku, 'precio_venta' => $nuevo],
                    $motivo,
                );
            }

            return true;
        });

        Inertia::flash('toast', $cambio
            ? ['type' => 'success', 'message' => "Precio de {$variante->sku} actualizado."]
            : ['type' => 'info', 'message' => 'El precio es el mismo; no se registró ningún cambio.']);

        return back();
    }

    /**
     * CU08-EX04: Baja lógica. No se elimina físicamente una variante con
     * movimientos; el Administrador la deja INACTIVA.
     */
    public function estado(Request $request, VarianteProducto $variante): RedirectResponse
    {
        if (! $request->user()?->tienePermiso('productos.gestionar')) {
            abort(403, 'Solo el Administrador puede realizar esta acción.');
        }

        $datos = $request->validate([
            'estado' => ['required', 'in:ACTIVO,INACTIVO'],
        ]);

        $variante->forceFill(['estado' => $datos['estado']])->save();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $datos['estado'] === 'INACTIVO'
                ? "Variante {$variante->sku} dada de baja."
                : "Variante {$variante->sku} reactivada.",
        ]);

        return back();
    }

    /**
     * CU08-EX04: Eliminación física solo si no hay ventas, compras ni movimientos.
     */
    public function destroy(Request $request, VarianteProducto $variante): RedirectResponse
    {
        if (! $request->user()?->tienePermiso('productos.gestionar')) {
            abort(403, 'Solo el Administrador puede realizar esta acción.');
        }

        if ($variante->tieneMovimientos()) {
            throw ValidationException::withMessages([
                'variante' => 'No se puede eliminar una variante con movimientos. Cambiá su estado a INACTIVO.',
            ]);
        }

        $sku = $variante->sku;
        $variante->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => "Variante {$sku} eliminada."]);

        return back();
    }

    // ── Control: Helpers internos ───────────────────────────────────────────

    /**
     * Convierte la selección del formulario en filas de valor_atributo,
     * reutilizando valores existentes (sin distinguir mayúsculas) o creándolos.
     *
     * @param  list<array{atributo_id: int, valor_atributo_id: int|null, valor: string|null}>  $atributos
     * @return Collection<int, ValorAtributo>
     */
    private function resolverValores(array $atributos)
    {
        return collect($atributos)->map(function (array $item): ValorAtributo {
            if ($item['valor_atributo_id'] !== null) {
                return ValorAtributo::query()->with('atributo')->findOrFail($item['valor_atributo_id']);
            }

            $texto = trim((string) $item['valor']);

            $existente = ValorAtributo::query()
                ->with('atributo')
                ->where('atributo_id', $item['atributo_id'])
                ->whereRaw('lower(valor) = ?', [mb_strtolower($texto)])
                ->first();

            if ($existente) {
                return $existente;
            }

            $orden = (int) ValorAtributo::query()
                ->where('atributo_id', $item['atributo_id'])
                ->max('orden_visualizacion');

            return ValorAtributo::query()->create([
                'atributo_id' => $item['atributo_id'],
                'valor' => $texto,
                'orden_visualizacion' => $orden + 1,
                'estado' => 'ACTIVO',
            ])->load('atributo');
        });
    }

    /**
     * @param  iterable<ValorAtributo>  $valores
     */
    private function textoAtributos(iterable $valores): string
    {
        $partes = collect($valores)
            ->map(fn (ValorAtributo $v) => ($v->atributo?->nombre ?? 'Atributo').': '.$v->valor)
            ->sort()
            ->values()
            ->all();

        return $partes === [] ? 'Sin atributos' : implode(', ', $partes);
    }

    /**
     * @param  list<int>  $conMovimientos
     * @return array<string, mixed>
     */
    private function formatVariante(VarianteProducto $v, array $conMovimientos = []): array
    {
        return [
            'id' => $v->id,
            'sku' => $v->sku,
            'codigo_barras' => $v->codigo_barras,
            'precio_venta' => $v->precio_venta,
            'precio_anterior' => $v->precio_anterior,
            'stock_actual' => $v->stock_actual,
            'stock_minimo' => $v->stock_minimo,
            'estado' => $v->estado ?? 'ACTIVO',
            'tiene_movimientos' => in_array($v->id, $conMovimientos, true),
            'atributos' => $v->valores
                ->map(fn (ValorAtributo $valor) => [
                    'atributo' => $valor->atributo?->nombre ?? 'Atributo',
                    'valor' => $valor->valor,
                ])
                ->sortBy('atributo')
                ->values(),
            'ultima_venta' => $this->fecha($v->getAttribute('ultima_venta')),
            'fecha_creacion' => $v->fecha_creacion?->format('d/m/Y'),
        ];
    }

    /**
     * Últimos cambios de precio registrados en bitacora_auditoria para las variantes dadas.
     *
     * @param  list<int>  $varianteIds
     * @return list<array<string, mixed>>
     */
    private function historialPrecios(array $varianteIds): array
    {
        if ($varianteIds === []) {
            return [];
        }

        return DB::table('bitacora_auditoria as b')
            ->leftJoin('usuario as u', 'u.id', '=', 'b.usuario_id')
            ->leftJoin('personal as p', 'p.id', '=', 'u.personal_id')
            ->leftJoin('variante_producto as vp', 'vp.id', '=', 'b.entidad_id')
            ->where('b.accion', 'ACTUALIZAR_PRECIO')
            ->where('b.tipo_entidad', 'variante_producto')
            ->whereIn('b.entidad_id', $varianteIds)
            ->orderByDesc('b.fecha_hora')
            ->orderByDesc('b.id')
            ->limit(30)
            ->get([
                'b.id',
                'b.entidad_id',
                'b.fecha_hora',
                'b.valor_anterior',
                'b.valor_nuevo',
                'b.motivo',
                'b.direccion_ip',
                'vp.sku',
                'p.nombre_completo',
                'u.email',
            ])
            ->map(function (object $fila): array {
                $antes = json_decode((string) $fila->valor_anterior, true);
                $ahora = json_decode((string) $fila->valor_nuevo, true);
                $momento = $fila->fecha_hora
                    ? Carbon::parse($fila->fecha_hora)->timezone(config('app.timezone'))
                    : null;

                return [
                    'id' => (int) $fila->id,
                    'variante_id' => (int) $fila->entidad_id,
                    'sku' => $fila->sku,
                    'fecha' => $momento?->format('d/m/Y'),
                    'hora' => $momento?->format('H:i'),
                    'usuario' => trim((string) ($fila->nombre_completo ?? '')) ?: ($fila->email ?? 'Sistema'),
                    'precio_anterior' => isset($antes['precio_venta']) ? (float) $antes['precio_venta'] : null,
                    'precio_nuevo' => isset($ahora['precio_venta']) ? (float) $ahora['precio_venta'] : null,
                    'motivo' => $fila->motivo,
                    'direccion_ip' => $fila->direccion_ip,
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
}
