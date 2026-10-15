<?php

namespace App\Http\Controllers;

use App\Http\Requests\ClienteRequest;
use App\Http\Requests\VentaRequest;
use App\Mail\NotaVentaMail;
use App\Models\Cliente;
use App\Models\DetalleVenta;
use App\Models\MetodoPago;
use App\Models\PagoVenta;
use App\Models\User;
use App\Models\VarianteProducto;
use App\Models\Venta;
use App\Services\BitacoraService;
use App\Services\NotaVentaPdf;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Throwable;

class VentaController extends Controller
{
    public function __construct(private NotaVentaPdf $notaPdf) {}

    /**
     * POS (nueva venta) + consulta de ventas.
     */
    public function index(Request $request): Response
    {
        $this->autorizarConsulta($request->user());

        $like = DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';
        $q = $request->string('q')->trim()->value();
        $estado = $request->string('estado')->value();
        $tab = $request->string('tab')->value();
        $desde = $request->string('desde')->value();
        $hasta = $request->string('hasta')->value();
        $clienteId = $request->integer('cliente');
        $creadaId = $request->integer('creada');
        $fichaId = $request->integer('ficha');

        if (! in_array($tab, ['nueva', 'consultar'], true)) {
            $tab = 'consultar';
        }

        if (! in_array($estado, ['COMPLETADA', 'ANULADA', ''], true)) {
            $estado = '';
        }

        $query = Venta::query()->with(['cliente:id,nombre,numero_documento', 'vendedor.personal']);

        if ($q !== '') {
            $busqueda = '%'.addcslashes($q, '%_\\').'%';
            $query->where(function ($sub) use ($busqueda, $like): void {
                $sub->where('numero_venta', $like, $busqueda)
                    ->orWhereHas('cliente', function ($cliente) use ($busqueda, $like): void {
                        $cliente->where('nombre', $like, $busqueda)
                            ->orWhere('numero_documento', $like, $busqueda);
                    })
                    ->orWhereHas('detalles', fn ($d) => $d->where('numero_serie', $like, $busqueda));
            });
        }

        if ($estado !== '') {
            $query->where('estado', $estado);
        }

        if ($desde !== '') {
            $query->whereDate('fecha', '>=', $desde);
        }

        if ($hasta !== '') {
            $query->whereDate('fecha', '<=', $hasta);
        }

        $ventas = $query
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        $totales = $this->totalesPagina($ventas->getCollection()->pluck('id')->all());

        return Inertia::render('Ventas', [
            'ventas' => [
                'data' => $ventas->getCollection()
                    ->map(fn (Venta $venta) => $this->formatListado($venta, (float) ($totales[$venta->id] ?? 0)))
                    ->values(),
                'meta' => [
                    'current_page' => $ventas->currentPage(),
                    'last_page' => $ventas->lastPage(),
                    'total' => $ventas->total(),
                    'from' => $ventas->firstItem(),
                    'to' => $ventas->lastItem(),
                ],
            ],
            'catalogo' => $this->catalogo(),
            'clientes' => $this->clientesPos(),
            'metodos' => MetodoPago::query()
                ->where('estado', 'ACTIVO')
                ->where('codigo', '<>', 'COMBINADO')
                ->orderBy('nombre')
                ->get(['id', 'codigo', 'nombre']),
            'ficha' => $this->ficha($fichaId > 0 ? $fichaId : $creadaId),
            'kpis' => [
                'hoy' => Venta::query()->whereDate('fecha', now()->toDateString())->where('estado', '<>', 'ANULADA')->count(),
                'completadas' => Venta::query()->where('estado', 'COMPLETADA')->count(),
                'anuladas' => Venta::query()->where('estado', 'ANULADA')->count(),
            ],
            'filtros' => [
                'q' => $q,
                'estado' => $estado,
                'tab' => $tab,
                'desde' => $desde,
                'hasta' => $hasta,
                'cliente' => $clienteId > 0 ? (string) $clienteId : '',
                'creada' => $creadaId > 0 ? (string) $creadaId : '',
                'ficha' => $fichaId > 0 ? (string) $fichaId : '',
            ],
            'abrirAlta' => $request->boolean('alta'),
            'puedeCrear' => $request->user()?->tienePermiso('ventas.crear') ?? false,
            'puedeAnular' => $request->user()?->tienePermiso('ventas.anular') ?? false,
            'puedeClientes' => $request->user()?->tienePermiso('clientes.gestionar') ?? false,
        ]);
    }

