<?php

namespace App\Http\Controllers\Tienda;

use App\Http\Controllers\Controller;
use App\Models\DetallePedido;
use App\Models\Pedido;
use App\Models\User;
use App\Models\Venta;
use App\Services\NotaVentaPdf;
use App\Services\Pagos\PasarelaPagos;
use App\Services\Pagos\PedidoEnLineaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Throwable;

/**
 * CU21: checkout del carrito y pago con tarjeta dentro de la tienda.
 */
class CheckoutController extends Controller
{
    public function __construct(
        private PedidoEnLineaService $pedidos,
        private PasarelaPagos $pasarela,
    ) {}

    public function checkout(Request $request): Response|RedirectResponse
    {
        $user = $this->cliente($request);

        if ($user instanceof RedirectResponse) {
            return $user;
        }

        $cliente = $user->cliente;

        return Inertia::render('tienda/Checkout', [
            'cliente' => [
                'nombre' => $cliente?->nombre,
                'documento' => trim(($cliente?->tipo_documento ?? '').' '.($cliente?->numero_documento ?? '')),
                'telefono' => $cliente?->telefono,
                'email' => $user->email,
            ],
            'pasarelaLista' => $this->pasarela->clavePublica() !== '',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $this->cliente($request);

        if ($user instanceof RedirectResponse) {
            return $user;
        }

        $datos = $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.variante_id' => ['required', 'integer', Rule::exists('variante_producto', 'id')],
            'items.*.cantidad' => ['required', 'integer', 'min:1', 'max:20'],
            'nombre' => ['required', 'string', 'min:3', 'max:150'],
            'telefono' => ['required', 'string', 'regex:/^[0-9+\-\s]{7,15}$/'],
            'observacion' => ['nullable', 'string', 'max:500'],
        ], [
            'items.required' => 'Tu carrito está vacío.',
            'items.min' => 'Tu carrito está vacío.',
            'items.*.variante_id.exists' => 'Uno de los productos del carrito ya no existe.',
            'items.*.cantidad.max' => 'Podés llevar hasta 20 unidades por producto.',
            'nombre.required' => 'Indicá quién retira el pedido.',
            'telefono.required' => 'Indicá un teléfono de contacto.',
            'telefono.regex' => 'El teléfono no tiene un formato válido.',
        ]);

        $pedido = $this->pedidos->crearPedido($user, $datos['items'], [
            'nombre' => trim($datos['nombre']),
            'telefono' => trim($datos['telefono']),
            'observacion' => $datos['observacion'] ?? null,
        ]);

        return redirect()->route('tienda.pedidos.pago', $pedido->numero_pedido);
    }

    public function pago(Request $request, string $numero): Response|RedirectResponse
    {
        $pedido = $this->pedidoPropio($request, $numero);

        if ($pedido->estaPagado()) {
            return redirect()->route('tienda.pedidos.show', $pedido->numero_pedido);
        }

        if ($pedido->estado !== Pedido::PENDIENTE_PAGO || $pedido->stripe_payment_intent_id === null) {
            Inertia::flash('toast', ['type' => 'warning', 'message' => 'Ese pedido ya no admite pagos. Armá uno nuevo desde el carrito.']);

            return redirect()->route('tienda.carrito');
        }

        try {
            $intento = $this->pasarela->consultarIntento($pedido->stripe_payment_intent_id);
        } catch (Throwable $e) {
            report($e);
            $intento = null;
        }

        return Inertia::render('tienda/Pago', [
            'pedido' => $this->formatPedido($pedido),
            'stripe' => [
                'clave' => $this->pasarela->clavePublica(),
                'clientSecret' => $intento['client_secret'] ?? null,
            ],
            'errorConexion' => $intento === null,
        ]);
    }

    public function confirmar(Request $request, string $numero): RedirectResponse
    {
        $pedido = $this->pedidoPropio($request, $numero);

        if (! $pedido->estaPagado()) {
            $intento = $this->pasarela->consultarIntento((string) $pedido->stripe_payment_intent_id);
            $pedido = $this->pedidos->registrarPago($pedido, $intento);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => '¡Pago aprobado! Tu pedido '.$pedido->numero_pedido.' está confirmado.']);

