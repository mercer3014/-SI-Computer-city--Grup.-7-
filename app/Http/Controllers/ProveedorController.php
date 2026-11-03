<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProveedorRequest;
use App\Models\Proveedor;
use App\Services\BitacoraService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ProveedorController extends Controller
{
    /**
     * CU13: consulta de proveedores. Por defecto solo los activos.
     */
    public function index(Request $request): Response
    {
        $like = DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';
        $q = $request->string('q')->trim()->value();
        $estado = $request->has('estado')
            ? $request->string('estado')->value()
            : 'ACTIVO';

        if (! in_array($estado, ['ACTIVO', 'INACTIVO', ''], true)) {
            $estado = 'ACTIVO';
        }

        $query = Proveedor::query();

        if ($q !== '') {
            $busqueda = '%'.addcslashes($q, '%_\\').'%';
            $query->where(function ($sub) use ($busqueda, $like): void {
                $sub->where('razon_social', $like, $busqueda)
                    ->orWhere('nombre_comercial', $like, $busqueda)
                    ->orWhere('nit', $like, $busqueda)
                    ->orWhere('contacto', $like, $busqueda)
                    ->orWhere('telefono', $like, $busqueda)
                    ->orWhere('email', $like, $busqueda);
            });
        }

        if ($estado !== '') {
            $query->where('estado', $estado);
        }

        $proveedores = $query
            ->orderBy('razon_social')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Proveedores', [
            'proveedores' => [
                'data' => $proveedores->getCollection()
                    ->map(fn (Proveedor $proveedor): array => $this->formatProveedor($proveedor))
                    ->values(),
                'meta' => [
                    'current_page' => $proveedores->currentPage(),
                    'last_page' => $proveedores->lastPage(),
                    'total' => $proveedores->total(),
                    'from' => $proveedores->firstItem(),
                    'to' => $proveedores->lastItem(),
                ],
            ],
            'kpis' => [
                'activos' => Proveedor::query()->where('estado', 'ACTIVO')->count(),
                'inactivos' => Proveedor::query()->where('estado', 'INACTIVO')->count(),
                'total' => Proveedor::query()->count(),
            ],
            'filtros' => [
                'q' => $q,
                'estado' => $estado,
            ],
        ]);
    }

    /**
     * CU13: alta. En PostgreSQL la inserción la hace sp_registrar_proveedor.
     */
    public function store(ProveedorRequest $request): RedirectResponse
    {
        $datos = $request->datos();

        $proveedor = DB::transaction(function () use ($datos): Proveedor {
            $id = $this->registrar($datos);
            $proveedor = Proveedor::query()->findOrFail($id);

            BitacoraService::registrar(
                'Compras',
                'CREAR_PROVEEDOR',
                'proveedor',
                $proveedor->id,
                null,
                ['nit' => $proveedor->nit, 'razon_social' => $proveedor->razon_social],
                "Alta de proveedor {$proveedor->razon_social}",
            );

            return $proveedor;
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Proveedor '.$proveedor->razon_social.' registrado correctamente.',
        ]);

        return back();
    }

    public function update(ProveedorRequest $request, Proveedor $proveedor): RedirectResponse
    {
        $datos = $request->datos();

        DB::transaction(function () use ($proveedor, $datos): void {
            $anterior = $proveedor->only(['nit', 'razon_social', 'telefono', 'email', 'estado']);
            $proveedor->forceFill($datos)->save();

            BitacoraService::registrar(
                'Compras',
                'ACTUALIZAR_PROVEEDOR',
                'proveedor',
                $proveedor->id,
                $anterior,
                $proveedor->only(['nit', 'razon_social', 'telefono', 'email', 'estado']),
                "Actualización de proveedor {$proveedor->razon_social}",
            );
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Proveedor actualizado.']);

        return back();
    }

    public function estado(Request $request, Proveedor $proveedor): RedirectResponse
    {
        if (! $request->user()?->tienePermiso('proveedores.gestionar')) {
            abort(403);
        }

        $datos = $request->validate([
            'estado' => ['required', 'in:ACTIVO,INACTIVO'],
        ]);

        $proveedor->forceFill(['estado' => $datos['estado']])->save();

        BitacoraService::registrar(
            'Compras',
            'CAMBIAR_ESTADO_PROVEEDOR',
            'proveedor',
            $proveedor->id,
            null,
            ['razon_social' => $proveedor->razon_social, 'estado' => $datos['estado']],
            $datos['estado'] === 'INACTIVO' ? 'Proveedor desactivado' : 'Proveedor reactivado',
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $datos['estado'] === 'INACTIVO'
                ? "{$proveedor->razon_social} quedó INACTIVO."
                : "{$proveedor->razon_social} quedó ACTIVO.",
        ]);

        return back();
    }

    /**
     * @param  array{
     *     nit: string,
     *     razon_social: string,
     *     nombre_comercial: string|null,
     *     contacto: string|null,
     *     telefono: string|null,
     *     email: string|null,
     *     direccion: string|null,
     *     observacion: string|null
     * }  $datos
     */
    private function registrar(array $datos): int
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(
                'CALL public.sp_registrar_proveedor(?, ?, ?, ?, ?, ?)',
                [
                    $datos['nit'],
                    $datos['razon_social'],
                    $datos['nombre_comercial'],
                    $datos['contacto'],
                    $datos['telefono'],
                    $datos['email'],
                ],
            );

            $id = DB::table('proveedor')
                ->whereRaw('lower(nit) = ?', [mb_strtolower($datos['nit'])])
                ->orderByDesc('id')
                ->value('id');

            DB::table('proveedor')->where('id', $id)->update([
                'direccion' => $datos['direccion'],
                'observacion' => $datos['observacion'],
            ]);

            return (int) $id;
        }

        // SQLite (tests) no tiene el procedimiento. Inserta las mismas columnas.
        return (int) DB::table('proveedor')->insertGetId([
            'nit' => $datos['nit'],
            'razon_social' => $datos['razon_social'],
            'nombre_comercial' => $datos['nombre_comercial'],
            'contacto' => $datos['contacto'],
            'telefono' => $datos['telefono'],
            'email' => $datos['email'],
            'direccion' => $datos['direccion'],
            'observacion' => $datos['observacion'],
            'estado' => 'ACTIVO',
            'fecha_registro' => now(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function formatProveedor(Proveedor $proveedor): array
    {
        return [
            'id' => $proveedor->id,
            'nit' => $proveedor->nit,
            'razon_social' => $proveedor->razon_social,
            'nombre_comercial' => $proveedor->nombre_comercial,
            'contacto' => $proveedor->contacto,
            'telefono' => $proveedor->telefono,
            'email' => $proveedor->email,
            'direccion' => $proveedor->direccion,
            'observacion' => $proveedor->observacion,
            'estado' => $proveedor->estado ?? 'ACTIVO',
            'fecha_registro' => $proveedor->fecha_registro?->timezone(config('app.timezone'))->format('d/m/Y'),
        ];
    }
}
