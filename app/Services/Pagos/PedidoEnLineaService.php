<?php

namespace App\Services\Pagos;

use App\Models\DetallePedido;
use App\Models\DetalleVenta;
use App\Models\MetodoPago;
use App\Models\PagoVenta;
use App\Models\Pedido;
use App\Models\User;
use App\Models\VarianteProducto;
use App\Models\Venta;
use App\Services\BitacoraService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * CU21: del carrito al pedido y del pago aprobado a la venta.
 *
 * Los precios y el stock siempre salen de la base, nunca del navegador.
 * El stock se descuenta recién cuando la pasarela aprueba el cobro, al crear
 * la venta (triggers de detalle_venta en PostgreSQL).
 */
class PedidoEnLineaService
{
    public const MESES_GARANTIA = 3;

    public function __construct(private PasarelaPagos $pasarela) {}

    /**
     * @param  list<array{variante_id: int, cantidad: int}>  $items
     * @param  array{nombre: string, telefono: string|null, observacion: string|null}  $contacto
     */
    public function crearPedido(User $user, array $items, array $contacto): Pedido
    {
        $items = $this->agrupar($items);

        $pedido = DB::transaction(function () use ($user, $items, $contacto): Pedido {
            $variantes = $this->variantes(array_keys($items));
            $this->validarDisponibilidad($items, $variantes);

            $this->cancelarPendientes($user);

            $pedido = Pedido::query()->create([
                'cliente_id' => $user->cliente_id,
                'usuario_id' => $user->id,
                'numero_pedido' => Pedido::siguienteNumero(),
                'nombre_contacto' => $contacto['nombre'],
                'telefono_contacto' => $contacto['telefono'],
                'metodo_entrega' => 'RETIRO_EN_TIENDA',
                'estado' => Pedido::PENDIENTE_PAGO,
                'origen' => Pedido::ORIGEN_TIENDA,
                'observacion' => $contacto['observacion'],
                'fecha' => now(),
                'total' => 0,
            ]);

            $total = 0.0;

            foreach ($items as $varianteId => $cantidad) {
                $variante = $variantes->get($varianteId);
                $total += $cantidad * (float) $variante->precio_venta;

                DetallePedido::query()->create([
                    'pedido_id' => $pedido->id,
                    'variante_id' => $variante->id,
                    'cantidad' => $cantidad,
                    'precio_unitario' => $variante->precio_venta,
                    'descuento' => 0,
                    'nombre_producto_snapshot' => $variante->producto?->nombre,
                    'variante_snapshot' => $variante->etiquetaAtributos() ?: $variante->sku,
                ]);
            }

            $pedido->forceFill(['total' => round($total, 2)])->save();

            return $pedido;
        });

        try {
            $intento = $this->pasarela->crearIntento($pedido, $user->email);
        } catch (Throwable $e) {
            report($e);
            $pedido->forceFill([
                'estado' => Pedido::CANCELADO,
                'error_pago' => 'No se pudo conectar con la pasarela de pagos.',
            ])->save();

            throw ValidationException::withMessages([
                'items' => 'No pudimos conectar con la pasarela de pagos. Intentá de nuevo en unos minutos.',
            ]);
        }

        $pedido->forceFill(['stripe_payment_intent_id' => $intento['id']])->save();

        BitacoraService::registrar(
            'Tienda',
            'CREAR_PEDIDO_WEB',
            'pedido',
            $pedido->id,
            null,
            ['numero_pedido' => $pedido->numero_pedido, 'total' => $pedido->total],
            'Pedido en línea '.$pedido->numero_pedido,
            (int) $user->id,
        );

        return $pedido;
    }