        return redirect()->route('tienda.pedidos.show', [$pedido->numero_pedido, 'nuevo' => 1]);
    }

    /**
     * Deja registrado el motivo del rechazo que informó la pasarela.
     */
    public function fallo(Request $request, string $numero): RedirectResponse
    {
        $pedido = $this->pedidoPropio($request, $numero);
        $datos = $request->validate(['mensaje' => ['nullable', 'string', 'max:500']]);

        $this->pedidos->registrarFallo($pedido, $datos['mensaje'] ?? null);

        return back();
    }

    public function index(Request $request): Response
    {
        $user = $request->user();

        $pedidos = Pedido::query()
            ->with(['detalles', 'venta:id,pedido_id,numero_venta'])
            ->where('usuario_id', $user?->id)
            ->where('estado', '<>', Pedido::CANCELADO)
            ->orderByDesc('fecha')
            ->limit(50)
            ->get()
            ->map(fn (Pedido $p) => $this->formatPedido($p))
            ->values();

        return Inertia::render('tienda/MisPedidos', [
            'pedidos' => $pedidos,
        ]);
    }

    public function show(Request $request, string $numero): Response
    {
        $pedido = $this->pedidoPropio($request, $numero);

        return Inertia::render('tienda/Pedido', [
            'pedido' => $this->formatPedido($pedido),
            'nuevo' => $request->boolean('nuevo'),
        ]);
    }

    public function comprobante(Request $request, string $numero, NotaVentaPdf $pdf): HttpResponse
    {
        $pedido = $this->pedidoPropio($request, $numero);
        $venta = $pedido->venta;

        abort_unless($venta instanceof Venta, 404, 'El pedido todavía no tiene comprobante.');

        return $pdf->descargar($venta);
    }

    private function cliente(Request $request): User|RedirectResponse
    {
        $user = $request->user();

        if ($user instanceof User && $user->cliente_id !== null) {
            return $user;
        }

        Inertia::flash('toast', [
            'type' => 'warning',
            'message' => 'Para comprar en línea necesitás una cuenta de cliente. Las cuentas del personal usan el POS.',
        ]);

        return redirect()->route('tienda.carrito');
    }

    private function pedidoPropio(Request $request, string $numero): Pedido
    {
        return Pedido::query()
            ->with(['detalles', 'venta:id,pedido_id,numero_venta,fecha'])
            ->where('numero_pedido', $numero)
            ->where('usuario_id', $request->user()?->id)
            ->firstOrFail();
    }

    /**
     * @return array<string, mixed>
     */
    private function formatPedido(Pedido $pedido): array
    {
        $zona = config('app.timezone');

        return [
            'numero' => $pedido->numero_pedido,
            'estado' => $pedido->estado,
            'fecha' => $pedido->fecha?->timezone($zona)->format('d/m/Y H:i'),
            'fecha_pago' => $pedido->fecha_pago?->timezone($zona)->format('d/m/Y H:i'),
            'total' => (float) $pedido->total,
            'contacto' => $pedido->nombre_contacto,
            'telefono' => $pedido->telefono_contacto,
            'entrega' => 'Retiro en tienda',
            'observacion' => $pedido->observacion,
            'error_pago' => $pedido->error_pago,
            'venta' => $pedido->venta?->numero_venta,
            'comprobante' => $pedido->venta !== null
                ? route('tienda.pedidos.comprobante', $pedido->numero_pedido)
                : null,
            'items' => $pedido->detalles
                ->map(fn (DetallePedido $d) => [
                    'producto' => $d->nombre_producto_snapshot,
                    'variante' => $d->variante_snapshot,
                    'cantidad' => $d->cantidad,
                    'precio' => (float) $d->precio_unitario,
                    'subtotal' => $d->subtotal(),
                ])
                ->values(),
        ];
    }
}
