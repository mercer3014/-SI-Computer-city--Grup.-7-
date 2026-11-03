<?php

namespace App\Http\Controllers\Tienda;

use App\Http\Controllers\Controller;
use App\Models\VarianteProducto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * El carrito vive en el navegador (también para visitantes). Este controlador
 * solo muestra la página y devuelve precio y stock vigentes de lo guardado.
 */
class CarritoController extends Controller
{
    public function show(): Response
    {
        return Inertia::render('tienda/Carrito');
    }

    public function variantes(Request $request): JsonResponse
    {
        $ids = collect(explode(',', $request->string('ids')->value()))
            ->map(fn (string $id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->take(50)
            ->values()
            ->all();

        $variantes = VarianteProducto::query()
            ->with(['producto:id,nombre,slug,estado', 'producto.imagenes', 'valores.atributo'])
            ->whereIn('id', $ids)
            ->get()
            ->map(function (VarianteProducto $v): array {
                $disponible = $v->estado === 'ACTIVO' && $v->producto?->estado === 'ACTIVO';
                $imagen = $v->producto?->imagenPrincipal();

                return [
                    'variante_id' => $v->id,
                    'producto' => $v->producto?->nombre,
                    'slug' => $v->producto?->slug,
                    'etiqueta' => $v->etiquetaAtributos() ?: null,
                    'sku' => $v->sku,
                    'precio' => (float) $v->precio_venta,
                    'stock' => $disponible ? max(0, $v->stock_actual) : 0,
                    'disponible' => $disponible,
                    'imagen' => $imagen?->url_webp,
                ];
            })
            ->values();

        return response()->json(['variantes' => $variantes]);
    }
}