    /**
     * Convierte el pedido en venta si la pasarela aprobó el cobro.
     * Es idempotente: la confirmación del navegador y el webhook pueden llegar
     * en cualquier orden.
     *
     * @param  array<string, mixed>  $intento
     */
    public function registrarPago(Pedido $pedido, array $intento): Pedido
    {
        if (($intento['id'] ?? null) !== $pedido->stripe_payment_intent_id) {
            throw ValidationException::withMessages([
                'pago' => 'El pago no corresponde a este pedido.',
            ]);
        }

        if (($intento['status'] ?? null) !== 'succeeded') {
            $mensaje = $intento['error'] ?? 'La pasarela todavía no aprobó el pago.';
            $pedido->forceFill(['error_pago' => $mensaje])->save();

            throw ValidationException::withMessages(['pago' => $mensaje]);
        }

        if ((int) $intento['amount'] !== $pedido->montoCentavos()
            || $intento['currency'] !== strtolower((string) config('services.stripe.currency', 'bob'))) {
            throw ValidationException::withMessages([
                'pago' => 'El monto cobrado no coincide con el total del pedido.',
            ]);
        }

        try {
            return DB::transaction(function () use ($pedido): Pedido {
                /** @var Pedido $bloqueado */
                $bloqueado = Pedido::query()->whereKey($pedido->id)->lockForUpdate()->firstOrFail();

                if ($bloqueado->estaPagado()) {
                    return $bloqueado;
                }

                $bloqueado->load('detalles');
                $items = $bloqueado->detalles
                    ->mapWithKeys(fn (DetallePedido $d) => [(int) $d->variante_id => (int) $d->cantidad])
                    ->all();
                $variantes = $this->variantes(array_keys($items));
                $this->validarDisponibilidad($items, $variantes, conPrecio: false);

                $venta = Venta::query()->create([
                    'cliente_id' => $bloqueado->cliente_id,
                    'pedido_id' => $bloqueado->id,
                    'usuario_id' => $bloqueado->usuario_id,
                    'numero_venta' => Venta::siguienteNumero(),
                    'fecha' => now(),
                    'estado' => 'COMPLETADA',
                    'descuento_global' => 0,
                    'observacion' => 'Venta en línea · pedido '.$bloqueado->numero_pedido,
                ]);

                foreach ($bloqueado->detalles as $linea) {
                    DetalleVenta::query()->create([
                        'venta_id' => $venta->id,
                        'variante_id' => $linea->variante_id,
                        'cantidad' => $linea->cantidad,
                        'precio_unitario' => $linea->precio_unitario,
                        'descuento' => 0,
                        'nombre_producto_snapshot' => $linea->nombre_producto_snapshot,
                        'variante_snapshot' => $linea->variante_snapshot,
                        'meses_garantia' => self::MESES_GARANTIA,
                        'fecha_fin_garantia' => now()->addMonths(self::MESES_GARANTIA)->toDateString(),
                        'estado' => 'VIGENTE',
                    ]);
                }

                PagoVenta::query()->create([
                    'venta_id' => $venta->id,
                    'metodo_pago_id' => $this->metodoTarjeta(),
                    'monto' => $bloqueado->total,
                    'referencia' => $bloqueado->stripe_payment_intent_id,
                    'fecha' => now(),
                    'observacion' => 'Cobro aprobado por Stripe',
                ]);

                $bloqueado->forceFill([
                    'estado' => Pedido::PAGADO,
                    'fecha_pago' => now(),
                    'error_pago' => null,
                ])->save();

                BitacoraService::registrar(
                    'Tienda',
                    'PAGO_PEDIDO_WEB',
                    'venta',
                    $venta->id,
                    ['estado' => Pedido::PENDIENTE_PAGO],
                    [
                        'numero_pedido' => $bloqueado->numero_pedido,
                        'numero_venta' => $venta->numero_venta,
                        'total' => $bloqueado->total,
                        'referencia' => $bloqueado->stripe_payment_intent_id,
                    ],
                    'Pago con tarjeta aprobado',
                    $bloqueado->usuario_id,
                );

                return $bloqueado;
            });
        } catch (ValidationException|QueryException $e) {
            $this->reembolsarPorFaltaDeStock($pedido, $e);

            throw ValidationException::withMessages([
                'pago' => 'Mientras pagabas se agotó uno de los productos. Te devolvimos el dinero a la tarjeta.',
            ]);
        }
    }

