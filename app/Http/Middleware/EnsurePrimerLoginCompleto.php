<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePrimerLoginCompleto
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->primer_login) {
            return $next($request);
        }

        if ($request->routeIs('password.first', 'password.first.update', 'logout')) {
            return $next($request);
        }

        return redirect()->route('password.first');
    }
}
