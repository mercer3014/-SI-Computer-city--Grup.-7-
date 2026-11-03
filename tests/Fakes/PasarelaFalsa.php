<?php

namespace Tests\Fakes;

use App\Models\Pedido;
use App\Services\Pagos\PasarelaPagos;

class PasarelaFalsa implements PasarelaPagos
{
    /** @var array<string, array<string, mixed>> */
    public array $intentos = [];

    /** @var list<string> */
    public array $reembolsos = [];

    /** @var list<string> */
    public array $cancelados = [];

    public function crearIntento(Pedido $pedido, string $email): array
    {
        $id = 'pi_test_'.$pedido->id;

        return $this->intentos[$id] = [
            'id' => $id,
            'status' => 'requires_payment_method',
            'amount' => $pedido->montoCentavos(),
            'currency' => 'bob',
            'client_secret' => $id.'_secret_abc',
            'metadata' => ['pedido_id' => (string) $pedido->id],
            'error' => null,
        ];
    }

    public function consultarIntento(string $id): array
    {
        return $this->intentos[$id];
    }

    public function cancelarIntento(string $id): void
    {
        $this->cancelados[] = $id;
    }

    public function reembolsar(string $id): void
    {
        $this->reembolsos[] = $id;
    }

    public function eventoWebhook(string $payload, string $firma): array
    {
        if ($firma !== 'firma-valida') {
            throw new \RuntimeException('Firma inválida');
        }

        $datos = json_decode($payload, true);

        return ['type' => $datos['type'], 'intento' => $this->intentos[$datos['id']] ?? []];
    }

    public function clavePublica(): string
    {
        return 'pk_test_falsa';
    }

    public function aprobar(string $id): void
    {
        $this->intentos[$id]['status'] = 'succeeded';
    }

    public function rechazar(string $id, string $mensaje): void
    {
        $this->intentos[$id]['status'] = 'requires_payment_method';
        $this->intentos[$id]['error'] = $mensaje;
    }
}
