<?php

namespace App\Services\Tienda;

use App\Models\VarianteProducto;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Reglas del carrito compartidas por el pago en línea (CU21) y la cotización web (CU19).
 *
 * Los precios y el stock salen siempre de la base, nunca del navegador.
 *
 * - `exigir()` es la versión estricta del pago: ante cualquier problema lanza un error.
 * - `ajustar()` es la versión de la cotización: quita lo que ya no se vende, baja las cantidades
 *   al stock disponible y avisa de los cambios de precio, sin tocar el stock.
 *
 * @phpstan-type LineaRevisada array{variante: VarianteProducto, cantidad: int, precio: float}
 * @phpstan-type PrecioCambiado array{variante_id: int, producto: string, anterior: float, nuevo: float}
 */
class ValidadorCarrito
{
    /**
     * Junta las líneas repetidas de una misma variante.
     *
     * @param  list<array{variante_id: int, cantidad: int}>  $items
     * @return array<int, int>
     */
    public function agrupar(array $items): array
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
    public function variantes(array $ids, bool $bloquear = false): Collection
    {
        $consulta = VarianteProducto::query()
            ->with(['producto:id,nombre,estado', 'valores.atributo'])
            ->whereIn('id', $ids);

        if ($bloquear) {
            $consulta->lockForUpdate();
        }

        return $consulta->get()->keyBy('id');
    }

    /**
     * Una variante se puede vender si ella y su producto están activos y, para cotizar o cobrar, tiene precio.
     */
    public function vendible(?VarianteProducto $variante, bool $conPrecio = true): bool
    {
        return $variante !== null
            && $variante->estado === 'ACTIVO'
            && $variante->producto?->estado === 'ACTIVO'
            && (! $conPrecio || (float) $variante->precio_venta > 0);
    }

    /**
     * @param  array<int, int>  $items
     * @param  Collection<int, VarianteProducto>  $variantes
     *
     * @throws ValidationException
     */
    public function exigir(array $items, Collection $variantes, bool $conPrecio = true): void
    {
        foreach ($items as $id => $cantidad) {
            $variante = $variantes->get($id);

            if ($variante === null || ! $this->vendible($variante, $conPrecio)) {
                throw ValidationException::withMessages([
                    'items' => 'Uno de los productos del carrito ya no está disponible. Quitalo para continuar.',
                ]);
            }

            if ($cantidad > $variante->stock_actual) {
                $nombre = $variante->producto->nombre;

                throw ValidationException::withMessages([
                    'items' => $variante->stock_actual > 0
                        ? "Solo quedan {$variante->stock_actual} unidades de {$nombre}."
                        : "{$nombre} se agotó.",
                ]);
            }
        }
    }

    /**
     * Versión tolerante para cotizar: devuelve las líneas que sí se pueden cotizar y los avisos
     * de lo que se quitó (EX-06 y sin stock), se ajustó (EX-02) o cambió de precio (EX-03).
     *
     * @param  array<int, int>  $items  cantidad por variante
     * @param  Collection<int, VarianteProducto>  $variantes
     * @param  array<int, float>  $preciosVistos  precio que el cliente vio por variante
     */
    public function ajustar(array $items, Collection $variantes, array $preciosVistos = []): RevisionCarrito
    {
        $lineas = [];
        $avisos = [];
        $cambios = [];

        foreach ($items as $id => $cantidad) {
            $variante = $variantes->get($id);

            if ($variante === null || ! $this->vendible($variante)) {
                $nombre = $variante === null ? 'Un producto' : $variante->producto->nombre;
                $avisos[] = "{$nombre} ya no está disponible y lo quitamos de tu cotización.";

                continue;
            }

            $nombre = $variante->producto->nombre;
            $stock = max(0, $variante->stock_actual);

            if ($stock === 0) {
                $avisos[] = "{$nombre} se agotó y lo quitamos de tu cotización.";

                continue;
            }

            if ($cantidad > $stock) {
                $avisos[] = "Solo quedan {$stock} unidades de {$nombre}; ajustamos la cantidad.";
                $cantidad = $stock;
            }

            $precio = round((float) $variante->precio_venta, 2);

            if (isset($preciosVistos[$id]) && abs($preciosVistos[$id] - $precio) >= 0.005) {
                $cambios[] = [
                    'variante_id' => $id,
                    'producto' => $nombre,
                    'anterior' => round($preciosVistos[$id], 2),
                    'nuevo' => $precio,
                ];
            }

            $lineas[] = ['variante' => $variante, 'cantidad' => $cantidad, 'precio' => $precio];
        }

        return new RevisionCarrito($lineas, $avisos, $cambios);
    }
}
