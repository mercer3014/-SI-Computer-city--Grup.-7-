<?php

namespace App\Http\Controllers;

use App\Models\CategoriaGasto;
use App\Models\Gasto;
use App\Models\MetodoPago;
use App\Models\MovimientoCaja;
use App\Models\User;
use App\Services\BitacoraService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * CU15: gastos y flujo de caja.
 *
 * El flujo consolida cuatro orígenes: cobros de ventas (pago_venta), compras a
 * proveedores, gastos operativos y movimientos manuales de caja. Los gastos
 * también dejan su EGRESO en movimiento_caja, pero se cuentan desde la tabla
 * gasto para no duplicarlos.
 */
class CajaController extends Controller
{
    /** @var list<string> */
    public const CATEGORIAS_INGRESO = [
        'Aporte de capital',
        'Servicio técnico',
        'Préstamo recibido',
        'Otros ingresos',
    ];

    /** @var list<string> */
    public const CATEGORIAS_EGRESO_MANUAL = [
        'Retiro del propietario',
        'Pago de préstamo',
        'Otros egresos',
    ];

    public function index(Request $request): Response
    {
        $this->autorizarConsulta($request->user());

        [$desde, $hasta] = $this->periodo($request);
        $tipo = $request->string('tipo')->value();
        $origen = $request->string('origen')->value();

        if (! in_array($tipo, ['INGRESO', 'EGRESO', ''], true)) {
            $tipo = '';
        }

        if (! in_array($origen, ['VENTA', 'COMPRA', 'GASTO', 'MOVIMIENTO', ''], true)) {
            $origen = '';
        }

        $movimientos = DB::query()
            ->fromSub($this->flujo($desde, $hasta), 'm')
            ->when($tipo !== '', fn (Builder $q) => $q->where('tipo', $tipo))
            ->when($origen !== '', fn (Builder $q) => $q->where('origen', $origen))
            ->orderByDesc('fecha')
            ->orderByDesc('origen_id')
            ->paginate(20)
            ->withQueryString();

        $activos = DB::query()
            ->fromSub($this->flujo($desde, $hasta), 'm')
            ->where('estado', 'ACTIVO');

        $porCategoria = (clone $activos)
            ->select('tipo', 'categoria')
            ->selectRaw('sum(monto) as total, count(*) as cantidad')
            ->groupBy('tipo', 'categoria')
            ->orderByDesc('total')
            ->get()
            ->map(fn (object $fila) => [
                'tipo' => $fila->tipo,
                'categoria' => $fila->categoria,
                'total' => round((float) $fila->total, 2),
                'cantidad' => (int) $fila->cantidad,
            ])
            ->values();

        $ingresos = (float) $porCategoria->where('tipo', 'INGRESO')->sum('total');
        $egresos = (float) $porCategoria->where('tipo', 'EGRESO')->sum('total');

        $historico = DB::query()
            ->fromSub($this->flujo(CarbonImmutable::create(2000), $hasta), 'h')
            ->where('estado', 'ACTIVO')
            ->selectRaw("coalesce(sum(case when tipo = 'INGRESO' then monto else -monto end), 0) as saldo")
            ->value('saldo');

        return Inertia::render('CajaGastos', [
            'movimientos' => [
                'data' => collect($movimientos->items())
                    ->map(fn (object $fila) => $this->formatMovimiento($fila))
                    ->values(),
                'meta' => [
                    'current_page' => $movimientos->currentPage(),
                    'last_page' => $movimientos->lastPage(),
                    'total' => $movimientos->total(),
                    'from' => $movimientos->firstItem(),
                    'to' => $movimientos->lastItem(),
                ],
            ],
            'resumen' => [
                'ingresos' => round($ingresos, 2),
                'egresos' => round($egresos, 2),
                'neto' => round($ingresos - $egresos, 2),
                'saldo' => round((float) $historico, 2),
                'por_categoria' => $porCategoria,
            ],
            'filtros' => [
                'desde' => $desde->toDateString(),
                'hasta' => $hasta->toDateString(),
                'tipo' => $tipo,
                'origen' => $origen,
            ],
            'opciones' => [
                'categorias_gasto' => CategoriaGasto::query()
                    ->where('estado', 'ACTIVO')
                    ->orderBy('nombre')
                    ->get(['id', 'nombre']),
                'categorias_ingreso' => self::CATEGORIAS_INGRESO,
                'categorias_egreso' => self::CATEGORIAS_EGRESO_MANUAL,
                'metodos' => MetodoPago::query()
                    ->where('estado', 'ACTIVO')
                    ->whereNotIn('codigo', ['COMBINADO', 'TARJETA'])
                    ->orderBy('nombre')
                    ->get(['id', 'nombre']),
            ],
            'puedeRegistrar' => $request->user()?->tienePermiso('gastos.gestionar') ?? false,
        ]);
    }

