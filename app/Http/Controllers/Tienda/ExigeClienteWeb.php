<?php

namespace App\Http\Controllers\Tienda;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Las cuentas del personal no compran ni cotizan en la tienda: se las devuelve al carrito con un aviso.
 */
trait ExigeClienteWeb
{
    private function cliente(Request $request, string $accion = 'comprar en línea'): User|RedirectResponse
    {
        $user = $request->user();

        if ($user instanceof User && $user->cliente_id !== null) {
            return $user;
        }

        Inertia::flash('toast', [
            'type' => 'warning',
            'message' => "Para {$accion} necesitás una cuenta de cliente. Las cuentas del personal usan el POS.",
        ]);

        return redirect()->route('tienda.carrito');
    }
}
