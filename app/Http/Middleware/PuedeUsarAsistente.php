<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * El asistente virtual es exclusivo del Administrador (CU22).
 *
 * Además del permiso `asistente.usar` (que ya revisa `permiso:asistente.usar`) se exige el rol Administrador.
 * Es defensa en profundidad: aunque alguien asigne el permiso a otro rol desde la pantalla de Roles,
 * ese rol sigue sin poder entrar al asistente.
 */
class PuedeUsarAsistente
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_unless(
            $user instanceof User && $user->puedeUsarAsistente(),
            403,
            'El asistente virtual es solo para el Administrador.',
        );

        return $next($request);
    }
}
