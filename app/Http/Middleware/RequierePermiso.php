<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequierePermiso
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string $clave): Response
    {
        $user = $request->user();

        abort_unless(
            $user instanceof User && $user->tienePermiso($clave),
            403,
            'No tenés permiso para acceder a esta sección.',
        );

        return $next($request);
    }
}
