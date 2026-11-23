<?php

namespace App\Http\Controllers\Tienda;

use App\Http\Controllers\Controller;
use App\Http\Requests\CalificarProductoRequest;
use App\Models\Pedido;
use App\Models\User;
use App\Services\Calificaciones\CalificacionService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * CU24: el Cliente Web califica los productos de sus pedidos pagados.
 */
class CalificacionController extends Controller
{
    public function __construct(private CalificacionService $calificaciones) {}

    public function store(CalificarProductoRequest $request, string $numero): RedirectResponse
    {
        $usuario = $request->user();

        abort_unless($usuario instanceof User, 403);

        $pedido = Pedido::query()->where('numero_pedido', $numero)->firstOrFail();

        abort_unless($pedido->usuario_id === $usuario->id, 403, 'Ese pedido no es tuyo.');

        $datos = $request->validated();

        $this->calificaciones->crear(
            $usuario,
            $pedido,
            (int) $datos['producto_id'],
            (int) $datos['puntuacion'],
            isset($datos['comentario']) ? (string) $datos['comentario'] : null,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Gracias por tu calificación']);

        return back();
    }
}
