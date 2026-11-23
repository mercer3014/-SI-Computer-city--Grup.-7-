<?php

namespace App\Http\Controllers;

use App\Http\Requests\CambiarEstadoCotizacionRequest;
use App\Models\Cotizacion;
use App\Models\DetalleCotizacion;
use App\Models\User;
use App\Services\Cotizaciones\ConversionCotizacionService;
use App\Services\Cotizaciones\CotizacionWebService;
use App\Services\Cotizaciones\GestionCotizacionService;
use App\Services\Reportes\Generadores\ProductosMasVisitados;
use App\Services\Reportes\PeriodoReporte;
use App\Services\Reportes\ServicioReportes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;
use Throwable;

/**
 * CU20: gestión de cotizaciones y ranking de productos más visitados.
 * Las rutas llevan `permiso:cotizaciones.gestionar`; convertir en venta además exige `ventas.crear`.
 */
class CotizacionController extends Controller
{
    private const POR_PAGINA = 15;

    public function __construct(
        private GestionCotizacionService $gestion,
        private ConversionCotizacionService $conversion,
        private CotizacionWebService $web,
        private ServicioReportes $reportes,
    ) {}

    public function index(Request $request): Response
    {
        $this->gestion->vencer();

        $filtros = $this->filtros($request);
        $fichaId = $request->integer('ficha');

        $pagina = $this->consulta($filtros)
            ->with(['cliente:id,nombre,telefono', 'venta:id,cotizacion_id,numero_venta'])
            ->addSelect([
                'cotizacion.*',
                'total' => DB::table('detalle_cotizacion as dc')
                    ->selectRaw('coalesce(sum(dc.cantidad * dc.precio_unitario - coalesce(dc.descuento, 0)), 0)')
                    ->whereColumn('dc.cotizacion_id', 'cotizacion.id'),
            ])
            ->orderByDesc('fecha_emision')
            ->orderByDesc('cotizacion.id')
            ->paginate(self::POR_PAGINA)
            ->withQueryString();

        $zona = (string) config('app.timezone');

        return Inertia::render('cotizaciones/Index', [
            'tab' => 'cotizaciones',
            'cotizaciones' => [
                'data' => $pagina->getCollection()
                    ->map(fn (Cotizacion $c): array => [
                        'id' => $c->id,
                        'codigo' => $c->codigo_cotizacion,
                        'cliente' => $c->cliente->nombre,
                        'estado' => $c->estado,
                        'origen' => $c->origen,
                        'fecha' => $c->fecha_emision?->timezone($zona)->format('d/m/Y'),
                        'vencimiento' => $c->fecha_vencimiento?->format('d/m/Y'),
                        'total' => round((float) $c->getAttribute('total'), 2),
                        'venta' => $c->venta === null ? null : ['id' => $c->venta->id, 'numero' => $c->venta->numero_venta],
                    ])
                    ->values(),
                'meta' => [
                    'current_page' => $pagina->currentPage(),
                    'last_page' => max(1, $pagina->lastPage()),
                    'total' => $pagina->total(),
                    'from' => $pagina->firstItem(),
                    'to' => $pagina->lastItem(),
                ],
            ],
            'kpis' => $this->kpis(),
            'filtros' => $filtros,
            'ficha' => $fichaId > 0 ? $this->ficha($fichaId, $request->user()) : null,
            'puedeConvertir' => $request->user()?->tienePermiso('ventas.crear') ?? false,
            'visitados' => null,
        ]);
    }

