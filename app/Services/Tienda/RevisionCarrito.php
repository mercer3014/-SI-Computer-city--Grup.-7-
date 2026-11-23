<?php

namespace App\Services\Tienda;

use App\Models\VarianteProducto;

/**
 * Resultado de revisar un carrito para cotizar (CU19).
 *
 * @phpstan-import-type LineaRevisada from ValidadorCarrito
 * @phpstan-import-type PrecioCambiado from ValidadorCarrito
 */
final class RevisionCarrito
{
    /**
     * @param  list<array{variante: VarianteProducto, cantidad: int, precio: float}>  $lineas
     * @param  list<string>  $avisos
     * @param  list<array{variante_id: int, producto: string, anterior: float, nuevo: float}>  $preciosCambiados
     */
    public function __construct(
        public readonly array $lineas,
        public readonly array $avisos,
        public readonly array $preciosCambiados,
    ) {}

    public function vacio(): bool
    {
        return $this->lineas === [];
    }

    public function huboCambioDePrecio(): bool
    {
        return $this->preciosCambiados !== [];
    }
}
