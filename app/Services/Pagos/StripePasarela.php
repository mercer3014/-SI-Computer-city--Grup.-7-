<?php

namespace App\Services\Pagos;

use App\Models\Pedido;
use Stripe\PaymentIntent;
use Stripe\StripeClient;
use Stripe\Webhook;

class StripePasarela implements PasarelaPagos
{
    private ?StripeClient $cliente = null;

    public function crearIntento(Pedido $pedido, string $email): array
    {
        $intento = $this->stripe()->paymentIntents->create([
            'amount' => $pedido->montoCentavos(),
            'currency' => config('services.stripe.currency', 'bob'),
            // Sin métodos con redirección: el cobro se completa dentro de la tienda.
            'automatic_payment_methods' => ['enabled' => true, 'allow_redirects' => 'never'],
            'description' => 'Computer City · Pedido '.$pedido->numero_pedido,
            'receipt_email' => $email,
            'metadata' => [
                'pedido_id' => (string) $pedido->id,
                'numero_pedido' => $pedido->numero_pedido,
            ],
        ], [
            'idempotency_key' => 'pedido-'.$pedido->id.'-'.$pedido->montoCentavos(),
        ]);

        return $this->normalizar($intento);
    }

    public function consultarIntento(string $id): array
    {
        return $this->normalizar($this->stripe()->paymentIntents->retrieve($id));
    }

    public function cancelarIntento(string $id): void
    {
        $this->stripe()->paymentIntents->cancel($id);
    }

    public function reembolsar(string $id): void
    {
        $this->stripe()->refunds->create(['payment_intent' => $id]);
    }

    public function eventoWebhook(string $payload, string $firma): array
    {
        $evento = Webhook::constructEvent(
            $payload,
            $firma,
            (string) config('services.stripe.webhook_secret'),
        );

        $objeto = $evento->data->object;

        return [
            'type' => (string) $evento->type,
            'intento' => $objeto instanceof PaymentIntent ? $this->normalizar($objeto) : [],
        ];
    }

    public function clavePublica(): string
    {
        return (string) config('services.stripe.public');
    }

    private function stripe(): StripeClient
    {
        return $this->cliente ??= new StripeClient((string) config('services.stripe.secret'));
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizar(PaymentIntent $intento): array
    {
        return [
            'id' => $intento->id,
            'status' => $intento->status,
            'amount' => (int) $intento->amount,
            'currency' => strtolower((string) $intento->currency),
            'client_secret' => $intento->client_secret,
            'metadata' => $intento->metadata?->toArray() ?? [],
            'error' => $this->motivoRechazo($intento),
        ];
    }

    private function motivoRechazo(PaymentIntent $intento): ?string
    {
        $error = $intento->last_payment_error;

        if ($error === null) {
            return null;
        }

        return match ($error->decline_code ?? $error->code) {
            'insufficient_funds' => 'La tarjeta no tiene fondos suficientes. Probá con otra tarjeta.',
            'expired_card' => 'La tarjeta está vencida. Probá con otra tarjeta.',
            'incorrect_cvc', 'invalid_cvc' => 'El código de seguridad (CVC) es incorrecto.',
            'incorrect_number', 'invalid_number' => 'El número de tarjeta es incorrecto.',
            'lost_card', 'stolen_card', 'pickup_card' => 'El banco bloqueó la tarjeta. Comunicate con tu banco o usá otra tarjeta.',
            'processing_error' => 'Hubo un error al procesar la tarjeta. Intentá de nuevo.',
            default => 'El banco rechazó la tarjeta. Probá con otra tarjeta u otro método.',
        };
    }
}
