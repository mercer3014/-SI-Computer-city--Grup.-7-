<?php

namespace App\Http\Controllers;

use App\Http\Requests\ClienteRequest;
use App\Models\Cliente;
use App\Services\BitacoraService;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ClienteController extends Controller
{
    /**
     * CU10-FR1: Listado, ficha con historial y ranking de clientes frecuentes.
     */
    public function index(Request $request): Response
    {
        $like = DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';
        $q = $request->string('q')->trim()->value();
        $estado = $request->string('estado')->value();
        $tab = $request->string('tab')->value();
        $fichaId = $request->integer('ficha');
        $desde = $request->string('desde')->value();
        $hasta = $request->string('hasta')->value();

        if (! in_array($estado, ['ACTIVO', 'INACTIVO', ''], true)) {
            $estado = '';
        }

        if (! in_array($tab, ['clientes', 'frecuentes'], true)) {
            $tab = 'clientes';
        }

        $query = Cliente::query();

        if ($q !== '') {
            $busqueda = '%'.addcslashes($q, '%_\\').'%';
            $query->where(function ($sub) use ($busqueda, $like): void {
                $sub->where('nombre', $like, $busqueda)
                    ->orWhere('razon_social', $like, $busqueda)
                    ->orWhere('numero_documento', $like, $busqueda)
                    ->orWhere('telefono', $like, $busqueda)
                    ->orWhere('email', $like, $busqueda);
            });
        }

        if ($estado !== '') {
            $query->where('estado', $estado);
        }

        $clientes = $query
            ->orderByRaw("case when nombre = 'Cliente Final' then 1 else 0 end")
            ->orderBy('nombre')
            ->paginate(20)
            ->withQueryString();

        $conVentas = DB::table('venta')
            ->whereIn('cliente_id', $clientes->getCollection()->pluck('id'))
            ->pluck('cliente_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->all();

        return Inertia::render('Clientes', [
            'clientes' => [
                'data' => $clientes->getCollection()
                    ->map(fn (Cliente $c) => $this->formatCliente($c, in_array($c->id, $conVentas, true)))
                    ->values(),
                'meta' => [
                    'current_page' => $clientes->currentPage(),
                    'last_page' => $clientes->lastPage(),
                    'total' => $clientes->total(),
                    'from' => $clientes->firstItem(),
                    'to' => $clientes->lastItem(),
                ],
            ],
            'ficha' => $this->ficha($fichaId),
            'frecuentes' => $this->frecuentes($desde, $hasta),
            'kpis' => [
                'total' => Cliente::query()->count(),
                'activos' => Cliente::query()->where('estado', 'ACTIVO')->count(),
                'con_ventas' => DB::table('venta')->select('cliente_id')->distinct()->count(),
            ],
            'filtros' => [
                'q' => $q,
                'estado' => $estado,
                'tab' => $tab,
                'ficha' => $fichaId > 0 ? (string) $fichaId : '',
                'desde' => $desde,
                'hasta' => $hasta,
            ],
        ]);
    }

    public function store(ClienteRequest $request): RedirectResponse
    {
        $datos = $this->datos($request);

        try {
            $cliente = DB::transaction(function () use ($datos) {
                $cliente = Cliente::query()->create($datos + [
                    'estado' => 'ACTIVO',
                ]);

                BitacoraService::registrar(
                    'Ventas',
                    'CREAR_CLIENTE',
                    'cliente',
                    $cliente->id,
                    null,
                    ['nombre' => $cliente->nombre, 'numero_documento' => $cliente->numero_documento],
                    "Alta de cliente {$cliente->nombre}",
                );

                return $cliente;
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'numero_documento' => 'Ya existe un cliente registrado con el documento indicado.',
            ]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Cliente '.$cliente->nombre.' registrado correctamente.']);

        return back();
    }

    public function update(ClienteRequest $request, Cliente $cliente): RedirectResponse
    {
        $datos = $this->datos($request);

        try {
            DB::transaction(function () use ($cliente, $datos): void {
                $anterior = $cliente->only(['nombre', 'numero_documento', 'telefono', 'email', 'estado']);
                $cliente->forceFill($datos)->save();

                BitacoraService::registrar(
                    'Ventas',
                    'ACTUALIZAR_CLIENTE',
                    'cliente',
                    $cliente->id,
                    $anterior,
                    $cliente->only(['nombre', 'numero_documento', 'telefono', 'email', 'estado']),
                    "Actualización de cliente {$cliente->nombre}",
                );
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'numero_documento' => 'Ya existe un cliente registrado con el documento indicado.',
            ]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Cliente actualizado.']);

        return back();
    }

    public function estado(Request $request, Cliente $cliente): RedirectResponse
    {
        if (! $request->user()?->tienePermiso('clientes.gestionar')) {
            abort(403);
        }

        $datos = $request->validate([
            'estado' => ['required', 'in:ACTIVO,INACTIVO'],
        ]);

        $cliente->forceFill(['estado' => $datos['estado']])->save();

        BitacoraService::registrar(
            'Ventas',
            'CAMBIAR_ESTADO_CLIENTE',
            'cliente',
            $cliente->id,
            null,
            ['nombre' => $cliente->nombre, 'estado' => $datos['estado']],
            $datos['estado'] === 'INACTIVO' ? 'Cliente desactivado' : 'Cliente reactivado',
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $datos['estado'] === 'INACTIVO'
                ? "{$cliente->nombre} quedó INACTIVO."
                : "{$cliente->nombre} quedó ACTIVO.",
        ]);

        return back();
    }

    /**
     * CU10-EX04: no se elimina un cliente con ventas; hay que dejarlo INACTIVO.
     */
    public function destroy(Request $request, Cliente $cliente): RedirectResponse
    {
        if (! $request->user()?->tienePermiso('clientes.gestionar')) {
            abort(403);
        }

        if ($cliente->tieneVentas()) {
            throw ValidationException::withMessages([
                'cliente' => 'No se puede eliminar un cliente con ventas registradas. Cambiá su estado a INACTIVO.',
            ]);
        }

        $nombre = $cliente->nombre;
        $cliente->delete();

        BitacoraService::registrar(
            'Ventas',
            'ELIMINAR_CLIENTE',
            'cliente',
            null,
            ['nombre' => $nombre],
            null,
            "Baja de cliente {$nombre}",
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => "Cliente {$nombre} eliminado."]);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function datos(ClienteRequest $request): array
    {
        $datos = $request->safe()->only([
            'tipo_documento',
            'numero_documento',
            'nombre',
            'razon_social',
            'telefono',
            'email',
            'direccion',
            'observacion',
        ]);

        if ($request->filled('estado')) {
            $datos['estado'] = $request->string('estado')->value();
        }

        return $datos;
    }

    /**
     * @return array<string, mixed>
     */
    private function formatCliente(Cliente $c, ?bool $tieneVentas = null): array
    {
        return [
            'id' => $c->id,
            'tipo_documento' => $c->tipo_documento,
            'numero_documento' => $c->numero_documento,
            'nombre' => $c->nombre,
            'razon_social' => $c->razon_social,
            'telefono' => $c->telefono,
            'email' => $c->email,
            'direccion' => $c->direccion,
            'observacion' => $c->observacion,
            'estado' => $c->estado ?? 'ACTIVO',
            'fecha_registro' => $c->fecha_registro?->timezone(config('app.timezone'))->format('d/m/Y'),
            'tiene_ventas' => $tieneVentas ?? $c->tieneVentas(),
            'es_generico' => $c->nombre === 'Cliente Final',
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function ficha(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        $cliente = Cliente::query()->find($id);

        if ($cliente === null) {
            return null;
        }

        $hoy = now()->toDateString();

        $compras = DB::table('venta as v')
            ->where('v.cliente_id', $id)
            ->orderByDesc('v.fecha')
            ->limit(40)
            ->get(['v.id', 'v.numero_venta', 'v.fecha', 'v.estado', 'v.descuento_global'])
            ->map(function (object $venta) use ($hoy): array {
                $total = (float) DB::table('detalle_venta')
                    ->where('venta_id', $venta->id)
                    ->selectRaw('coalesce(sum(cantidad * precio_unitario - coalesce(descuento, 0)), 0) as total')
                    ->value('total');

                $garantias = (int) DB::table('detalle_venta')
                    ->where('venta_id', $venta->id)
                    ->where('estado', 'VIGENTE')
                    ->whereDate('fecha_fin_garantia', '>=', $hoy)
                    ->count();

                $momento = $venta->fecha
                    ? Carbon::parse($venta->fecha)->timezone(config('app.timezone'))
                    : null;

                return [
                    'id' => (int) $venta->id,
                    'numero' => $venta->numero_venta,
                    'fecha' => $momento?->format('d/m/Y H:i'),
                    'estado' => $venta->estado,
                    'total' => round($total - (float) ($venta->descuento_global ?? 0), 2),
                    'garantias_vigentes' => $garantias,
                ];
            })
            ->all();

        return $this->formatCliente($cliente) + [
            'compras' => $compras,
            'compras_total' => count($compras),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function frecuentes(string $desde, string $hasta): array
    {
        $totales = DB::table('venta as v')
            ->where('v.estado', '<>', 'ANULADA')
            ->when($desde !== '', fn ($q) => $q->whereDate('v.fecha', '>=', $desde))
            ->when($hasta !== '', fn ($q) => $q->whereDate('v.fecha', '<=', $hasta))
            ->select('v.id', 'v.cliente_id', 'v.descuento_global')
            ->get();

        if ($totales->isEmpty()) {
            return [];
        }

        $detalle = DB::table('detalle_venta')
            ->whereIn('venta_id', $totales->pluck('id'))
            ->select('venta_id')
            ->selectRaw('coalesce(sum(cantidad * precio_unitario - coalesce(descuento, 0)), 0) as total')
            ->groupBy('venta_id')
            ->pluck('total', 'venta_id');

        $porCliente = [];

        foreach ($totales as $venta) {
            $cid = (int) $venta->cliente_id;
            $monto = (float) ($detalle[$venta->id] ?? 0) - (float) ($venta->descuento_global ?? 0);
            $porCliente[$cid] ??= ['compras' => 0, 'monto' => 0.0];
            $porCliente[$cid]['compras']++;
            $porCliente[$cid]['monto'] += $monto;
        }

        $clientes = Cliente::query()
            ->whereIn('id', array_keys($porCliente))
            ->get(['id', 'nombre', 'razon_social', 'numero_documento', 'telefono'])
            ->keyBy('id');

        return collect($porCliente)
            ->map(function (array $stats, int $id) use ($clientes): array {
                $c = $clientes->get($id);

                return [
                    'id' => $id,
                    'nombre' => $c?->nombre ?? 'Cliente',
                    'razon_social' => $c?->razon_social,
                    'numero_documento' => $c?->numero_documento,
                    'telefono' => $c?->telefono,
                    'compras' => $stats['compras'],
                    'monto' => round($stats['monto'], 2),
                ];
            })
            ->sortByDesc('compras')
            ->values()
            ->take(20)
            ->all();
    }
}