    /**
     * Ranking de productos por visitas al catálogo público.
     */
    public function masVisitados(Request $request): Response
    {
        $usuario = $this->usuario($request);
        $hoy = PeriodoReporte::hoy();
        $error = null;

        $desde = $request->string('desde')->trim()->value() ?: $hoy->subDays(29)->toDateString();
        $hasta = $request->string('hasta')->trim()->value() ?: $hoy->toDateString();

        try {
            $periodo = PeriodoReporte::resolver('personalizado', $desde, $hasta);
        } catch (InvalidArgumentException $e) {
            $error = $e->getMessage();
            $periodo = PeriodoReporte::resolver('personalizado', $hoy->subDays(29)->toDateString(), $hoy->toDateString());
        }

        $resultado = $this->reportes->armar(
            'productos_mas_visitados',
            'Productos más visitados',
            app(ProductosMasVisitados::class),
            $periodo,
            [],
            $usuario,
        );

        return Inertia::render('cotizaciones/Index', [
            'tab' => 'visitados',
            'cotizaciones' => null,
            'kpis' => $this->kpis(),
            'filtros' => $this->filtros($request),
            'ficha' => null,
            'puedeConvertir' => $usuario->tienePermiso('ventas.crear'),
            'visitados' => [
                'resultado' => $resultado->aArray(),
                'desde' => $periodo->desde->toDateString(),
                'hasta' => $periodo->hasta->toDateString(),
                'error' => $error,
            ],
        ]);
    }

    /**
     * El detalle se muestra como panel lateral del listado.
     */
    public function show(Cotizacion $cotizacion): RedirectResponse
    {
        return redirect()->route('cotizaciones.index', ['ficha' => $cotizacion->id]);
    }

    public function estado(CambiarEstadoCotizacionRequest $request, Cotizacion $cotizacion): RedirectResponse
    {
        $datos = $request->validated();

        $this->gestion->cambiarEstado(
            $cotizacion,
            (string) $datos['estado'],
            isset($datos['observacion']) ? (string) $datos['observacion'] : null,
            $this->usuario($request),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Cotización '.$cotizacion->codigo_cotizacion.' actualizada.']);

        return back();
    }

    public function renovar(Request $request, Cotizacion $cotizacion): RedirectResponse
    {
        $nueva = $this->gestion->renovar($cotizacion, $this->usuario($request));

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Se creó la cotización '.$nueva->codigo_cotizacion.' con precios actuales. La anterior quedó vencida.',
        ]);

