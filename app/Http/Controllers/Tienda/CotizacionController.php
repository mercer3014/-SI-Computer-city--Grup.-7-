<?php

namespace App\Http\Controllers\Tienda;

use App\Http\Controllers\Controller;
use App\Http\Requests\GenerarCotizacionRequest;
use App\Models\Cliente;
use App\Models\Cotizacion;
use App\Models\DetalleCotizacion;
use App\Models\User;
use App\Services\Cotizaciones\CotizacionPdf;
use App\Services\Cotizaciones\CotizacionWebService;
use App\Services\Cotizaciones\PreciosDesactualizados;
use App\Services\Tienda\ConfiguracionTiendaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * CU19: cotización web. Requiere cuenta de cliente, igual que el pago en línea (CU21),
 * y cada cliente solo ve sus propias cotizaciones.
 */
class CotizacionController extends Controller
{
    use ExigeClienteWeb;

    public function __construct(
        private CotizacionWebService $servicio,
        private CotizacionPdf $pdf,
        private ConfiguracionTiendaService $tienda,
    ) {}

    public function cotizar(Request $request): Response|RedirectResponse
    {
        $user = $this->cliente($request, 'cotizar');

        if ($user instanceof RedirectResponse) {
            return $user;
        }

        $cliente = $user->cliente;

        return Inertia::render('tienda/Cotizar', [
            'cliente' => [
                'nombre' => $cliente?->nombre,
                'telefono' => $cliente?->telefono,
                'email' => $cliente?->email ?: $user->email,
                'tipo_documento' => $cliente instanceof Cliente ? $cliente->tipo_documento : null,
                'numero_documento' => $cliente instanceof Cliente ? $cliente->numero_documento : null,
            ],
            'validezDias' => max(1, (int) config('tienda.dias_validez_cotizacion', 7)),
        ]);
    }

    public function store(GenerarCotizacionRequest $request): RedirectResponse
    {
        $user = $this->cliente($request, 'cotizar');

        if ($user instanceof RedirectResponse) {
            return $user;
        }

        try {
            $resultado = $this->servicio->generar($user, $request->items(), $request->contacto());
        } catch (PreciosDesactualizados $e) {
            Inertia::flash('precios_nuevos', $e->cambios);

            return back()->withErrors(['precios' => $e->getMessage()]);
        }

        $avisos = array_map(
            fn (string $aviso): array => ['type' => 'warning', 'message' => $aviso],
            $resultado->avisos,
        );

        Inertia::flash('toast', [
            ['type' => 'success', 'message' => 'Cotización '.$resultado->cotizacion->codigo_cotizacion.' generada.'],
            ...$avisos,
        ]);

        return redirect()->route('tienda.cotizaciones.show', [$resultado->cotizacion->codigo_cotizacion, 'nueva' => 1]);
    }

    public function index(Request $request): Response|RedirectResponse
    {
        $user = $this->cliente($request, 'cotizar');

        if ($user instanceof RedirectResponse) {
            return $user;
        }

        $cotizaciones = Cotizacion::query()
            ->with('detalles')
            ->where('usuario_id', $user->id)
            ->orderByDesc('fecha_emision')
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(fn (Cotizacion $c): array => $this->resumen($c))
            ->values();

        return Inertia::render('tienda/MisCotizaciones', ['cotizaciones' => $cotizaciones]);
    }

    public function show(Request $request, string $codigo): Response|RedirectResponse
    {
        $user = $this->cliente($request, 'cotizar');

        if ($user instanceof RedirectResponse) {
            return $user;
        }

        $cotizacion = $this->propia($user, $codigo);

        return Inertia::render('tienda/Cotizacion', [
            'cotizacion' => [
                ...$this->resumen($cotizacion),
                'contacto' => [
                    'nombre' => $cotizacion->cliente?->nombre,
                    'telefono' => $cotizacion->cliente?->telefono,
                    'email' => $cotizacion->cliente?->email,
                ],
                'items' => $cotizacion->detalles
                    ->map(fn (DetalleCotizacion $d): array => [
                        'descripcion' => $d->descripcion,
                        'cantidad' => $d->cantidad,
                        'precio' => (float) $d->precio_unitario,
                        'subtotal' => $d->subtotal(),
                    ])
                    ->values(),
                'pdf' => route('tienda.cotizaciones.pdf', $cotizacion->codigo_cotizacion),
            ],
            'whatsapp_url' => $this->servicio->enlaceWhatsapp($cotizacion),
            'telefono_tienda' => $this->tienda->actual()['telefono'],
            'nueva' => $request->boolean('nueva'),
        ]);
    }

    public function pdf(Request $request, string $codigo): HttpResponse|RedirectResponse
    {
        $user = $this->cliente($request, 'cotizar');

        if ($user instanceof RedirectResponse) {
            return $user;
        }

        return $this->pdf->descargar($this->propia($user, $codigo));
    }

    /**
     * Solo las cotizaciones del propio usuario: una ajena responde 404.
     */
    private function propia(User $user, string $codigo): Cotizacion
    {
        return Cotizacion::query()
            ->with(['cliente', 'detalles'])
            ->where('codigo_cotizacion', $codigo)
            ->where('usuario_id', $user->id)
            ->firstOrFail();
    }

    /**
     * @return array<string, mixed>
     */
    private function resumen(Cotizacion $cotizacion): array
    {
        $zona = (string) config('app.timezone');

        return [
            'codigo' => $cotizacion->codigo_cotizacion,
            'estado' => $cotizacion->estado,
            'fecha' => $cotizacion->fecha_emision?->timezone($zona)->format('d/m/Y H:i'),
            'vencimiento' => $cotizacion->fecha_vencimiento?->format('d/m/Y'),
            'vigente' => $cotizacion->vigente(),
            'total' => $cotizacion->total(),
            'productos' => $cotizacion->detalles->sum('cantidad'),
        ];
    }
}