    /**
     * Registra un gasto (EGRESO con categoría de gasto) o un movimiento manual.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->autorizarRegistro($request->user());

        $datos = $request->validate([
            'tipo' => ['required', Rule::in([MovimientoCaja::INGRESO, MovimientoCaja::EGRESO])],
            'es_gasto' => ['boolean'],
            'monto' => ['required', 'numeric', 'gt:0', 'max:9999999.99'],
            'fecha' => ['required', 'date', 'before_or_equal:today'],
            'descripcion' => ['required', 'string', 'min:3', 'max:255'],
            'categoria_gasto_id' => [
                'nullable',
                'integer',
                Rule::exists('categoria_gasto', 'id')->where('estado', 'ACTIVO'),
            ],
            'categoria' => ['nullable', 'string', 'max:100'],
            'metodo_pago_id' => ['nullable', 'integer', Rule::exists('metodo_pago', 'id')],
            'referencia' => ['nullable', 'string', 'max:100'],
            'observacion' => ['nullable', 'string', 'max:1000'],
        ], [
            'tipo.required' => 'Elegí si es un ingreso o un egreso.',
            'monto.required' => 'Ingresá el monto.',
            'monto.numeric' => 'El monto debe ser un número.',
            'monto.gt' => 'El monto debe ser mayor a cero.',
            'monto.max' => 'El monto es demasiado alto.',
            'fecha.required' => 'Indicá la fecha del movimiento.',
            'fecha.before_or_equal' => 'La fecha no puede ser futura.',
            'descripcion.required' => 'Describí el movimiento.',
            'descripcion.min' => 'La descripción debe tener al menos 3 caracteres.',
            'categoria_gasto_id.exists' => 'La categoría de gasto no existe o está inactiva.',
        ]);

        $esGasto = $datos['tipo'] === MovimientoCaja::EGRESO && ($datos['es_gasto'] ?? true);

        if ($esGasto && empty($datos['categoria_gasto_id'])) {
            throw ValidationException::withMessages([
                'categoria_gasto_id' => 'Elegí la categoría del gasto.',
            ]);
        }

        if (! $esGasto && trim((string) ($datos['categoria'] ?? '')) === '') {
            throw ValidationException::withMessages([
                'categoria' => 'Elegí la categoría del movimiento.',
            ]);
        }

        $usuarioId = (int) $request->user()->id;
        $fecha = CarbonImmutable::parse($datos['fecha'])->setTimeFrom(now());
        $descripcion = trim($datos['descripcion']);

        $mensaje = DB::transaction(function () use ($datos, $esGasto, $usuarioId, $fecha, $descripcion): string {
            if ($esGasto) {
                $gasto = Gasto::query()->create([
                    'categoria_gasto_id' => $datos['categoria_gasto_id'],
                    'metodo_pago_id' => $datos['metodo_pago_id'] ?? null,
                    'usuario_id' => $usuarioId,
                    'fecha' => $fecha,
                    'concepto' => $descripcion,
                    'monto' => $datos['monto'],
                    'referencia' => $datos['referencia'] ?? null,
                    'observacion' => $datos['observacion'] ?? null,
                    'estado' => 'ACTIVO',
                ]);
                $gasto->load('categoria');

                MovimientoCaja::query()->create([
                    'usuario_id' => $usuarioId,
                    'gasto_id' => $gasto->id,
                    'metodo_pago_id' => $gasto->metodo_pago_id,
                    'tipo' => MovimientoCaja::EGRESO,
                    'categoria' => $gasto->categoria?->nombre,
                    'monto' => $gasto->monto,
                    'fecha' => $fecha,
                    'descripcion' => $descripcion,
                    'referencia' => $gasto->referencia,
                    'estado' => 'ACTIVO',
                ]);

                BitacoraService::registrar(
                    'Finanzas',
                    'REGISTRAR_GASTO',
                    'gasto',
                    $gasto->id,
                    null,
                    [
                        'categoria' => $gasto->categoria?->nombre,
                        'concepto' => $gasto->concepto,
                        'monto' => $gasto->monto,
                    ],
                    "Gasto {$gasto->concepto}",
                );

                return 'Gasto registrado: Bs '.number_format($gasto->monto, 2, '.', ',').'.';
            }

            $movimiento = MovimientoCaja::query()->create([
                'usuario_id' => $usuarioId,
                'metodo_pago_id' => $datos['metodo_pago_id'] ?? null,
                'tipo' => $datos['tipo'],
                'categoria' => trim((string) $datos['categoria']),
                'monto' => $datos['monto'],
                'fecha' => $fecha,
                'descripcion' => $descripcion,
                'referencia' => $datos['referencia'] ?? null,
                'estado' => 'ACTIVO',
            ]);

            BitacoraService::registrar(
                'Finanzas',
                'REGISTRAR_MOVIMIENTO_CAJA',
                'movimiento_caja',
                $movimiento->id,
                null,
                [
                    'tipo' => $movimiento->tipo,
                    'categoria' => $movimiento->categoria,
                    'monto' => $movimiento->monto,
                ],
                $descripcion,
            );

            return ($movimiento->tipo === MovimientoCaja::INGRESO ? 'Ingreso' : 'Egreso')
                .' registrado: Bs '.number_format($movimiento->monto, 2, '.', ',').'.';
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => $mensaje]);

        return back();
    }

    /**
     * Baja lógica: el registro queda ANULADO y deja de sumar al flujo.
     */
    public function anular(Request $request, string $origen, int $id): RedirectResponse
    {
        $this->autorizarRegistro($request->user());

        $datos = $request->validate([
            'motivo' => ['required', 'string', 'min:5', 'max:500'],
        ], [
            'motivo.required' => 'Indicá el motivo de la anulación.',
            'motivo.min' => 'El motivo debe tener al menos 5 caracteres.',
        ]);

        $motivo = trim($datos['motivo']);

        if ($origen === 'GASTO') {
            $gasto = Gasto::query()->findOrFail($id);

            if ($gasto->estado === 'ANULADO') {
                throw ValidationException::withMessages(['motivo' => 'El gasto ya está anulado.']);
            }

            DB::transaction(function () use ($gasto, $motivo): void {
                $gasto->forceFill(['estado' => 'ANULADO', 'motivo_anulacion' => $motivo])->save();
                MovimientoCaja::query()
                    ->where('gasto_id', $gasto->id)
                    ->update(['estado' => 'ANULADO', 'motivo_anulacion' => $motivo]);

                BitacoraService::registrar(
                    'Finanzas',
                    'ANULAR_GASTO',
                    'gasto',
                    $gasto->id,
                    ['estado' => 'ACTIVO', 'monto' => $gasto->monto],
                    ['estado' => 'ANULADO'],
                    $motivo,
                );
            });
        } elseif ($origen === 'MOVIMIENTO') {
            $movimiento = MovimientoCaja::query()->whereNull('gasto_id')->findOrFail($id);

            if ($movimiento->estado === 'ANULADO') {
                throw ValidationException::withMessages(['motivo' => 'El movimiento ya está anulado.']);
            }

            DB::transaction(function () use ($movimiento, $motivo): void {
                $movimiento->forceFill(['estado' => 'ANULADO', 'motivo_anulacion' => $motivo])->save();

                BitacoraService::registrar(
                    'Finanzas',
                    'ANULAR_MOVIMIENTO_CAJA',
                    'movimiento_caja',
                    $movimiento->id,
                    ['estado' => 'ACTIVO', 'monto' => $movimiento->monto],
                    ['estado' => 'ANULADO'],
                    $motivo,
                );
            });
        } else {
            abort(404);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Registro anulado. Ya no suma al flujo de caja.']);

        return back();
    }

    public function storeCategoria(Request $request): RedirectResponse
    {
        $this->autorizarRegistro($request->user());

        $datos = $request->validate([
            'nombre' => [
                'required',
                'string',
                'max:150',
                function (string $atributo, mixed $valor, \Closure $fail): void {
                    $existe = DB::table('categoria_gasto')
                        ->whereRaw('lower(nombre) = ?', [mb_strtolower(trim((string) $valor))])
                        ->exists();

                    if ($existe) {
                        $fail('Ya existe una categoría de gasto con ese nombre.');
                    }
                },
            ],
            'descripcion' => ['nullable', 'string', 'max:500'],
        ], [
            'nombre.required' => 'Ingresá el nombre de la categoría.',
        ]);

        $categoria = CategoriaGasto::query()->create([
            'nombre' => trim($datos['nombre']),
            'descripcion' => $datos['descripcion'] ?? null,
            'estado' => 'ACTIVO',
        ]);

        BitacoraService::registrar(
            'Finanzas',
            'CREAR_CATEGORIA_GASTO',
            'categoria_gasto',
            $categoria->id,
            null,
            ['nombre' => $categoria->nombre],
            "Categoría de gasto {$categoria->nombre}",
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Categoría '.$categoria->nombre.' creada.']);

        return back();
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function periodo(Request $request): array
    {
        $hoy = CarbonImmutable::now(config('app.timezone'))->startOfDay();

        try {
            $desde = $request->filled('desde')
                ? CarbonImmutable::parse($request->string('desde')->value())->startOfDay()
                : $hoy->startOfMonth();
            $hasta = $request->filled('hasta')
                ? CarbonImmutable::parse($request->string('hasta')->value())->startOfDay()
                : $hoy;
        } catch (\Throwable) {
            return [$hoy->startOfMonth(), $hoy];
        }

        return $desde->greaterThan($hasta) ? [$hasta, $desde] : [$desde, $hasta];
    }

    /**
     * Movimientos unificados con columnas homogéneas.
     */
    private function flujo(CarbonImmutable $desde, CarbonImmutable $hasta): Builder
    {
        $inicio = $desde->startOfDay()->toDateTimeString();
        $fin = $hasta->endOfDay()->toDateTimeString();

        $ventas = DB::table('pago_venta as pv')
            ->join('venta as v', 'v.id', '=', 'pv.venta_id')
            ->leftJoin('metodo_pago as mp', 'mp.id', '=', 'pv.metodo_pago_id')
            ->leftJoin('cliente as cl', 'cl.id', '=', 'v.cliente_id')
            ->where('v.estado', '<>', 'ANULADA')
            ->whereBetween('pv.fecha', [$inicio, $fin])
            ->selectRaw("'VENTA' as origen")
            ->selectRaw('pv.id as origen_id')
            ->selectRaw("'INGRESO' as tipo")
            ->selectRaw("case when v.pedido_id is null then 'Ventas en tienda' else 'Ventas en línea' end as categoria")
            ->selectRaw("('Venta ' || v.numero_venta || ' · ' || coalesce(cl.nombre, 'Cliente Final')) as descripcion")
            ->selectRaw('mp.nombre as metodo')
            ->selectRaw('coalesce(pv.referencia, v.numero_venta) as referencia')
            ->selectRaw('pv.monto as monto')
            ->selectRaw('pv.fecha as fecha')
            ->selectRaw("'ACTIVO' as estado");

        $totalesCompra = DB::table('detalle_compra')
            ->select('compra_id')
            ->selectRaw('sum(cantidad * costo_unitario) as total')
            ->groupBy('compra_id');

        $compras = DB::table('compra as c')
            ->joinSub($totalesCompra, 'dc', 'dc.compra_id', '=', 'c.id')
            ->leftJoin('proveedor as p', 'p.id', '=', 'c.proveedor_id')
            ->leftJoin('metodo_pago as mp', 'mp.id', '=', 'c.metodo_pago_id')
            ->where(fn (Builder $q) => $q->whereNull('c.estado')->orWhere('c.estado', '<>', 'ANULADA'))
            ->whereBetween('c.fecha_compra', [$inicio, $fin])
            ->selectRaw("'COMPRA' as origen")
            ->selectRaw('c.id as origen_id')
            ->selectRaw("'EGRESO' as tipo")
            ->selectRaw("'Compras a proveedores' as categoria")
            ->selectRaw("('Compra ' || c.numero_compra || ' · ' || coalesce(p.razon_social, 'Proveedor')) as descripcion")
            ->selectRaw('mp.nombre as metodo')
            ->selectRaw('c.numero_documento as referencia')
            ->selectRaw('dc.total as monto')
            ->selectRaw('c.fecha_compra as fecha')
            ->selectRaw("'ACTIVO' as estado");

        $gastos = DB::table('gasto as g')
            ->leftJoin('categoria_gasto as cg', 'cg.id', '=', 'g.categoria_gasto_id')
            ->leftJoin('metodo_pago as mp', 'mp.id', '=', 'g.metodo_pago_id')
            ->whereBetween('g.fecha', [$inicio, $fin])
            ->selectRaw("'GASTO' as origen")
            ->selectRaw('g.id as origen_id')
            ->selectRaw("'EGRESO' as tipo")
            ->selectRaw("coalesce(cg.nombre, 'Gasto operativo') as categoria")
            ->selectRaw('g.concepto as descripcion')
            ->selectRaw('mp.nombre as metodo')
            ->selectRaw('g.referencia as referencia')
            ->selectRaw('g.monto as monto')
            ->selectRaw('g.fecha as fecha')
            ->selectRaw("coalesce(g.estado, 'ACTIVO') as estado");

        $manuales = DB::table('movimiento_caja as mc')
            ->leftJoin('metodo_pago as mp', 'mp.id', '=', 'mc.metodo_pago_id')
            ->whereNull('mc.gasto_id')
            ->whereBetween('mc.fecha', [$inicio, $fin])
            ->selectRaw("'MOVIMIENTO' as origen")
            ->selectRaw('mc.id as origen_id')
            ->selectRaw('mc.tipo as tipo')
            ->selectRaw("coalesce(mc.categoria, 'Sin categoría') as categoria")
            ->selectRaw('mc.descripcion as descripcion')
            ->selectRaw('mp.nombre as metodo')
            ->selectRaw('mc.referencia as referencia')
            ->selectRaw('mc.monto as monto')
            ->selectRaw('mc.fecha as fecha')
            ->selectRaw("coalesce(mc.estado, 'ACTIVO') as estado");

        return $ventas->unionAll($compras)->unionAll($gastos)->unionAll($manuales);
    }

    /**
     * @return array<string, mixed>
     */
    private function formatMovimiento(object $fila): array
    {
        $fecha = $fila->fecha !== null
            ? CarbonImmutable::parse($fila->fecha)->timezone(config('app.timezone'))
            : null;

        return [
            'clave' => $fila->origen.'-'.$fila->origen_id,
            'origen' => $fila->origen,
            'origen_id' => (int) $fila->origen_id,
            'tipo' => $fila->tipo,
            'categoria' => $fila->categoria,
            'descripcion' => $fila->descripcion,
            'metodo' => $fila->metodo,
            'referencia' => $fila->referencia,
            'monto' => round((float) $fila->monto, 2),
            'fecha' => $fecha?->format('d/m/Y'),
            'estado' => $fila->estado,
            'anulable' => in_array($fila->origen, ['GASTO', 'MOVIMIENTO'], true) && $fila->estado === 'ACTIVO',
        ];
    }

    private function autorizarConsulta(?User $user): void
    {
        abort_unless(
            $user instanceof User && ($user->tienePermiso('gastos.gestionar') || $user->tienePermiso('reportes.ver')),
            403,
            'No tenés permiso para acceder al flujo de caja.',
        );
    }

    private function autorizarRegistro(?User $user): void
    {
        abort_unless(
            $user instanceof User && $user->tienePermiso('gastos.gestionar'),
            403,
            'Solo quien gestiona gastos puede registrar movimientos de caja.',
        );
    }
}