        return redirect()->route('cotizaciones.index', ['ficha' => $nueva->id]);
    }

    /**
     * @return array{estado: string, desde: string, hasta: string, q: string}
     */
    private function filtros(Request $request): array
    {
        $estado = $request->string('estado')->value();

        return [
            'estado' => in_array($estado, Cotizacion::ESTADOS, true) ? $estado : '',
            'desde' => $this->fecha($request->string('desde')->value()),
            'hasta' => $this->fecha($request->string('hasta')->value()),
            'q' => mb_substr($request->string('q')->trim()->value(), 0, 100),
        ];
    }

    private function fecha(string $valor): string
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor) !== 1) {
            return '';
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $valor)?->format('Y-m-d') === $valor ? $valor : '';
        } catch (Throwable) {
            return '';
        }
    }

    /**
     * @param  array{estado: string, desde: string, hasta: string, q: string}  $filtros
     * @return Builder<Cotizacion>
     */
    private function consulta(array $filtros): Builder
    {
        $like = DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';
        $consulta = Cotizacion::query();

        if ($filtros['estado'] !== '') {
            $consulta->where('estado', $filtros['estado']);
        }

        if ($filtros['desde'] !== '') {
            $consulta->whereDate('fecha_emision', '>=', $filtros['desde']);
        }

        if ($filtros['hasta'] !== '') {
            $consulta->whereDate('fecha_emision', '<=', $filtros['hasta']);
        }

        if ($filtros['q'] !== '') {
            $busqueda = '%'.addcslashes($filtros['q'], '%_\\').'%';
            $consulta->where(function (Builder $sub) use ($busqueda, $like): void {
                $sub->where('codigo_cotizacion', $like, $busqueda)
                    ->orWhereHas('cliente', fn ($cliente) => $cliente
                        ->where('nombre', $like, $busqueda)
                        ->orWhere('numero_documento', $like, $busqueda));
            });
        }

        return $consulta;
    }

    /**
     * @return array<string, int>
     */
    private function kpis(): array
    {
        $conteo = Cotizacion::query()
            ->selectRaw('estado, count(*) as total')
            ->groupBy('estado')
            ->pluck('total', 'estado');

        $kpis = ['total' => (int) $conteo->sum()];

        foreach (Cotizacion::ESTADOS as $estado) {
            $kpis[$estado] = (int) ($conteo[$estado] ?? 0);
        }

        return $kpis;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function ficha(int $id, ?User $usuario): ?array
    {
        $cotizacion = Cotizacion::query()
            ->with(['cliente', 'detalles.variante.producto', 'actualizadoPor.personal', 'venta:id,cotizacion_id,numero_venta'])
            ->find($id);

        if ($cotizacion === null) {
            return null;
        }

        $zona = (string) config('app.timezone');
        $motivo = $this->conversion->motivoBloqueo($cotizacion);
        $telefono = $cotizacion->cliente->telefono;
        $whatsapp = $this->web->normalizarWhatsapp($telefono);
        $vencida = $cotizacion->estado === Cotizacion::VENCIDA
            || (in_array($cotizacion->estado, [Cotizacion::PENDIENTE, Cotizacion::ATENDIDA], true) && ! $cotizacion->vigente());

        return [
            'id' => $cotizacion->id,
            'codigo' => $cotizacion->codigo_cotizacion,
            'estado' => $cotizacion->estado,
            'origen' => $cotizacion->origen,
            'fecha' => $cotizacion->fecha_emision?->timezone($zona)->format('d/m/Y H:i'),
            'vencimiento' => $cotizacion->fecha_vencimiento?->format('d/m/Y'),
            'vigente' => $cotizacion->vigente(),
            'total' => $cotizacion->total(),
            'observacion' => $cotizacion->observacion,
            'fecha_estado' => $cotizacion->fecha_estado?->timezone($zona)->format('d/m/Y H:i'),
            'actualizado_por' => $cotizacion->actualizadoPor === null
                ? null
                : ($cotizacion->actualizadoPor->name !== '' ? $cotizacion->actualizadoPor->name : $cotizacion->actualizadoPor->email),
            'cliente' => [
                'id' => $cotizacion->cliente->id,
                'nombre' => $cotizacion->cliente->nombre,
                'documento' => trim(($cotizacion->cliente->tipo_documento ?? '').' '.($cotizacion->cliente->numero_documento ?? '')),
                'telefono' => $telefono,
                'email' => $cotizacion->cliente->email,
                'whatsapp_url' => $whatsapp === null ? null : 'https://wa.me/'.$whatsapp,
            ],
            'items' => $cotizacion->detalles
                ->map(fn (DetalleCotizacion $d): array => [
                    'descripcion' => $d->descripcion ?? $d->variante->producto->nombre,
                    'cantidad' => $d->cantidad,
                    'precio_cotizado' => (float) $d->precio_unitario,
                    'precio_actual' => (float) $d->variante->precio_venta,
                    'subtotal' => $d->subtotal(),
                    'stock' => $d->variante->stock_actual,
                ])
                ->values(),
            'venta' => $cotizacion->venta === null ? null : [
                'id' => $cotizacion->venta->id,
                'numero' => $cotizacion->venta->numero_venta,
            ],
            'puede_convertir' => $motivo === null,
            'motivo_bloqueo' => $motivo,
            'puede_renovar' => $vencida,
            'puede_cambiar_estado' => ! in_array($cotizacion->estado, [Cotizacion::CONVERTIDA, Cotizacion::VENCIDA], true) && ! $vencida,
            'puede_vender' => $usuario?->tienePermiso('ventas.crear') ?? false,
        ];
    }

    private function usuario(Request $request): User
    {
        $usuario = $request->user();

        abort_unless($usuario instanceof User, 403);

        return $usuario;
    }
}
