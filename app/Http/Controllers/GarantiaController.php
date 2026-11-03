<?php

namespace App\Http\Controllers;

use App\Services\BitacoraService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class GarantiaController extends Controller
{
    public function index(Request $request): Response
    {
        $serie = $request->string('numero_serie')->trim()->value();
        $filtro = $request->string('filtro')->value();

        if (! in_array($filtro, ['todas', 'vigentes', 'por_vencer', 'vencidas', 'devueltas'], true)) {
            $filtro = 'todas';
        }

        $resultados = $serie === ''
            ? $this->listarGarantias($filtro)
            : $this->consultarGarantia($serie);

        return Inertia::render('Garantias', [
            'busqueda' => $serie,
            'filtro' => $filtro,
            'resultados' => $resultados,
            'kpis' => [
                'todas' => count($this->listarGarantias('todas')),
                'vigentes' => count($this->listarGarantias('vigentes')),
                'por_vencer' => count($this->listarGarantias('por_vencer')),
                'vencidas' => count($this->listarGarantias('vencidas')),
                'devueltas' => count($this->listarGarantias('devueltas')),
            ],
            'puedeProcesar' => $request->user()?->tienePermiso('garantias.gestionar') ?? false,
        ]);
    }

    public function devolver(Request $request): RedirectResponse
    {
        if (! $request->user()?->tienePermiso('garantias.gestionar')) {
            abort(403);
        }

        $datos = $request->validate([
            'numero_serie' => ['required', 'string', 'max:100'],
            'numero_venta' => ['required', 'string', 'max:50'],
            'motivo' => ['required', 'string', 'min:5', 'max:500'],
        ], [
            'numero_serie.required' => 'Ingresá el número de serie.',
            'numero_venta.required' => 'Seleccioná una venta válida.',
            'motivo.required' => 'Indicá el motivo de la devolución.',
            'motivo.min' => 'El motivo debe tener al menos 5 caracteres.',
        ]);

        $garantias = $this->consultarGarantia($datos['numero_serie']);
        $garantia = collect($garantias)->first(
            fn (array $item): bool => $item['numero_venta'] === $datos['numero_venta'],
        );

        if ($garantia === null) {
            throw ValidationException::withMessages([
                'numero_venta' => 'La venta no corresponde al número de serie consultado.',
            ]);
        }

        if (! $garantia['vigente'] || $garantia['estado_item'] !== 'VIGENTE') {
            throw ValidationException::withMessages([
                'numero_serie' => 'La garantía no está vigente o el artículo ya fue devuelto.',
            ]);
        }

        try {
            DB::transaction(function () use ($datos, $request): void {
                DB::statement(
                    'CALL public.sp_anular_venta(?, ?, ?)',
                    [$datos['numero_venta'], (int) $request->user()->id, trim($datos['motivo'])],
                );

                BitacoraService::registrar(
                    'Garantías',
                    'PROCESAR_DEVOLUCION',
                    'venta',
                    null,
                    null,
                    [
                        'numero_venta' => $datos['numero_venta'],
                        'numero_serie' => $datos['numero_serie'],
                        'motivo' => trim($datos['motivo']),
                    ],
                    'Devolución procesada para '.$datos['numero_venta'],
                );
            });
        } catch (Throwable $exception) {
            report($exception);

            throw ValidationException::withMessages([
                'numero_venta' => 'No se pudo procesar la devolución. Verificá que la venta siga activa.',
            ]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Devolución procesada. El stock fue repuesto por la base de datos.',
        ]);

        return redirect()->route('garantias.index', [
            'numero_serie' => $datos['numero_serie'],
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function listarGarantias(string $filtro): array
    {
        $query = DB::table('detalle_venta as dv')
            ->join('venta as v', 'v.id', '=', 'dv.venta_id')
            ->join('variante_producto as vp', 'vp.id', '=', 'dv.variante_id')
            ->join('producto as p', 'p.id', '=', 'vp.producto_id')
            ->leftJoin('cliente as c', 'c.id', '=', 'v.cliente_id')
            ->whereNotNull('dv.numero_serie')
            ->where('dv.numero_serie', '<>', '')
            ->select([
                'v.numero_venta',
                'p.nombre as producto',
                'dv.numero_serie',
                'v.fecha as fecha_venta',
                'dv.fecha_fin_garantia',
                'dv.estado as estado_item',
                'c.nombre as cliente',
            ])
            ->orderByRaw("case when dv.estado = 'VIGENTE' and dv.fecha_fin_garantia >= CURRENT_DATE then 0 else 1 end")
            ->orderBy('dv.fecha_fin_garantia')
            ->orderByDesc('v.fecha');

        match ($filtro) {
            'vigentes' => $query
                ->where('dv.estado', 'VIGENTE')
                ->whereDate('dv.fecha_fin_garantia', '>=', now()->toDateString()),
            'por_vencer' => $query
                ->where('dv.estado', 'VIGENTE')
                ->whereDate('dv.fecha_fin_garantia', '>=', now()->toDateString())
                ->whereDate('dv.fecha_fin_garantia', '<=', now()->addDays(30)->toDateString()),
            'vencidas' => $query
                ->where('dv.estado', 'VIGENTE')
                ->whereDate('dv.fecha_fin_garantia', '<', now()->toDateString()),
            'devueltas' => $query->where('dv.estado', 'DEVUELTO'),
            default => $query,
        };

        return $query->get()->map(fn (object $fila): array => $this->formatearGarantia($fila))->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function consultarGarantia(string $numeroSerie): array
    {
        return array_map(
            fn (object $fila): array => $this->formatearGarantia($fila, $numeroSerie),
            DB::select('SELECT * FROM public.fn_validar_garantia(?)', [$numeroSerie]),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function formatearGarantia(object $fila, ?string $numeroSerie = null): array
    {
        return [
                'numero_venta' => (string) $fila->numero_venta,
                'producto' => (string) $fila->producto,
                'numero_serie' => $numeroSerie ?? ($fila->numero_serie ?? null),
                'fecha_venta' => $fila->fecha_venta,
                'fecha_fin_garantia' => $fila->fecha_fin_garantia,
                'vigente' => isset($fila->vigente)
                    ? (bool) $fila->vigente
                    : ($fila->fecha_fin_garantia !== null && $fila->fecha_fin_garantia >= now()->toDateString()),
                'estado_item' => (string) $fila->estado_item,
                'cliente' => $fila->cliente ?? null,
        ];
    }
}