<?php

namespace App\Services\Pagos;

use App\Models\Pedido;

/**
 * CU21: contrato con la pasarela externa. Devuelve arreglos planos para no
 * acoplar el dominio a los objetos del SDK.
 *
 * Un intento tiene la forma:
 * ['id' => string, 'status' => string, 'amount' => int, 'currency' => string,
 *  'client_secret' => string|null, 'metadata' => array<string, string>,
 *  'error' => string|null]
 */
interface PasarelaPagos
{
    /**
     * @return array<string, mixed>
     */
    public function crearIntento(Pedido $pedido, string $email): array;

    /**
     * @return array<string, mixed>
     */
    public function consultarIntento(string $id): array;

    public function cancelarIntento(string $id): void;

    public function reembolsar(string $id): void;

    /**
     * Verifica la firma del webhook y devuelve ['type' => string, 'intento' => array].
     *
     * @return array{type: string, intento: array<string, mixed>}
     */
    public function eventoWebhook(string $payload, string $firma): array;

    public function clavePublica(): string;
}
