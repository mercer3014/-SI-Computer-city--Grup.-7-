<?php

namespace App\Http\Controllers;

use App\Http\Requests\PeriodoDashboardRequest;
use App\Models\User;
use App\Services\Dashboard\DashboardService;
use App\Services\Reportes\PeriodoReporte;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * CU23: panel de inicio con indicadores según los permisos de cada usuario. Solo lee, no deja bitácora.
 */
class DashboardController extends Controller
{
    public function index(PeriodoDashboardRequest $request, DashboardService $servicio): Response|RedirectResponse
    {
        /** @var User $usuario */
        $usuario = $request->user();

        if ($usuario->esClienteWeb()) {
            return redirect()->route('tienda.index');
        }

        $periodo = $request->periodo();

        return Inertia::render('Dashboard', [
            'periodo' => [
                'clave' => $request->clave(),
                'desde' => $periodo->desde->toDateString(),
                'hasta' => $periodo->hasta->toDateString(),
                'etiqueta' => $periodo->etiqueta(),
            ],
            'hoy' => PeriodoReporte::hoy()->toDateString(),
            ...$servicio->panel($usuario, $periodo),
        ]);
    }
}