    /**
     * CU11: transacción atómica Venta → DetalleVenta → PagoVenta.
     * El stock y el Kardex los resuelven los triggers de PostgreSQL.
     */
    public function store(VentaRequest $request): RedirectResponse
    {
        $datos = $request->validated();
        $usuarioId = (int) $request->user()->id;

        $nombreClienteNuevo = null;

        try {
            $venta = DB::transaction(function () use ($datos, $usuarioId, &$nombreClienteNuevo) {
                $variantes = $this->variantesBloqueadas($datos['items']);
                $this->validarStock($datos['items'], $variantes);

                $clienteId = $datos['cliente_id'] ?? null;

                if (is_array($datos['cliente_nuevo'] ?? null)) {
                    $nuevo = $datos['cliente_nuevo'];
                    $cliente = Cliente::query()->create([
                        'tipo_documento' => $nuevo['tipo_documento'],
                        'numero_documento' => $nuevo['numero_documento'],
                        'nombre' => $nuevo['nombre'],
                        'telefono' => $nuevo['telefono'] ?? null,
                        'email' => $nuevo['email'] ?? null,
                        'estado' => 'ACTIVO',
                    ]);

                    BitacoraService::registrar(
                        'Ventas',
                        'CREAR_CLIENTE',
                        'cliente',
                        $cliente->id,
                        null,
                        ['nombre' => $cliente->nombre, 'numero_documento' => $cliente->numero_documento],
                        "Alta de cliente {$cliente->nombre} desde el POS",
                    );

                    $nombreClienteNuevo = $cliente->nombre;
                    $clienteId = $cliente->id;
                }

                $venta = Venta::query()->create([
                    'cliente_id' => $clienteId,
                    'usuario_id' => $usuarioId,
                    'numero_venta' => Venta::siguienteNumero(),
                    'fecha' => now(),
                    'estado' => 'COMPLETADA',
                    'descuento_global' => (float) ($datos['descuento_global'] ?? 0),
                    'observacion' => $datos['observacion'] ?? null,
                ]);

                foreach ($datos['items'] as $item) {
                    $variante = $variantes->get((int) $item['variante_id']);
                    $meses = (int) ($item['meses_garantia'] ?? 3);
                    $fecha = $venta->fecha ?? now();

                    DetalleVenta::query()->create([
                        'venta_id' => $venta->id,
                        'variante_id' => $variante->id,
                        'cantidad' => (int) $item['cantidad'],
                        'precio_unitario' => $variante->precio_venta,
                        'descuento' => (float) ($item['descuento'] ?? 0),
                        'costo_unitario_snapshot' => $this->ultimoCosto($variante->id),
                        'nombre_producto_snapshot' => $variante->producto?->nombre,
                        'variante_snapshot' => $variante->etiquetaAtributos(),
                        'numero_serie' => $item['numero_serie'] ?? null,
                        'meses_garantia' => $meses > 0 ? $meses : 3,
                        'fecha_fin_garantia' => Carbon::parse($fecha)->addMonths($meses > 0 ? $meses : 3)->toDateString(),
                        'estado' => 'VIGENTE',
                    ]);
                }

                foreach ($datos['pagos'] as $pago) {
                    PagoVenta::query()->create([
                        'venta_id' => $venta->id,
                        'metodo_pago_id' => $pago['metodo_pago_id'],
                        'monto' => $pago['monto'],
                        'referencia' => $pago['referencia'] ?? null,
                        'fecha' => now(),
                    ]);
                }

                $venta->load(['detalles', 'cliente']);

                BitacoraService::registrar(
                    'Ventas',
                    'REGISTRAR_VENTA',
                    'venta',
                    $venta->id,
                    null,
                    [
                        'numero_venta' => $venta->numero_venta,
                        'cliente' => $venta->cliente?->nombre,
                        'total' => $venta->total(),
                    ],
                    'Venta '.$venta->numero_venta,
                );

                return $venta;
            });
        } catch (QueryException $e) {
            $this->relanzarStock($e);
            throw $e;
        }

        $emailError = null;

        if ($request->boolean('enviar_email')) {
            $emailError = $this->enviarNota($venta);
        }

        $avisos = [];

        if (is_string($nombreClienteNuevo) && $nombreClienteNuevo !== '') {
            $avisos[] = [
                'type' => 'success',
                'message' => 'Cliente '.$nombreClienteNuevo.' registrado correctamente.',
            ];
        }

        $avisos[] = [
            'type' => $emailError === null ? 'success' : 'warning',
            'message' => $emailError === null
                ? 'Venta '.$venta->numero_venta.' registrada.'
                : 'Venta '.$venta->numero_venta.' registrada. No se pudo enviar el correo: '.$emailError,
        ];

        Inertia::flash('toast', $avisos);

        return redirect()->route('ventas.index', [
            'creada' => $venta->id,
        ]);
    }

