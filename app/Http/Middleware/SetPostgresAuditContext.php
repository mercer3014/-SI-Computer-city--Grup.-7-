<?php

namespace App\Http\Middleware;

use App\Services\BitacoraService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetPostgresAuditContext
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        BitacoraService::bindContext();

        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        BitacoraService::clearContext();
    }
}
