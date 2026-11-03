<?php

namespace App\Http\Controllers\Tienda;

use App\Http\Controllers\Controller;
use App\Models\Pedido;
use App\Services\Pagos\PasarelaPagos;
use App\Services\Pagos\PedidoEnLineaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * CU21: notificación asíncrona de Stripe. Respalda la confirmación del
 * navegador si el cliente cierra la pestaña antes de volver.
 */
class StripeWebhookController extends Controller
{
    public function __invoke(Request $request, PasarelaPagos $pasarela, PedidoEnLineaService $pedidos): JsonResponse
    {
        try {
            $evento = $pasarela->eventoWebhook(
                $request->getContent(),
                (string) $request->header('Stripe-Signature', ''),
            );
        } catch (Throwable $e) {
            report($e);

            return response()->json(['error' => 'Firma inválida'], 400);
        }

        $intento = $evento['intento'];
        $pedido = isset($intento['id'])
            ? Pedido::query()->where('stripe_payment_intent_id', $intento['id'])->first()
            : null;

        if ($pedido === null) {
            return response()->json(['recibido' => true]);
        }

        try {
            match ($evento['type']) {
                'payment_intent.succeeded' => $pedidos->registrarPago($pedido, $intento),
                'payment_intent.payment_failed' => $pedidos->registrarFallo($pedido, $intento['error'] ?? null),
                default => null,
            };
        } catch (ValidationException $e) {
            report($e);
        }

        return response()->json(['recibido' => true]);
    }
}
