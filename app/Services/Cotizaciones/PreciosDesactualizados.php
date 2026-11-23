<?php

namespace App\Services\Cotizaciones;

use RuntimeException;

/**
 * EX-03 de CU19: el precio que vio el cliente ya no es el vigente. No se registra nada;
 * se devuelven los precios nuevos para que el cliente confirme otra vez.
 */
class PreciosDesactualizados extends RuntimeException
{
    /**
     * @param  list<array{variante_id: int, producto: string, anterior: float, nuevo: float}>  $cambios
     */
    public function __construct(public readonly array $cambios)
    {
        parent::__construct('Los precios de tu carrito cambiaron. Revisa los nuevos y confirma otra vez.');
    }
}