    public function storeCliente(ClienteRequest $request): RedirectResponse
    {
        $datos = $request->safe()->only([
            'tipo_documento',
            'numero_documento',
            'nombre',
            'razon_social',
            'telefono',
            'email',
            'direccion',
            'observacion',
        ]);

        $cliente = Cliente::query()->create($datos + ['estado' => 'ACTIVO']);

        BitacoraService::registrar(
            'Ventas',
            'CREAR_CLIENTE',
            'cliente',
            $cliente->id,
            null,
            ['nombre' => $cliente->nombre, 'numero_documento' => $cliente->numero_documento],
            "Alta de cliente {$cliente->nombre} desde el POS",
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Cliente '.$cliente->nombre.' registrado correctamente.']);

        return redirect()->route('ventas.index', [
            'alta' => 1,
            'cliente' => $cliente->id,
        ]);
    }

    public function nota(Request $request, Venta $venta): HttpResponse
    {
        $this->autorizarConsulta($request->user());

        return $this->notaPdf->descargar($venta);
    }

    /**
     * EX-05: la venta ya existe; reenviar o descargar no la altera.
     */
    public function reenviar(Request $request, Venta $venta): RedirectResponse
    {
        if (! $request->user()?->tienePermiso('ventas.crear')) {
            abort(403);
        }

        $error = $this->enviarNota($venta);

        Inertia::flash('toast', $error === null
            ? ['type' => 'success', 'message' => 'Nota de venta reenviada a '.$venta->cliente?->email.'.']
            : ['type' => 'error', 'message' => $error],
        );

        return back();
    }

    /**
     * EX-06 / EX-07: solo Administrador (ventas.anular) con motivo.
     * El reingreso de stock lo dispara trg_venta_restaurar_stock_anulacion.
     */
    public function anular(Request $request, Venta $venta): RedirectResponse
    {
        if (! $request->user()?->tienePermiso('ventas.anular')) {
            abort(403, 'Solo el Administrador puede anular una venta.');
        }

        if ($venta->estaAnulada()) {
            throw ValidationException::withMessages([
                'venta' => 'La venta '.$venta->numero_venta.' ya está ANULADA.',
            ]);
        }

        $datos = $request->validate([
            'motivo' => ['required', 'string', 'min:5', 'max:500'],
        ], [
            'motivo.required' => 'Indicá el motivo de la anulación.',
            'motivo.min' => 'Indicá el motivo de la anulación.',
        ]);

        DB::transaction(function () use ($venta, $datos, $request): void {
            $venta->forceFill([
                'estado' => 'ANULADA',
                'fecha_anulacion' => now(),
                'anulado_por' => $request->user()->id,
                'motivo_anulacion' => trim($datos['motivo']),
            ])->save();

            BitacoraService::registrar(
                'Ventas',
                'ANULAR_VENTA',
                'venta',
                $venta->id,
                ['numero_venta' => $venta->numero_venta, 'estado' => 'COMPLETADA'],
                ['numero_venta' => $venta->numero_venta, 'estado' => 'ANULADA'],
                $venta->motivo_anulacion,
            );
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Venta '.$venta->numero_venta.' anulada. El stock fue reingresado.',
        ]);

        return back();
    }

    private function autorizarConsulta(?User $user): void
    {
        abort_unless(
            $user instanceof User && ($user->tienePermiso('ventas.crear') || $user->tienePermiso('ventas.anular')),
            403,
            'No tenés permiso para acceder a ventas.',
        );
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return Collection<int, VarianteProducto>
     */
    private function variantesBloqueadas(array $items): Collection
    {
        $ids = collect($items)->pluck('variante_id')->map(fn ($id) => (int) $id)->unique()->values();

        return VarianteProducto::query()
            ->with(['producto:id,nombre,estado', 'valores.atributo'])
            ->whereIn('id', $ids)
            ->lockForUpdate()
            ->get()
            ->keyBy('id');
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @param  Collection<int, VarianteProducto>  $variantes
     */
    private function validarStock(array $items, Collection $variantes): void
    {
        $pedida = [];

        foreach ($items as $item) {
            $id = (int) $item['variante_id'];
            $variante = $variantes->get($id);

            if ($variante === null) {
                throw ValidationException::withMessages([
                    'items' => 'Una de las variantes ya no existe.',
                ]);
            }

            $pedida[$id] = ($pedida[$id] ?? 0) + (int) $item['cantidad'];

            if ($pedida[$id] > $variante->stock_actual) {
                throw ValidationException::withMessages([
                    'items' => 'Stock insuficiente para '.$variante->sku.' (disponible: '.$variante->stock_actual.', solicitado: '.$pedida[$id].').',
                ]);
            }
        }
    }

    private function ultimoCosto(int $varianteId): ?float
    {
        $costo = DB::table('detalle_compra')
            ->where('variante_id', $varianteId)
            ->orderByDesc('id')
            ->value('costo_unitario');

        return $costo === null ? null : (float) $costo;
    }

    private function relanzarStock(QueryException $e): void
    {
        $mensaje = $e->getMessage();

        if (str_contains($mensaje, 'Stock insuficiente')) {
            throw ValidationException::withMessages([
                'items' => 'Stock insuficiente para uno de los productos. Recargá el catálogo e intentá de nuevo.',
            ]);
        }
    }

    private function enviarNota(Venta $venta): ?string
    {
        $venta->loadMissing('cliente');
        $email = $venta->cliente?->email;

        if (! is_string($email) || $email === '') {
            return 'El cliente no tiene un email registrado. Descargá la nota e imprimila.';
        }

        try {
            Mail::to($email)->send(new NotaVentaMail($venta, $this->notaPdf->binario($venta)));
        } catch (Throwable $e) {
            report($e);

            return 'No se pudo enviar el correo. La venta quedó registrada; descargá la nota o reintentá el envío.';
        }

        return null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function catalogo(): array
    {
        return VarianteProducto::query()
            ->with(['producto:id,nombre,estado', 'valores.atributo'])
            ->where('variante_producto.estado', 'ACTIVO')
            ->whereHas('producto', fn ($p) => $p->where('estado', 'ACTIVO'))
            ->orderBy('sku')
            ->limit(400)
            ->get()
            ->map(fn (VarianteProducto $v) => [
                'id' => $v->id,
                'sku' => $v->sku,
                'codigo_barras' => $v->codigo_barras,
                'producto' => $v->producto?->nombre,
                'atributos' => $v->etiquetaAtributos(),
                'precio_venta' => (float) $v->precio_venta,
                'stock_actual' => $v->stock_actual,
                'stock_minimo' => $v->stock_minimo,
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function clientesPos(): array
    {
        return Cliente::query()
            ->where('estado', 'ACTIVO')
            ->orderByRaw("case when nombre = 'Cliente Final' then 0 else 1 end")
            ->orderBy('nombre')
            ->limit(300)
            ->get(['id', 'nombre', 'razon_social', 'tipo_documento', 'numero_documento', 'telefono', 'email'])
            ->map(fn (Cliente $c) => [
                'id' => $c->id,
                'nombre' => $c->nombre,
                'razon_social' => $c->razon_social,
                'tipo_documento' => $c->tipo_documento,
                'numero_documento' => $c->numero_documento,
                'telefono' => $c->telefono,
                'email' => $c->email,
                'es_generico' => $c->nombre === 'Cliente Final',
            ])
            ->all();
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, float>
     */
    private function totalesPagina(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $detalles = DB::table('detalle_venta')
            ->whereIn('venta_id', $ids)
            ->select('venta_id')
            ->selectRaw('coalesce(sum(cantidad * precio_unitario - coalesce(descuento, 0)), 0) as bruto')
            ->groupBy('venta_id')
            ->pluck('bruto', 'venta_id');

        $descuentos = Venta::query()->whereIn('id', $ids)->pluck('descuento_global', 'id');

        $totales = [];

        foreach ($ids as $id) {
            $totales[$id] = round((float) ($detalles[$id] ?? 0) - (float) ($descuentos[$id] ?? 0), 2);
        }

        return $totales;
    }

    /**
     * @return array<string, mixed>
     */
    private function formatListado(Venta $venta, float $total): array
    {
        $momento = $venta->fecha?->timezone(config('app.timezone'));

        return [
            'id' => $venta->id,
            'numero' => $venta->numero_venta,
            'fecha' => $momento?->format('d/m/Y H:i'),
            'estado' => $venta->estado,
            'cliente' => $venta->cliente?->nombre,
            'documento' => $venta->cliente?->numero_documento,
            'vendedor' => $venta->vendedor?->name ?: $venta->vendedor?->email,
            'total' => $total,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function ficha(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        $venta = Venta::query()
            ->with(['cliente', 'vendedor.personal', 'detalles.variante.producto', 'pagos.metodo', 'anulador.personal'])
            ->find($id);

        if ($venta === null) {
            return null;
        }

        $zona = config('app.timezone');
        $momento = $venta->fecha?->timezone($zona);

        return [
            'id' => $venta->id,
            'numero' => $venta->numero_venta,
            'fecha' => $momento?->format('d/m/Y H:i'),
            'estado' => $venta->estado,
            'observacion' => $venta->observacion,
            'descuento_global' => (float) $venta->descuento_global,
            'total' => $venta->total(),
            'motivo_anulacion' => $venta->motivo_anulacion,
            'fecha_anulacion' => $venta->fecha_anulacion?->timezone($zona)?->format('d/m/Y H:i'),
            'pdf' => route('ventas.nota', $venta),
            'cliente' => [
                'id' => $venta->cliente?->id,
                'nombre' => $venta->cliente?->nombre,
                'documento' => trim(($venta->cliente?->tipo_documento ?? '').' '.($venta->cliente?->numero_documento ?? '')),
                'email' => $venta->cliente?->email,
                'telefono' => $venta->cliente?->telefono,
            ],
            'vendedor' => $venta->vendedor?->name ?: $venta->vendedor?->email,
            'items' => $venta->detalles->map(fn (DetalleVenta $d) => [
                'id' => $d->id,
                'sku' => $d->variante?->sku,
                'producto' => $d->nombre_producto_snapshot ?: $d->variante?->producto?->nombre,
                'variante' => $d->variante_snapshot,
                'cantidad' => $d->cantidad,
                'precio_unitario' => (float) $d->precio_unitario,
                'descuento' => (float) $d->descuento,
                'subtotal' => $d->subtotal(),
                'numero_serie' => $d->numero_serie,
                'meses_garantia' => $d->meses_garantia,
                'fecha_fin_garantia' => $d->fecha_fin_garantia?->format('d/m/Y'),
                'estado' => $d->estado,
            ])->all(),
            'pagos' => $venta->pagos->map(fn (PagoVenta $p) => [
                'id' => $p->id,
                'metodo' => $p->metodo?->nombre,
                'monto' => (float) $p->monto,
                'referencia' => $p->referencia,
            ])->all(),
        ];
    }
}