    public function registrarFallo(Pedido $pedido, ?string $mensaje): void
    {
        if ($pedido->estaPagado()) {
            return;
        }

        $pedido->forceFill(['error_pago' => $mensaje ?? 'La tarjeta fue rechazada.'])->save();
    }

    /**
     * @param  list<array{variante_id: int, cantidad: int}>  $items
     * @return array<int, int>
     */
    private function agrupar(array $items): array
    {
        $agrupados = [];

        foreach ($items as $item) {
            $id = (int) $item['variante_id'];
            $agrupados[$id] = ($agrupados[$id] ?? 0) + (int) $item['cantidad'];
        }

        return $agrupados;
    }

    /**
     * @param  list<int>  $ids
     * @return Collection<int, VarianteProducto>
     */
    private function variantes(array $ids): Collection
    {
        return VarianteProducto::query()
            ->with(['producto:id,nombre,estado', 'valores.atributo'])
            ->whereIn('id', $ids)
            ->lockForUpdate()
            ->get()
            ->keyBy('id');
    }

    /**
     * @param  array<int, int>  $items
     * @param  Collection<int, VarianteProducto>  $variantes
     */
    private function validarDisponibilidad(array $items, Collection $variantes, bool $conPrecio = true): void
    {
        foreach ($items as $id => $cantidad) {
            $variante = $variantes->get($id);

            if ($variante === null
                || $variante->estado !== 'ACTIVO'
                || $variante->producto?->estado !== 'ACTIVO'
                || ($conPrecio && (float) $variante->precio_venta <= 0)) {
                throw ValidationException::withMessages([
                    'items' => 'Uno de los productos del carrito ya no está disponible. Quitalo para continuar.',
                ]);
            }

            if ($cantidad > $variante->stock_actual) {
                $nombre = $variante->producto?->nombre ?? $variante->sku;

                throw ValidationException::withMessages([
                    'items' => $variante->stock_actual > 0
                        ? "Solo quedan {$variante->stock_actual} unidades de {$nombre}."
                        : "{$nombre} se agotó.",
                ]);
            }
        }
    }

    private function cancelarPendientes(User $user): void
    {
        $pendientes = Pedido::query()
            ->where('usuario_id', $user->id)
            ->where('estado', Pedido::PENDIENTE_PAGO)
            ->get();

        foreach ($pendientes as $pendiente) {
            $pendiente->forceFill(['estado' => Pedido::CANCELADO])->save();

            if ($pendiente->stripe_payment_intent_id !== null) {
                DB::afterCommit(function () use ($pendiente): void {
                    try {
                        $this->pasarela->cancelarIntento((string) $pendiente->stripe_payment_intent_id);
                    } catch (Throwable $e) {
                        report($e);
                    }
                });
            }
        }
    }

    private function reembolsarPorFaltaDeStock(Pedido $pedido, Throwable $causa): void
    {
        report($causa);

        try {
            $this->pasarela->reembolsar((string) $pedido->stripe_payment_intent_id);
        } catch (Throwable $e) {
            report($e);
        }

        $pedido->forceFill([
            'estado' => Pedido::REEMBOLSADO,
            'error_pago' => 'Sin stock al confirmar el pago; se reembolsó el cobro.',
        ])->save();
    }

    private function metodoTarjeta(): int
    {
        $id = MetodoPago::query()->where('codigo', 'TARJETA')->value('id');

        if ($id === null) {
            $id = MetodoPago::query()->create([
                'codigo' => 'TARJETA',
                'nombre' => 'Tarjeta (Stripe)',
                'estado' => 'ACTIVO',
            ])->id;
        }

        return (int) $id;
    }
}
